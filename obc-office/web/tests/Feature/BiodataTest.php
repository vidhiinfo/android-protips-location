<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BiodataTest extends OfficeTestCase
{
    private function payload(array $over = []): array
    {
        return $over + [
            'received_on' => today()->toDateString(), 'received_from' => 'UP State President', 'received_via' => 'state_office',
            'category' => 'for_post', 'name' => 'Bio Person', 'father_name' => 'Father Name', 'mobile' => '9800000001',
            'state_id' => $this->up->id, 'district_id' => $this->lucknow->id,
        ];
    }

    public function test_office_records_biodata_with_photo_and_files(): void
    {
        Storage::fake('local');
        $this->actingAs($this->makeUser('national_staff'))->post('/biodata', $this->payload([
            'photo' => UploadedFile::fake()->image('face.jpg'),
            'documents' => [UploadedFile::fake()->create('biodata.pdf', 300, 'application/pdf'), UploadedFile::fake()->image('page2.jpg')],
        ]))->assertRedirect();

        $b = Biodata::first();
        $this->assertSame(sprintf('BIO-%s-%05d', date('Y'), $b->id), $b->reference_no);
        $this->assertSame('received', $b->status, 'defaults to the first configured status');
        $this->assertCount(2, $b->documents);
        Storage::disk('local')->assertExists($b->documents->first()->path);

        $this->get(route('biodata.show', $b))->assertOk()->assertSee('biodata.pdf');
        $this->get(route('biodata.documents.show', $b->documents->first()))->assertOk();
        $this->get(route('biodata.photo', $b))->assertOk();
        $this->get(route('biodata.print', $b))->assertOk()->assertSee('Bio Person');
    }

    public function test_search_filters_and_duplicate_detection(): void
    {
        $this->actingAs($this->admin());
        $this->post('/biodata', $this->payload(['received_on' => today()->subMonth()->toDateString(), 'received_from' => 'District office']));
        $this->post('/biodata', $this->payload())->assertSessionHas('ok', fn ($m) => str_contains($m, '1 earlier biodata'));
        $this->post('/biodata', $this->payload(['name' => 'Other Person', 'mobile' => '9800000009', 'father_name' => 'X', 'category' => 'general']));

        $this->get('/biodata?q=Other')->assertSee('Other Person')->assertDontSee('Bio Person');
        $this->get('/biodata?category=for_post')->assertSee('Bio Person')->assertDontSee('Other Person');
        $this->get('/biodata?from='.today()->toDateString())->assertSee('Other Person');

        $latest = Biodata::where('name', 'Bio Person')->latest('id')->first();
        $this->get(route('biodata.show', $latest))->assertSee('Also received earlier');
    }

    public function test_required_fields_and_reference_prefix_are_configurable(): void
    {
        Setting::put('biodata_required_fields', ['dob', 'documents']);
        Setting::put('biodata_prefix', 'BD');
        $this->actingAs($this->admin());
        $this->post('/biodata', $this->payload())->assertSessionHasErrors(['dob', 'documents']);
        $this->post('/biodata', $this->payload(['dob' => '1985-01-01', 'documents' => [UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')]]))
            ->assertSessionHasNoErrors();
        $this->assertStringStartsWith('BD-', Biodata::first()->reference_no);
    }

    public function test_custom_biodata_fields(): void
    {
        $this->actingAs($this->admin())->post('/settings/fields', ['entity' => 'biodata', 'label' => 'Assembly constituency', 'type' => 'text', 'required' => 1, 'active' => 1]);
        $this->post('/biodata', $this->payload())->assertSessionHasErrors('extra.assembly_constituency');
        $this->post('/biodata', $this->payload(['extra' => ['assembly_constituency' => 'Lucknow East']]))->assertSessionHasNoErrors();
        $this->get('/biodata/export')->assertOk();
        $this->assertStringContainsString('Lucknow East', $this->get('/biodata/export')->streamedContent());
    }

    public function test_state_users_have_no_access_by_default_but_can_be_granted_scoped_access(): void
    {
        $sa = $this->stateAdmin();
        Biodata::forceCreate(['name' => 'RJ Bio', 'received_on' => today(), 'state_id' => $this->rj->id]);
        $this->actingAs($sa)->get('/biodata')->assertForbidden();

        $perms = setting('permissions');
        $perms['state_admin']['biodata'] = 'manage';
        Setting::put('permissions', $perms);

        $this->get('/biodata')->assertOk()->assertDontSee('RJ Bio');
        $this->post('/biodata', $this->payload(['state_id' => $this->rj->id, 'district_id' => null]));
        $this->assertSame($this->up->id, Biodata::firstWhere('name', 'Bio Person')->state_id);
        $this->get(route('biodata.show', Biodata::firstWhere('name', 'RJ Bio')))->assertForbidden();
    }

    public function test_api_create_and_list(): void
    {
        Storage::fake('local');
        $staff = $this->makeUser('national_staff');
        $token = $this->postJson('/api/v1/login', ['username' => $staff->username, 'password' => 'Secret123', 'device_name' => 't'])->json('token');
        $h = ['Authorization' => "Bearer $token", 'Accept' => 'application/json'];

        $this->post('/api/v1/biodata', $this->payload(['documents' => [UploadedFile::fake()->image('page1.jpg')]]), $h)
            ->assertCreated()->assertJsonPath('name', 'Bio Person')->assertJsonCount(1, 'documents');
        $this->get('/api/v1/biodata?q=Bio', $h)->assertOk()->assertJsonPath('data.0.reference_no', Biodata::first()->reference_no);
    }
}
