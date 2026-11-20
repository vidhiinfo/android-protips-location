<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** State summary: the latest totals fetched from each division installation (STATE_SOURCES). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('state_snapshots')) {
            Schema::create('state_snapshots', function (Blueprint $t) {
                $t->id();
                $t->string('source', 100)->unique('uk_state_source');
                $t->string('url', 300)->nullable();
                $t->boolean('ok')->default(false);
                $t->string('error', 300)->nullable();
                $t->longText('payload')->nullable()->comment('JSON: totals per assembly from the division');
                $t->dateTime('fetched_at')->nullable();
                $t->dateTime('last_ok_at')->nullable();
            });
        }
    }

    public function down(): void { Schema::dropIfExists('state_snapshots'); }
};
