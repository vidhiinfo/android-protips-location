<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $guarded = ['id', 'created_by'];

    protected $casts = ['dob' => 'date', 'anniversary' => 'date', 'last_met' => 'date'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }
}
