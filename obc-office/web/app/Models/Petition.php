<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Petition extends Model
{
    public const STATUSES = ['received' => 'Received', 'forwarded' => 'Forwarded', 'in_progress' => 'In progress', 'closed' => 'Closed'];

    public const CATEGORIES = ['Organisational', 'Government scheme', 'Police / Legal', 'Health', 'Education', 'Employment', 'Recommendation', 'Other'];

    protected $guarded = ['id', 'tracking_no', 'created_by'];

    protected $casts = ['received_on' => 'date', 'due_date' => 'date'];

    protected static function booted(): void
    {
        static::created(function (Petition $p) {
            $p->tracking_no = sprintf('%s-%s-%05d', setting('petition_prefix', 'PET'), now()->format('Y'), $p->id);
            $p->saveQuietly();
        });
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(PetitionUpdate::class)->latest();
    }
}
