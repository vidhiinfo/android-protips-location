<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assembly extends Model
{
    protected $table = 'assemblies';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['assembly_no' => 'integer'];

    public function display(): string
    {
        return $this->assembly_no . ' - ' . $this->name . ($this->name_local ? ' / ' . $this->name_local : '');
    }
}
