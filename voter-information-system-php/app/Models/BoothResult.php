<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoothResult extends Model
{
    protected $table = 'booth_results';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['votes' => 'integer', 'booth_no' => 'integer', 'assembly_no' => 'integer'];

    public function candidate() { return $this->belongsTo(Candidate::class); }
}
