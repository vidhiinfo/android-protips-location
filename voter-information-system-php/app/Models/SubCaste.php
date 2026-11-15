<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sub caste master; each sub caste belongs to one caste. */
class SubCaste extends Model
{
    protected $table = 'sub_castes';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean', 'caste_id' => 'integer'];

    public function caste() { return $this->belongsTo(Caste::class); }

    protected static function booted(): void
    {
        static::saving(fn (SubCaste $s) => $s->name = trim(preg_replace('/\s+/u', ' ', (string) $s->name)));
    }
}
