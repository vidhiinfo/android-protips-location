<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Election extends Model
{
    protected $table = 'elections';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['current' => 'boolean', 'poll_date' => 'date', 'election_year' => 'integer'];

    public const TYPES = ['ASSEMBLY', 'LOK_SABHA', 'MUNICIPAL', 'PANCHAYAT', 'BY_ELECTION'];

    public static function currentOrLatest(?int $id = null): ?self
    {
        if ($id) return static::find($id);
        return static::where('current', true)->first() ?? static::orderByDesc('election_year')->orderBy('name')->first();
    }
}
