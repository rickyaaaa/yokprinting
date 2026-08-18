<?php

namespace App\Services\Security;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    /**
     * Record an audit activity with request context captured automatically.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $module,
        string $action,
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        string $riskLevel = ActivityLog::RISK_LOW,
        ?User $actor = null,
        ?Request $request = null,
        bool $inferActor = true,
    ): ActivityLog {
        $request ??= request();
        if ($inferActor && $actor === null) {
            $actor = $request->user();
        }

        return ActivityLog::query()->create([
            'user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'actor_role' => $actor?->role,
            'module' => $module,
            'action' => $action,
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => array_filter([
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                ...$metadata,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'risk_level' => $riskLevel,
            'occurred_at' => now(),
        ]);
    }
}
