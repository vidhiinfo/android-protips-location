<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consent and privacy (DPDP Act 2023): each voter's consent to be contacted with its history,
 * the register of privacy requests (access, correction, erasure, withdrawal), and when a voter's data was erased.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $t) {
            if (!Schema::hasColumn('voters', 'consent_contact')) $t->char('consent_contact', 1)->nullable()->comment('Y agreed to be contacted, N refused / withdrew, NULL not asked');
            if (!Schema::hasColumn('voters', 'consent_at')) $t->dateTime('consent_at')->nullable();
            if (!Schema::hasColumn('voters', 'consent_by')) $t->string('consent_by', 50)->nullable();
            if (!Schema::hasColumn('voters', 'consent_source')) $t->string('consent_source', 20)->nullable()->comment('survey, phone, form, request, other');
            if (!Schema::hasColumn('voters', 'erased_at')) $t->dateTime('erased_at')->nullable()->comment('party-collected data erased on request / retention');
        });
        if (!Schema::hasTable('consent_log')) {
            Schema::create('consent_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('voter_id');
                $t->char('value', 1)->nullable();
                $t->string('source', 20)->nullable();
                $t->string('note', 300)->nullable();
                $t->string('by_user', 50)->nullable();
                $t->dateTime('at');
                $t->index('voter_id', 'idx_consent_voter');
            });
        }
        if (!Schema::hasTable('privacy_requests')) {
            Schema::create('privacy_requests', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('voter_id')->nullable();
                $t->integer('assembly_no')->nullable();
                $t->string('name', 150);
                $t->string('epic_no', 30)->nullable();
                $t->string('type', 15)->comment('ACCESS, CORRECTION, ERASURE, WITHDRAW, OTHER');
                $t->string('channel', 30)->nullable()->comment('phone, letter, email, in person, worker');
                $t->string('details', 1000)->nullable();
                $t->string('status', 10)->default('OPEN')->comment('OPEN, DONE, REJECTED');
                $t->string('outcome', 1000)->nullable();
                $t->string('created_by', 50)->nullable();
                $t->dateTime('requested_at');
                $t->dateTime('due_at')->nullable();
                $t->string('done_by', 50)->nullable();
                $t->dateTime('done_at')->nullable();
                $t->index(['status', 'due_at'], 'idx_privacy_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('consent_log');
        Schema::table('voters', fn (Blueprint $t) => $t->dropColumn(['consent_contact', 'consent_at', 'consent_by', 'consent_source', 'erased_at']));
    }
};
