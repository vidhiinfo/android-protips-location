<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bulk updates with the values they replaced, so a bulk update can be undone (Voters > Bulk changes). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bulk_changes')) {
            Schema::create('bulk_changes', function (Blueprint $t) {
                $t->id();
                $t->string('created_by', 50)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->integer('assembly_no')->nullable();
                $t->text('fields')->comment('JSON: field => new value');
                $t->integer('voter_count')->default(0);
                $t->string('status', 10)->default('APPLIED')->comment('APPLIED, UNDONE');
                $t->string('undone_by', 50)->nullable();
                $t->dateTime('undone_at')->nullable();
                $t->integer('skipped')->default(0)->comment('values changed again later, kept at undo');
                $t->index(['assembly_no', 'created_at'], 'idx_bulk_ac');
            });
        }
        if (!Schema::hasTable('bulk_change_rows')) {
            Schema::create('bulk_change_rows', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('bulk_change_id');
                $t->unsignedBigInteger('voter_id');
                $t->text('before')->comment('JSON: field => value before the bulk update');
                $t->text('after')->comment('JSON: field => value written');
                $t->index('bulk_change_id', 'idx_bulkrow_change');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_change_rows');
        Schema::dropIfExists('bulk_changes');
    }
};
