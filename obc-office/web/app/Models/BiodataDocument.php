<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiodataDocument extends Model
{
    protected $fillable = ['biodata_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    public function biodata(): BelongsTo
    {
        return $this->belongsTo(Biodata::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, round($this->size / 1024)).' KB';
    }
}
