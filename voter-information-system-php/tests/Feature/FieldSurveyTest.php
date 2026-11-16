<?php

namespace Tests\Feature;

use App\Models\{Caste, SubCaste, Voter};
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldSurveyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    private function login(string $u = 'admin', string $p = 'admin@123') { return $this->post('/login', ['username' => $u, 'password' => $p]); }

    public function test_bulk_update_sets_only_filled_fields_for_ticked_voters(): void
    {
        $c = Caste::firstOrCreate(['name' => 'Saini'], ['category' => 'OBC', 'active' => true]);
        SubCaste::firstOrCreate(['caste_id' => $c->id, 'name' => 'Mali'], ['active' => true]);
        $vs = Voter::where('assembly_no', 45)->orderBy('id')->limit(3)->get();
        Voter::whereIn('id', $vs->pluck('id'))->update(['social_status' => 'Teacher', 'category' => null]);
        $this->login();
        $this->post('/voters/bulk', ['ids' => [$vs[0]->id, $vs[1]->id], 'caste' => 'Saini', 'sub_caste' => 'Mali', 'political_status' => 'Supporter'])
            ->assertRedirect()->assertSessionHas('success');
        foreach ([0, 1] as $i) {
            $v = $vs[$i]->fresh();
            $this->assertSame(['Saini', 'Mali', 'Supporter', 'Teacher', 'OBC'], [$v->caste, $v->sub_caste, $v->political_status, $v->social_status, $v->category]);
        }
        $this->assertNotSame('Supporter-x', $vs[2]->fresh()->political_status);
        $this->assertSame($vs[2]->caste, $vs[2]->fresh()->caste);
        $this->post('/voters/bulk', ['ids' => [$vs[0]->id], 'caste' => '-'])->assertRedirect();
        $this->assertNull($vs[0]->fresh()->caste);
        $this->assertNull($vs[0]->fresh()->sub_caste);
        $this->post('/voters/bulk', ['ids' => [$vs[0]->id]])->assertSessionHas('error', 'bulk.nothing');
        $this->get('/voters?assemblyNo=45')->assertOk()->assertSee('data-bulk-bar', false);
    }

    public function test_viewer_cannot_bulk_update_or_survey(): void
    {
        $id = Voter::first()->id;
        $this->login('viewer', 'viewer@123');
        $this->post('/voters/bulk', ['ids' => [$id], 'caste' => 'X'])->assertForbidden();
        $this->get('/survey')->assertForbidden();
        $this->get('/voters')->assertOk()->assertDontSee('data-bulk-bar', false);
    }

    public function test_field_survey_lists_houses_and_saves_each_member(): void
    {
        $v = Voter::where('assembly_no', 45)->where('booth_no', 1)->orderBy('serial_no')->first();
        $this->login();
        $this->get('/survey?assemblyNo=45&boothNo=1')->assertOk()->assertSee(url('/survey/house/' . $v->id), false);
        $members = app(\App\Services\Voters\VoterService::class)->household($v);
        $this->get('/survey/house/' . $v->id)->assertOk()->assertSee($v->name);
        $data = [];
        foreach ($members as $m) $data[$m->id] = ['mobile' => '+91 98765-43210', 'political_status' => 'Neutral', 'caste' => 'Jat', 'beneficiary1' => 'Ujjwala'];
        $this->post('/survey/house/' . $v->id, ['m' => $data, 'go' => 'stay'])->assertRedirect('/survey/house/' . $v->id)->assertSessionHas('success');
        foreach ($members as $m) {
            $m = $m->fresh();
            $this->assertSame(['9876543210', 'Neutral', 'Jat', 'Ujjwala'], [$m->mobile, $m->political_status, $m->caste, $m->beneficiary1]);
        }
        $this->post('/survey/house/' . $v->id, ['m' => [$v->id => ['mobile' => '12345']]])->assertSessionHas('error');
        $this->assertSame('9876543210', $v->fresh()->mobile);
    }

    public function test_audit_log_records_ip_address_and_device(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '49.36.10.20', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36'])
            ->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $log = \App\Models\AuditLog::where('action', 'LOGIN')->latest('id')->first();
        $this->assertSame('49.36.10.20', $log->ip_address);
        $this->get('/admin/audit?ip=49.36')->assertOk()->assertSee('49.36.10.20')->assertSee('Chrome / Android');
        $this->get('/admin/audit?ip=10.0.0')->assertOk()->assertDontSee('49.36.10.20');
    }
}
