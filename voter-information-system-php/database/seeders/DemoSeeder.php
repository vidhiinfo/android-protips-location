<?php

namespace Database\Seeders;

use App\Models\{AppUser, Assembly, Booth, BoothResult, Candidate, Election, MessageTemplate, Party, Role, Voter};
use App\Services\Auth\Defaults;
use App\Support\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Fictional sample data: three assemblies with Hindi / Urdu / Bengali local names, an election with booth results and users. */
class DemoSeeder extends Seeder
{
    private const REGIONS = [
        [45, 'Jaipur North', 'जयपुर उत्तर', 'Jaipur', 'Rajasthan',
            ['Ramesh:रमेश', 'Suresh:सुरेश', 'Mahesh:महेश', 'Rajesh:राजेश', 'Amit:अमित', 'Vikas:विकास', 'Sunil:सुनील', 'Anil:अनिल', 'Deepak:दीपक', 'Manoj:मनोज', 'Rohit:रोहित', 'Sanjay:संजय'],
            ['Sunita:सुनीता', 'Anita:अनीता', 'Kavita:कविता', 'Pooja:पूजा', 'Neha:नेहा', 'Priya:प्रिया', 'Rekha:रेखा', 'Seema:सीमा', 'Geeta:गीता', 'Meena:मीना', 'Ritu:रितु', 'Asha:आशा'],
            ['Sharma:शर्मा', 'Verma:वर्मा', 'Saini:सैनी', 'Gupta:गुप्ता', 'Singh:सिंह', 'Yadav:यादव', 'Meena:मीणा', 'Jain:जैन', 'Choudhary:चौधरी'],
            ['Shastri Nagar:शास्त्री नगर', 'Malviya Nagar:मालवीय नगर', 'Vaishali Nagar:वैशाली नगर', 'Ward 5:वार्ड 5'], 'गली'],
        [46, 'Lucknow West', 'لکھنؤ مغرب', 'Lucknow', 'Uttar Pradesh',
            ['Imran:عمران', 'Salman:سلمان', 'Arif:عارف', 'Faisal:فیصل', 'Javed:جاوید', 'Naved:نوید', 'Rizwan:رضوان', 'Tariq:طارق', 'Asif:آصف', 'Shahid:شاہد'],
            ['Ayesha:عائشہ', 'Fatima:فاطمہ', 'Zainab:زینب', 'Sana:ثنا', 'Nazia:نازیہ', 'Rubina:روبینہ', 'Shabana:شبانہ', 'Farhana:فرحانہ', 'Noor:نور', 'Saima:صائمہ'],
            ['Khan:خان', 'Ansari:انصاری', 'Siddiqui:صدیقی', 'Qureshi:قریشی', 'Rizvi:رضوی', 'Hashmi:ہاشمی'],
            ['Aminabad:امین آباد', 'Chowk:چوک', 'Nakhas:نخاس'], 'گلی'],
        [52, 'Kolkata Port', 'কলকাতা বন্দর', 'Kolkata', 'West Bengal',
            ['Subrata:সুব্রত', 'Partha:পার্থ', 'Arijit:অরিজিৎ', 'Sourav:সৌরভ', 'Debashis:দেবাশিস', 'Amit:অমিত', 'Tapas:তাপস', 'Biswajit:বিশ্বজিৎ', 'Rajib:রাজীব', 'Sanjoy:সঞ্জয়'],
            ['Rina:রিনা', 'Mousumi:মৌসুমী', 'Sumita:সুমিতা', 'Ananya:অনন্যা', 'Payel:পায়েল', 'Moumita:মৌমিতা', 'Rupa:রূপা', 'Shampa:শম্পা', 'Dipa:দীপা', 'Tanushree:তনুশ্রী'],
            ['Banerjee:ব্যানার্জি', 'Chatterjee:চ্যাটার্জি', 'Mukherjee:মুখার্জি', 'Das:দাস', 'Ghosh:ঘোষ', 'Bose:বসু', 'Sen:সেন', 'Dutta:দত্ত'],
            ['Garden Reach:গার্ডেন রিচ', 'Khidirpur:খিদিরপুর', 'Metiabruz:মেটিয়াবুরুজ'], 'রাস্তা'],
    ];

    private static function pair(string $s): array { return explode(':', $s); }
    private static function pick(array $a): array { return self::pair($a[mt_rand(0, count($a) - 1)]); }

    public function run(int $families = 220): void
    {
        app(Defaults::class)->ensure(true);
        if (Voter::exists()) return;
        mt_srand(42);
        $epic = 1000000;
        $boothsPer = 12;
        $now = now();
        DB::transaction(function () use (&$epic, $boothsPer, $families, $now) {
            foreach (self::REGIONS as [$ac, $name, $local, $district, $state, $male, $female, $surnames, $areas, $street]) {
                Assembly::create(['assembly_no' => $ac, 'name' => $name, 'name_local' => $local, 'district' => $district, 'state' => $state, 'reserved_for' => 'GEN']);
                $boothName = fn ($b) => 'Govt. School Room ' . (($b - 1) % 4 + 1) . ', ' . self::pair($areas[($b - 1) % count($areas)])[0];
                for ($b = 1; $b <= $boothsPer; $b++) Booth::create(['assembly_no' => $ac, 'booth_no' => $b, 'name' => $boothName($b), 'village_ward' => self::pair($areas[($b - 1) % count($areas)])[0]]);
                $serial = 1;
                $rows = [];
                $add = function (array $v) use (&$rows, &$epic, &$serial, $ac, $name, $district, $now) {
                    $rows[] = $v + ['epic_no' => $v['epic_no'] ?? sprintf('DEM%07d', ++$epic), 'assembly_no' => $ac, 'assembly_name' => $name, 'serial_no' => $serial++,
                        'district' => $district, 'created_at' => $now, 'updated_at' => $now];
                };
                for ($fam = 0; $fam < $families; $fam++) {
                    $sur = self::pick($surnames); $area = self::pick($areas);
                    $st = mt_rand(1, 30); $house = (string) mt_rand(1, 300); $booth = mt_rand(1, $boothsPer);
                    $head = self::pick($male); $grand = self::pick($male); $headAge = mt_rand(35, 69); $members = mt_rand(1, 5);
                    $base = ['booth_no' => $booth, 'booth_name' => $boothName($booth), 'house_no' => $house, 'address' => "Street $st",
                        'address_local' => "$street $st, {$area[1]}", 'village_ward' => $area[0],
                        'pincode' => $ac == 45 ? (string) (302001 + mt_rand(0, 8)) : ($ac == 46 ? (string) (226001 + mt_rand(0, 8)) : '700024')];
                    if ($fam % 12 == 5) {
                        $gm = (bool) mt_rand(0, 1); $gf = $gm ? $grand : self::pick($female); $rel = self::pick($male);
                        $add($base + ['name' => "{$gf[0]} {$sur[0]}", 'name_local' => "{$gf[1]} {$sur[1]}", 'gender' => $gm ? 'MALE' : 'FEMALE',
                            'relation_type' => $gm ? 'FATHER' : 'HUSBAND', 'relative_name' => "{$rel[0]} {$sur[0]}", 'relative_name_local' => "{$rel[1]} {$sur[1]}",
                            'age' => mt_rand(85, 96), 'mobile' => null, 'category' => null]);
                    }
                    for ($m = 0; $m < $members; $m++) {
                        if ($m == 0) { $isMale = true; $first = $head; $relative = $grand; $rel = 'FATHER'; $age = $headAge; }
                        elseif ($m == 1) { $isMale = false; $first = self::pick($female); $relative = $head; $rel = 'HUSBAND'; $age = $headAge - mt_rand(2, 6); }
                        else { $isMale = (bool) mt_rand(0, 1); $first = self::pick($isMale ? $male : $female); $relative = $head; $rel = 'FATHER'; $age = max(18, $headAge - mt_rand(22, 31)); }
                        $cat = mt_rand(0, 19);
                        $v = $base + ['name' => "{$first[0]} {$sur[0]}", 'name_local' => "{$first[1]} {$sur[1]}", 'gender' => $isMale ? 'MALE' : 'FEMALE',
                            'relation_type' => $rel, 'relative_name' => "{$relative[0]} {$sur[0]}", 'relative_name_local' => "{$relative[1]} {$sur[1]}", 'age' => $age,
                            'mobile' => mt_rand(0, 9) < 8 ? (string) (9000000000 + mt_rand(0, 999999999)) : null,
                            'category' => $cat < 7 ? 'GEN' : ($cat < 13 ? 'OBC' : ($cat < 16 ? 'SC' : ($cat < 18 ? 'ST' : null)))];
                        $add($v);
                        if ($fam % 40 == 7 && $m == 0) {   // deliberate duplicate (spacing / case differ)
                            $add(array_merge($v, ['epic_no' => sprintf('DUP%07d', ++$epic), 'name' => strtoupper(str_replace(' ', '  ', $v['name'])),
                                'age' => $v['age'] + 1, 'booth_no' => $booth % $boothsPer + 1, 'house_no' => (string) mt_rand(1, 300), 'address' => 'Street ' . mt_rand(1, 30), 'mobile' => null]));
                        }
                    }
                }
                foreach (array_chunk($rows, 300) as $chunk) Voter::insert($chunk);
            }

            $p = fn ($n, $l, $s, $sym, $c) => Party::create(['name' => $n, 'name_local' => $l, 'short_name' => $s, 'symbol' => $sym, 'color' => $c]);
            $p1 = $p('Jan Vikas Party', 'जन विकास पार्टी', 'JVP', 'Lamp', '#FF9933');
            $p2 = $p('Lok Samta Dal', 'लोक समता दल', 'LSD', 'Wheel', '#138808');
            $p3 = $p('Rashtriya Pragati Morcha', 'राष्ट्रीय प्रगति मोर्चा', 'RPM', 'Kite', '#1E90FF');
            $nota = $p('None of the Above', 'इनमें से कोई नहीं', 'NOTA', '', '#808080');
            $e = Election::create(['name' => 'Vidhan Sabha General Election 2023', 'type' => 'ASSEMBLY', 'election_year' => 2023, 'poll_date' => '2023-11-25', 'current' => true]);
            foreach (self::REGIONS as [$ac, , , , , $male, , $surnames]) {
                $cands = [];
                foreach ([$p1, $p2, $p3, null, $nota] as $i => $party) {
                    if ($party === $nota) { $cn = 'NOTA'; $cl = null; }
                    else { $f = self::pair($male[($i * 3 + $ac) % count($male)]); $s = self::pair($surnames[($i * 2 + 1) % count($surnames)]); $cn = "$f[0] $s[0]"; $cl = "$f[1] $s[1]"; }
                    $cands[] = Candidate::create(['election_id' => $e->id, 'assembly_no' => $ac, 'party_id' => $party?->id, 'ballot_no' => $i + 1, 'name' => $cn, 'name_local' => $cl]);
                }
                $w = [0.38 + mt_rand(0, 100) / 1000, 0.30 + mt_rand(0, 100) / 1000, 0.18, 0.08, 0.02];
                for ($b = 1; $b <= $boothsPer - 2; $b++) {
                    $electors = Voter::where('assembly_no', $ac)->where('booth_no', $b)->count();
                    $polled = (int) round($electors * (0.55 + mt_rand(0, 300) / 1000));
                    foreach ($cands as $i => $c) BoothResult::create(['election_id' => $e->id, 'assembly_no' => $ac, 'booth_no' => $b, 'candidate_id' => $c->id,
                        'votes' => (int) round($polled * $w[$i] * (0.75 + mt_rand(0, 500) / 1000)), 'entered_by' => 'demo', 'updated_at' => now()]);
                }
            }

            $tpl = fn ($n, $ch, $l, $body, $pid, $params) => MessageTemplate::create(['name' => $n, 'channel' => $ch, 'language' => $l, 'body' => $body,
                'provider_template_id' => $pid, 'params' => $params, 'active' => true]);
            $tpl('Polling day reminder', 'SMS', 'en', 'Dear {name}, please vote on polling day. Your booth: {boothNo} {boothName}, serial no. {serialNo}. - Jan Vikas Party', '1107170000000000001', 'name,boothNo,boothName,serialNo');
            $tpl('मतदान दिवस अनुस्मारक', 'SMS', 'hi', 'प्रिय {nameLocal}, कृपया मतदान अवश्य करें। आपका बूथ: {boothNo} {boothName}, क्रम सं. {serialNo}। - जन विकास पार्टी', '1107170000000000002', 'nameLocal,boothNo,boothName,serialNo');
            $tpl('ووٹنگ یاد دہانی', 'SMS', 'ur', 'محترم {nameLocal}، براہ کرم ووٹ ضرور دیں۔ آپ کا بوتھ: {boothNo} {boothName}، سیریل نمبر {serialNo}۔', '1107170000000000003', 'nameLocal,boothNo,boothName,serialNo');
            $tpl('ভোটের অনুস্মারক', 'SMS', 'bn', 'প্রিয় {nameLocal}, অনুগ্রহ করে ভোট দিন। আপনার বুথ: {boothNo} {boothName}, ক্রম নং {serialNo}।', '1107170000000000004', 'nameLocal,boothNo,boothName,serialNo');
            $tpl('Booth slip', 'WHATSAPP', 'en', 'Namaste {name}! Your voter slip: EPIC {epicNo}, booth {boothNo} ({boothName}), serial {serialNo}.', 'voter_slip', 'name,epicNo,boothNo,boothName,serialNo');
            $tpl('बूथ पर्ची', 'WHATSAPP', 'hi', 'नमस्ते {nameLocal}! आपकी मतदाता पर्ची: EPIC {epicNo}, बूथ {boothNo} ({boothName}), क्रम सं. {serialNo}।', 'voter_slip', 'nameLocal,epicNo,boothNo,boothName,serialNo');

            $agent = Role::create(['code' => 'BOOTH_AGENT', 'name' => 'Booth Agent', 'description' => 'Voter lookup and booth result entry', 'system_role' => false]);
            $agent->setPermissions(['VOTER_VIEW', 'RESULT_VIEW', 'RESULT_ENTRY']);
            foreach ([['operator', 'Data Entry Operator', 'OPERATOR', 'operator@123', null], ['viewer', 'Read-only Viewer', 'VIEWER', 'viewer@123', null],
                         ['jaipur', 'Jaipur North Operator', 'OPERATOR', 'jaipur@123', 45], ['agent', 'Booth Agent (Kolkata Port)', 'BOOTH_AGENT', 'agent@1234', 52]] as [$u, $fn, $role, $pw, $a]) {
                $user = AppUser::create(['username' => $u, 'full_name' => $fn, 'password_hash' => AppUser::hashPassword($pw), 'enabled' => true, 'assembly_no' => $a, 'created_at' => now()]);
                $user->roles()->attach(Role::where('code', $role)->value('id'));
            }
        });
    }
}
