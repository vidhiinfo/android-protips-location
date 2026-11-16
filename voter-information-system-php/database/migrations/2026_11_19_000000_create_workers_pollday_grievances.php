<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Party workers (booth president, panna pramukh, volunteer) and their voters, poll-day "voted" marks, and voter grievances. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workers')) {
            Schema::create('workers', function (Blueprint $t) {
                $t->id();
                $t->string('name', 120);
                $t->string('mobile', 15)->nullable();
                $t->integer('assembly_no');
                $t->integer('booth_no')->nullable();
                $t->string('role', 30)->default('VOLUNTEER');
                $t->string('username', 50)->nullable()->comment('login of this worker: sees only the voters assigned to them');
                $t->boolean('active')->default(true);
                $t->string('notes', 300)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->index(['assembly_no', 'booth_no'], 'idx_worker_booth');
                $t->index('username', 'idx_worker_user');
            });
        }
        if (!Schema::hasColumn('voters', 'worker_id')) {
            Schema::table('voters', function (Blueprint $t) {
                $t->unsignedBigInteger('worker_id')->nullable();
                $t->index('worker_id', 'idx_voter_worker');
            });
        }
        if (!Schema::hasTable('poll_marks')) {
            Schema::create('poll_marks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('election_id');
                $t->unsignedBigInteger('voter_id');
                $t->integer('assembly_no');
                $t->integer('booth_no');
                $t->string('marked_by', 50)->nullable();
                $t->dateTime('marked_at');
                $t->unique(['election_id', 'voter_id'], 'uk_poll_mark');
                $t->index(['election_id', 'assembly_no', 'booth_no'], 'idx_poll_booth');
            });
        }
        if (!Schema::hasTable('grievances')) {
            Schema::create('grievances', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('voter_id')->nullable();
                $t->integer('assembly_no');
                $t->integer('ward_no')->nullable();
                $t->integer('booth_no')->nullable();
                $t->string('name', 120);
                $t->string('mobile', 15)->nullable();
                $t->string('category', 30);
                $t->string('subject', 200);
                $t->string('description', 2000)->nullable();
                $t->string('location', 300)->nullable();
                $t->string('priority', 10)->default('NORMAL');
                $t->string('status', 20)->default('OPEN');
                $t->unsignedBigInteger('worker_id')->nullable();
                $t->string('resolution', 1000)->nullable();
                $t->string('created_by', 50)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->dateTime('updated_at')->nullable();
                $t->dateTime('resolved_at')->nullable();
                $t->index(['assembly_no', 'booth_no'], 'idx_grv_booth');
                $t->index('status', 'idx_grv_status');
                $t->index('voter_id', 'idx_grv_voter');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grievances');
        Schema::dropIfExists('poll_marks');
        if (Schema::hasColumn('voters', 'worker_id')) Schema::table('voters', fn (Blueprint $t) => $t->dropColumn('worker_id'));
        Schema::dropIfExists('workers');
    }
};
