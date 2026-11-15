<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Caste master: the list offered in the voter's Caste drop-down. */
class Caste extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean'];
    public const CATEGORIES = ['GEN', 'OBC', 'SC', 'ST'];

    public function subCastes() { return $this->hasMany(SubCaste::class)->orderBy('name'); }

    protected static function booted(): void
    {
        static::saving(function (Caste $c) {
            $c->name = trim(preg_replace('/\s+/u', ' ', (string) $c->name));
            $c->category = $c->category ? strtoupper($c->category) : null;
        });
    }

    /** Active castes with their active sub castes, for the voter form: [caste name => [sub caste names]]. */
    public static function tree(): array
    {
        $out = [];
        foreach (self::with(['subCastes' => fn ($q) => $q->where('active', true)])->where('active', true)->orderBy('name')->get() as $c) {
            $out[$c->name] = $c->subCastes->pluck('name')->all();
        }
        return $out;
    }
}
