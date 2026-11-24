<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $guarded = ['id', 'created_by'];

    protected $casts = ['visited_at' => 'datetime'];
}
