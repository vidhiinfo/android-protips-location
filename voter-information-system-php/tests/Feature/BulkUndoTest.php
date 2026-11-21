<?php

namespace Tests\Feature;

use App\Models\{AppUser, Role, Voter};
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BulkUndoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    public function test_bulk_update_can_be_undone_keeping_later_edits(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $vs = Voter::where('assembly_no', 45)->orderBy('id')->limit(3)->get();
        $before = $vs->mapWithKeys(fn ($v) => [$v->id => [$v->caste, $v->political_status]])->all();
        $this->post('/voters/bulk', ['ids' => $vs->pluck('id')->all(), 'caste' => 'Zz Caste', 'political_status' => 'Supporter'])->assertRedirect();
        foreach ($vs as $v) $this->assertSame(['Zz Caste', 'Supporter'], [$v->fresh()->caste, $v->fresh()->political_status]);
        $change = DB::table('bulk_changes')->first();
        $this->assertSame([3, 'APPLIED'], [(int) $change->voter_count, $change->status]);
        // someone edits one voter after the bulk update
        $edited = $vs[1];
        DB::table('voters')->where('id', $edited->id)->update(['political_status' => 'Opposition']);
        $this->get('/voters/bulk-changes')->assertOk()->assertSee('Zz Caste')->assertSee(t('roll.undo'));
        $this->post('/voters/bulk-changes/' . $change->id . '/undo')->assertRedirect('/voters/bulk-changes')->assertSessionHas('success', t('bulkUndo.done', 3, 1));
        foreach ([$vs[0], $vs[2]] as $v) $this->assertSame($before[$v->id], [$v->fresh()->caste, $v->fresh()->political_status]);
        $this->assertSame([$before[$edited->id][0], 'Opposition'], [$edited->fresh()->caste, $edited->fresh()->political_status]);   // later edit kept
        $this->assertSame('UNDONE', DB::table('bulk_changes')->value('status'));
        $this->post('/voters/bulk-changes/' . $change->id . '/undo')->assertRedirect();   // second time: nothing happens
        $this->assertSame(1, DB::table('audit_log')->where('action', 'UNDO')->where('entity', 'BULK')->count());
    }

    public function test_only_the_creator_or_an_admin_can_undo(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->post('/voters/bulk', ['ids' => Voter::orderBy('id')->limit(2)->pluck('id')->all(), 'caste' => 'Zz Caste']);
        $this->post('/logout');
        $op = AppUser::create(['username' => 'op', 'full_name' => 'op', 'enabled' => true, 'created_at' => now(), 'password_hash' => AppUser::hashPassword('secret@123')]);
        $op->roles()->attach(Role::where('code', 'OPERATOR')->value('id'));
        $this->post('/login', ['username' => 'op', 'password' => 'secret@123']);
        $this->get('/voters/bulk-changes')->assertOk()->assertDontSee('Zz Caste');   // sees only own bulk updates
        $this->post('/voters/bulk-changes/' . DB::table('bulk_changes')->value('id') . '/undo')->assertForbidden();
    }
}
