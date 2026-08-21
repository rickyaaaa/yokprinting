<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRolesRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Security\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * List roles with search, filter, sort, and permission metadata.
     */
    public function index(ListRolesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? $validated['q'] ?? '');
        $status = $validated['status'] ?? 'all';
        $limit = (int) ($validated['limit'] ?? 100);
        $sort = $validated['sort'] ?? 'sort_order';
        $direction = $validated['direction'] ?? 'asc';

        $roles = Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('module')->orderBy('sort_order')])
            ->withCount('users')
            ->when(
                $status !== 'all',
                fn (Builder $query): Builder => $query->where('status', $status),
            )
            ->when(
                filled($validated['guard_name'] ?? null),
                fn (Builder $query): Builder => $query->where('guard_name', $validated['guard_name']),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('scope', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->limit($limit)
            ->get()
            ->map(fn (Role $role): array => $this->serializeRole($role))
            ->values();

        return response()->json([
            'data' => $roles,
            'meta' => [
                'count' => $roles->count(),
                'limit' => $limit,
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'guard_name' => $validated['guard_name'] ?? null,
                ],
                'sort' => [
                    'key' => $sort,
                    'direction' => $direction,
                ],
            ],
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request, ActivityLogger $activityLogger): JsonResponse
    {
        [$payload, $permissionIds] = $this->splitRolePayload($request->validated());

        $role = Role::query()->create($payload);

        if ($permissionIds !== null) {
            $role->syncPermissions($permissionIds);
        }

        $activityLogger->record(
            module: 'role',
            action: 'create',
            event: 'Role created',
            description: "Role {$role->code} dibuat.",
            subject: $role,
            metadata: ['permission_ids' => $permissionIds],
            riskLevel: ActivityLog::RISK_MEDIUM,
        );

        return response()->json([
            'data' => $this->serializeRole($role->refresh()->load('permissions')->loadCount('users')),
            'message' => 'Role created successfully.',
        ], 201);
    }

    /**
     * Display a role.
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeRole($role->load('permissions')->loadCount('users')),
        ]);
    }

    /**
     * Update a role.
     */
    public function update(UpdateRoleRequest $request, Role $role, ActivityLogger $activityLogger): JsonResponse
    {
        $original = $role->only(['name', 'code', 'scope', 'status', 'is_system', 'sort_order']);
        [$payload, $permissionIds] = $this->splitRolePayload($request->validated());

        if (array_key_exists('code', $payload)
            && $payload['code'] !== $role->code
            && $role->users()->exists()) {
            return response()->json([
                'message' => 'Kode role tidak dapat diubah selama masih dipakai user. Pindahkan user terlebih dahulu.',
            ], 409);
        }

        $role->update($payload);

        if ($permissionIds !== null) {
            $role->syncPermissions($permissionIds);
        }

        $activityLogger->record(
            module: 'role',
            action: 'update',
            event: 'Role updated',
            description: "Role {$role->code} diperbarui.",
            subject: $role,
            metadata: [
                'before' => $original,
                'after' => $role->only(['name', 'code', 'scope', 'status', 'is_system', 'sort_order']),
                'permission_ids' => $permissionIds,
            ],
            riskLevel: ActivityLog::RISK_MEDIUM,
        );

        return response()->json([
            'data' => $this->serializeRole($role->refresh()->load('permissions')->loadCount('users')),
            'message' => 'Role updated successfully.',
        ]);
    }

    /**
     * Soft delete a role.
     */
    public function destroy(Role $role, ActivityLogger $activityLogger): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'System roles cannot be deleted.',
            ], 409);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Role yang masih dipakai user tidak dapat dihapus. Pindahkan user terlebih dahulu.',
            ], 409);
        }

        $activityLogger->record(
            module: 'role',
            action: 'delete',
            event: 'Role deleted',
            description: "Role {$role->code} dihapus.",
            subject: $role,
            metadata: ['code' => $role->code],
            riskLevel: ActivityLog::RISK_HIGH,
        );

        $role->delete();

        return response()->json(status: 204);
    }

    /**
     * Split role attributes from optional permission sync payload.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: list<int>|null}
     */
    private function splitRolePayload(array $validated): array
    {
        $shouldSyncPermissions = array_key_exists('permission_ids', $validated)
            || array_key_exists('permissions', $validated);

        $permissionIds = $validated['permission_ids'] ?? [];
        $permissionCodes = $validated['permissions'] ?? [];

        unset($validated['permission_ids'], $validated['permissions']);

        if (! $shouldSyncPermissions) {
            return [$validated, null];
        }

        $resolvedIds = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        return [
            $validated,
            array_values(array_unique([
                ...array_map('intval', $permissionIds),
                ...array_map('intval', $resolvedIds),
            ])),
        ];
    }

    /**
     * Transform a role model for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeRole(Role $role): array
    {
        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'code' => $role->code,
            'guard_name' => $role->guard_name,
            'description' => $role->description,
            'scope' => $role->scope,
            'status' => $role->status,
            'is_system' => $role->is_system,
            'sort_order' => $role->sort_order,
            'users_count' => $role->users_count ?? $role->users()->count(),
            'permissions' => $role->relationLoaded('permissions')
                ? $role->permissions->map(fn (Permission $permission): array => [
                    'id' => $permission->getKey(),
                    'name' => $permission->name,
                    'code' => $permission->code,
                    'module' => $permission->module,
                    'action' => $permission->action,
                    'risk_level' => $permission->risk_level,
                ])->values()->all()
                : [],
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
        ];
    }
}
