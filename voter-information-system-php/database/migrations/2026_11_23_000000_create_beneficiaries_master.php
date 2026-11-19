<?php

use App\Support\StatusMasters;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Beneficiary / help master: help given to voters (admission, ration card, Ayushman card ...) and government schemes. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('beneficiaries')) {
            Schema::create('beneficiaries', function (Blueprint $t) {
                $t->id();
                $t->string('name', 150)->unique('uk_beneficiaries_name');
                $t->string('name_local', 150)->nullable();
                $t->string('category', 20)->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('active')->default(true);
            });
        }
        StatusMasters::seed('beneficiaries');
    }

    public function down(): void { Schema::dropIfExists('beneficiaries'); }
};
