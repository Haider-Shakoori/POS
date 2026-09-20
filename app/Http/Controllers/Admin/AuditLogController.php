<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'event' => ['nullable', 'string', 'max:120'],
            'actor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = AuditLog::query()->with('actor:id,name,username');

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('event', 'like', $term)
                    ->orWhere('auditable_type', 'like', $term)
                    ->orWhere('auditable_id', 'like', $term)
                    ->orWhere('ip_address', 'like', $term);
            });
        }

        return view('admin.audit.index', [
            'logs' => $query
                ->latest('created_at')
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'events' => AuditLog::query()
                ->select('event')
                ->distinct()
                ->orderBy('event')
                ->limit(150)
                ->pluck('event'),
            'actors' => User::query()
                ->whereHas('roles')
                ->orderBy('name')
                ->get(['id', 'name', 'username']),
            'filters' => $filters,
        ]);
    }
}
