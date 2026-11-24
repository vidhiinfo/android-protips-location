<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key/value application settings (branding, templates, module toggles, role permissions...).
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value')->nullable();
            $t->timestamps();
        });

        // Admin-editable dropdown lists, grouped (e.g. group "petition_category").
        Schema::create('lookups', function (Blueprint $t) {
            $t->id();
            $t->string('group', 40)->index();
            $t->string('value', 60);
            $t->string('label');
            $t->string('color', 20)->nullable();
            $t->unsignedSmallInteger('sort')->default(100);
            $t->boolean('active')->default(true);
            $t->boolean('locked')->default(false); // system values the code relies on; label editable, not deletable
            $t->unique(['group', 'value']);
        });

        // Extra fields an admin can add to a record type without code changes.
        Schema::create('custom_fields', function (Blueprint $t) {
            $t->id();
            $t->string('entity', 30)->default('member');
            $t->string('key', 40);
            $t->string('label');
            $t->string('type', 20)->default('text'); // text | textarea | number | date | select | checkbox
            $t->text('options')->nullable(); // one option per line for "select"
            $t->boolean('required')->default(false);
            $t->boolean('show_in_list')->default(false);
            $t->unsignedSmallInteger('sort')->default(100);
            $t->boolean('active')->default(true);
            $t->unique(['entity', 'key']);
        });

        Schema::table('members', function (Blueprint $t) {
            $t->json('extra')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn('extra'));
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('lookups');
        Schema::dropIfExists('settings');
    }
};
