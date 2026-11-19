<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index for the voter list's default order (assembly, booth, serial no.): a full assembly opens without sorting lakhs of rows. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasIndex('voters', 'idx_voter_list')) {
            Schema::table('voters', fn (Blueprint $t) => $t->index(['assembly_no', 'booth_no', 'serial_no'], 'idx_voter_list'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('voters', 'idx_voter_list')) Schema::table('voters', fn (Blueprint $t) => $t->dropIndex('idx_voter_list'));
    }
};
