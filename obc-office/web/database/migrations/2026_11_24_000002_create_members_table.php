<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10)->default('bearer'); // bearer | worker
            $t->string('name');
            $t->string('father_name')->nullable();
            $t->string('gender', 10)->nullable();
            $t->date('dob')->nullable();
            $t->string('mobile', 15)->nullable()->index();
            $t->string('whatsapp', 15)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('profession')->nullable();
            $t->string('education')->nullable();
            $t->string('skills')->nullable();
            $t->string('level', 20)->default('district'); // national | state | district | mandal
            $t->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $t->string('mandal')->nullable();
            $t->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $t->date('appointed_on')->nullable();
            $t->date('tenure_end')->nullable();
            $t->string('status', 10)->default('active'); // active | proposed | removed
            $t->string('photo_path')->nullable();
            $t->string('facebook')->nullable();
            $t->string('twitter')->nullable();
            $t->string('instagram')->nullable();
            $t->boolean('consent')->default(false);
            $t->timestamp('consent_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['state_id', 'district_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
