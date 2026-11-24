<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Biodata extends Model
{
    use SoftDeletes;

    protected $table = 'biodata';

    protected $fillable = [
        'received_on', 'received_from', 'received_via', 'category', 'status', 'name', 'father_name', 'gender', 'dob',
        'mobile', 'whatsapp', 'email', 'address', 'state_id', 'district_id', 'mandal', 'education', 'profession',
        'background', 'remarks',
    ];

    protected $casts = ['received_on' => 'date', 'dob' => 'date', 'extra' => 'array'];

    protected $hidden = ['photo_path'];

    protected static function booted(): void
    {
        static::created(function (Biodata $b) {
            $b->reference_no = sprintf('%s-%s-%05d', setting('biodata_prefix', 'BIO'), now()->format('Y'), $b->id);
            $b->saveQuietly();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BiodataDocument::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('biodata.photo', $this).'?v='.substr(md5($this->photo_path), 0, 8) : null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }

    public function area(): string
    {
        return collect([$this->mandal, $this->district?->name, $this->state?->name])->filter()->implode(', ');
    }

    /** Other biodata of (probably) the same person: same mobile, or same name + father's name. */
    public function earlierCopies()
    {
        return static::where('id', '!=', $this->id)
            ->where(function ($q) {
                if ($this->mobile) {
                    $q->orWhere('mobile', $this->mobile);
                }
                if ($this->father_name) {
                    $q->orWhere(fn ($w) => $w->where('name', $this->name)->where('father_name', $this->father_name));
                }
                if (! $this->mobile && ! $this->father_name) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('received_on')->get();
    }
}
