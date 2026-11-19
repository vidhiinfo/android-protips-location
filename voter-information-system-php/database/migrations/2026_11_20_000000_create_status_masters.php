<?php

use App\Support\StatusMasters;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Social / Political / Organisational Status masters (drop-down lists), filled with starting values. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['social_statuses' => 'group_name', 'political_statuses' => 'leaning', 'org_statuses' => 'level'] as $table => $group) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) use ($group, $table) {
                    $t->id();
                    $t->string('name', 100)->unique('uk_' . $table . '_name');
                    $t->string('name_local', 100)->nullable();
                    $t->string($group, 20)->nullable();
                    $t->integer('sort_order')->default(0);
                    $t->boolean('active')->default(true);
                });
            }
            StatusMasters::seed($table);
        }
    }

    public function down(): void
    {
        foreach (['social_statuses', 'political_statuses', 'org_statuses'] as $t) Schema::dropIfExists($t);
    }
};
