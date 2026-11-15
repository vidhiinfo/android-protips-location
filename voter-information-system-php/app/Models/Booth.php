<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booth extends Model
{
    protected $table = 'booths';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['assembly_no' => 'integer', 'booth_no' => 'integer'];
}
