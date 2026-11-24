<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('code', 5)->nullable();
            $t->boolean('is_ut')->default(false);
        });

        Schema::create('districts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('state_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unique(['state_id', 'name']);
        });

        // Configurable posts per level, with the sanctioned number of seats (for vacancy tracking).
        Schema::create('designations', function (Blueprint $t) {
            $t->id();
            $t->string('level', 20); // national | state | district | mandal
            $t->string('title');
            $t->unsignedSmallInteger('seniority')->default(100);
            $t->unsignedSmallInteger('max_count')->default(1);
            $t->unique(['level', 'title']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('username')->nullable()->unique()->after('name');
            $t->string('role', 20)->default('national_staff')->after('password');
            $t->foreignId('state_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $t->foreignId('district_id')->nullable()->after('state_id')->constrained()->nullOnDelete();
            $t->string('mobile', 15)->nullable()->after('district_id');
            $t->boolean('active')->default(true)->after('mobile');
            $t->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('state_id');
            $t->dropConstrainedForeignId('district_id');
            $t->dropColumn(['username', 'role', 'mobile', 'active', 'last_login_at']);
        });
        Schema::dropIfExists('designations');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('states');
    }
};
