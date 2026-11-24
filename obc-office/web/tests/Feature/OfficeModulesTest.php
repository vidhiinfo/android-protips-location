<?php

namespace Tests\Feature;

use App\Models\Circular;
use App\Models\Member;
use App\Models\Petition;
use App\Models\Program;
use App\Models\Task;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OfficeModulesTest extends OfficeTestCase
{
    public function test_petition_lifecycle(): void
    {
        $this->actingAs($this->admin())->post('/petitions', [
            'petitioner_name' => 'Shyam', 'mobile' => '9876543210', 'subject' => 'Scholarship', 'priority' => 'high',
            'status' => 'received', 'received_on' => today()->toDateString(), 'state_id' => $this->up->id,
        ])->assertRedirect();
        $p = Petition::first();
        $this->assertMatchesRegularExpression('/^PET-\d{4}-00001$/', $p->tracking_no);

        $this->post(route('petitions.updates.store', $p), ['status' => 'forwarded', 'forwarded_to' => 'DM Lucknow', 'note' => 'Sent'])->assertRedirect();
        $this->assertSame('forwarded', $p->fresh()->status);
        $this->assertSame('DM Lucknow', $p->fresh()->forwarded_to);
        $this->assertCount(2, $p->updates);
        $this->get(route('petitions.ack', $p))->assertOk()->assertSee($p->tracking_no);
    }

    public function test_assignee_can_update_own_task_but_not_others(): void
    {
        $sa = $this->stateAdmin();
        $mine = Task::forceCreate(['title' => 'Send list', 'assigned_to' => $sa->id, 'status' => 'open', 'priority' => 'normal']);
        $other = Task::forceCreate(['title' => 'Secret', 'status' => 'open', 'priority' => 'normal']);

        $this->actingAs($sa)->get('/tasks')->assertSee('Send list')->assertDontSee('Secret');
        $this->patch(route('tasks.status', $mine), ['status' => 'done'])->assertRedirect();
        $this->assertSame('done', $mine->fresh()->status);
        $this->patch(route('tasks.status', $other), ['status' => 'done'])->assertForbidden();
        $this->get('/tasks/create')->assertForbidden();
    }

    public function test_circular_targeting_and_read_receipts(): void
    {
        $admin = $this->admin();
        $sa = $this->makeUser('state_admin', ['state_id' => $this->up->id, 'name' => 'UP Admin']);
        $rjAdmin = $this->makeUser('state_admin', ['state_id' => $this->rj->id, 'name' => 'RJ Admin']);
        $c = Circular::create(['title' => 'For UP only', 'target_state_id' => $this->up->id]);

        $this->actingAs($rjAdmin)->get('/circulars')->assertDontSee('For UP only');
        $this->get(route('circulars.show', $c))->assertForbidden();

        $this->actingAs($sa)->get(route('circulars.show', $c))->assertOk();
        $this->assertTrue($c->readers()->where('users.id', $sa->id)->exists());

        $this->actingAs($admin)->get(route('circulars.show', $c))->assertOk()->assertSee($sa->name)->assertDontSee($rjAdmin->name);
    }

    public function test_state_submits_programme_report_with_photos(): void
    {
        Storage::fake('local');
        $program = Program::create(['name' => 'Pakhwada']);
        $sa = $this->stateAdmin();

        $this->actingAs($sa)->post(route('programs.reports.store', $program), [
            'state_id' => $this->rj->id, 'event_date' => today()->toDateString(), 'attendance' => 250,
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect();

        $report = $program->reports()->first();
        $this->assertSame($this->up->id, $report->state_id, 'state is forced to own state');
        $this->assertCount(2, $report->photos);
        $this->get(route('programs.reports.photo', [$report, 0]))->assertOk();
    }

    public function test_greetings_list_todays_birthdays_with_whatsapp_link(): void
    {
        Member::create(['name' => 'Birthday Person', 'level' => 'state', 'state_id' => $this->up->id, 'type' => 'bearer',
            'status' => 'active', 'mobile' => '9876543210', 'dob' => now()->subYears(40)->toDateString()]);
        $this->actingAs($this->admin())->get('/greetings?days=0')->assertOk()
            ->assertSee('Birthday Person')->assertSee('https://wa.me/919876543210', false);
    }

    public function test_audit_log_records_changes(): void
    {
        $this->actingAs($this->admin())->post('/contacts', ['name' => 'Logged Contact']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'entity' => 'Contact', 'detail' => 'Logged Contact']);
        $this->get('/audit')->assertOk()->assertSee('Logged Contact');
    }
}
