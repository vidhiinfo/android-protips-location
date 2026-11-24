<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'code', 'is_ut'];

    protected $casts = ['is_ut' => 'boolean'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class)->orderBy('name');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
