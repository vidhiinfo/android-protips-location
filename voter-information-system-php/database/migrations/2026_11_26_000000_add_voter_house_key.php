<?php

use App\Support\Text;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * house_key: the house no. without spaces, hyphens and dots, lower case (RZ-154 = R Z-154), computed by the database
 * (virtual column, nothing stored twice) and indexed, so a household is found without reading the whole assembly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('voters', 'house_key')) {
            Schema::table('voters', fn (Blueprint $t) => $t->string('house_key', 50)->virtualAs(Text::HOUSE_KEY_SQL)->nullable());
        }
        if (!Schema::hasIndex('voters', 'idx_voter_house')) {
            Schema::table('voters', fn (Blueprint $t) => $t->index(['assembly_no', 'house_key'], 'idx_voter_house'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('voters', 'idx_voter_house')) Schema::table('voters', fn (Blueprint $t) => $t->dropIndex('idx_voter_house'));
        if (Schema::hasColumn('voters', 'house_key')) Schema::table('voters', fn (Blueprint $t) => $t->dropColumn('house_key'));
    }
};
