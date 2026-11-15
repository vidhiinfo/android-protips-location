<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Profile and outreach fields on voters (membership no., birthdays, statuses, beneficiaries, caste ...). */
return new class extends Migration
{
    private const COLUMNS = [
        'membership_no' => ['string', 50], 'dob' => ['date'], 'anniversary' => ['date'], 'deleted' => ['boolean'],
        'shift1' => ['string', 100], 'shift2' => ['string', 100], 'place_of_origin' => ['string', 150], 'current_place' => ['string', 150],
        'linked_with' => ['string', 150], 'social_status' => ['string', 100], 'political_status' => ['string', 100],
        'organisational_status' => ['string', 100], 'beneficiary1' => ['string', 150], 'beneficiary2' => ['string', 150],
        'beneficiary3' => ['string', 150], 'caste' => ['string', 100],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $name => $def) {
            if (Schema::hasColumn('voters', $name)) continue;   // already added from db/schema.sql or db/upgrade-*.sql
            Schema::table('voters', function (Blueprint $t) use ($name, $def) {
                match ($def[0]) {
                    'string' => $t->string($name, $def[1])->nullable(),
                    'date' => $t->date($name)->nullable(),
                    'boolean' => $t->boolean($name)->default(false),
                };
                if (in_array($name, ['caste', 'membership_no'], true)) $t->index($name, 'idx_voter_' . $name);
            });
        }
    }

    public function down(): void
    {
        Schema::table('voters', fn (Blueprint $t) => $t->dropColumn(array_keys(self::COLUMNS)));
    }
};
