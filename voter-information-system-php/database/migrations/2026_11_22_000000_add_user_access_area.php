<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Access area of a login inside its assembly: wards, booths and a serial range (empty = the whole assembly). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('app_users', 'scope_booths')) return;
        Schema::table('app_users', function (Blueprint $t) {
            $t->string('scope_wards', 200)->nullable();
            $t->string('scope_booths', 500)->nullable();
            $t->integer('scope_serial_from')->nullable();
            $t->integer('scope_serial_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_users', fn (Blueprint $t) => $t->dropColumn(['scope_wards', 'scope_booths', 'scope_serial_from', 'scope_serial_to']));
    }
};
