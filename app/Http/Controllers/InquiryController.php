<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\InquiryActivity;
use App\Models\Space;
use App\Models\User;
use App\Notifications\InquiryActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $space = Space::publiclyListed()->with('property.owner')->whereKey($space->id)->firstOrFail();
        $data = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        $inquiry = DB::transaction(function () use ($request, $space, $data): Inquiry {
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

    public function reply(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($user, $inquiry);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        DB::transaction(function () use ($user, $inquiry, $data): void {
            $locked = Inquiry::query()->whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            $this->authorizeParticipant($user, $locked);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['message' => 'This inquiry is closed and can no longer receive replies.']);
            }

            $locked->activities()->create([
                'user_id' => $user->id,
                'activity_type' => 'message',
                'body' => $data['message'],
            ]);

            $recipient = $user->id === $locked->owner_id ? $locked->renter : $locked->owner;
            $recipient->notify(new InquiryActivityNotification($locked->load('space'), 'New reply to your inquiry'));
        });

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
            ->with(['space.property', 'renter', 'owner'])
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
            ->with(['space.property', 'renter', 'owner', 'activities.user'])
            ->whereKey($inquiry->id)
            ->firstOrFail();

        $inquiry->activities()
            ->whereNull('read_at')
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $user->id)
            ->update(['read_at' => now()]);
        $user->unreadNotifications()
            ->where('type', InquiryActivityNotification::class)
            ->get()
            ->filter(fn ($notification) => (int) ($notification->data['inquiry_id'] ?? 0) === $inquiry->id)
            ->each->markAsRead();

        return view('inquiries.show', compact('inquiry'));
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
