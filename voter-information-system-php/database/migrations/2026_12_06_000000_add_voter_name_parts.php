<?php

use App\Support\NameParts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First / middle / last name of the voter and of the father / husband / mother, computed by the database from
 * name and relative_name (virtual columns: always follow the name, nothing stored twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        foreach (NameParts::COLUMNS as $col => $src) {
            if (Schema::hasColumn('voters', $col)) continue;
            Schema::table('voters', fn (Blueprint $t) => $t->string($col, 120)->virtualAs(NameParts::sql($src, NameParts::part($col), $driver))->nullable());
        }
    }

    public function down(): void
    {
        foreach (array_keys(NameParts::COLUMNS) as $col) if (Schema::hasColumn('voters', $col)) Schema::table('voters', fn (Blueprint $t) => $t->dropColumn($col));
    }
};
