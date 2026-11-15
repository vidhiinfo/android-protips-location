<?php

namespace Tests\Unit;

use App\Services\Voters\VoterService;
use App\Support\FastValidator;
use App\Support\Validation;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** The import's fast validator must accept / reject exactly what Laravel's validator does. */
class FastValidatorTest extends TestCase
{
    public function test_same_verdict_as_laravel(): void
    {
        $ok = ['epic_no' => 'ABC1234567', 'name' => 'Ramesh', 'gender' => 'MALE', 'assembly_no' => 45, 'booth_no' => 3];
        $cases = [
            $ok,
            $ok + ['age' => 18, 'mobile' => '9876543210', 'pincode' => '302001', 'email' => 'a@b.in', 'relation_type' => 'HUSBAND', 'serial_no' => '7'],
            array_merge($ok, ['epic_no' => null]),
            array_merge($ok, ['epic_no' => '   ']),
            array_merge($ok, ['epic_no' => str_repeat('X', 21)]),
            array_merge($ok, ['name' => str_repeat('न', 120)]),
            array_merge($ok, ['name' => str_repeat('न', 121)]),
            array_merge($ok, ['gender' => 'male']),
            array_merge($ok, ['gender' => null]),
            $ok + ['age' => 17], $ok + ['age' => 131], $ok + ['age' => '45'], $ok + ['age' => '4.5'], $ok + ['age' => 'abc'],
            array_merge($ok, ['assembly_no' => 0]), array_merge($ok, ['assembly_no' => '12a']), array_merge($ok, ['booth_no' => null]),
            $ok + ['serial_no' => 0], $ok + ['mobile' => '5876543210'], $ok + ['mobile' => '98765'], $ok + ['alt_mobile' => '98765432100'],
            $ok + ['pincode' => '30200'], $ok + ['email' => 'not-an-email'], $ok + ['relation_type' => 'UNCLE'], $ok + ['remarks' => str_repeat('a', 501)],
            $ok + ['house_no' => str_repeat('1', 31)], $ok + ['category' => null],
            $ok + ['dob' => '1980-02-29', 'anniversary' => '2005-11-20', 'deleted' => '1', 'caste' => 'Jat', 'beneficiary1' => 'PM Awas'],
            $ok + ['dob' => '1981-02-29'], $ok + ['dob' => '29-02-1980'], $ok + ['dob' => '2999-01-01'], $ok + ['dob' => '1899-12-31'],
            $ok + ['anniversary' => 'tomorrow'], $ok + ['deleted' => 'yes'], $ok + ['deleted' => 0], $ok + ['caste' => str_repeat('क', 101)],
            $ok + ['membership_no' => str_repeat('9', 51)],
        ];
        $fast = new FastValidator(VoterService::rules(), Validation::messages());
        foreach ($cases as $i => $data) {
            $laravel = Validator::make($data, VoterService::rules(), Validation::messages());
            $err = $fast->firstError($data);
            $this->assertSame($laravel->fails(), $err !== null, "case $i: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            if ($err) {
                $field = array_key_first($laravel->errors()->messages());
                $this->assertSame([$field, $laravel->errors()->first($field)], $err, "case $i");
            }
        }
    }
}
