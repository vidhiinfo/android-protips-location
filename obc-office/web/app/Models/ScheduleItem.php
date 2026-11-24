<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleItem extends Model
{
    protected $guarded = ['id', 'created_by'];

    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime'];
}
