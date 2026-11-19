<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Beneficiary / help master: what the campaign helped a voter with, or the government scheme they get (voter's Beneficiary 1 / 2 / 3). */
class Beneficiary extends Model
{
    protected $table = 'beneficiaries';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];

    protected static function booted(): void
    {
        static::saving(fn ($m) => $m->name = trim(preg_replace('/\s+/u', ' ', (string) $m->name)));
        static::saved(fn () => \App\Support\StatusMasters::flush());
        static::deleted(fn () => \App\Support\StatusMasters::flush());
    }
}
