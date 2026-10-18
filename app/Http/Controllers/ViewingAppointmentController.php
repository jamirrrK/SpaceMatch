<?php

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\User;
use App\Models\ViewingActivity;
use App\Models\ViewingAppointment;
use App\Notifications\ViewingActivityNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ViewingAppointmentController extends Controller
{
    private const DURATION_MINUTES = 60;

    public function renterIndex(Request $request): View
    {
        return $this->indexFor($request->user(), User::ROLE_RENTER);
    }

    public function ownerIndex(Request $request): View
    {
        return $this->indexFor($request->user(), User::ROLE_OWNER);
    }

    public function request(Request $request, Space $space): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(User::ROLE_RENTER), 403);
        $space = Space::publiclyListed()->with('property.owner')->whereKey($space->id)->firstOrFail();
        $data = $request->validate([
            'requested_start_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
        ]);
        $requestedStart = Carbon::createFromFormat('Y-m-d\TH:i', $data['requested_start_at'], config('app.timezone'));

        DB::transaction(function () use ($user, $space, $requestedStart): void {
            $appointment = ViewingAppointment::create([
                'space_id' => $space->id,
                'renter_id' => $user->id,
                'owner_id' => $space->property->owner_id,
                'requested_start_at' => $requestedStart,
                'status' => 'pending',
            ]);
            $appointment->activities()->create([
                'user_id' => $user->id,
                'activity_type' => 'requested',
                'scheduled_start_at' => $requestedStart,
            ]);
            $space->property->owner->notify(new ViewingActivityNotification($appointment->load('space'), 'New viewing request'));
        });

        return redirect()->route('renter.viewings.index')->with('status', 'Viewing request sent. The owner must confirm the time before it is scheduled.');
    }

    public function showRenter(Request $request, ViewingAppointment $appointment): View
    {
        return $this->showFor($request->user(), $appointment, User::ROLE_RENTER);
    }

    public function showOwner(Request $request, ViewingAppointment $appointment): View
    {
        return $this->showFor($request->user(), $appointment, User::ROLE_OWNER);
    }

    public function accept(Request $request, ViewingAppointment $appointment): RedirectResponse
    {
        $this->requireOwner($request->user(), $appointment);

        DB::transaction(function () use ($request, $appointment): void {
            $locked = $this->lockAppointment($appointment);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending viewing requests can be accepted.']);
            }
            $this->ensureSlotAvailable($locked, $locked->requested_start_at);
            $this->transition($locked, $request->user(), 'scheduled', $locked->requested_start_at, null);
        });

        return back()->with('status', 'Viewing scheduled. The time is shown in '.config('app.timezone').'.');
    }

    public function reschedule(Request $request, ViewingAppointment $appointment): RedirectResponse
    {
        $this->requireOwner($request->user(), $appointment);
        $data = $request->validate([
            'scheduled_start_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $newStart = Carbon::createFromFormat('Y-m-d\TH:i', $data['scheduled_start_at'], config('app.timezone'));
        DB::transaction(function () use ($request, $appointment, $newStart, $data): void {
            $locked = $this->lockAppointment($appointment);
            if (! in_array($locked->status, ['pending', 'scheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending or scheduled viewings can be rescheduled.']);
            }
            $this->ensureSlotAvailable($locked, $newStart);
            $this->transition($locked, $request->user(), 'rescheduled', $newStart, $data['reason']);
        });

        return back()->with('status', 'New viewing time proposed. The student must confirm it before the viewing is scheduled.');
    }

    public function decline(Request $request, ViewingAppointment $appointment): RedirectResponse
    {
        $this->requireOwner($request->user(), $appointment);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $appointment, $data): void {
            $locked = $this->lockAppointment($appointment);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending viewing requests can be declined.']);
            }
            $this->transition($locked, $request->user(), 'declined', null, $data['reason']);
        });

        return back()->with('status', 'Viewing request declined and the renter has been notified.');
    }

    public function cancel(Request $request, ViewingAppointment $appointment): RedirectResponse
    {
        $user = $request->user();
        $this->requireRenter($user, $appointment);
        DB::transaction(function () use ($user, $appointment): void {
            $locked = $this->lockAppointment($appointment);
            if (! in_array($locked->status, ['pending', 'scheduled', 'rescheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'This viewing can no longer be cancelled.']);
            }
            $this->transition($locked, $user, 'cancelled', null, null);
        });

        return back()->with('status', 'Viewing cancelled.');
    }

    public function confirmReschedule(Request $request, ViewingAppointment $appointment): RedirectResponse
    {
        $user = $request->user();
        $this->requireRenter($user, $appointment);

        DB::transaction(function () use ($user, $appointment): void {
            $locked = $this->lockAppointment($appointment);
            if ($locked->status !== 'rescheduled' || ! $locked->scheduled_start_at) {
                throw ValidationException::withMessages(['status' => 'There is no proposed time waiting for your confirmation.']);
            }
            $this->ensureSlotAvailable($locked, $locked->scheduled_start_at);
            $this->transition($locked, $user, 'scheduled', $locked->scheduled_start_at, null);
        });

        return back()->with('status', 'New viewing time confirmed.');
    }

    private function indexFor(User $user, string $role): View
    {
        abort_unless($user->hasRole($role), 403);
        $appointments = ViewingAppointment::query()
            ->where($role === User::ROLE_OWNER ? 'owner_id' : 'renter_id', $user->id)
            ->with(['space.property', 'renter', 'owner'])
            ->latest('updated_at')->paginate(15)->withQueryString();

        return view('viewings.index', ['appointments' => $appointments, 'ownerSide' => $role === User::ROLE_OWNER]);
    }

    private function showFor(User $user, ViewingAppointment $appointment, string $role): View
    {
        abort_unless($user->hasRole($role), 403);
        $appointment = ViewingAppointment::visibleTo($user)
            ->with(['space.property', 'renter', 'owner', 'activities.user'])
            ->whereKey($appointment->id)->firstOrFail();
        $user->unreadNotifications()
            ->where('type', ViewingActivityNotification::class)
            ->get()
            ->filter(fn ($notification) => (int) ($notification->data['viewing_appointment_id'] ?? 0) === $appointment->id)
            ->each->markAsRead();

        return view('viewings.show', compact('appointment'));
    }

    private function requireOwner(User $user, ViewingAppointment $appointment): void
    {
        abort_unless($user->hasRole(User::ROLE_OWNER) && $appointment->owner_id === $user->id, 404);
    }

    private function requireRenter(User $user, ViewingAppointment $appointment): void
    {
        abort_unless($user->hasRole(User::ROLE_RENTER) && $appointment->renter_id === $user->id, 404);
    }

    private function lockAppointment(ViewingAppointment $appointment): ViewingAppointment
    {
        $locked = ViewingAppointment::query()->whereKey($appointment->id)->firstOrFail();
        Space::query()->whereKey($locked->space_id)->lockForUpdate()->firstOrFail();

        return ViewingAppointment::query()->whereKey($locked->id)->lockForUpdate()->firstOrFail();
    }

    private function ensureSlotAvailable(ViewingAppointment $appointment, Carbon $start): void
    {
        $end = $start->copy()->addMinutes(self::DURATION_MINUTES);
        $conflict = ViewingAppointment::query()
            ->where('space_id', $appointment->space_id)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->where('id', '!=', $appointment->id)
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_start_at', '>', $start->copy()->subMinutes(self::DURATION_MINUTES))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['scheduled_start_at' => 'That one-hour viewing slot conflicts with another confirmed viewing. Choose another time.']);
        }
    }

    private function transition(
        ViewingAppointment $appointment,
        User $actor,
        string $status,
        ?Carbon $scheduledStart,
        ?string $reason,
    ): void {
        $previous = $appointment->status;
        $appointment->update([
            'status' => $status,
            'scheduled_start_at' => in_array($status, ['scheduled', 'rescheduled'], true) ? $scheduledStart : null,
            'response_reason' => $reason,
        ]);
        $appointment->activities()->create([
            'user_id' => $actor->id,
            'activity_type' => $status,
            'scheduled_start_at' => in_array($status, ['scheduled', 'rescheduled'], true) ? $scheduledStart : null,
            'reason' => $reason,
        ]);

        $recipient = $actor->id === $appointment->owner_id ? $appointment->renter : $appointment->owner;
        $recipient->notify(new ViewingActivityNotification($appointment->load('space'), 'Viewing status: '.$status.' (was '.$previous.')'));
    }
}
