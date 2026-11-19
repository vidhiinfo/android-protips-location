<?php

namespace Tests\Feature;

use App\Models\{PoliticalStatus, SocialStatus, Voter};
use App\Services\Reports\AnalyticsService;
use App\Support\StatusMasters;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusMastersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StatusMasters::flush();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    private function login() { return $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']); }

    public function test_masters_are_seeded_and_listed(): void
    {
        $this->assertSame(28, SocialStatus::count());
        $this->assertSame(12, PoliticalStatus::count());
        $this->assertSame('S', PoliticalStatus::where('name', 'Supporter')->value('leaning'));
        $this->login();
        foreach (['socialstatuses' => 'Teacher', 'politicalstatuses' => 'Swing Voter', 'orgstatuses' => 'Panna Pramukh'] as $t => $see) {
            $this->get("/masters/$t")->assertOk()->assertSee($see);
        }
        $this->get('/masters/politicalstatuses')->assertSee(t('master.notInMaster'));   // demo data has "Neutral", "Opposition" typed earlier
    }

    public function test_drop_downs_on_entry_screens(): void
    {
        $v = Voter::where('assembly_no', 45)->first();
        $this->login();
        $this->get('/voters/' . $v->id . '/edit')->assertOk()->assertSee('<select name="political_status"', false)->assertSee('Strong Supporter (पक्का समर्थक)')
            ->assertSee('<select name="social_status"', false)->assertSee('<select name="organisational_status"', false);
        $this->get('/survey/house/' . $v->id)->assertOk()->assertSee('[political_status]" class="form-select"', false);
        $this->get('/voters?assemblyNo=45')->assertOk()->assertSee('name="socialStatus"', false)->assertSee('name="orgStatus"', false)->assertSee('name="sentiment"', false);
    }

    public function test_rename_cascades_filters_work_and_hindi_is_normalised(): void
    {
        $ids = Voter::where('assembly_no', 45)->orderBy('id')->limit(3)->pluck('id');
        Voter::whereIn('id', $ids)->update(['social_status' => 'Teacher', 'organisational_status' => 'Booth President', 'political_status' => 'Swing Voter']);
        $this->login();
        $t = SocialStatus::where('name', 'Teacher')->first();
        $this->post('/masters/socialstatuses', ['id' => $t->id, 'name' => 'School Teacher', 'name_local' => 'शिक्षक', 'group_name' => 'PROFESSION', 'sort_order' => 70, 'active' => 1])->assertRedirect();
        $this->assertSame(3, Voter::whereIn('id', $ids)->where('social_status', 'School Teacher')->count());
        $this->assertSame(0, Voter::where('social_status', 'Teacher')->count());
        $this->post('/masters/socialstatuses/' . $t->id . '/delete')->assertSessionHas('error', 'flash.inUse');
        $n = Voter::where('assembly_no', 45)->where('social_status', 'School Teacher')->count();
        $this->get('/voters?assemblyNo=45&socialStatus=School+Teacher')->assertOk()->assertSee(Voter::find($ids[0])->epic_no);
        $this->assertSame($n, \App\Services\Voters\VoterFilter::fromRequest(request()->merge(['socialStatus' => 'School Teacher', 'assemblyNo' => 45]))->apply(Voter::query())->count());
        $this->get('/voters?assemblyNo=45&orgStatus=Booth+President')->assertOk()->assertSee(Voter::find($ids[1])->epic_no);
        // bulk update with the Hindi name stores the master name
        $this->post('/voters/bulk', ['ids' => [$ids[0]], 'political_status' => 'पक्का समर्थक'])->assertRedirect();
        $this->assertSame('Strong Supporter', Voter::find($ids[0])->political_status);
    }

    public function test_analytics_leaning_comes_from_the_master(): void
    {
        PoliticalStatus::create(['name' => 'Hamare Saath', 'name_local' => 'हमारे साथ', 'leaning' => 'S', 'sort_order' => 1, 'active' => true]);
        StatusMasters::flush();
        Voter::where('assembly_no', 45)->update(['political_status' => null]);
        $ids = Voter::where('assembly_no', 45)->where('booth_no', 1)->orderBy('id')->limit(3)->pluck('id');
        Voter::where('id', $ids[0])->update(['political_status' => 'Hamare Saath']);        // no keyword: master says Support
        Voter::where('id', $ids[1])->update(['political_status' => 'Swing Voter']);         // master says Neutral
        Voter::where('id', $ids[2])->update(['political_status' => 'Not Met / Not Available']); // master says Not surveyed
        $row = app(AnalyticsService::class)->sentimentByBooth(45, 1)['45/1'];
        $this->assertSame([1, 1, 0], [$row['S'], $row['N'], $row['O']]);
        $this->login();
        $this->get('/voters?assemblyNo=45&sentiment=S')->assertOk()->assertSee(Voter::find($ids[0])->epic_no)->assertDontSee(Voter::find($ids[1])->epic_no);
    }

    public function test_beneficiary_master_drop_downs_filter_and_message_placeholder(): void
    {
        $this->assertSame(26, \App\Models\Beneficiary::count());
        $v = Voter::where('assembly_no', 45)->first();
        Voter::where('assembly_no', 45)->update(['beneficiary1' => null, 'beneficiary2' => null, 'beneficiary3' => null]);
        $v->update(['beneficiary2' => 'Ration Card']);
        $this->login();
        $this->get('/masters/beneficiaries')->assertOk()->assertSee('Ayushman Card')->assertSee(t('master.benCat.HELP'));
        $this->get('/voters/' . $v->id . '/edit')->assertOk()->assertSee('<select name="beneficiary1"', false)->assertSee('Ayushman Card (आयुष्मान कार्ड)');
        $this->get('/voters?assemblyNo=45&beneficiary=Ration+Card')->assertOk()->assertSee('<select name="beneficiary"', false)->assertSee($v->epic_no);
        $b = \App\Models\Beneficiary::where('name', 'Ration Card')->first();
        $this->post('/masters/beneficiaries', ['id' => $b->id, 'name' => 'Ration Card (new / update)', 'category' => 'HELP', 'sort_order' => 20, 'active' => 1])->assertRedirect();
        $this->assertSame('Ration Card (new / update)', $v->fresh()->beneficiary2);
        $this->assertSame('Ration Card (new / update)', \App\Services\Messaging\TemplateRenderer::placeholders()['helpGiven']($v->fresh()));
    }

    public function test_field_survey_card_has_every_field_and_saves_them(): void
    {
        $v = Voter::where('assembly_no', 45)->where('booth_no', 1)->orderBy('serial_no')->first();
        $this->login();
        $html = $this->get('/survey/house/' . $v->id)->assertOk()->getContent();
        foreach (['alt_mobile', 'organisational_status', 'beneficiary1', 'dob', 'remarks', 'political_status'] as $f) $this->assertStringContainsString("[{$v->id}][$f]", $html);
        $this->assertStringContainsString('data-quick-value="Supporter"', $html);                                        // one-tap political status
        $this->post('/survey/house/' . $v->id, ['m' => [$v->id => ['political_status' => 'समर्थक', 'beneficiary1' => 'आयुष्मान कार्ड', 'alt_mobile' => '091234 56789',
            'dob' => '1980-05-01', 'organisational_status' => 'Panna Pramukh', 'remarks' => 'Met at home']], 'go' => 'stay'])->assertSessionHas('success');
        $v->refresh();
        $this->assertSame(['Supporter', 'Ayushman Card', '9123456789', '1980-05-01', 'Panna Pramukh', 'Met at home'],
            [$v->political_status, $v->beneficiary1, $v->alt_mobile, $v->dob->format('Y-m-d'), $v->organisational_status, $v->remarks]);
        $this->post('/survey/house/' . $v->id, ['m' => [$v->id => ['dob' => '2999-01-01']]])->assertSessionHas('error');   // future date refused
    }
}
