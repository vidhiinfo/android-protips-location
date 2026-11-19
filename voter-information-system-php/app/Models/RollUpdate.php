<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One uploaded voter roll compared with / applied to an assembly (see App\Services\Voters\RollUpdateService). */
class RollUpdate extends Model
{
    protected $table = 'roll_updates';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime', 'applied_at' => 'datetime', 'undone_at' => 'datetime', 'assembly_no' => 'integer'];

    public function countsData(): array { return json_decode((string) $this->counts, true) ?: ['actions' => [], 'booths' => []]; }
    public function errorList(): array { return json_decode((string) $this->errors, true) ?: []; }
    public function boothList(): array { return $this->booths === null || $this->booths === '' ? [] : array_map('intval', explode(',', $this->booths)); }
}
