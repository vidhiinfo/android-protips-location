<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field tools: GPS location of each house, tasks and targets for workers, daily attendance (check-in / check-out),
 * and the record of merged duplicate voters (so a merge can be undone).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('house_locations')) {
            Schema::create('house_locations', function (Blueprint $t) {
                $t->id();
                $t->integer('assembly_no');
                $t->integer('booth_no');
                $t->string('house_ref', 191)->comment('house no. key | address | village (same grouping as the field survey)');
                $t->unsignedBigInteger('voter_id')->nullable()->comment('first voter of the house when the location was taken');
                $t->decimal('lat', 10, 7);
                $t->decimal('lng', 10, 7);
                $t->integer('accuracy_m')->nullable();
                $t->string('by_user', 50)->nullable();
                $t->dateTime('at');
                $t->unique(['assembly_no', 'booth_no', 'house_ref'], 'uk_house_loc');
            });
        }
        if (!Schema::hasTable('worker_tasks')) {
            Schema::create('worker_tasks', function (Blueprint $t) {
                $t->id();
                $t->string('title', 200);
                $t->string('kind', 20)->default('OTHER')->comment('SURVEY (target = voters to survey), VISIT, CALL, MEETING, OTHER');
                $t->unsignedBigInteger('worker_id')->nullable();
                $t->string('username', 50)->nullable()->comment('login the task is for');
                $t->integer('assembly_no');
                $t->integer('booth_no')->nullable();
                $t->integer('target')->nullable();
                $t->integer('done_count')->nullable()->comment('progress reported by the worker (non-survey tasks)');
                $t->date('due_date')->nullable();
                $t->string('status', 10)->default('OPEN');
                $t->string('notes', 1000)->nullable();
                $t->string('created_by', 50)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->dateTime('done_at')->nullable();
                $t->date('reminded_on')->nullable();
                $t->index(['username', 'status'], 'idx_task_user');
                $t->index(['assembly_no', 'status', 'due_date'], 'idx_task_due');
            });
        }
        if (!Schema::hasTable('attendance')) {
            Schema::create('attendance', function (Blueprint $t) {
                $t->id();
                $t->string('username', 50);
                $t->unsignedBigInteger('worker_id')->nullable();
                $t->integer('assembly_no')->nullable();
                $t->date('day');
                $t->dateTime('check_in');
                $t->decimal('in_lat', 10, 7)->nullable();
                $t->decimal('in_lng', 10, 7)->nullable();
                $t->dateTime('check_out')->nullable();
                $t->decimal('out_lat', 10, 7)->nullable();
                $t->decimal('out_lng', 10, 7)->nullable();
                $t->string('note', 300)->nullable();
                $t->unique(['username', 'day'], 'uk_attendance');
                $t->index(['assembly_no', 'day'], 'idx_att_day');
            });
        }
        if (!Schema::hasTable('voter_merges')) {
            Schema::create('voter_merges', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('keep_id');
                $t->unsignedBigInteger('drop_id');
                $t->integer('assembly_no');
                $t->text('before')->comment('JSON: fields of the kept voter before the merge, the removed voter\'s deleted flag, moved rows');
                $t->string('by_user', 50)->nullable();
                $t->dateTime('at');
                $t->dateTime('undone_at')->nullable();
                $t->index('keep_id', 'idx_merge_keep');
                $t->index('drop_id', 'idx_merge_drop');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_merges');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('worker_tasks');
        Schema::dropIfExists('house_locations');
    }
};
