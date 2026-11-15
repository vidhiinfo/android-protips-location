<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $table = 'candidates';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['assembly_no' => 'integer', 'ballot_no' => 'integer'];

    public function election() { return $this->belongsTo(Election::class); }
    public function party() { return $this->belongsTo(Party::class); }

    public function partyShort(): string { return $this->party?->short_name ?? 'IND'; }
    public function partyColor(): string { return $this->party?->color ?: '#6c757d'; }
}
