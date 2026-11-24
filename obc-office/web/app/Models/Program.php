<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $guarded = ['id', 'created_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function reports(): HasMany
    {
        return $this->hasMany(ProgramReport::class);
    }
}
