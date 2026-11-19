<?php

namespace Tests\Feature;

use App\Models\Voter;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HideWardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        Voter::where('assembly_no', 45)->update(['ward_no' => 101]);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
    }

    public function test_ward_no_shown_by_default(): void
    {
        $this->get('/voters?assemblyNo=45')->assertOk()->assertSee(t('col.wardNo'))->assertSee('name="wardNo"', false);
    }

    public function test_ward_no_hidden_everywhere_when_switched_off(): void
    {
        config(['voters.show_ward_no' => false]);
        $v = Voter::where('assembly_no', 45)->first();
        $ward = t('col.wardNo');
        foreach (['/voters?assemblyNo=45', '/voters/' . $v->id, '/voters/' . $v->id . '/edit', '/dashboard?assemblyNo=45', '/analytics?assemblyNo=45',
                     '/reports/booth-summary?assemblyNo=45', '/survey?assemblyNo=45', '/admin/users/new', '/voters/print?assemblyNo=45'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('name="wardNo"', $html, $url);
            $this->assertStringNotContainsString('>' . $ward . '<', $html, $url);
            $this->assertStringNotContainsString('scope_wards', $html, $url);
        }
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->get('/voters/export/excel?assemblyNo=45')->baseResponse->getFile()->getPathname());
        $this->assertNotContains($ward, $book->getActiveSheet()->rangeToArray('A4:Z4')[0]);
        $tpl = tempnam(sys_get_temp_dir(), 't') . '.xlsx';
        file_put_contents($tpl, $this->get('/voters/import/template')->getContent());
        $row = \PhpOffice\PhpSpreadsheet\IOFactory::load($tpl)->getActiveSheet()->rangeToArray('A1:C2');
        $this->assertSame([t('col.assemblyNo'), t('col.assemblyName'), t('col.boothNo')], $row[0]);
        $this->assertSame(12, (int) $row[1][2]);   // sample row stays in line with the headers
    }
}
