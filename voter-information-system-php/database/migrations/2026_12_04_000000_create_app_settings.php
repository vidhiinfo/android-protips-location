<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings changed by the administrator on screen (Admin > Settings), e.g. SMS / WhatsApp on or off. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $t) {
                $t->string('name', 64)->primary();
                $t->string('value', 1000)->nullable();
                $t->string('updated_by', 50)->nullable();
                $t->dateTime('updated_at')->nullable();
            });
        }
    }

    public function down(): void { Schema::dropIfExists('app_settings'); }
};
