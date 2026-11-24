<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_items', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('kind', 20)->default('meeting'); // meeting | tour | event | call | other
            $t->dateTime('start_at');
            $t->dateTime('end_at')->nullable();
            $t->string('location')->nullable();
            $t->string('contact_person')->nullable();
            $t->string('contact_mobile', 15)->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('planned');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('visitors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('mobile', 15)->nullable()->index();
            $t->string('from_place')->nullable();
            $t->string('purpose')->nullable();
            $t->string('referred_by')->nullable();
            $t->dateTime('visited_at');
            $t->string('status', 20)->default('waiting'); // waiting | met | rescheduled | left
            $t->text('remarks')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('petitions', function (Blueprint $t) {
            $t->id();
            $t->string('tracking_no')->nullable()->unique();
            $t->string('petitioner_name');
            $t->string('mobile', 15)->nullable();
            $t->text('address')->nullable();
            $t->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category')->nullable();
            $t->string('subject');
            $t->text('details')->nullable();
            $t->string('forwarded_to')->nullable();
            $t->string('priority', 10)->default('normal');
            $t->string('status', 20)->default('received'); // received | forwarded | in_progress | closed
            $t->date('received_on');
            $t->date('due_date')->nullable();
            $t->string('attachment_path')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('petition_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('petition_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->nullable();
            $t->text('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('details')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->date('due_date')->nullable();
            $t->string('priority', 10)->default('normal');
            $t->string('status', 20)->default('open'); // open | in_progress | done
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category', 30)->nullable(); // MP, MLA, Official, Journalist, Community leader...
            $t->string('organisation')->nullable();
            $t->string('designation')->nullable();
            $t->string('mobile', 15)->nullable();
            $t->string('email')->nullable();
            $t->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $t->date('dob')->nullable();
            $t->date('anniversary')->nullable();
            $t->date('last_met')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('letters', function (Blueprint $t) {
            $t->id();
            $t->string('direction', 10)->default('inward'); // inward | outward
            $t->string('ref_no')->nullable();
            $t->date('letter_date')->nullable();
            $t->string('party')->nullable(); // from / to
            $t->string('subject');
            $t->text('summary')->nullable();
            $t->string('action_taken')->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('circulars', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body')->nullable();
            $t->foreignId('target_state_id')->nullable()->constrained('states')->nullOnDelete(); // null = all states
            $t->string('attachment_path')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('circular_reads', function (Blueprint $t) {
            $t->foreignId('circular_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('read_at')->useCurrent();
            $t->primary(['circular_id', 'user_id']);
        });

        Schema::create('programs', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('program_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $t->date('event_date')->nullable();
            $t->string('venue')->nullable();
            $t->unsignedInteger('attendance')->nullable();
            $t->text('summary')->nullable();
            $t->json('photos')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 30);
            $t->string('entity', 40)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('detail')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'program_reports', 'programs', 'circular_reads', 'circulars', 'letters',
            'contacts', 'tasks', 'petition_updates', 'petitions', 'visitors', 'schedule_items'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
