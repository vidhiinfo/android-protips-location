<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A worker picked from the voter list: link to their voter record (name, booth and mobile come from it). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workers') && !Schema::hasColumn('workers', 'voter_id')) {
            Schema::table('workers', function (Blueprint $t) {
                $t->unsignedBigInteger('voter_id')->nullable();
                $t->index('voter_id', 'idx_worker_voter');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workers', 'voter_id')) Schema::table('workers', fn (Blueprint $t) => $t->dropColumn('voter_id'));
    }
};
