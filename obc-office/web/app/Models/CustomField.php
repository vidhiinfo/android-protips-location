<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CustomField extends Model
{
    public $timestamps = false;

    public const TYPES = ['text' => 'Text', 'textarea' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'select' => 'Dropdown', 'checkbox' => 'Yes / No'];

    protected $fillable = ['entity', 'key', 'label', 'type', 'options', 'required', 'show_in_list', 'sort', 'active'];

    protected $casts = ['required' => 'boolean', 'show_in_list' => 'boolean', 'active' => 'boolean'];

    public static function for(string $entity)
    {
        return static::where('entity', $entity)->where('active', true)->orderBy('sort')->get();
    }

    public function optionList(): array
    {
        return collect(preg_split('/\r?\n/', (string) $this->options))->map(fn ($o) => trim($o))->filter()->values()->all();
    }

    public function rules(): array
    {
        $r = [$this->required ? 'required' : 'nullable'];
        $r[] = match ($this->type) {
            'number' => 'numeric',
            'date' => 'date',
            'checkbox' => 'boolean',
            'select' => Rule::in($this->optionList()),
            default => 'string',
        };
        if (in_array($this->type, ['text'], true)) {
            $r[] = 'max:255';
        }

        return $r;
    }
}
