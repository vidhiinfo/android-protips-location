<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    public $timestamps = false;

    public const LEVELS = ['national' => 'National', 'state' => 'State', 'district' => 'District', 'mandal' => 'Mandal / Block'];

    protected $fillable = ['level', 'title', 'seniority', 'max_count'];
}
