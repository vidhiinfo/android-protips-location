<?php

namespace Tests\Feature;

use App\Models\Voter;
use App\Support\NameParts;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class NamePartsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    private function login() { return $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']); }

    public function test_parts_are_computed_from_the_name_and_follow_every_change(): void
    {
        $this->assertSame(['DINESH', 'CHANDER', 'BISHT'], NameParts::split('DINESH CHANDER BISHT'));
        $this->assertSame(['BEENA', null, null], NameParts::split(' BEENA '));
        $this->assertSame(['RAM', 'KUMAR SHARMA', 'JI'], NameParts::split('RAM  KUMAR SHARMA JI'));
        $v = Voter::where('assembly_no', 45)->orderBy('id')->first();
        $v->update(['name' => 'DINESH CHANDER BISHT', 'relative_name' => 'PURAN BISHT']);
        $v->refresh();
        $this->assertSame(['DINESH', 'CHANDER', 'BISHT', 'PURAN', null, 'BISHT'],
            [$v->first_name, $v->middle_name, $v->last_name, $v->relative_first_name, $v->relative_middle_name, $v->relative_last_name]);
        \Illuminate\Support\Facades\DB::table('voters')->where('id', $v->id)->update(['name' => 'PANKAJ BISHT']);   // raw SQL (like an .sql import)
        $this->assertSame(['PANKAJ', null, 'BISHT'], array_values((array) \Illuminate\Support\Facades\DB::table('voters')->where('id', $v->id)->first(['first_name', 'middle_name', 'last_name'])));
        $this->assertSame(0, Voter::whereNotNull('name')->where('name', '<>', '')->whereNull('first_name')->count(), 'every voter has a first name');
    }

    public function test_voter_page_export_and_import_of_split_names(): void
    {
        $v = Voter::where('assembly_no', 45)->orderBy('id')->first();
        $v->update(['name' => 'SHWETA RANI BISHT', 'relative_name' => 'DINESH CHANDER BISHT']);
        $this->login();
        $this->get('/voters/' . $v->id)->assertOk()->assertSee(t('name.parts'))->assertSee('RANI')->assertSee(t('name.relParts'));
        $x = $this->get('/voters/export/excel?assemblyNo=45')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'exp') . '.xlsx';
        file_put_contents($path, $x->streamedContent() ?: $x->getContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
        $head = null; foreach ($sheet as $row) if (in_array(t('col.firstName'), $row, true)) { $head = $row; break; }
        $this->assertNotNull($head, 'export has the split name columns');
        foreach (['col.middleName', 'col.lastName', 'col.relFirstName', 'col.relLastName'] as $k) $this->assertContains(t($k), $head);
        // re-importing the export must not try to write the computed columns
        $this->post('/voters/import', ['file' => new UploadedFile($path, 'back.xlsx', null, null, true)])->assertOk();
        $this->assertSame('SHWETA RANI BISHT', $v->fresh()->name);
        // a sheet with only first / middle / last columns builds the full names
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray([
            ['Booth', 'Serial No', 'EPIC', 'First Name', 'Middle Name', 'Last Name', 'Father First Name', 'Father Last Name', 'Gender', 'Age'],
            ['3', '901', 'NPT1234567', 'Amit', 'Kumar', 'Saini', 'Hari', 'Saini', 'M', '30'],
        ]);
        $p2 = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, 'Xlsx')->save($p2);
        $this->post('/voters/import', ['file' => new UploadedFile($p2, 'split.xlsx', null, null, true), 'defaultAssembly' => 45])->assertOk();
        $n = Voter::where('epic_no', 'NPT1234567')->firstOrFail();
        $this->assertSame(['Amit Kumar Saini', 'Hari Saini', 'Amit', 'Kumar', 'Saini'], [$n->name, $n->relative_name, $n->first_name, $n->middle_name, $n->last_name]);
    }
}
