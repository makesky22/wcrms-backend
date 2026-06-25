<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    use HasFactory;

    /**
     * Explicit table name matches the migration exactly.
     * Migration creates 'audit_trail' (singular) — so we pin it here
     * to stop Eloquent defaulting to 'audit_trails' (plural).
     */
    protected $table = 'audit_trail';

    protected $fillable = [
        'user_id',
        'action',
        'model',
        'model_id',
        'changes',
        'ip_address',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Static helper ─────────────────────────────────────────────────────

    /**
     * Log an action to the audit trail. Fails silently so it never
     * breaks the main request flow.
     *
     * Usage:
     *   AuditTrail::log($request->user(), 'login', 'User', $user->id, ['email' => $user->email]);
     */
    public static function log(
        $user,
        string $action,
        ?string $model    = null,
        $modelId          = null,
        array $changes    = []
    ): ?self {
        try {
            return static::create([
                'user_id'    => optional($user)->id,
                'action'     => $action,
                'model'      => $model,
                'model_id'   => $modelId,
                'changes'    => $changes,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning(
                'AuditTrail::log failed: ' . $e->getMessage()
            );
            return null;
        }
    }
}
