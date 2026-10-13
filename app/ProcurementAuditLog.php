<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use LogicException;

class ProcurementAuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'entity_type', 'entity_id', 'event', 'from_status', 'to_status',
        'reason', 'meta', 'user_id', 'user_name', 'ip_address',
    ];

    protected $casts = ['meta' => 'array'];

    protected static function booted()
    {
        // Audit history is append-only through the application.
        static::updating(function () {
            throw new LogicException('Procurement audit records cannot be modified.');
        });
        static::deleting(function () {
            throw new LogicException('Procurement audit records cannot be deleted.');
        });
    }

    public static function record($entity, $event, $fromStatus = null, $toStatus = null, $reason = null, array $meta = [])
    {
        $user = Auth::user();

        return static::create([
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->getKey(),
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'meta' => $meta ?: null,
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->name : 'System',
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * For pre-existing workflows: skip silently when the audit table has not been migrated yet.
     */
    public static function recordIfAvailable($entity, $event, $fromStatus = null, $toStatus = null, $reason = null, array $meta = [])
    {
        if (!Schema::hasTable((new static)->getTable())) {
            return null;
        }

        return static::record($entity, $event, $fromStatus, $toStatus, $reason, $meta);
    }

    public static function forEntity($entity)
    {
        return static::where('entity_type', class_basename($entity))
            ->where('entity_id', $entity->getKey())
            ->orderBy('id');
    }
}
