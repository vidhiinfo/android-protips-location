<?php

namespace Database\Seeders;

use App\Models\Circular;
use App\Models\Contact;
use App\Models\Designation;
use App\Models\District;
use App\Models\Letter;
use App\Models\Member;
use App\Models\Petition;
use App\Models\PetitionUpdate;
use App\Models\Program;
use App\Models\ProgramReport;
use App\Models\ScheduleItem;
use App\Models\State;
use App\Models\Task;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Seeder;

/** Fictional sample data for demos and training (php artisan db:seed --class=DemoDataSeeder). */
class DemoDataSeeder extends Seeder
{
    private array $first = ['Ramesh', 'Suresh', 'Mahesh', 'Dinesh', 'Rajesh', 'Anil', 'Sunil', 'Vijay', 'Sanjay', 'Ajay',
        'Manoj', 'Pankaj', 'Rakesh', 'Mukesh', 'Ashok', 'Sunita', 'Kavita', 'Anita', 'Rekha', 'Pooja', 'Neha', 'Geeta', 'Meena', 'Seema'];

    private array $last = ['Yadav', 'Kushwaha', 'Maurya', 'Patel', 'Kurmi', 'Saini', 'Prajapati', 'Gupta', 'Verma', 'Chaurasia',
        'Nishad', 'Rajbhar', 'Pal', 'Sahu', 'Lodhi', 'Gurjar', 'Jat', 'Vishwakarma', 'Teli', 'Mali'];

    public function run(): void
    {
        $password = env('DEMO_PASSWORD', 'Demo@12345');
        $up = State::where('name', 'Uttar Pradesh')->first();
        $lko = District::where('name', 'Lucknow')->first();
        $raj = State::where('name', 'Rajasthan')->first();

        $staff = User::firstOrCreate(['username' => 'staff'], ['name' => 'Office Staff (Demo)', 'email' => 'staff@users.local', 'password' => $password, 'role' => 'national_staff']);
        $upAdmin = User::firstOrCreate(['username' => 'up_admin'], ['name' => 'UP State Admin (Demo)', 'email' => 'up_admin@users.local', 'password' => $password, 'role' => 'state_admin', 'state_id' => $up->id]);
        User::firstOrCreate(['username' => 'lucknow_admin'], ['name' => 'Lucknow District Admin (Demo)', 'email' => 'lko@users.local', 'password' => $password, 'role' => 'district_admin', 'state_id' => $up->id, 'district_id' => $lko->id]);
        User::firstOrCreate(['username' => 'rj_admin'], ['name' => 'Rajasthan State Admin (Demo)', 'email' => 'rj_admin@users.local', 'password' => $password, 'role' => 'state_admin', 'state_id' => $raj->id]);

        if (Member::count() > 0) {
            return;
        }

        mt_srand(42);
        $pick = fn (array $a) => $a[mt_rand(0, count($a) - 1)];
        $name = fn () => $pick($this->first).' '.$pick($this->last);
        $mobile = fn () => '9'.str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
        $desig = Designation::all()->groupBy('level');

        $make = function (array $attrs) use ($name, $mobile, $pick) {
            $n = $attrs['name'] ?? $name();

            return Member::create($attrs + [
                'name' => $n, 'type' => 'bearer', 'status' => 'active',
                'gender' => in_array(explode(' ', $n)[0], ['Sunita', 'Kavita', 'Anita', 'Rekha', 'Pooja', 'Neha', 'Geeta', 'Meena', 'Seema']) ? 'female' : 'male',
                'mobile' => $mobile(), 'dob' => now()->subYears(mt_rand(28, 65))->subDays(mt_rand(0, 364))->toDateString(),
                'profession' => $pick(['agriculture', 'business', 'advocate', 'teacher', 'social_worker']),
                'education' => $pick(['graduate', 'post_graduate', '12th']),
                'appointed_on' => now()->subMonths(mt_rand(1, 24))->toDateString(), 'consent' => true, 'consent_at' => now(),
            ]);
        };

        // National team
        foreach ($desig['national']->take(8) as $d) {
            foreach (range(1, min($d->max_count, 2)) as $_) {
                $make(['level' => 'national', 'designation_id' => $d->id]);
            }
        }
        // State teams (partially filled, so vacancies show up)
        foreach (State::whereIn('name', ['Uttar Pradesh', 'Bihar', 'Rajasthan', 'Madhya Pradesh', 'Maharashtra', 'Gujarat'])->get() as $i => $state) {
            foreach ($desig['state']->take(8 - $i) as $d) {
                $make(['level' => 'state', 'state_id' => $state->id, 'designation_id' => $d->id, 'photo_path' => null]);
            }
            foreach (District::where('state_id', $state->id)->get() as $dist) {
                foreach ($desig['district']->take(mt_rand(2, 5)) as $d) {
                    $make(['level' => 'district', 'state_id' => $state->id, 'district_id' => $dist->id, 'designation_id' => $d->id]);
                }
                foreach (range(1, mt_rand(2, 4)) as $_) {
                    $make(['type' => 'worker', 'level' => 'district', 'state_id' => $state->id, 'district_id' => $dist->id, 'skills' => $pick(['Social media', 'Booth management', 'Legal', 'Event management'])]);
                }
            }
        }
        // Someone with a birthday today, and a pending proposal from a state
        $make(['name' => 'Rajesh Kushwaha', 'level' => 'state', 'state_id' => $up->id, 'designation_id' => $desig['state']->firstWhere('title', 'Secretary')->id, 'dob' => now()->subYears(52)->toDateString()]);
        $make(['name' => 'Kavita Maurya', 'level' => 'district', 'state_id' => $up->id, 'district_id' => $lko->id, 'designation_id' => $desig['district']->firstWhere('title', 'Vice President')->id, 'status' => 'proposed']);

        // President's office
        $slots = [[10, 'meeting', 'Meeting with state presidents (VC)', 'National Office'], [12, 'media', 'Press interaction', 'Media Room'],
            [15, 'meeting', 'Delegation from Bihar', 'National Office'], [18, 'event', 'Samajik Nyay Sammelan', 'Talkatora Stadium, New Delhi']];
        foreach ($slots as [$h, $kind, $title, $loc]) {
            ScheduleItem::create(['title' => $title, 'kind' => $kind, 'start_at' => today()->setHour($h), 'end_at' => today()->setHour($h + 1), 'location' => $loc, 'status' => 'confirmed']);
        }
        ScheduleItem::create(['title' => 'Uttar Pradesh tour — Lucknow, Ayodhya', 'kind' => 'tour', 'start_at' => today()->addDays(3)->setHour(8), 'end_at' => today()->addDays(4)->setHour(20), 'location' => 'Lucknow', 'contact_person' => 'UP State President', 'status' => 'planned']);

        foreach (['Delegation from Varanasi' => 'met', 'Shri Mahesh Pal' => 'waiting', 'Students union representatives' => 'waiting'] as $n => $st) {
            Visitor::create(['name' => $n, 'mobile' => $mobile(), 'from_place' => $pick(['Varanasi', 'Patna', 'Jaipur', 'Bhopal']), 'purpose' => $pick(['Organisational matter', 'Courtesy call', 'Invitation for event']), 'visited_at' => now()->subMinutes(mt_rand(10, 200)), 'status' => $st]);
        }

        $subjects = ['Request for scholarship assistance', 'Inclusion of community in state OBC list', 'Medical help for treatment at AIIMS', 'Complaint regarding district office functioning', 'Invitation to state convention'];
        foreach ($subjects as $i => $subject) {
            $p = Petition::create(['petitioner_name' => $name(), 'mobile' => $mobile(), 'state_id' => $pick([$up->id, $raj->id]), 'subject' => $subject,
                'category' => $pick(['govt_scheme', 'health', 'organisational', 'education']), 'priority' => $pick(['normal', 'high']),
                'status' => $pick(['received', 'forwarded', 'in_progress', 'closed']), 'received_on' => today()->subDays($i * 4), 'due_date' => today()->subDays($i * 4)->addDays(15)]);
            PetitionUpdate::create(['petition_id' => $p->id, 'status' => 'received', 'note' => 'Petition registered.']);
        }

        Task::create(['title' => 'Send list of district presidents with photos', 'assigned_to' => $upAdmin->id, 'due_date' => today()->addDays(2), 'priority' => 'high', 'status' => 'open']);
        Task::create(['title' => 'Prepare speech notes for Sammelan', 'assigned_to' => $staff->id, 'due_date' => today(), 'priority' => 'urgent', 'status' => 'in_progress']);

        Contact::create(['name' => 'Shri Example MP', 'category' => 'mp', 'designation' => 'Member of Parliament', 'state_id' => $up->id, 'mobile' => $mobile(), 'dob' => now()->subYears(58)->addDay()->toDateString()]);
        Contact::create(['name' => 'Shri Example Journalist', 'category' => 'journalist', 'organisation' => 'Example News', 'mobile' => $mobile(), 'anniversary' => now()->subYears(20)->toDateString()]);

        Letter::create(['direction' => 'inward', 'ref_no' => 'UP/2026/114', 'letter_date' => today()->subDay(), 'party' => 'UP State Unit', 'subject' => 'Proposal for district office bearers', 'status' => 'pending']);

        Circular::create(['title' => 'Submit updated office-bearer lists with photos by month end', 'body' => "All state presidents are requested to update their state and district office-bearer data, including photographs, in the portal by the end of this month.\n\nThe national office will review completeness state-wise.", 'created_by' => User::where('role', 'super_admin')->value('id')]);

        $prog = Program::create(['name' => 'Samajik Nyay Pakhwada', 'description' => 'Fortnight of outreach programmes in every district.', 'start_date' => today()->subDays(5), 'end_date' => today()->addDays(10)]);
        foreach (District::where('state_id', $up->id)->take(4)->get() as $dist) {
            ProgramReport::forceCreate(['program_id' => $prog->id, 'state_id' => $up->id, 'district_id' => $dist->id, 'event_date' => today()->subDays(mt_rand(0, 4)),
                'venue' => $dist->name.' district office', 'attendance' => mt_rand(80, 600), 'summary' => 'Programme held successfully.', 'photos' => [], 'submitted_by' => $upAdmin->id]);
        }
    }
}
