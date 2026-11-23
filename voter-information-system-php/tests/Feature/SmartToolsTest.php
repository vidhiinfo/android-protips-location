<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
    }

    public function test_scan_and_voice_switch_on_and_off_in_settings(): void
    {
        // defaults: scan on, voice off
        $page = $this->get('/voters?assemblyNo=45')->assertOk();
        $page->assertSee('data-vt-scan="1"', false)->assertSee('data-vt-voice="0"', false)->assertSee('js/smart-tools.js', false)
            ->assertSee('data-scan="submit"', false)->assertSee('Read printed number');
        $this->get('/field')->assertOk()->assertSee('data-scan="input"', false);
        $this->get('/admin/settings')->assertOk()->assertSee('Smart tools')->assertSee('Voice typing');

        // voice on
        $this->post('/admin/settings', ['s' => ['voice_enabled' => '1']])->assertRedirect();
        $this->get('/grievances/new')->assertOk()->assertSee('data-vt-voice="1"', false);

        // both off: no buttons, no script, no labels
        $this->post('/admin/settings', ['s' => ['scan_enabled' => '0', 'voice_enabled' => '0']])->assertRedirect();
        $this->get('/voters?assemblyNo=45')->assertOk()->assertSee('data-vt-scan="0"', false)->assertDontSee('js/smart-tools.js', false)
            ->assertDontSee('Read printed number');
        $this->assertDatabaseHas('app_settings', ['name' => 'scan_enabled', 'value' => '0']);
    }

    public function test_voter_slips_carry_a_barcode_only_when_scanning_is_on(): void
    {
        $voters = \App\Models\Voter::where('assembly_no', 45)->where('deleted', false)->orderBy('id')->limit(2)->get();
        $pdf = app(\App\Services\Export\PdfExporter::class);
        config(['voters.tools.scan' => true]);
        $with = $pdf->slips($voters, null);
        config(['voters.tools.scan' => false]);
        $without = $pdf->slips($voters, null);
        $this->assertStringStartsWith('%PDF', $with);
        $this->assertGreaterThan(strlen($without), strlen($with));   // the barcodes add drawing to the page
    }

    public function test_reading_files_are_served_from_this_site(): void
    {
        foreach (['tesseract.min.js', 'worker.min.js', 'core/tesseract-core-lstm.wasm.js', 'core/tesseract-core-simd-lstm.wasm.js', 'lang/eng.traineddata.gz'] as $f) {
            $this->assertFileExists(public_path('vendor/tesseract/' . $f));
        }
        $this->assertFileExists(public_path('js/smart-tools.js'));
    }
}
