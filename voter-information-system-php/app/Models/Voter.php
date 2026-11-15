<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Model;

/**
 * A voter. Text columns are Unicode (utf8mb4); *_local columns hold the regional-language spelling.
 */
class Voter extends Model
{
    protected $table = 'voters';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $casts = ['age' => 'integer', 'assembly_no' => 'integer', 'booth_no' => 'integer', 'serial_no' => 'integer'];

    public const GENDERS = ['MALE', 'FEMALE', 'OTHER'];
    public const RELATIONS = ['FATHER', 'HUSBAND', 'MOTHER', 'OTHER'];

    protected static function booted(): void
    {
        static::saving(function (Voter $v) {
            $v->epic_no = $v->epic_no === null ? null : strtoupper((string) Text::asciiDigits(trim($v->epic_no)));
            foreach (['mobile', 'alt_mobile', 'pincode'] as $f) {
                $v->$f = $v->$f === null ? null : (preg_replace('/[\s-]/', '', (string) Text::asciiDigits($v->$f)) ?: null);
            }
            foreach (['name', 'name_local', 'relative_name', 'relative_name_local', 'address', 'address_local', 'village_ward',
                         'district', 'assembly_name', 'booth_name', 'remarks', 'house_no', 'category', 'email'] as $f) {
                $v->$f = Text::nfc($v->$f);
            }
            $v->relation_type ??= 'FATHER';
        });
    }

    /** Message key of the relation prefix (S/O, D/O, W/O, C/O). */
    public function relationKey(): string
    {
        return match ($this->relation_type ?: 'FATHER') {
            'HUSBAND' => 'relation.prefix.wife',
            'OTHER' => 'relation.prefix.care',
            default => $this->gender === 'FEMALE' ? 'relation.prefix.daughter' : 'relation.prefix.son',
        };
    }

    public function relationDisplay(): string
    {
        return $this->relative_name ? t($this->relationKey()) . ' ' . $this->relative_name : '';
    }

    /** Address without the house no. (shown in its own column). */
    public function fullAddress(): string
    {
        return implode(', ', array_filter([$this->address, $this->village_ward, $this->district, $this->pincode], fn ($p) => $p !== null && $p !== ''));
    }

    public function genderLabel(): string
    {
        return $this->gender ? t('gender.' . $this->gender) : '';
    }
}
