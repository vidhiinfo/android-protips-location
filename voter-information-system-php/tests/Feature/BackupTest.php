<?php

namespace Tests\Feature;

use App\Services\System\BackupService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/vis-backup-test-' . uniqid();
        mkdir($this->dir . '/app', 0777, true);
        app()->useStoragePath($this->dir);
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(5);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/app/backups/{,.}*', GLOB_BRACE) ?: [] as $f) if (is_file($f)) unlink($f);
        @rmdir($this->dir . '/app/backups'); @rmdir($this->dir . '/app'); @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_backup_now_download_and_contents(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->get('/admin/backups')->assertOk()->assertSee(t('backup.none'))->assertSee('vis:backup');
        $this->post('/admin/backups')->assertRedirect('/admin/backups');
        $list = app(BackupService::class)->list();
        $this->assertCount(1, $list);
        $this->assertFileExists($this->dir . '/app/backups/.htaccess');
        $sql = gzdecode(file_get_contents($list[0]['path']));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertSame(DB::table('voters')->count(), $this->rowsOf($sql, 'voters'));
        $this->assertSame(DB::table('roles')->count(), $this->rowsOf($sql, 'roles'));
        $this->get('/admin/backups')->assertOk()->assertSee($list[0]['name']);
        $this->get('/admin/backups/' . $list[0]['name'])->assertOk()->assertHeader('content-type', 'application/gzip');
        $this->get('/admin/backups/..%2F.env')->assertNotFound();
        $this->assertSame(1, DB::table('audit_log')->where('action', 'DOWNLOAD')->where('entity', 'BACKUP')->count());
    }

    /** Rows of one table in the dump. */
    private function rowsOf(string $sql, string $table): int
    {
        $n = 0;
        foreach (preg_split('/^INSERT INTO /m', $sql) as $i => $chunk) {
            if ($i === 0 || !(str_starts_with($chunk, '"' . $table . '"') || str_starts_with($chunk, '`' . $table . '`'))) continue;
            $n += substr_count(explode(";\n", $chunk)[0], "\n(");
        }
        return $n;
    }

    public function test_command_keeps_newest_and_daily_check(): void
    {
        config(['voters.backup.keep' => 2]);
        $b = app(BackupService::class);
        $this->assertTrue($b->due());
        foreach ([1, 2, 3] as $i) { $this->artisan('vis:backup')->assertSuccessful(); if ($i < 3) sleep(1); }
        $this->assertCount(2, $b->list());
        $this->assertFalse($b->due());
        $this->artisan('vis:backup --if-due')->expectsOutput('Backup not due yet')->assertSuccessful();
        $this->assertCount(2, $b->list());
    }

    public function test_page_visit_makes_the_daily_backup_when_missing(): void
    {
        config(['voters.backup.on_visit' => true]);
        $this->get('/login')->assertOk();
        $this->assertCount(1, app(BackupService::class)->list());
        $this->get('/login')->assertOk();   // checked at most once an hour
        $this->assertCount(1, app(BackupService::class)->list());
    }

    public function test_only_user_admins(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->post('/logout');
        $op = \App\Models\AppUser::create(['username' => 'op', 'full_name' => 'op', 'enabled' => true, 'created_at' => now(), 'password_hash' => \App\Models\AppUser::hashPassword('secret@123')]);
        $op->roles()->attach(\App\Models\Role::where('code', 'OPERATOR')->value('id'));
        $this->post('/login', ['username' => 'op', 'password' => 'secret@123']);
        $this->get('/admin/backups')->assertForbidden();
        $this->post('/admin/backups')->assertForbidden();
    }
}
