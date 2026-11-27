<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisation chain: mandals (named groups of booths in an assembly) and the mandal of a mandal in-charge.
 * Lok Sabha in-charge -> assembly in-charge -> mandal in-charge -> booth president -> panna pramukh is worked out
 * from role, assembly, mandal and booth, so nobody has to keep a "reports to" column up to date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mandals')) {
            Schema::create('mandals', function (Blueprint $t) {
                $t->id();
                $t->integer('assembly_no');
                $t->string('name', 100);
                $t->string('booths', 1000)->nullable();   // "1, 2, 3, 10"
                $t->boolean('active')->default(true);
                $t->dateTime('created_at')->nullable();
                $t->index('assembly_no', 'idx_mandal_assembly');
            });
        }
        if (Schema::hasTable('workers') && !Schema::hasColumn('workers', 'mandal_id')) {
            Schema::table('workers', function (Blueprint $t) {
                $t->unsignedBigInteger('mandal_id')->nullable();
                $t->index('mandal_id', 'idx_worker_mandal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workers', 'mandal_id')) Schema::table('workers', fn (Blueprint $t) => $t->dropColumn('mandal_id'));
        Schema::dropIfExists('mandals');
    }
};
