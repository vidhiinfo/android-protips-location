<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Circular extends Model
{
    protected $fillable = ['title', 'body', 'target_state_id'];

    public function targetState(): BelongsTo
    {
        return $this->belongsTo(State::class, 'target_state_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'circular_reads')->withPivot('read_at');
    }

    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        if (! $u->isNational()) {
            $q->where(fn ($w) => $w->whereNull('target_state_id')->orWhere('target_state_id', $u->state_id));
        }

        return $q;
    }
}
