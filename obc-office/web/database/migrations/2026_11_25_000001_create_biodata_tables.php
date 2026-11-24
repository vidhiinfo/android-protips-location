<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Register of every biodata the office receives (from states, districts, individuals...).
        Schema::create('biodata', function (Blueprint $t) {
            $t->id();
            $t->string('reference_no')->nullable()->unique();

            // Receipt
            $t->date('received_on');
            $t->string('received_from')->nullable();   // e.g. "UP State President", "Shri X, MLA"
            $t->string('received_via', 40)->nullable(); // lookup: biodata_source
            $t->string('category', 40)->nullable();     // lookup: biodata_category
            $t->string('status', 40)->nullable();       // lookup: biodata_status

            // Person
            $t->string('name');
            $t->string('father_name')->nullable();
            $t->string('gender', 10)->nullable();
            $t->date('dob')->nullable();
            $t->string('mobile', 15)->nullable()->index();
            $t->string('whatsapp', 15)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $t->string('mandal')->nullable();
            $t->string('education')->nullable();
            $t->string('profession')->nullable();
            $t->string('photo_path')->nullable();

            $t->text('background')->nullable(); // party / political / social background summary
            $t->text('remarks')->nullable();
            $t->json('extra')->nullable();

            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['state_id', 'district_id']);
            $t->index('received_on');
        });

        Schema::create('biodata_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('biodata_id')->constrained('biodata')->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 100)->nullable();
            $t->unsignedInteger('size')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biodata_documents');
        Schema::dropIfExists('biodata');
    }
};
