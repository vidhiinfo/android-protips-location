<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outreach tools: automatic birthday / anniversary greetings, delivery and read receipts from the providers,
 * the two-way WhatsApp / SMS inbox, and the phone-bank call log.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('auto_greetings')) {
            Schema::create('auto_greetings', function (Blueprint $t) {
                $t->id();
                $t->string('kind', 12)->comment('BIRTHDAY or ANNIVERSARY');
                $t->unsignedBigInteger('template_id');
                $t->integer('assembly_no')->nullable()->comment('NULL = every assembly');
                $t->string('send_at', 5)->default('09:00');
                $t->boolean('active')->default(true);
                $t->date('last_run_on')->nullable();
                $t->unsignedBigInteger('last_campaign_id')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->dateTime('created_at')->nullable();
            });
        }
        Schema::table('message_outbox', function (Blueprint $t) {
            if (!Schema::hasColumn('message_outbox', 'delivery')) $t->string('delivery', 12)->nullable()->comment('receipt from the provider: DELIVERED, READ, UNDELIVERED');
            if (!Schema::hasColumn('message_outbox', 'delivered_at')) $t->dateTime('delivered_at')->nullable();
            if (!Schema::hasColumn('message_outbox', 'read_at')) $t->dateTime('read_at')->nullable();
        });
        if (!Schema::hasIndex('message_outbox', 'idx_outbox_provider')) {
            Schema::table('message_outbox', fn (Blueprint $t) => $t->index('provider_id', 'idx_outbox_provider'));
        }
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $t) {
                $t->id();
                $t->string('channel', 10);
                $t->string('mobile', 16)->comment('+91XXXXXXXXXX');
                $t->unsignedBigInteger('voter_id')->nullable();
                $t->string('name', 120)->nullable();
                $t->integer('assembly_no')->nullable();
                $t->integer('unread')->default(0);
                $t->string('status', 10)->default('OPEN');
                $t->string('assigned_to', 50)->nullable();
                $t->dateTime('last_in_at')->nullable()->comment('last message from the voter (WhatsApp: free replies within 24 hours)');
                $t->dateTime('last_at')->nullable();
                $t->unique(['channel', 'mobile'], 'uk_conversation');
                $t->index(['status', 'last_at'], 'idx_conv_status');
            });
        }
        if (!Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('conversation_id');
                $t->char('direction', 3)->comment('IN or OUT');
                $t->string('body', 2000)->nullable();
                $t->string('media_type', 20)->nullable();
                $t->string('provider_id', 100)->nullable();
                $t->string('status', 12)->nullable()->comment('OUT: SENT, FAILED, DELIVERED, READ');
                $t->string('error', 300)->nullable();
                $t->string('by_user', 50)->nullable();
                $t->dateTime('at');
                $t->index(['conversation_id', 'id'], 'idx_convmsg');
                $t->index('provider_id', 'idx_convmsg_provider');
            });
        }
        if (!Schema::hasTable('call_logs')) {
            Schema::create('call_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('voter_id');
                $t->integer('assembly_no');
                $t->integer('booth_no')->nullable();
                $t->string('outcome', 16)->comment('ANSWERED, NO_ANSWER, BUSY, SWITCHED_OFF, WRONG_NUMBER, CALL_BACK, REFUSED');
                $t->char('leaning', 1)->nullable()->comment('S support, N neutral, O opposition (what the voter said)');
                $t->string('notes', 500)->nullable();
                $t->dateTime('callback_at')->nullable();
                $t->string('by_user', 50)->nullable();
                $t->dateTime('at');
                $t->index(['voter_id', 'at'], 'idx_call_voter');
                $t->index(['assembly_no', 'at'], 'idx_call_day');
                $t->index(['by_user', 'at'], 'idx_call_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::table('message_outbox', function (Blueprint $t) {
            foreach (['delivery', 'delivered_at', 'read_at'] as $c) if (Schema::hasColumn('message_outbox', $c)) $t->dropColumn($c);
        });
        Schema::dropIfExists('auto_greetings');
    }
};
