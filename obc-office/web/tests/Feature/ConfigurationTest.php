<?php

namespace Tests\Feature;

use App\Models\Lookup;
use App\Models\Petition;
use App\Models\Setting;

class ConfigurationTest extends OfficeTestCase
{
    public function test_branding_settings_are_saved_and_rendered(): void
    {
        $this->actingAs($this->admin())->post('/settings/general', [
            'org_name' => 'Test Morcha HQ', 'org_short_name' => 'Test Morcha', 'president_name' => 'Shri Test', 'theme_color' => '#112233',
            'greeting_days_ahead' => 5, 'petition_prefix' => 'GRV', 'petition_default_due_days' => 10,
            'petition_closed_status' => 'closed', 'photo_max_px' => 500, 'date_format' => 'd/m/Y',
            'datetime_format' => 'd/m/Y H:i', 'per_page' => 20, 'member_required_fields' => ['mobile'],
            'biodata_prefix' => 'BIO', 'biodata_max_file_mb' => 10, 'biodata_required_fields' => ['mobile'],
        ])->assertRedirect();

        $this->assertSame('Test Morcha HQ', setting('org_name'));
        $this->assertSame(['name', 'level', 'mobile'], setting('member_required_fields'));
        $this->assertFalse(setting('require_consent'));
        $this->get('/')->assertSee('Test Morcha')->assertSee('#112233', false);
    }

    public function test_petition_prefix_is_configurable(): void
    {
        Setting::put('petition_prefix', 'GRV');
        $this->actingAs($this->admin())->post('/petitions', [
            'petitioner_name' => 'A', 'subject' => 'S', 'priority' => 'normal', 'status' => 'received', 'received_on' => today()->toDateString(),
        ]);
        $this->assertStringStartsWith('GRV-'.date('Y').'-', Petition::first()->tracking_no);
    }

    public function test_dropdown_options_can_be_added_and_used(): void
    {
        $this->actingAs($this->admin())->post('/settings/lookups', ['group' => 'contact_category', 'label' => 'Sarpanch'])->assertRedirect();
        $this->assertDatabaseHas('lookups', ['group' => 'contact_category', 'value' => 'sarpanch']);
        $this->post('/contacts', ['name' => 'Gram Pradhan', 'category' => 'sarpanch'])->assertSessionHasNoErrors();
        $this->post('/contacts', ['name' => 'X', 'category' => 'not_a_category'])->assertSessionHasErrors('category');
    }

    public function test_locked_options_cannot_be_deleted(): void
    {
        $locked = Lookup::where(['group' => 'petition_status', 'value' => 'closed'])->first();
        $this->actingAs($this->admin())->delete(route('settings.lookups.destroy', $locked))->assertStatus(422);
    }

    public function test_permission_matrix_controls_access(): void
    {
        $staff = $this->makeUser('national_staff');
        $this->actingAs($staff)->get('/petitions')->assertOk();
        $this->get('/settings')->assertForbidden();

        $perms = setting('permissions');
        $perms['national_staff']['petitions'] = 'view';
        Setting::put('permissions', $perms);
        $this->get('/petitions')->assertOk();
        $this->get('/petitions/create')->assertForbidden();

        $perms['national_staff']['petitions'] = 'none';
        Setting::put('permissions', $perms);
        $this->get('/petitions')->assertForbidden();
    }

    public function test_module_switch_hides_module_even_for_super_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/visitors')->assertOk();
        Setting::put('modules_enabled', array_merge(setting('modules_enabled'), ['visitors' => false]));
        $this->get('/visitors')->assertForbidden();
        $this->get('/')->assertDontSee('Visitors today');
    }

    public function test_access_form_round_trip_never_disables_settings(): void
    {
        $this->actingAs($this->admin())->post('/settings/access', ['enabled' => ['members' => 1], 'perm' => ['state_admin' => ['members' => 'view', 'users' => 'manage']]])->assertRedirect();
        $this->assertTrue(setting('modules_enabled')['settings']);
        $this->assertSame('view', setting('permissions')['state_admin']['members']);
        $this->assertSame('manage', setting('permissions')['state_admin']['users']);
        $this->assertSame('none', setting('permissions')['state_admin']['petitions']);
    }

    public function test_state_admin_cannot_see_presidents_office_modules_by_default(): void
    {
        $this->actingAs($this->stateAdmin());
        foreach (['/schedule', '/visitors', '/petitions', '/contacts', '/letters', '/users', '/settings', '/audit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/tasks')->assertOk();
        $this->get('/circulars')->assertOk();
    }
}
