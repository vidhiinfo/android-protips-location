<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'entity', 'entity_id', 'detail', 'ip'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $model = null, ?string $detail = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity' => $model ? class_basename($model) : null,
            'entity_id' => $model?->getKey(),
            'detail' => $detail ? mb_substr($detail, 0, 250) : null,
            'ip' => request()->ip(),
        ]);
    }
}
