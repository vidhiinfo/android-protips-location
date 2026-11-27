<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A mandal: a named group of booths in one assembly, led by a mandal in-charge. */
class Mandal extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean', 'created_at' => 'datetime'];

    /** Booth numbers of this mandal. */
    public function boothList(): array { return \App\Support\Area::ints($this->booths); }

    /** "1-20, 25, 31" -> [1, 2, ..., 20, 25, 31] (ranges allowed, at most 2,000 booths). */
    public static function parseBooths(?string $s): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', trim((string) $s)) as $part) {
            if (preg_match('/^(\d{1,4})\s*-\s*(\d{1,4})$/', $part, $m)) { [$lo, $hi] = [min((int) $m[1], (int) $m[2]), max((int) $m[1], (int) $m[2])]; if ($hi - $lo <= 2000) $out = array_merge($out, range($lo, $hi)); }
            elseif (ctype_digit($part) && (int) $part > 0) $out[] = (int) $part;
        }
        $out = array_values(array_unique($out)); sort($out);
        return $out;
    }
}
