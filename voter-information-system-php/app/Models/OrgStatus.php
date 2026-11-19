<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Organisational Status master (post in the party organisation). */
class OrgStatus extends Model
{
    protected $table = 'org_statuses';
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
