<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Grievance;
use App\Models\Voter;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GrievanceTransportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['voters.security.office_session_minutes' => 0]);   // these tests jump days ahead while logged in
    }

    private function login(string $u = 'admin', string $p = 'admin@123') { return $this->post('/login', ['username' => $u, 'password' => $p]); }

    private function log(array $extra = []): Grievance
    {
        $this->post('/grievances', array_merge(['name' => 'Asha', 'assembly_no' => 45, 'booth_no' => 1, 'category' => 'WATER', 'subject' => 'No water supply',
            'priority' => 'NORMAL', 'status' => 'OPEN'], $extra))->assertRedirect();
        return Grievance::latest('id')->first();
    }

    public function test_due_date_from_category_priority_and_escalation(): void
    {
        $this->login();
        $g = $this->log();
        $this->assertSame(3, (int) round($g->created_at->diffInDays($g->due_at)));   // WATER = 3 days
        $u = $this->log(['category' => 'ROAD', 'priority' => 'URGENT']);
        $this->assertSame(5, (int) round($u->created_at->diffInDays($u->due_at)));   // ROAD 15 / 3
        $this->post('/grievances/deadlines', ['days' => ['WATER' => 2], 'to' => ['WATER' => 'admin']])->assertRedirect();
        $this->assertSame(2, (int) DB::table('grievance_sla')->where('category', 'WATER')->value('days'));
        // 4 days later: WATER overdue (level 1), not yet twice the time
        $this->travel(4)->days();
        $svc = app(\App\Services\Grievances\GrievanceService::class);
        $this->assertSame(1, $svc->escalate(true));
        $this->assertSame(1, $g->fresh()->escalation_level);
        $this->get('/grievances?status=OVERDUE')->assertOk()->assertSee('No water supply');
        // 3 more days (7 > 3 + 3): level 2, priority raised
        $this->travel(3)->days();
        $svc->escalate(true);
        $this->assertSame([2, 'HIGH'], [$g->fresh()->escalation_level, $g->fresh()->priority]);
        $this->get('/grievances/' . $g->id)->assertOk()->assertSee('Escalated to level 2');
        // resolving stops it showing as overdue
        $this->post('/grievances/' . $g->id . '/status', ['status' => 'RESOLVED', 'resolution' => 'Pipe fixed'])->assertRedirect();
        $this->assertFalse($g->fresh()->overdue());
        $this->assertSame(1, DB::table('grievance_updates')->where('grievance_id', $g->id)->where('kind', 'STATUS')->where('text', 'like', '%Pipe fixed%')->count());
    }

    public function test_notes_and_photos(): void
    {
        $this->login();
        $g = $this->log();
        $this->post('/grievances/' . $g->id . '/note', ['text' => 'Called the Jal Board office'])->assertRedirect()->assertSessionHas('success');
        $img = UploadedFile::fake()->image('leak.jpg', 3000, 2000);
        $this->post('/grievances/' . $g->id . '/photos', ['photos' => [$img], 'stage' => 'BEFORE', 'caption' => 'Leaking pipe'])->assertRedirect()->assertSessionHas('success');
        $p = DB::table('grievance_photos')->first();
        $path = \App\Services\Grievances\GrievanceService::photoPath($p);
        $this->assertFileExists($path);
        $this->assertSame(1600, getimagesize($path)[0]);   // resized
        $this->get('/grievances/' . $g->id . '/photos/' . $p->id)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/grievances/' . $g->id)->assertOk()->assertSee('Called the Jal Board office')->assertSee('Leaking pipe');
        $this->post('/grievances/' . $g->id . '/photos', ['photos' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]])->assertSessionHasErrors();
        $this->post('/grievances/' . $g->id . '/photos/' . $p->id . '/delete')->assertRedirect();
        $this->assertFileDoesNotExist($path);
        // viewers have no grievance access
        $this->post('/logout');
        $this->login('viewer', 'viewer@123');
        $this->get('/grievances/' . $g->id . '/photos/' . $p->id)->assertForbidden();
    }

    public function test_transport_requests_board(): void
    {
        $e = Election::where('current', true)->first() ?? Election::create(['name' => 'Test 2027', 'type' => 'ASSEMBLY', 'election_year' => 2027, 'current' => true]);
        $old = Voter::where('assembly_no', 45)->where('booth_no', 1)->where('deleted', false)->orderBy('serial_no')->first();
        $old->update(['age' => 88]);
        $this->login();
        $this->get('/transport?assemblyNo=45&boothNo=1')->assertOk()->assertSee($old->name);   // offered as 85+
        $this->post('/transport', ['voter_ids' => [$old->id], 'reason' => 'AGE_85', 'need' => 'WHEELCHAIR', 'pickup_time' => '10:30', 'boothNo' => 1])->assertSessionHas('success');
        $this->post('/transport', ['voter_ids' => [$old->id], 'reason' => 'AGE_85', 'need' => 'VEHICLE'])->assertSessionHas('error');   // no duplicate
        $t = DB::table('transport_requests')->first();
        $this->assertSame([$e->id, 'REQUESTED', 'WHEELCHAIR'], [(int) $t->election_id, $t->status, $t->need]);
        $this->post('/transport/' . $t->id, ['vehicle' => 'UP32 AB 1234', 'driver_name' => 'Ramesh', 'driver_mobile' => '9810000077'])->assertRedirect();
        $this->assertSame('ASSIGNED', DB::table('transport_requests')->value('status'));
        $this->get('/transport/print?assemblyNo=45&boothNo=1')->assertOk()->assertSee('UP32 AB 1234');
        $this->post('/transport/' . $t->id, ['status' => 'VOTED'])->assertRedirect();
        $this->assertTrue(DB::table('poll_marks')->where('election_id', $e->id)->where('voter_id', $old->id)->exists());
        $this->get('/voters/' . $old->id)->assertOk()->assertSee('/transport?', false);
    }
}
