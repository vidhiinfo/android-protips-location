<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Two-step login: mobile number for codes, method (app / sms / whatsapp), encrypted authenticator secret, hashed recovery codes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_users', function (Blueprint $t) {
            if (!Schema::hasColumn('app_users', 'mobile')) $t->string('mobile', 15)->nullable();
            if (!Schema::hasColumn('app_users', 'otp_method')) $t->string('otp_method', 10)->nullable()->comment('app, sms, whatsapp; null = off');
            if (!Schema::hasColumn('app_users', 'otp_secret')) $t->text('otp_secret')->nullable()->comment('authenticator secret, encrypted with APP_KEY');
            if (!Schema::hasColumn('app_users', 'otp_recovery')) $t->text('otp_recovery')->nullable()->comment('JSON: bcrypt hashes of unused recovery codes');
            if (!Schema::hasColumn('app_users', 'otp_since')) $t->dateTime('otp_since')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_users', fn (Blueprint $t) => $t->dropColumn(['mobile', 'otp_method', 'otp_secret', 'otp_recovery', 'otp_since']));
    }
};
