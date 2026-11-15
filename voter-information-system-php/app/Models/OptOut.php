<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptOut extends Model
{
    protected $table = 'message_optouts';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime'];
}
