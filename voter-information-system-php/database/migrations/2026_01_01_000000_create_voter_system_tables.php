<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same tables and columns as the Java version (db/schema.sql), so both versions can share one MySQL database.
 * On shared hosting without SSH you can import db/schema.sql in phpMyAdmin instead of running migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $t) {
            $t->id();
            $t->string('epic_no', 20)->unique('uk_voter_epic');
            $t->string('name', 120)->index('idx_voter_name');
            $t->string('name_local', 120)->nullable()->index('idx_voter_name_local');
            $t->string('relative_name', 120)->nullable();
            $t->string('relative_name_local', 120)->nullable();
            $t->string('relation_type', 10)->nullable();
            $t->string('gender', 10);
            $t->integer('age')->nullable();
            $t->integer('assembly_no');
            $t->string('assembly_name', 100)->nullable();
            $t->integer('booth_no');
            $t->string('booth_name', 200)->nullable();
            $t->integer('serial_no')->nullable();
            $t->string('house_no', 30)->nullable();
            $t->string('address', 300)->nullable();
            $t->string('address_local', 300)->nullable();
            $t->string('village_ward', 100)->nullable()->index('idx_voter_village');
            $t->string('district', 100)->nullable()->index('idx_voter_district');
            $t->string('pincode', 6)->nullable();
            $t->string('mobile', 10)->nullable()->index('idx_voter_mobile');
            $t->string('alt_mobile', 10)->nullable();
            $t->string('email', 120)->nullable();
            $t->string('category', 50)->nullable();
            $t->string('remarks', 500)->nullable();
            $t->dateTime('created_at', 6);
            $t->dateTime('updated_at', 6);
            $t->index(['assembly_no', 'booth_no'], 'idx_voter_assembly_booth');
        });

        Schema::create('assemblies', function (Blueprint $t) {
            $t->id();
            $t->integer('assembly_no')->unique('uk_assembly_no');
            $t->string('name', 100);
            $t->string('name_local', 100)->nullable();
            $t->string('district', 100)->nullable();
            $t->string('state', 100)->nullable();
            $t->string('reserved_for', 10)->nullable();
        });

        Schema::create('booths', function (Blueprint $t) {
            $t->id();
            $t->integer('assembly_no');
            $t->integer('booth_no');
            $t->string('name', 200);
            $t->string('name_local', 200)->nullable();
            $t->string('location', 300)->nullable();
            $t->string('village_ward', 100)->nullable();
            $t->unique(['assembly_no', 'booth_no'], 'uk_booth');
        });

        Schema::create('parties', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('name_local', 150)->nullable();
            $t->string('short_name', 20)->unique('uk_party_short');
            $t->string('symbol', 100)->nullable();
            $t->string('color', 7)->nullable();
        });

        Schema::create('elections', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('type', 20);
            $t->integer('election_year');
            $t->date('poll_date')->nullable();
            $t->boolean('current')->default(false);
        });

        Schema::create('candidates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('election_id');
            $t->integer('assembly_no');
            $t->string('name', 120);
            $t->string('name_local', 120)->nullable();
            $t->unsignedBigInteger('party_id')->nullable();
            $t->integer('ballot_no')->nullable();
            $t->index(['election_id', 'assembly_no'], 'idx_candidate_contest');
            $t->foreign('election_id', 'fk_candidate_election')->references('id')->on('elections');
            $t->foreign('party_id', 'fk_candidate_party')->references('id')->on('parties');
        });

        Schema::create('booth_results', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('election_id');
            $t->integer('assembly_no');
            $t->integer('booth_no');
            $t->unsignedBigInteger('candidate_id');
            $t->integer('votes');
            $t->string('entered_by', 50)->nullable();
            $t->dateTime('updated_at', 6)->nullable();
            $t->unique(['candidate_id', 'booth_no'], 'uk_booth_result');
            $t->index(['election_id', 'assembly_no', 'booth_no'], 'idx_result_booth');
            $t->foreign('candidate_id', 'fk_result_candidate')->references('id')->on('candidates');
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique('uk_role_code');
            $t->string('name', 100);
            $t->string('description', 300)->nullable();
            $t->boolean('system_role')->default(false);
        });

        Schema::create('role_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('permission', 30);
            $t->unique(['role_id', 'permission'], 'uk_role_permission');
            $t->foreign('role_id', 'fk_permission_role')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::create('app_users', function (Blueprint $t) {
            $t->id();
            $t->string('username', 50)->unique('uk_user_username');
            $t->string('password_hash', 100);
            $t->string('full_name', 100);
            $t->boolean('enabled')->default(true);
            $t->integer('assembly_no')->nullable();
            $t->dateTime('last_login', 6)->nullable();
            $t->dateTime('created_at', 6)->nullable();
        });

        Schema::create('user_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['user_id', 'role_id']);
            $t->foreign('user_id', 'fk_user_roles_user')->references('id')->on('app_users')->cascadeOnDelete();
            $t->foreign('role_id', 'fk_user_roles_role')->references('id')->on('roles');
        });

        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->dateTime('at', 6)->index('idx_audit_time');
            $t->string('username', 50)->nullable()->index('idx_audit_user');
            $t->string('action', 30);
            $t->string('entity', 30)->nullable();
            $t->string('entity_id', 50)->nullable();
            $t->string('details', 500)->nullable();
        });

        Schema::create('message_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('channel', 10);
            $t->string('language', 10);
            $t->string('body', 1000);
            $t->string('provider_template_id', 100)->nullable();
            $t->string('params', 300)->nullable();
            $t->boolean('active')->default(true);
            $t->dateTime('created_at', 6)->nullable();
        });

        Schema::create('message_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('channel', 10);
            $t->unsignedBigInteger('template_id');
            $t->string('filter_query', 1000)->nullable();
            $t->string('filter_summary', 500)->nullable();
            $t->string('status', 10);
            $t->dateTime('scheduled_at', 6)->nullable();
            $t->integer('total')->default(0);
            $t->integer('sent')->default(0);
            $t->integer('failed')->default(0);
            $t->integer('skipped')->default(0);
            $t->string('created_by', 50)->nullable();
            $t->dateTime('created_at', 6)->nullable();
            $t->dateTime('completed_at', 6)->nullable();
            $t->foreign('template_id', 'fk_campaign_template')->references('id')->on('message_templates');
        });

        Schema::create('message_outbox', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('campaign_id')->index('idx_outbox_campaign');
            $t->unsignedBigInteger('voter_id')->nullable();
            $t->string('voter_name', 120)->nullable();
            $t->string('mobile', 16);
            $t->string('body', 1000);
            $t->string('param_values', 1000)->nullable();
            $t->string('status', 10);
            $t->integer('attempts')->default(0);
            $t->string('provider_id', 100)->nullable();
            $t->string('error', 300)->nullable();
            $t->dateTime('created_at', 6)->nullable();
            $t->dateTime('sent_at', 6)->nullable();
            $t->index(['status', 'campaign_id'], 'idx_outbox_status');
        });

        Schema::create('message_optouts', function (Blueprint $t) {
            $t->id();
            $t->string('mobile', 10);
            $t->string('channel', 10)->nullable();
            $t->string('reason', 200)->nullable();
            $t->string('created_by', 50)->nullable();
            $t->dateTime('created_at', 6)->nullable();
            $t->unique(['mobile', 'channel'], 'uk_optout');
        });
    }

    public function down(): void
    {
        foreach (['message_optouts', 'message_outbox', 'message_campaigns', 'message_templates', 'audit_log', 'user_roles',
                     'app_users', 'role_permissions', 'roles', 'booth_results', 'candidates', 'elections', 'parties', 'booths',
                     'assemblies', 'voters'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
