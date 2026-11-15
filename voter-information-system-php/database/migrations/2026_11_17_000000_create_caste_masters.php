<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Caste and Sub Caste masters (drop-downs for the voter's caste / sub caste) and voters.sub_caste. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('castes')) {
            Schema::create('castes', function (Blueprint $t) {
                $t->id();
                $t->string('name', 100)->unique('uk_caste_name');
                $t->string('name_local', 100)->nullable();
                $t->string('category', 10)->nullable();
                $t->boolean('active')->default(true);
            });
        }
        if (!Schema::hasTable('sub_castes')) {
            Schema::create('sub_castes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('caste_id')->constrained('castes', 'id', 'fk_sub_caste_caste');
                $t->string('name', 100);
                $t->string('name_local', 100)->nullable();
                $t->boolean('active')->default(true);
                $t->unique(['caste_id', 'name'], 'uk_sub_caste');
            });
        }
        if (!Schema::hasColumn('voters', 'sub_caste')) {
            Schema::table('voters', function (Blueprint $t) {
                $t->string('sub_caste', 100)->nullable()->after('caste');
                $t->index('sub_caste', 'idx_voter_sub_caste');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_castes');
        Schema::dropIfExists('castes');
        if (Schema::hasColumn('voters', 'sub_caste')) Schema::table('voters', fn (Blueprint $t) => $t->dropColumn('sub_caste'));
    }
};
