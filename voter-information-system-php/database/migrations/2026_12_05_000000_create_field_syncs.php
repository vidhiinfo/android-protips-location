<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Offline field app: which login downloaded which booth, and when each phone last synced (war room shows silent booths). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('field_syncs')) {
            Schema::create('field_syncs', function (Blueprint $t) {
                $t->id();
                $t->string('username', 50);
                $t->integer('assembly_no');
                $t->integer('booth_no');
                $t->dateTime('downloaded_at')->nullable();
                $t->dateTime('synced_at')->nullable();
                $t->integer('last_items')->default(0)->comment('items received in the last sync');
                $t->unique(['username', 'assembly_no', 'booth_no'], 'uk_field_sync');
                $t->index(['assembly_no', 'booth_no'], 'idx_field_sync_booth');
            });
        }
    }

    public function down(): void { Schema::dropIfExists('field_syncs'); }
};
