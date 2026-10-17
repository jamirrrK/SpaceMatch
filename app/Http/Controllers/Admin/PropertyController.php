<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPropertyFilterRequest;
use App\Http\Requests\AdminPropertyStatusRequest;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(AdminPropertyFilterRequest $request): View
    {
        Gate::authorize('viewAny', Property::class);
        $filters = $request->validated();
        $query = Property::with('owner')->withCount([
            'spaces',
            'spaces as published_spaces_count' => fn (Builder $spaces) => $spaces->where('listing_status', 'published'),
        ]);

        if (isset($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn (Builder $search) => $search
                ->where('property_name', 'like', $term)
                ->orWhere('city', 'like', $term)
                ->orWhere('barangay', 'like', $term)
                ->orWhere('address', 'like', $term)
                ->orWhereHas('owner', fn (Builder $owner) => $owner->where('name', 'like', $term)));
        }

        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (! empty($filters['status'])) {
            $filters['status'] === 'active'
                ? $query->whereIn('status', ['active', 'available'])
                : $query->where('status', $filters['status']);
        }

        return view('admin.properties.index', [
            'properties' => $query->latest()->orderByDesc('id')->paginate(15)->withQueryString(),
            'owners' => User::where('role', User::ROLE_OWNER)->orderBy('name')->get(['id', 'name', 'email']),
            'filters' => $filters,
        ]);
    }

    public function show(Property $property): View
    {
        Gate::authorize('view', $property);
        $property->load(['owner', 'images', 'spaces.amenities', 'spaces.images']);

        // descriptions predate structured audits, so guard malformed legacy JSON.
        $auditLogs = AuditLog::with('user')->where('action', 'property.status_changed')
            ->whereRaw("CASE WHEN JSON_VALID(description) THEN JSON_EXTRACT(description, '$.property_id') ELSE NULL END = ?", [(int) $property->id])
            ->latest()->orderByDesc('id')->get();

        return view('admin.properties.show', compact('property', 'auditLogs'));
    }

    public function updateStatus(AdminPropertyStatusRequest $request, Property $property): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $property, $data): void {
            $lockedProperty = Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('moderate', $lockedProperty);
            $previousStatus = $lockedProperty->status;

            $lockedProperty->forceFill([
                'status' => $data['status'],
                'moderated_at' => $data['status'] === 'inactive' ? now() : null,
                'moderation_reason' => $data['status'] === 'inactive' ? $data['reason'] : null,
            ])->save();

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'property.status_changed',
                'description' => json_encode([
                    'property_id' => $lockedProperty->id,
                    'owner_id' => $lockedProperty->owner_id,
                    'from' => $previousStatus,
                    'to' => $data['status'],
                    'reason' => $data['reason'],
                ], JSON_THROW_ON_ERROR),
            ]);
        });

        return redirect()->route('admin.properties.show', $property)
            ->with('status', 'Property status updated and recorded in the audit trail.');
    }
}
