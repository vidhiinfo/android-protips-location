<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Worker roles (Masters > Worker roles): rename, hide, reorder the built-in roles and add own roles that work like one of them. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('worker_roles')) {
            Schema::create('worker_roles', function (Blueprint $t) {
                $t->id();
                $t->string('code', 30)->unique();
                $t->string('base', 30);
                $t->string('name', 60)->nullable();
                $t->string('name_hi', 60)->nullable();
                $t->string('name_pa', 60)->nullable();
                $t->integer('sort')->default(0);
                $t->boolean('active')->default(true);
                $t->boolean('builtin')->default(false);
                $t->dateTime('created_at')->nullable();
            });
        }
        \App\Support\WorkerRoles::seed();
    }

    public function down(): void { Schema::dropIfExists('worker_roles'); }
};
