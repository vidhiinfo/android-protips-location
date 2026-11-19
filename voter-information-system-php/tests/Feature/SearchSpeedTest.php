<?php

namespace Tests\Feature;

use App\Models\Voter;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSpeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
    }

    private function matching(string $html): string { preg_match('/' . preg_quote(t('list.matching'), '/') . '<\/div><div class="num text-primary">([\d+]+)/', $html, $m); return $m[1] ?? '?'; }

    public function test_text_search_counts_and_epic_shortcut(): void
    {
        $v = Voter::where('assembly_no', 45)->orderBy('id')->first();
        $term = strtolower(explode(' ', $v->name)[0]);
        $f = new \App\Services\Voters\VoterFilter(); $f->set('q', $term);
        $expected = $f->apply(Voter::query())->count();   // every match, counted the slow way
        $this->assertGreaterThan(0, $expected);
        $this->assertSame((string) min($expected, \App\Services\Voters\VoterService::SEARCH_COUNT_CAP), rtrim($this->matching($this->get('/voters?q=' . urlencode($term))->assertOk()->getContent()), '+'));
        $this->get('/voters?q=' . urlencode($term) . '&page=2')->assertOk();
        // a complete EPIC No. goes straight to that voter, in any letter case
        $html = $this->get('/voters?q=' . strtolower($v->epic_no))->assertOk()->assertSee($v->epic_no)->getContent();
        $this->assertSame('1', $this->matching($html));
        // a partial EPIC still finds by "contains"
        $this->assertNotSame('0', $this->matching($this->get('/voters?q=' . substr($v->epic_no, 0, 6))->getContent()));
        $this->get('/voters?q=zzqqxx')->assertOk()->assertDontSee(t('list.searchSlow'));
    }
}
