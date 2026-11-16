<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** IP address and browser / device of every audited action (login, change, import ...). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('audit_log', 'ip_address')) return;   // added by db/schema.sql or the upgrade file
        Schema::table('audit_log', function (Blueprint $t) {
            $t->string('ip_address', 45)->nullable()->after('details');
            $t->string('user_agent', 255)->nullable()->after('ip_address');
            $t->index('ip_address', 'idx_audit_ip');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', fn (Blueprint $t) => $t->dropColumn(['ip_address', 'user_agent']));
    }
};
