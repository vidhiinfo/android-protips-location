<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;

    public const TYPES = ['bearer' => 'Office Bearer', 'worker' => 'Party Worker'];

    public const STATUSES = ['active' => 'Active', 'proposed' => 'Proposed (awaiting approval)', 'removed' => 'Removed'];

    protected $fillable = [
        'type', 'name', 'father_name', 'gender', 'dob', 'mobile', 'whatsapp', 'email', 'address', 'profession',
        'education', 'skills', 'level', 'state_id', 'district_id', 'mandal', 'designation_id', 'appointed_on',
        'tenure_end', 'status', 'facebook', 'twitter', 'instagram', 'consent', 'notes',
    ];

    protected $casts = [
        'dob' => 'date',
        'appointed_on' => 'date',
        'tenure_end' => 'date',
        'consent' => 'boolean',
        'consent_at' => 'datetime',
        'extra' => 'array',
    ];

    protected $hidden = ['photo_path'];

    protected $appends = ['photo_url'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? route('members.photo', $this).'?v='.substr(md5($this->photo_path), 0, 8) : null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }

    public function area(): string
    {
        return collect([$this->mandal, $this->district?->name, $this->state?->name])->filter()->implode(', ') ?: 'National';
    }

    /** Share of the important fields that are filled in (used for data-quality reports). */
    public function completeness(): int
    {
        $fields = ['photo_path', 'mobile', 'dob', 'designation_id', 'address', 'appointed_on'];
        $filled = collect($fields)->filter(fn ($f) => filled($this->{$f}))->count();

        return (int) round($filled / count($fields) * 100);
    }
}
