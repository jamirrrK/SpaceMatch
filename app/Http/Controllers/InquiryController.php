<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\InquiryActivity;
use App\Models\Space;
use App\Models\User;
use App\Notifications\InquiryActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function renterIndex(Request $request): View
    {
        return $this->indexFor($request->user(), User::ROLE_RENTER);
    }

    public function ownerIndex(Request $request): View
    {
        return $this->indexFor($request->user(), User::ROLE_OWNER);
    }

    public function start(Request $request, Space $space): RedirectResponse
    {
        if (! $request->user()->hasRole(User::ROLE_RENTER)) {
            abort(403);
        }

        $space = Space::with('property.owner')->whereKey($space->id)->firstOrFail();
        $existingRental = Booking::where('space_id', $space->id)->where('renter_id', $request->user()->id)
            ->whereIn('status', ['confirmed', 'active'])->exists();
        abort_unless($existingRental || Space::publiclyListed()->whereKey($space->id)->exists(), 404);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        $inquiry = DB::transaction(function () use ($request, $space, $data): Inquiry {
            Space::whereKey($space->id)->lockForUpdate()->firstOrFail();
            $existing = Inquiry::where('space_id', $space->id)->where('renter_id', $request->user()->id)
                ->where('owner_id', $space->property->owner_id)->where('status', 'open')->first();
            if ($existing) {
                return $existing;
            }
            $inquiry = Inquiry::create([
                'space_id' => $space->id,
                'renter_id' => $request->user()->id,
                'owner_id' => $space->property->owner_id,
                'status' => 'open',
            ]);

            $inquiry->activities()->create([
                'user_id' => $request->user()->id,
                'activity_type' => 'message',
                'body' => $data['message'],
            ]);

            $space->property->owner->notify(new InquiryActivityNotification($inquiry->load('space'), 'New student inquiry'));

            return $inquiry;
        });

        return redirect()->route('renter.inquiries.show', $inquiry)
            ->with('status', 'Your inquiry was sent to the property owner.');
    }

    public function showRenter(Request $request, Inquiry $inquiry): View
    {
        return $this->showFor($request->user(), $inquiry, User::ROLE_RENTER);
    }

    public function showOwner(Request $request, Inquiry $inquiry): View
    {
        return $this->showFor($request->user(), $inquiry, User::ROLE_OWNER);
    }

    public function updates(Request $request, Inquiry $inquiry): JsonResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($user, $inquiry);
        $data = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $activities = InquiryActivity::query()
            ->where('inquiry_id', $inquiry->id)
            ->where('id', '>', $data['after_id'] ?? 0)
            ->with('user:id,name')
            ->oldest('id')
            ->limit(100)
            ->get();

        $inquiry->activities()
            ->whereIn('id', $activities->pluck('id'))
            ->whereNull('read_at')
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $user->id)
            ->update(['read_at' => now()]);
        $this->markInquiryNotificationsRead($user, $inquiry);

        return response()->json([
            'activities' => $activities->map(fn (InquiryActivity $activity): array => [
                'id' => $activity->id,
                'type' => $activity->activity_type,
                'body' => $activity->body,
                'from_status' => $activity->from_status,
                'to_status' => $activity->to_status,
                'user_id' => $activity->user_id,
                'user_name' => $activity->user?->name ?? 'SpaceMatch',
                'created_at' => $activity->created_at?->toIso8601String(),
                'created_at_label' => $activity->created_at?->format('M j, Y g:i A'),
            ])->values(),
            'unread_inquiries' => $user->unreadNotifications()
                ->where('type', InquiryActivityNotification::class)->count(),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'status' => $inquiry->fresh()->status,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function reply(Request $request, Inquiry $inquiry): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($user, $inquiry);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        $sent = DB::transaction(function () use ($user, $inquiry, $data): bool {
            $locked = Inquiry::query()->whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            $this->authorizeParticipant($user, $locked);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['message' => 'This inquiry is closed and can no longer receive replies.']);
            }

            if ($locked->activities()->where('user_id', $user->id)->where('body', $data['message'])
                ->where('created_at', '>=', now()->subMinute())->exists()) {
                return false;
            }
            $locked->activities()->create([
                'user_id' => $user->id,
                'activity_type' => 'message',
                'body' => $data['message'],
            ]);

            $recipient = $user->id === $locked->owner_id ? $locked->renter : $locked->owner;
            $recipient->notify(new InquiryActivityNotification($locked->load('space'), 'New reply to your inquiry'));

            return true;
        });

        if ($request->expectsJson()) {
            return response()->json(['sent' => $sent], $sent ? 201 : 200);
        }

        return back()->with('status', 'Your reply was sent.');
    }

    public function close(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(User::ROLE_OWNER), 403);
        $this->authorizeParticipant($user, $inquiry);

        DB::transaction(function () use ($user, $inquiry): void {
            $locked = Inquiry::query()->whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            $this->authorizeParticipant($user, $locked);
            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['status' => 'This inquiry is already closed.']);
            }

            $locked->update(['status' => 'closed']);
            $locked->activities()->create([
                'user_id' => $user->id,
                'activity_type' => 'status_changed',
                'from_status' => 'open',
                'to_status' => 'closed',
            ]);
            $locked->renter->notify(new InquiryActivityNotification($locked->load('space'), 'The owner closed your inquiry'));
        });

        return back()->with('status', 'Inquiry closed.');
    }

    private function indexFor(User $user, string $role): View
    {
        abort_unless($user->hasRole($role), 403);
        $ownerSide = $role === User::ROLE_OWNER;
        $inquiries = Inquiry::query()
            ->where($ownerSide ? 'owner_id' : 'renter_id', $user->id)
            ->with(['space.property', 'space.images', 'renter', 'owner', 'latestActivity'])
            ->withCount(['activities as unread_activities_count' => fn ($activities) => $activities
                ->whereNull('read_at')->where('user_id', '!=', $user->id)])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('inquiries.index', compact('inquiries', 'ownerSide'));
    }

    private function showFor(User $user, Inquiry $inquiry, string $role): View
    {
        abort_unless($user->hasRole($role), 403);
        $inquiry = Inquiry::query()->visibleTo($user)
            ->with(['space.property', 'renter', 'owner'])
            ->whereKey($inquiry->id)
            ->firstOrFail();

        $inquiry->setRelation('activities', $inquiry->activities()
            ->with('user:id,name')
            ->reorder('id', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values());

        $inquiry->activities()
            ->whereNull('read_at')
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $user->id)
            ->update(['read_at' => now()]);
        $this->markInquiryNotificationsRead($user, $inquiry);

        return view('inquiries.show', compact('inquiry'));
    }

    private function markInquiryNotificationsRead(User $user, Inquiry $inquiry): void
    {
        $user->unreadNotifications()
            ->where('type', InquiryActivityNotification::class)
            ->get()
            ->filter(fn ($notification) => (int) ($notification->data['inquiry_id'] ?? 0) === $inquiry->id)
            ->each->markAsRead();
    }

    private function authorizeParticipant(User $user, Inquiry $inquiry): void
    {
        abort_unless(
            ($user->hasRole(User::ROLE_RENTER) && $inquiry->renter_id === $user->id)
            || ($user->hasRole(User::ROLE_OWNER) && $inquiry->owner_id === $user->id),
            404,
        );
    }
}
