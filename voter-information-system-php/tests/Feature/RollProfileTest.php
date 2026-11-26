<?php

namespace Tests\Feature;

use App\Models\Voter;
use App\Services\Reports\AnalyticsService;
use App\Services\Voters\FamilyService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    private function login() { return $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']); }

    private static function v(int $sr, string $name, string $rel, string $relName, string $g, int $age): object
    {
        return (object) ['id' => $sr, 'serial_no' => $sr, 'name' => $name, 'relation_type' => $rel, 'relative_name' => $relName, 'gender' => $g, 'age' => $age];
    }

    public function test_families_are_linked_only_through_the_names_printed_in_the_roll(): void
    {
        $house = [
            self::v(1, 'RANJIT KUMAR', 'FATHER', 'PRITHVI CHAND', 'MALE', 62),
            self::v(2, 'RAMESH ARORA', 'FATHER', 'ATAM PRAKASH ARORA', 'MALE', 62),
            self::v(3, 'RAJUNI ARORA', 'HUSBAND', 'RAMESH ARORA', 'FEMALE', 56),
            self::v(4, 'MAYANK ARORA', 'FATHER', 'RAMESH KUMAR ARORA', 'MALE', 28),     // middle name added
            self::v(5, 'HARJEET SINGH', 'FATHER', 'KULDEEP SINGH', 'MALE', 48),
            self::v(6, 'DALJEET SINGH', 'FATHER', 'KULDEEP SINGH', 'MALE', 43),         // brothers
            self::v(7, 'NARINDER KAUR', 'HUSBAND', 'DALJEET SINGH KHOKHAR', 'FEMALE', 37),
            self::v(8, 'NARINDER P SINGH', 'FATHER', 'BHAGWANT SINGH', 'MALE', 66),
            self::v(9, 'PARVINDER KAUR', 'HUSBAND', 'N P SINGH', 'FEMALE', 60),         // initials
            self::v(10, 'RAJ KUMARI', 'FATHER', 'RAMESH ARORA', 'FEMALE', 30),         // a father must be a man: links to Ramesh, not by gender mismatch
        ];
        $fam = app(FamilyService::class)->split($house);
        $ids = array_map(fn ($f) => array_map(fn ($v) => $v->id, $f), $fam);
        $sorted = array_map(function ($f) { sort($f); return $f; }, $ids);
        $this->assertContains([1], $sorted);
        $this->assertContains([2, 3, 4, 10], $sorted);
        $this->assertContains([5, 6, 7], $sorted);
        $this->assertContains([8, 9], $sorted);
        $this->assertSame(4, count($fam));
        $this->assertSame(2, $fam[1][0]->id, 'head = oldest man');
        // a woman is never taken as someone's father
        $two = app(FamilyService::class)->split([self::v(1, 'SUNITA', 'HUSBAND', 'X', 'FEMALE', 40), self::v(2, 'AMIT', 'FATHER', 'SUNITA', 'MALE', 20)]);
        $this->assertCount(2, $two);
        $this->assertTrue(FamilyService::sameName(['ashwani', 'dhall'], ['ashwini']));
        $this->assertFalse(FamilyService::sameName(['ajay', 'dhall'], ['ajay', 'kumar']));
        $this->assertFalse(FamilyService::sameName(['n'], ['narinder']), 'a lone initial is not enough');
    }

    public function test_household_view_shows_families_and_caste_can_go_to_one_family(): void
    {
        $vs = Voter::where('assembly_no', 45)->orderBy('id')->limit(4)->get();
        $same = ['house_no' => 'Q-77', 'address' => 'TEST LANE', 'village_ward' => 'W1', 'booth_no' => $vs[0]->booth_no, 'caste' => null, 'sub_caste' => null];
        $vs[0]->update($same + ['name' => 'RAMESH ARORA', 'relation_type' => 'FATHER', 'relative_name' => 'ATAM', 'gender' => 'MALE', 'age' => 60]);
        $vs[1]->update($same + ['name' => 'RAJUNI ARORA', 'relation_type' => 'HUSBAND', 'relative_name' => 'RAMESH ARORA', 'gender' => 'FEMALE', 'age' => 55]);
        $vs[2]->update($same + ['name' => 'HEMANT SAXENA', 'relation_type' => 'FATHER', 'relative_name' => 'RAGHUBIR PRASAD', 'gender' => 'MALE', 'age' => 43]);
        $vs[3]->update($same + ['name' => 'NEHA SAXENA', 'relation_type' => 'HUSBAND', 'relative_name' => 'HEMANT SAXENA', 'gender' => 'FEMALE', 'age' => 40]);
        $this->login();
        $this->get('/voters/' . $vs[0]->id . '/household')->assertOk()->assertSee(t('family.label', 1))->assertSee(t('family.label', 2))->assertSee(t('family.applyCaste', 2));
        $this->post('/voters/' . $vs[0]->id . '/household/caste', ['caste' => 'Test Caste', 'scope' => 'family'])->assertRedirect();
        $this->assertSame(['Test Caste', 'Test Caste', null, null], $vs->map(fn ($v) => $v->fresh()->caste)->all());
        $this->post('/voters/' . $vs[0]->id . '/household/caste', ['caste' => 'Other Caste'])->assertRedirect();   // default: whole house
        $this->assertSame(4, Voter::whereIn('id', $vs->pluck('id'))->where('caste', 'Other Caste')->count());
    }

    public function test_demographic_profile_adds_up_and_outreach_ranks_booths(): void
    {
        $an = app(AnalyticsService::class);
        $p = $an->build('demographic-profile', 45, null, null, null);
        $live = Voter::where('assembly_no', 45)->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted'));
        $this->assertSame((clone $live)->count(), $p->totals[3]);
        $this->assertSame((clone $live)->where('gender', 'FEMALE')->count(), $p->totals[5]);
        $this->assertSame((clone $live)->whereBetween('age', [18, 19])->count(), $p->totals[9]);
        $this->assertSame(array_sum(array_column($p->rows, 3)), $p->totals[3]);
        $this->assertGreaterThanOrEqual($p->totals[17], $p->totals[18], 'families >= houses');
        $o = $an->build('outreach-priority', 45, null, null, null);
        $this->assertSame(count($p->rows), count($o->rows));
        $scores = array_column($o->rows, 11);
        $sorted = $scores; rsort($sorted);
        $this->assertSame($sorted, $scores, 'highest score first');
        $this->assertSame(1, $o->rows[0][0]);
        $this->assertNotSame('', $o->note);
    }
}
