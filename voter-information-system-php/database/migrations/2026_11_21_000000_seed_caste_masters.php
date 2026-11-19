<?php

use Illuminate\Database\Migrations\Migration;

/** Empty Caste / Sub Caste masters get the Delhi list (84 castes, 191 sub castes; edit in Masters). */
return new class extends Migration
{
    public function up(): void { \App\Models\Caste::seedDefaults(); }
    public function down(): void {}
};
