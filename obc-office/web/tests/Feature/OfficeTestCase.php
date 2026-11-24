<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\District;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class OfficeTestCase extends TestCase
{
    use RefreshDatabase;

    protected State $up;

    protected State $rj;

    protected District $lucknow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        Setting::flush();
        $this->up = State::where('name', 'Uttar Pradesh')->first();
        $this->rj = State::where('name', 'Rajasthan')->first();
        $this->lucknow = District::where('name', 'Lucknow')->first();
    }

    protected function makeUser(string $role, array $attrs = []): User
    {
        return User::create($attrs + [
            'name' => ucfirst($role), 'username' => $role.mt_rand(1000, 9999), 'email' => $role.mt_rand(1000, 99999).'@t.local',
            'password' => 'Secret123', 'role' => $role, 'active' => true,
        ]);
    }

    protected function admin(): User
    {
        return $this->makeUser('super_admin');
    }

    protected function stateAdmin(): User
    {
        return $this->makeUser('state_admin', ['state_id' => $this->up->id]);
    }

    protected function memberPayload(array $over = []): array
    {
        return $over + [
            'type' => 'bearer', 'name' => 'Test Person', 'mobile' => '9'.mt_rand(100000000, 999999999),
            'level' => 'district', 'state_id' => $this->up->id, 'district_id' => $this->lucknow->id,
            'designation_id' => Designation::where('level', 'district')->value('id'), 'consent' => '1',
        ];
    }
}
