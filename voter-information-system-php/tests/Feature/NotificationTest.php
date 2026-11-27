<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Worker;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        Worker::query()->delete();
    }

    private function login(string $u, string $p, ?int $a = null) { $this->post('/logout'); return $this->post('/login', ['username' => $u, 'password' => $p, 'assemblyNo' => $a]); }

    private function person(string $username, string $role, int $booth, int $assembly = 45): Worker
    {
        AppUser::create(['username' => $username, 'password_hash' => bcrypt('pass12345'), 'full_name' => ucfirst($username), 'enabled' => true, 'assembly_no' => $assembly, 'created_at' => now()]);
        return Worker::create(['name' => ucfirst($username), 'assembly_no' => $assembly, 'booth_no' => $booth, 'role' => $role, 'username' => $username, 'active' => true, 'created_at' => now()]);
    }

    public function test_announcement_task_grievance_and_the_bell(): void
    {
        $p1 = $this->person('pres1', 'BOOTH_PRESIDENT', 1);
        $this->person('pres2', 'BOOTH_PRESIDENT', 2);
        $this->person('panna1', 'PANNA_PRAMUKH', 1);
        $this->person('pres52', 'BOOTH_PRESIDENT', 1, 52);
        $this->login('admin', 'admin@123');

        // announcement to the booth presidents of assembly 45: preview, then send
        $form = ['title' => 'Meeting at 5 pm', 'body' => 'Party office', 'target' => 'workerrole', 'role' => 'BOOTH_PRESIDENT', 'assembly' => 45, 'url' => url('/my/tasks')];
        $this->post('/notifications/send', $form + ['preview' => 1])->assertSessionHas('success', t('notif.willReach', 2));
        $this->assertSame(0, DB::table('notifications')->count());
        $this->post('/notifications/send', $form)->assertRedirect()->assertSessionHas('success', t('notif.sent', 2));
        $this->assertEqualsCanonicalizing(['pres1', 'pres2'], DB::table('notifications')->pluck('username')->all());
        $this->assertSame('/my/tasks', DB::table('notifications')->value('url'));
        // one booth only
        $this->post('/notifications/send', ['title' => 'Booth 1 team', 'target' => 'all', 'assembly' => 45, 'booth' => 1])->assertSessionHas('success', t('notif.sent', 2));
        $this->assertEqualsCanonicalizing(['pres1', 'panna1'], DB::table('notifications')->where('title', 'Booth 1 team')->pluck('username')->all());

        // a task given to a worker with a login
        $this->post('/workers/tasks', ['title' => 'Door to door, lane 3', 'kind' => 'VISIT', 'worker_id' => $p1->id, 'assemblyNo' => 45, 'due_date' => now()->addDay()->toDateString()])->assertRedirect();
        $this->assertSame(1, DB::table('notifications')->where('username', 'pres1')->where('kind', 'TASK')->count());

        // a grievance given to a worker
        $this->post('/grievances', ['name' => 'Ram', 'assembly_no' => 45, 'booth_no' => 1, 'category' => \App\Models\Grievance::CATEGORIES[0], 'subject' => 'Street light',
            'priority' => 'NORMAL', 'status' => 'OPEN', 'worker_id' => $p1->id])->assertRedirect();
        $g = DB::table('notifications')->where('username', 'pres1')->where('kind', 'GRIEVANCE')->first();
        $this->assertNotNull($g);
        $this->assertStringContainsString('Street light', $g->title);

        // the worker sees the bell with 4 unread, opens one (read + taken to its page), marks the rest read
        $this->login('pres1', 'pass12345', 45);
        $this->get('/notifications')->assertOk()->assertSee('data-notif', false)->assertSee('Meeting at 5 pm');
        $this->getJson('/notifications/count')->assertExactJson(['unread' => 4]);
        $first = DB::table('notifications')->where('username', 'pres1')->where('title', 'Meeting at 5 pm')->value('id');
        $this->get('/notifications/' . $first)->assertRedirect(url('/my/tasks'));
        $this->getJson('/notifications/count')->assertExactJson(['unread' => 3]);
        $this->post('/notifications/read-all')->assertRedirect();
        $this->getJson('/notifications/count')->assertExactJson(['unread' => 0]);
        $this->get('/notifications')->assertOk()->assertSee('Door to door, lane 3');
        // someone else's notification cannot be opened
        $other = DB::table('notifications')->where('username', 'pres2')->value('id');
        $this->get('/notifications/' . $other)->assertNotFound();
    }
}
