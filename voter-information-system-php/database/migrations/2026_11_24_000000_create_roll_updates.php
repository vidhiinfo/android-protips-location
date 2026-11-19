<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roll update: a new (final / revised) voter roll compared with the voters already in the system.
 * roll_updates = one uploaded roll; roll_update_rows = what happens to each voter (same, changed, shifted, new, restored, deleted),
 * with the values before the update so it can be undone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roll_updates')) {
            Schema::create('roll_updates', function (Blueprint $t) {
                $t->id();
                $t->integer('assembly_no');
                $t->string('file_name', 255);
                $t->text('booths')->nullable()->comment('booth numbers in the uploaded roll, comma separated');
                $t->string('status', 12)->default('PREVIEW')->comment('PREVIEW, APPLIED, UNDONE, DISCARDED');
                $t->text('counts')->nullable()->comment('JSON: rows per action');
                $t->text('errors')->nullable()->comment('JSON: rows that could not be read');
                $t->string('created_by', 50)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->string('applied_by', 50)->nullable();
                $t->dateTime('applied_at')->nullable();
                $t->dateTime('undone_at')->nullable();
                $t->index(['assembly_no', 'status'], 'idx_rollupd_ac');
            });
        }
        if (!Schema::hasTable('roll_update_rows')) {
            Schema::create('roll_update_rows', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('roll_update_id');
                $t->integer('row_no')->nullable();
                $t->string('action', 12)->comment('SAME, CHANGED, SHIFTED, NEW, RESTORED, NAMEMATCH, DELETED');
                $t->unsignedBigInteger('voter_id')->nullable();
                $t->string('epic_no', 30)->nullable();
                $t->string('name', 150)->nullable();
                $t->integer('booth_no')->nullable()->comment('booth in the new roll (old booth for DELETED)');
                $t->integer('serial_no')->nullable();
                $t->integer('old_booth_no')->nullable();
                $t->integer('old_serial_no')->nullable();
                $t->boolean('has_survey')->default(false)->comment('voter already has data entered by the party');
                $t->text('data')->nullable()->comment('JSON: roll values from the new roll');
                $t->text('changes')->nullable()->comment('JSON: field => [old, new]');
                $t->text('before')->nullable()->comment('JSON: values before applying, for undo');
                $t->index(['roll_update_id', 'action'], 'idx_rollrow_action');
                $t->index(['roll_update_id', 'booth_no'], 'idx_rollrow_booth');
                $t->index(['roll_update_id', 'voter_id'], 'idx_rollrow_voter');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roll_update_rows');
        Schema::dropIfExists('roll_updates');
    }
};
