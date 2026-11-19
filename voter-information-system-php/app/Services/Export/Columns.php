<?php

namespace App\Services\Export;

use App\Models\Voter;

/** Voter export columns (same keys and order as the Java version). pdf = relative width in the PDF, 0 = not in the PDF. */
class Columns
{
    public static function all(): array
    {
        $cols = self::columns();
        return Voter::showWard() ? $cols : array_values(array_filter($cols, fn ($c) => $c[0] !== 'col.wardNo'));
    }

    private static function columns(): array
    {
        return [
            ['col.assemblyNo', fn (Voter $v) => $v->assembly_no, 4],
            ['col.assemblyName', fn (Voter $v) => $v->assembly_name, 0],
            ['col.wardNo', fn (Voter $v) => $v->ward_no, 4],
            ['col.boothNo', fn (Voter $v) => $v->booth_no, 4],
            ['col.serialNo', fn (Voter $v) => $v->serial_no, 4],
            ['col.epicNo', fn (Voter $v) => $v->epic_no, 9],
            ['col.name', fn (Voter $v) => $v->name, 11],
            ['col.nameLocal', fn (Voter $v) => $v->name_local, 11],
            ['col.relation', fn (Voter $v) => $v->relation_type ? t('relation.' . $v->relation_type) : null, 0],
            ['col.relativeName', fn (Voter $v) => $v->relative_name, 11],
            ['col.relativeNameLocal', fn (Voter $v) => $v->relative_name_local, 11],
            ['col.gender', fn (Voter $v) => $v->gender ? t('gender.' . $v->gender) : null, 5],
            ['col.age', fn (Voter $v) => $v->age, 3],
            ['col.houseNo', fn (Voter $v) => $v->house_no, 4],
            ['col.address', fn (Voter $v) => $v->address, 9],
            ['col.addressLocal', fn (Voter $v) => $v->address_local, 0],
            ['col.villageWard', fn (Voter $v) => $v->village_ward, 0],
            ['col.district', fn (Voter $v) => $v->district, 0],
            ['col.pincode', fn (Voter $v) => $v->pincode, 0],
            ['col.mobile', fn (Voter $v) => $v->mobile, 8],
            ['col.altMobile', fn (Voter $v) => $v->alt_mobile, 0],
            ['col.email', fn (Voter $v) => $v->email, 0],
            ['col.boothName', fn (Voter $v) => $v->booth_name, 0],
            ['col.category', fn (Voter $v) => $v->category, 0],
            ['col.remarks', fn (Voter $v) => $v->remarks, 0],
            ...array_map(fn ($field, $d) => ['col.' . $d[0], fn (Voter $v) => $v->profileValue($field), 0], array_keys(Voter::PROFILE), Voter::PROFILE),
        ];
    }

    public static function pdf(): array { return array_values(array_filter(self::all(), fn ($c) => $c[2] > 0)); }
}
