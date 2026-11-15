<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    protected $table = 'parties';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function setShortNameAttribute($v): void
    {
        $this->attributes['short_name'] = $v === null ? null : strtoupper(trim($v));
    }
}
