<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Security\ActivityLogger;
use Illuminate\Http\JsonResponse;

class RolePermissionController extends Controller
{
    /**
     * Display permissions currently assigned to a role.
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeRolePermissions($role->load('permissions')),
        ]);
    }

    /**
     * Replace permissions assigned to a role.
     */
    public function update(SyncRolePermissionsRequest $request, Role $role, ActivityLogger $activityLogger): JsonResponse
    {
        $before = $role->permissions()->pluck('code')->all();
        $syncPayload = $this->buildSyncPayload($request->validated());

        $role->permissions()->sync($syncPayload);
        $role->refresh()->load('permissions');

        $activityLogger->record(
            module: 'role',
            action: 'permissions_update',
            event: 'Role permissions synced',
            description: "Permission untuk role {$role->code} diperbarui.",
            subject: $role,
            metadata: [
                'before' => $before,
                'after' => $role->permissions->pluck('code')->values()->all(),
            ],
            riskLevel: ActivityLog::RISK_HIGH,
        );

        return response()->json([
            'data' => $this->serializeRolePermissions($role),
            'message' => 'Role permissions synced successfully.',
        ]);
    }

    /**
     * Convert permission IDs/codes and optional constraints into belongsToMany sync payload.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, array{constraints: array<string, mixed>|null}>
     */
    private function buildSyncPayload(array $validated): array
    {
        $constraints = $validated['constraints'] ?? [];
        $syncPayload = [];

        foreach ($validated['permission_ids'] ?? [] as $permissionId) {
            $permissionId = (int) $permissionId;
            $syncPayload[$permissionId] = [
                'constraints' => $constraints[(string) $permissionId] ?? null,
            ];
        }

        $permissionsByCode = Permission::query()
            ->whereIn('code', $validated['permissions'] ?? [])
            ->get()
            ->keyBy('code');

        foreach ($validated['permissions'] ?? [] as $permissionCode) {
            /** @var Permission $permission */
            $permission = $permissionsByCode[$permissionCode];

            $syncPayload[$permission->getKey()] = [
                'constraints' => $constraints[$permissionCode] ?? $syncPayload[$permission->getKey()]['constraints'] ?? null,
            ];
        }

        return $syncPayload;
    }

    /**
     * Transform assigned role permissions for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeRolePermissions(Role $role): array
    {
        $permissions = $role->permissions
            ->sortBy(['module', 'sort_order'])
            ->map(fn (Permission $permission): array => [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'code' => $permission->code,
                'module' => $permission->module,
                'action' => $permission->action,
                'risk_level' => $permission->risk_level,
                'constraints' => $permission->pivot?->constraints,
            ])
            ->values();

        return [
            'role' => [
                'id' => $role->getKey(),
                'name' => $role->name,
                'code' => $role->code,
                'status' => $role->status,
            ],
            'permission_count' => $permissions->count(),
            'permissions' => $permissions->all(),
        ];
    }
}
