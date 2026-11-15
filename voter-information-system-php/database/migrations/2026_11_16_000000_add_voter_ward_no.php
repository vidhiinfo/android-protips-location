<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Municipal ward no. of the voter (booths are grouped by ward on every screen). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('voters', 'ward_no')) return;   // added by db/schema.sql or an import file
        Schema::table('voters', function (Blueprint $t) {
            $t->integer('ward_no')->nullable()->after('village_ward');
            $t->index('ward_no', 'idx_voter_ward_no');
        });
    }

    public function down(): void
    {
        Schema::table('voters', fn (Blueprint $t) => $t->dropColumn('ward_no'));
    }
};
