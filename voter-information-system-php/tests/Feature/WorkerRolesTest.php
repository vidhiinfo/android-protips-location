<?php

namespace Tests\Feature;

use App\Models\Mandal;
use App\Models\Worker;
use App\Services\Org\OrganisationService;
use App\Support\Lang;
use App\Support\WorkerRoles;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkerRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        Worker::query()->delete();
        WorkerRoles::flush();
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
    }

    public function test_rename_hide_and_own_role_in_the_chain(): void
    {
        $this->get('/masters/worker-roles')->assertOk()->assertSee(t('wrole.title'))->assertSee('BOOTH_AGENT');
        // rename the mandal in-charge (English + Hindi) and hide booth agent
        $this->post('/masters/worker-roles', ['r' => [
            'MANDAL_INCHARGE' => ['name' => 'Shakti Kendra Pramukh', 'name_hi' => 'शक्ति केंद्र प्रमुख', 'sort' => 30, 'active' => 1],
            'BOOTH_AGENT' => ['sort' => 50],
            'VOLUNTEER' => ['sort' => 70, 'active' => 1],
        ]])->assertRedirect();
        WorkerRoles::flush();
        $this->assertSame('Shakti Kendra Pramukh', role_label('MANDAL_INCHARGE'));
        Lang::setCurrent('hi'); $this->assertSame('शक्ति केंद्र प्रमुख', role_label('MANDAL_INCHARGE')); Lang::setCurrent('en');
        $this->assertNotContains('BOOTH_AGENT', WorkerRoles::codes(true));
        $form = $this->get('/workers/new?assemblyNo=45')->assertOk()->assertSee('Shakti Kendra Pramukh')->getContent();
        $this->assertStringNotContainsString('value="BOOTH_AGENT"', $form);

        // own role working like a booth president: fills the booth's president gap and is under the mandal in-charge
        $this->post('/masters/worker-roles/add', ['name' => 'Booth Sanyojak', 'name_hi' => 'बूथ संयोजक', 'base' => 'BOOTH_PRESIDENT'])->assertRedirect()->assertSessionHas('success');
        WorkerRoles::flush();
        $code = DB::table('worker_roles')->where('name', 'Booth Sanyojak')->value('code');
        $this->assertSame('BOOTH_SANYOJAK', $code);
        $this->assertSame('BOOTH_PRESIDENT', WorkerRoles::base($code));
        $m = Mandal::create(['assembly_no' => 45, 'name' => 'M1', 'booths' => '1, 2', 'active' => true]);
        $this->post('/workers', ['name' => 'Head', 'assembly_no' => 45, 'role' => 'MANDAL_INCHARGE', 'mandal_id' => $m->id, 'active' => 1])->assertRedirect();
        $this->post('/workers', ['name' => 'Sanyojak One', 'assembly_no' => 45, 'booth_no' => 1, 'role' => $code, 'active' => 1])->assertRedirect();
        $head = Worker::where('name', 'Head')->firstOrFail(); $s = Worker::where('name', 'Sanyojak One')->firstOrFail();
        $org = app(OrganisationService::class);
        $this->assertSame([$s->id], $org->under($head)->pluck('id')->all());
        $this->assertSame(1, $org->summary([45])[45]['withPresident']);
        $this->assertSame(1, $org->assembly(45)['booths'][1]['president']->count());
        $this->get('/workers/' . $s->id)->assertSee('Booth Sanyojak');
        // a hidden role cannot be given to a new worker
        $this->post('/workers', ['name' => 'Agent X', 'assembly_no' => 45, 'booth_no' => 2, 'role' => 'BOOTH_AGENT', 'active' => 1])->assertSessionHasErrors('role');

        // an own role in use cannot be deleted; unused it can; built-in never
        $this->post('/masters/worker-roles/' . $code . '/delete')->assertSessionHas('error');
        $s->update(['role' => 'VOLUNTEER']);
        $this->post('/masters/worker-roles/' . $code . '/delete')->assertSessionHas('success');
        $this->assertFalse(DB::table('worker_roles')->where('code', $code)->exists());
        $this->post('/masters/worker-roles/VOLUNTEER/delete')->assertNotFound();
    }
}
