<?php

namespace Tests\Feature;

use App\Models\{AppUser, Role, Voter};
use App\Services\Messaging\MessagingService;
use App\Services\Voters\VoterFilter;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
    }

    private function withMobile(int $n) { return Voter::where('assembly_no', 45)->whereNotNull('mobile')->orderBy('id')->limit($n)->get(); }

    public function test_consent_is_recorded_with_history_and_filters_messaging(): void
    {
        [$a, $b] = $this->withMobile(2)->all();
        $this->post('/voters/' . $a->id . '/consent', ['consent' => 'N', 'source' => 'phone', 'note' => 'asked not to call'])->assertRedirect('/voters/' . $a->id);
        $this->post('/voters/' . $b->id . '/consent', ['consent' => 'Y', 'source' => 'survey']);
        $this->assertSame(['N', 'phone', 'admin'], [$a->fresh()->consent_contact, $a->fresh()->consent_source, $a->fresh()->consent_by]);
        $this->assertSame(2, DB::table('consent_log')->count());
        $this->get('/voters/' . $a->id)->assertOk()->assertSee(t('privacy.consent.N'));
        $this->get('/voters?consent=N')->assertOk()->assertSee($a->epic_no)->assertDontSee($b->epic_no);

        $f = new VoterFilter(); $f->set('assemblyNo', 45);
        $t = \App\Models\MessageTemplate::create(['name' => 'T', 'channel' => 'SMS', 'language' => 'en', 'body' => 'Hello {name}', 'active' => true]);
        $p = app(MessagingService::class)->preview($f, $t);
        $this->assertSame(1, $p['noConsent']);   // the refused voter is never messaged
        config(['voters.privacy.consent_required' => true]);
        $p2 = app(MessagingService::class)->preview($f, $t);
        $this->assertSame(1, $p2['recipients']);   // only the voter who agreed
    }

    public function test_field_survey_records_consent(): void
    {
        $v = $this->withMobile(1)->first();
        $this->post('/survey/house/' . $v->id, ['m' => [$v->id => ['consent' => 'Y', 'mobile' => $v->mobile]]])->assertRedirect();
        $this->assertSame(['Y', 'survey'], [$v->fresh()->consent_contact, $v->fresh()->consent_source]);
        $this->get('/survey/house/' . $v->id)->assertOk()->assertSee(t('privacy.consentShort'));
    }

    public function test_access_export_and_erase_on_request(): void
    {
        $v = $this->withMobile(1)->first();
        $v->update(['caste' => 'Saini', 'political_status' => 'Supporter', 'remarks' => 'met twice']);
        DB::table('message_outbox')->insert(['campaign_id' => 1, 'voter_id' => $v->id, 'mobile' => '+91' . $v->mobile, 'body' => 'Hello', 'status' => 'SENT', 'created_at' => now()]);
        $res = $this->get('/voters/' . $v->id . '/privacy/export')->assertOk();
        $j = json_decode($res->getContent(), true);
        $this->assertSame([$v->epic_no, 'Saini'], [$j['voter']['epic_no'], $j['voter']['caste']]);
        $this->assertCount(1, $j['messages']);
        $this->post('/voters/' . $v->id . '/privacy/erase', ['confirm' => 'WRONG'])->assertSessionHas('error', 'privacy.eraseConfirmWrong');
        $this->assertSame('Saini', $v->fresh()->caste);
        $this->post('/voters/' . $v->id . '/privacy/erase', ['confirm' => strtolower($v->epic_no), 'reason' => 'letter 12'])->assertSessionHas('success', 'privacy.erased');
        $e = $v->fresh();
        $this->assertSame([null, null, null, null, 'N'], [$e->mobile, $e->caste, $e->political_status, $e->remarks, $e->consent_contact]);
        $this->assertSame([$v->epic_no, $v->name, $v->booth_no], [$e->epic_no, $e->name, $e->booth_no]);   // roll details stay
        $this->assertNotNull($e->erased_at);
        $this->assertSame('[erased]', DB::table('message_outbox')->where('voter_id', $v->id)->value('body'));
        $this->assertSame(1, DB::table('audit_log')->where('action', 'PRIVACY_ERASE')->count());
    }

    public function test_register_withdraw_retention_and_permission(): void
    {
        $v = $this->withMobile(1)->first();
        $this->post('/admin/privacy', ['type' => 'WITHDRAW', 'epic_no' => $v->epic_no, 'channel' => 'phone'])->assertRedirect('/admin/privacy');
        $this->assertSame('N', $v->fresh()->consent_contact);   // withdrawal takes effect at once
        $req = DB::table('privacy_requests')->first();
        $this->assertSame([$v->name, 'OPEN'], [$req->name, $req->status]);
        $this->get('/admin/privacy')->assertOk()->assertSee($v->name)->assertSee(t('privacy.type.WITHDRAW'));
        $this->post('/admin/privacy/' . $req->id . '/close', ['status' => 'DONE', 'outcome' => 'Consent withdrawn']);
        $this->assertSame('DONE', DB::table('privacy_requests')->value('status'));

        $this->post('/admin/privacy/retention', ['assembly_no' => 45, 'confirm' => 46])->assertSessionHas('error', 'privacy.eraseConfirmWrong');
        $left = Voter::where('assembly_no', 46)->whereNotNull('caste')->count();
        $this->post('/admin/privacy/retention', ['assembly_no' => 45, 'confirm' => 45])->assertRedirect('/admin/privacy');
        $this->assertSame(0, Voter::where('assembly_no', 45)->where(fn ($w) => $w->whereNotNull('caste')->orWhereNotNull('mobile'))->count());
        $this->assertSame($left, Voter::where('assembly_no', 46)->whereNotNull('caste')->count());   // other assemblies untouched

        $this->post('/logout');
        $op = AppUser::create(['username' => 'op', 'full_name' => 'op', 'enabled' => true, 'created_at' => now(), 'password_hash' => AppUser::hashPassword('secret@123')]);
        $op->roles()->attach(Role::where('code', 'OPERATOR')->value('id'));
        $this->post('/login', ['username' => 'op', 'password' => 'secret@123']);
        $this->get('/admin/privacy')->assertForbidden();
        $this->get('/voters/' . $v->id . '/privacy/export')->assertForbidden();
        $this->post('/voters/' . $v->id . '/consent', ['consent' => 'Y', 'source' => 'phone'])->assertRedirect();   // operators record consent
    }
}
