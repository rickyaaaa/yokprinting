<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListActivityLogsRequest;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    /**
     * List audit log activities with dashboard-friendly filters.
     */
    public function index(ListActivityLogsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? $validated['q'] ?? '');
        $riskLevel = $validated['risk_level'] ?? 'all';
        $limit = (int) ($validated['limit'] ?? 100);
        $sort = $validated['sort'] ?? 'occurred_at';
        $direction = $validated['direction'] ?? 'desc';

        $logs = ActivityLog::query()
            ->with('user:id,name,email,role')
            ->when(
                filled($validated['module'] ?? null),
                fn (Builder $query): Builder => $query->where('module', $validated['module']),
            )
            ->when(
                filled($validated['action'] ?? null),
                fn (Builder $query): Builder => $query->where('action', $validated['action']),
            )
            ->when(
                $riskLevel !== 'all',
                fn (Builder $query): Builder => $query->where('risk_level', $riskLevel),
            )
            ->when(
                filled($validated['actor_role'] ?? null),
                fn (Builder $query): Builder => $query->where('actor_role', $validated['actor_role']),
            )
            ->when(
                filled($validated['user_id'] ?? null),
                fn (Builder $query): Builder => $query->where('user_id', $validated['user_id']),
            )
            ->when(
                filled($validated['date_from'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('occurred_at', '>=', $validated['date_from']),
            )
            ->when(
                filled($validated['date_to'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('occurred_at', '<=', $validated['date_to']),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $userQuery): Builder => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log): array => $this->serializeActivityLog($log))
            ->values();

        return response()->json([
            'data' => $logs,
            'meta' => [
                'count' => $logs->count(),
                'limit' => $limit,
                'filters' => [
                    'search' => $search,
                    'module' => $validated['module'] ?? null,
                    'action' => $validated['action'] ?? null,
                    'risk_level' => $riskLevel,
                    'actor_role' => $validated['actor_role'] ?? null,
                    'user_id' => $validated['user_id'] ?? null,
                    'date_from' => $validated['date_from'] ?? null,
                    'date_to' => $validated['date_to'] ?? null,
                ],
                'sort' => [
                    'key' => $sort,
                    'direction' => $direction,
                ],
            ],
        ]);
    }

    /**
     * Transform an activity log model for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeActivityLog(ActivityLog $log): array
    {
        return [
            'id' => $log->getKey(),
            'user_id' => $log->user_id,
            'user' => $log->user ? [
                'id' => $log->user->getKey(),
                'name' => $log->user->name,
                'email' => $log->user->email,
                'role' => $log->user->role,
            ] : null,
            'actor_name' => $log->actor_name,
            'actor_role' => $log->actor_role,
            'module' => $log->module,
            'action' => $log->action,
            'event' => $log->event,
            'description' => $log->description,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'ip_address' => $log->ip_address,
            'metadata' => $log->metadata ?? [],
            'risk_level' => $log->risk_level,
            'occurred_at' => $log->occurred_at?->toISOString(),
            'created_at' => $log->created_at?->toISOString(),
        ];
    }
}
