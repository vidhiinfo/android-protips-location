<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** In-app notifications (bell in the top bar): one row per login. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) return;
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->string('username', 50);
            $t->string('kind', 20);
            $t->string('title', 200);
            $t->string('body', 1000)->nullable();
            $t->string('url', 300)->nullable();
            $t->string('created_by', 50)->nullable();
            $t->dateTime('created_at')->nullable();
            $t->dateTime('read_at')->nullable();
            $t->index(['username', 'read_at'], 'idx_notif_user');
            $t->index('created_at', 'idx_notif_time');
        });
    }

    public function down(): void { Schema::dropIfExists('notifications'); }
};
