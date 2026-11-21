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
    protected $guarded = ['id', 'created_at', 'updated_at', 'house_key'];   // house_key is computed by the database
    protected $casts = ['consent_at' => 'datetime', 'erased_at' => 'datetime', 'age' => 'integer', 'assembly_no' => 'integer', 'booth_no' => 'integer', 'serial_no' => 'integer', 'ward_no' => 'integer',
        'dob' => 'date:Y-m-d', 'anniversary' => 'date:Y-m-d', 'deleted' => 'boolean'];
    protected $attributes = ['deleted' => false];

    /**
     * Profile and outreach fields: column => [message key suffix (col.* / field.*), type, max length].
     * One list used by the form, details page, Excel export / import and validation.
     */
    public const PROFILE = [
        'membership_no' => ['membershipNo', 'text', 50],
        'dob' => ['dob', 'date', null],
        'anniversary' => ['anniversary', 'date', null],
        'caste' => ['caste', 'text', 100],
        'sub_caste' => ['subCaste', 'text', 100],
        'social_status' => ['socialStatus', 'text', 100],
        'political_status' => ['politicalStatus', 'text', 100],
        'organisational_status' => ['organisationalStatus', 'text', 100],
        'beneficiary1' => ['beneficiary1', 'text', 150],
        'beneficiary2' => ['beneficiary2', 'text', 150],
        'beneficiary3' => ['beneficiary3', 'text', 150],
        'place_of_origin' => ['placeOfOrigin', 'text', 150],
        'current_place' => ['currentPlace', 'text', 150],
        'linked_with' => ['linkedWith', 'text', 150],
        'shift1' => ['shift1', 'text', 100],
        'shift2' => ['shift2', 'text', 100],
        'deleted' => ['deleted', 'checkbox', null],
    ];

    /** Ward No is shown unless SHOW_WARD_NO=false. */
    public static function showWard(): bool { return (bool) config('voters.show_ward_no', true); }

    public const GENDERS = ['MALE', 'FEMALE', 'OTHER'];
    public const RELATIONS = ['FATHER', 'HUSBAND', 'MOTHER', 'OTHER'];

    protected static function booted(): void
    {
        // a user with an access area (ward / booth / serial range) only ever sees the voters inside it
        static::addGlobalScope('area', fn ($q) => \App\Support\Area::apply($q, $q->getModel()->getTable() . '.'));
        static::saving(function (Voter $v) {
            $v->epic_no = $v->epic_no === null ? null : strtoupper((string) Text::asciiDigits(trim($v->epic_no)));
            foreach (['mobile', 'alt_mobile', 'pincode'] as $f) {
                $v->$f = $v->$f === null ? null : (preg_replace('/[\s-]/', '', (string) Text::asciiDigits($v->$f)) ?: null);
            }
            foreach (['name', 'name_local', 'relative_name', 'relative_name_local', 'address', 'address_local', 'village_ward',
                         'district', 'assembly_name', 'booth_name', 'remarks', 'house_no', 'category', 'email',
                         'membership_no', 'caste', 'sub_caste', 'social_status', 'political_status', 'organisational_status', 'beneficiary1',
                         'beneficiary2', 'beneficiary3', 'place_of_origin', 'current_place', 'linked_with', 'shift1', 'shift2'] as $f) {
                $v->$f = Text::nfc($v->$f);
            }
            $v->relation_type ??= 'FATHER';
            $v->deleted = (bool) $v->deleted;
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
        $parts = [];
        foreach ([$this->address, $this->village_ward, $this->district, $this->pincode] as $p) {
            if ($p === null || trim((string) $p) === '') continue;
            if (in_array(mb_strtolower(trim((string) $p)), array_map(fn ($x) => mb_strtolower($x), $parts), true)) continue;   // section written as address and ward
            $parts[] = trim((string) $p);
        }
        return implode(', ', $parts);
    }

    /** Display value of a profile field (dates as dd-mm-yyyy, the deleted flag as Yes / empty). */
    public function profileValue(string $field): ?string
    {
        $v = $this->$field;
        if ($v === null || $v === '') return null;
        if ($v instanceof \DateTimeInterface) return $v->format('d-m-Y');
        if ($field === 'deleted') return $v ? t('common.yes') : null;
        return (string) $v;
    }

    public function genderLabel(): string
    {
        return $this->gender ? t('gender.' . $this->gender) : '';
    }
}
