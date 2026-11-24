<?php

namespace Database\Seeders;

use App\Models\Designation;
use App\Models\District;
use App\Models\Lookup;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Reference data. Safe to re-run: only adds what is missing, never overwrites admin edits. */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['Andhra Pradesh', 'AP'], ['Arunachal Pradesh', 'AR'], ['Assam', 'AS'], ['Bihar', 'BR'], ['Chhattisgarh', 'CG'],
            ['Goa', 'GA'], ['Gujarat', 'GJ'], ['Haryana', 'HR'], ['Himachal Pradesh', 'HP'], ['Jharkhand', 'JH'],
            ['Karnataka', 'KA'], ['Kerala', 'KL'], ['Madhya Pradesh', 'MP'], ['Maharashtra', 'MH'], ['Manipur', 'MN'],
            ['Meghalaya', 'ML'], ['Mizoram', 'MZ'], ['Nagaland', 'NL'], ['Odisha', 'OD'], ['Punjab', 'PB'],
            ['Rajasthan', 'RJ'], ['Sikkim', 'SK'], ['Tamil Nadu', 'TN'], ['Telangana', 'TS'], ['Tripura', 'TR'],
            ['Uttar Pradesh', 'UP'], ['Uttarakhand', 'UK'], ['West Bengal', 'WB'],
        ];
        $uts = [
            ['Andaman and Nicobar Islands', 'AN'], ['Chandigarh', 'CH'], ['Dadra and Nagar Haveli and Daman and Diu', 'DH'],
            ['Delhi', 'DL'], ['Jammu and Kashmir', 'JK'], ['Ladakh', 'LA'], ['Lakshadweep', 'LD'], ['Puducherry', 'PY'],
        ];
        foreach ($states as [$n, $c]) {
            State::firstOrCreate(['name' => $n], ['code' => $c, 'is_ut' => false]);
        }
        foreach ($uts as [$n, $c]) {
            State::firstOrCreate(['name' => $n], ['code' => $c, 'is_ut' => true]);
        }

        // A starter set of districts; the full list is added from Masters (paste one per line) or via CSV import.
        $districts = [
            'Uttar Pradesh' => ['Agra', 'Ayodhya', 'Gorakhpur', 'Kanpur Nagar', 'Lucknow', 'Meerut', 'Prayagraj', 'Varanasi'],
            'Bihar' => ['Bhagalpur', 'Darbhanga', 'Gaya', 'Muzaffarpur', 'Patna'],
            'Rajasthan' => ['Ajmer', 'Bikaner', 'Jaipur', 'Jodhpur', 'Kota', 'Udaipur'],
            'Madhya Pradesh' => ['Bhopal', 'Gwalior', 'Indore', 'Jabalpur', 'Ujjain'],
            'Maharashtra' => ['Mumbai City', 'Nagpur', 'Nashik', 'Pune', 'Thane'],
            'Gujarat' => ['Ahmedabad', 'Rajkot', 'Surat', 'Vadodara'],
            'Delhi' => ['Central Delhi', 'New Delhi', 'North Delhi', 'South Delhi'],
        ];
        foreach ($districts as $state => $names) {
            $sid = State::where('name', $state)->value('id');
            foreach ($names as $n) {
                District::firstOrCreate(['state_id' => $sid, 'name' => $n]);
            }
        }

        // Posts per level with sanctioned seats — edit freely in Masters.
        $posts = [
            ['President', 1, 1], ['Vice President', 2, 6], ['General Secretary', 3, 3], ['Secretary', 4, 6],
            ['Treasurer', 5, 1], ['Spokesperson', 6, 2], ['Media In-charge', 7, 1], ['Social Media In-charge', 8, 1],
            ['Office Secretary', 9, 1], ['Executive Member', 20, 30],
        ];
        foreach (['national', 'state', 'district'] as $level) {
            foreach ($posts as [$title, $seniority, $max]) {
                Designation::firstOrCreate(['level' => $level, 'title' => $title], ['seniority' => $seniority, 'max_count' => $max]);
            }
        }
        foreach ([['President', 1, 1], ['General Secretary', 2, 2], ['Secretary', 3, 3], ['Member', 20, 15]] as [$title, $seniority, $max]) {
            Designation::firstOrCreate(['level' => 'mandal', 'title' => $title], ['seniority' => $seniority, 'max_count' => $max]);
        }

        // Dropdown lists. "locked" values are referenced by code; their labels can still be renamed.
        $lists = [
            'level' => [['national', 'National', true], ['state', 'State', true], ['district', 'District', true], ['mandal', 'Mandal / Block', true], ['booth', 'Booth', true]],
            'member_type' => [['bearer', 'Office Bearer', true], ['worker', 'Party Worker', true]],
            'member_status' => [['active', 'Active', true, 'green'], ['proposed', 'Proposed (awaiting approval)', true, 'amber'], ['removed', 'Removed', true, 'gray']],
            'gender' => [['male', 'Male'], ['female', 'Female'], ['other', 'Other']],
            'profession' => [['Agriculture'], ['Business'], ['Advocate'], ['Doctor'], ['Teacher'], ['Engineer'], ['Social Worker'], ['Student'], ['Government Service (Retd.)'], ['Private Service'], ['Other']],
            'education' => [['Below 10th'], ['10th'], ['12th'], ['Graduate'], ['Post Graduate'], ['Professional Degree'], ['Doctorate']],
            'petition_category' => [['organisational', 'Organisational'], ['govt_scheme', 'Government scheme'], ['police_legal', 'Police / Legal'], ['health', 'Health / Medical help'], ['education', 'Education'], ['employment', 'Employment'], ['recommendation', 'Recommendation letter'], ['other', 'Other']],
            'petition_status' => [['received', 'Received', true, 'blue'], ['forwarded', 'Forwarded', false, 'amber'], ['in_progress', 'In progress', false, 'amber'], ['closed', 'Closed', true, 'green']],
            'priority' => [['low', 'Low', false, 'gray'], ['normal', 'Normal', true, 'blue'], ['high', 'High', false, 'amber'], ['urgent', 'Urgent', false, 'red']],
            'schedule_kind' => [['meeting', 'Meeting', true], ['tour', 'Tour / Visit'], ['event', 'Event / Rally'], ['call', 'Call / VC'], ['media', 'Media / Interview'], ['personal', 'Personal']],
            'schedule_status' => [['planned', 'Planned', true, 'blue'], ['confirmed', 'Confirmed', false, 'green'], ['done', 'Done', false, 'gray'], ['cancelled', 'Cancelled', false, 'red']],
            'visitor_status' => [['waiting', 'Waiting', true, 'amber'], ['met', 'Met', false, 'green'], ['rescheduled', 'Rescheduled', false, 'blue'], ['left', 'Left without meeting', false, 'gray']],
            'task_status' => [['open', 'Open', true, 'blue'], ['in_progress', 'In progress', false, 'amber'], ['done', 'Done', true, 'green']],
            'contact_category' => [['mp', 'MP'], ['mla', 'MLA'], ['minister', 'Minister'], ['party_leader', 'Party leader'], ['official', 'Government official'], ['journalist', 'Journalist'], ['community', 'Community leader'], ['business', 'Business'], ['other', 'Other']],
            'letter_status' => [['pending', 'Pending', true, 'amber'], ['replied', 'Replied', false, 'green'], ['filed', 'Filed', false, 'gray']],
        ];
        foreach ($lists as $group => $items) {
            foreach ($items as $i => $item) {
                $value = $item[0];
                $label = $item[1] ?? $item[0];
                Lookup::firstOrCreate(['group' => $group, 'value' => Str::slug($value, '_')], [
                    'label' => $label, 'locked' => $item[2] ?? false, 'color' => $item[3] ?? null, 'sort' => ($i + 1) * 10, 'active' => true,
                ]);
            }
        }
    }
}
