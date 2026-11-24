<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Letter extends Model
{
    protected $guarded = ['id', 'created_by'];

    protected $casts = ['letter_date' => 'date'];
}
