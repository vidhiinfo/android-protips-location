<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grievances: due date per category (SLA), escalation when overdue, a timeline of updates and photos.
 * Poll day: transport requests for voters aged 85+ / with disability.
 */
return new class extends Migration
{
    /** Days to resolve, by category (editable under Grievances > Deadlines). */
    public const DEFAULT_DAYS = ['WATER' => 3, 'ELECTRICITY' => 2, 'ROAD' => 15, 'SEWER' => 5, 'GARBAGE' => 2, 'RATION' => 7, 'PENSION' => 30,
        'HEALTH' => 3, 'EDUCATION' => 15, 'DOCUMENTS' => 15, 'POLICE' => 3, 'HOUSING' => 30, 'EMPLOYMENT' => 30, 'OTHER' => 7];

    public function up(): void
    {
        Schema::table('grievances', function (Blueprint $t) {
            if (!Schema::hasColumn('grievances', 'due_at')) $t->dateTime('due_at')->nullable();
            if (!Schema::hasColumn('grievances', 'escalation_level')) $t->integer('escalation_level')->default(0)->comment('0 none, 1 overdue, 2 overdue twice the time');
            if (!Schema::hasColumn('grievances', 'escalated_at')) $t->dateTime('escalated_at')->nullable();
        });
        if (!Schema::hasTable('grievance_sla')) {
            Schema::create('grievance_sla', function (Blueprint $t) {
                $t->id();
                $t->string('category', 30)->unique('uk_grv_sla');
                $t->integer('days');
                $t->string('escalate_to', 50)->nullable()->comment('login told when overdue (level 1)');
                $t->string('escalate_to2', 50)->nullable()->comment('login told at twice the time (level 2)');
            });
            foreach (self::DEFAULT_DAYS as $c => $d) DB::table('grievance_sla')->insert(['category' => $c, 'days' => $d]);
        }
        if (!Schema::hasTable('grievance_updates')) {
            Schema::create('grievance_updates', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('grievance_id');
                $t->string('kind', 12)->comment('NOTE, STATUS, ESCALATED, PHOTO');
                $t->string('text', 1000)->nullable();
                $t->string('by_user', 50)->nullable();
                $t->dateTime('at');
                $t->index(['grievance_id', 'id'], 'idx_grv_upd');
            });
        }
        if (!Schema::hasTable('grievance_photos')) {
            Schema::create('grievance_photos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('grievance_id');
                $t->string('stage', 8)->default('BEFORE')->comment('BEFORE (the problem) or AFTER (the fix)');
                $t->string('path', 200)->comment('under storage/app/grievances');
                $t->string('mime', 40);
                $t->integer('bytes');
                $t->string('caption', 200)->nullable();
                $t->string('by_user', 50)->nullable();
                $t->dateTime('at');
                $t->index('grievance_id', 'idx_grv_photo');
            });
        }
        if (Schema::hasColumn('grievances', 'due_at') && !Schema::hasIndex('grievances', 'idx_grv_due')) {
            Schema::table('grievances', fn (Blueprint $t) => $t->index(['status', 'due_at'], 'idx_grv_due'));
        }
        if (!Schema::hasTable('transport_requests')) {
            Schema::create('transport_requests', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('voter_id');
                $t->unsignedBigInteger('election_id')->nullable();
                $t->integer('assembly_no');
                $t->integer('booth_no');
                $t->string('reason', 12)->comment('AGE_85, DISABLED, ILL, OTHER');
                $t->string('need', 12)->default('VEHICLE')->comment('VEHICLE, WHEELCHAIR, ESCORT');
                $t->string('pickup_address', 300)->nullable();
                $t->string('pickup_time', 5)->nullable();
                $t->string('mobile', 15)->nullable();
                $t->string('status', 10)->default('REQUESTED')->comment('REQUESTED, ASSIGNED, PICKED, VOTED, CANCELLED');
                $t->string('vehicle', 60)->nullable();
                $t->string('driver_name', 80)->nullable();
                $t->string('driver_mobile', 15)->nullable();
                $t->unsignedBigInteger('worker_id')->nullable();
                $t->string('notes', 500)->nullable();
                $t->string('created_by', 50)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->dateTime('updated_at')->nullable();
                $t->unique(['voter_id', 'election_id'], 'uk_transport_voter');
                $t->index(['assembly_no', 'booth_no', 'status'], 'idx_transport_booth');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_requests');
        Schema::dropIfExists('grievance_photos');
        Schema::dropIfExists('grievance_updates');
        Schema::dropIfExists('grievance_sla');
        Schema::table('grievances', function (Blueprint $t) {
            foreach (['due_at', 'escalation_level', 'escalated_at'] as $c) if (Schema::hasColumn('grievances', $c)) $t->dropColumn($c);
        });
    }
};
