<?php

namespace Tests\Feature;

use App\Services\Auth\Defaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Login against the customer's own users / roles tables (USER_SOURCE=external). */
class ExternalUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('create table legacy_users (id integer primary key, login varchar(50), pwd varchar(100), active int, ac_no int, display_name varchar(100))');
        DB::statement('create table legacy_roles (id integer primary key, role_name varchar(50))');
        DB::statement('create table legacy_user_roles (user_id int, role_id int)');
        DB::table('legacy_roles')->insert([['id' => 1, 'role_name' => 'Supervisor'], ['id' => 2, 'role_name' => 'Field_Staff']]);
        DB::table('legacy_users')->insert([
            ['id' => 1, 'login' => 'boss', 'pwd' => password_hash('boss@123', PASSWORD_BCRYPT), 'active' => 1, 'ac_no' => null, 'display_name' => 'The Boss'],
            ['id' => 2, 'login' => 'field', 'pwd' => password_hash('field@123', PASSWORD_BCRYPT), 'active' => 1, 'ac_no' => 45, 'display_name' => 'Field Worker'],
            ['id' => 3, 'login' => 'gone', 'pwd' => password_hash('gone@123', PASSWORD_BCRYPT), 'active' => 0, 'ac_no' => null, 'display_name' => 'Left'],
        ]);
        DB::table('legacy_user_roles')->insert([['user_id' => 1, 'role_id' => 1], ['user_id' => 2, 'role_id' => 2], ['user_id' => 3, 'role_id' => 1]]);
        DB::table('assemblies')->insert([['assembly_no' => 45, 'name' => 'Jaipur North'], ['assembly_no' => 46, 'name' => 'Lucknow West']]);
        config(['voters.security.user_source' => 'external', 'voters.security.external' => [
            'users_query' => 'select login as username, pwd as password, active as enabled, ac_no as assembly_no, display_name as full_name from legacy_users where login = ?',
            'roles_query' => 'select r.role_name from legacy_user_roles ur join legacy_users u on u.id = ur.user_id join legacy_roles r on r.id = ur.role_id where u.login = ?',
            'role_map' => 'SUPERVISOR=ADMIN, FIELD_STAFF=VIEWER', 'connection' => '', 'password_encoding' => 'bcrypt']]);
        app(Defaults::class)->ensure(true);
    }

    public function test_no_built_in_admin_is_created(): void
    {
        $this->assertSame(0, DB::table('app_users')->count());
    }

    public function test_external_supervisor_becomes_admin(): void
    {
        $this->post('/login', ['username' => 'boss', 'password' => 'boss@123'])->assertRedirect('/dashboard');
        $this->get('/admin/roles')->assertOk();
        $this->get('/admin/users')->assertOk()->assertSee(t('admin.externalUsers'));
    }

    public function test_external_field_staff_is_viewer_locked_to_assembly(): void
    {
        $this->post('/login', ['username' => 'field', 'password' => 'field@123', 'assemblyNo' => 46])->assertRedirect('/login?error=assembly');
        $this->post('/login', ['username' => 'field', 'password' => 'field@123', 'assemblyNo' => 45])->assertRedirect('/dashboard');
        $this->get('/voters')->assertOk();
        $this->get('/voters/new')->assertForbidden();
    }

    public function test_disabled_or_wrong_password_is_rejected(): void
    {
        $this->post('/login', ['username' => 'gone', 'password' => 'gone@123'])->assertRedirect('/login?error=credentials');
        $this->post('/login', ['username' => 'boss', 'password' => 'wrong'])->assertRedirect('/login?error=credentials');
    }
}
