<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->with('user');
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'action' => trim((string) $request->input('action', '')),
        ];

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('action', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
        }

        if ($filters['action'] !== '') {
            $query->where('action', 'like', $filters['action']);
        }

        $auditLogs = $query->latest()->paginate(20)->withQueryString();

        return view('admin.audit-logs.index', compact('auditLogs', 'filters'));
    }
}
