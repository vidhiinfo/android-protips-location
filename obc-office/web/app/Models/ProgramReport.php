<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramReport extends Model
{
    protected $fillable = ['program_id', 'state_id', 'district_id', 'event_date', 'venue', 'attendance', 'summary'];

    protected $casts = ['event_date' => 'date', 'photos' => 'array'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
