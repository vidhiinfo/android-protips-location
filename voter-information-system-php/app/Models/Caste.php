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

    /** Display labels for the drop-downs: [caste or sub caste name => "Brahmin (ब्राह्मण)"]. */
    public static function labels(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $out = [];
        foreach (self::with(['subCastes' => fn ($q) => $q->where('active', true)])->where('active', true)->get() as $c) {
            $out[$c->name] = $c->name . ($c->name_local && $c->name_local !== $c->name ? ' (' . $c->name_local . ')' : '');
            foreach ($c->subCastes as $s) $out[$s->name] ??= $s->name . ($s->name_local && $s->name_local !== $s->name ? ' (' . $s->name_local . ')' : '');
        }
        return $cache = $out;
    }

    /** Fills empty Caste / Sub Caste masters with the Delhi list (db/caste-masters-delhi.sql). */
    public static function seedDefaults(): int
    {
        if (self::exists()) return 0;
        $file = base_path('db/caste-masters-delhi.sql');
        if (!is_file($file)) return 0;
        $sql = file_get_contents($file);
        $q = "'((?:[^']|'')*)'";
        preg_match_all("/INSERT IGNORE INTO castes \\(name, name_local, category, active\\) VALUES \\($q, (?:$q|NULL), (?:$q|NULL), 1\\);/u", $sql, $cm, PREG_SET_ORDER);
        foreach ($cm as $m) {
            self::create(['name' => str_replace("''", "'", $m[1]), 'name_local' => ($m[2] ?? '') !== '' ? str_replace("''", "'", $m[2]) : null,
                'category' => ($m[3] ?? '') !== '' ? $m[3] : null, 'active' => true]);
        }
        $ids = self::pluck('id', 'name')->all();
        preg_match_all("/INSERT IGNORE INTO sub_castes \\(caste_id, name, name_local, active\\) SELECT id, $q, (?:$q|NULL), 1 FROM castes WHERE name = $q;/u", $sql, $sm, PREG_SET_ORDER);
        foreach ($sm as $m) {
            $cid = $ids[str_replace("''", "'", $m[3])] ?? null;
            if ($cid) SubCaste::firstOrCreate(['caste_id' => $cid, 'name' => str_replace("''", "'", $m[1])], ['name_local' => ($m[2] ?? '') !== '' ? str_replace("''", "'", $m[2]) : null, 'active' => true]);
        }
        return count($cm);
    }
}
