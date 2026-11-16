<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Poll day: this voter has voted in this election (marked by a booth agent). */
class PollMark extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['marked_at' => 'datetime'];
}
