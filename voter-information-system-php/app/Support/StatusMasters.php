<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Social / Political / Organisational Status masters: the drop-down lists of the voter form, field survey,
 * bulk update, survey sheet and voter filters. Values are stored on the voter as the English name.
 * Political statuses also carry a leaning (S = support, N = neutral, O = opposition, U = not surveyed) used by Analytics.
 */
class StatusMasters
{
    /** voter column => [table, group column] */
    public const TABLES = ['social_status' => ['social_statuses', 'group_name'], 'political_status' => ['political_statuses', 'leaning'],
        'organisational_status' => ['org_statuses', 'level'], 'beneficiary' => ['beneficiaries', 'category']];
    /** Voter column => master key (Beneficiary 1 / 2 / 3 share one master). */
    public const COLUMNS = ['social_status' => 'social_status', 'political_status' => 'political_status', 'organisational_status' => 'organisational_status',
        'beneficiary1' => 'beneficiary', 'beneficiary2' => 'beneficiary', 'beneficiary3' => 'beneficiary'];
    public const BEN_CATEGORIES = ['HELP', 'SCHEME'];
    public const SOCIAL_GROUPS = ['INFLUENCE', 'PROFESSION', 'WORK', 'OTHER'];
    public const LEANINGS = ['S', 'N', 'O', 'U'];
    public const ORG_LEVELS = ['BOOTH', 'WARD', 'DISTRICT', 'FRONT', 'ELECTED'];

    /** Starting values: [name, Hindi name, group / leaning / level]. */
    public const DEFAULTS = [
        'social_statuses' => [
            ['Opinion Leader', 'प्रभावशाली व्यक्ति', 'INFLUENCE'], ['Religious Leader', 'धार्मिक नेता', 'INFLUENCE'], ['Community / Biradari Head', 'समाज प्रमुख', 'INFLUENCE'],
            ['RWA Office Bearer', 'आरडब्ल्यूए पदाधिकारी', 'INFLUENCE'], ['Market Association Member', 'व्यापार मंडल सदस्य', 'INFLUENCE'],
            ['Doctor', 'डॉक्टर', 'PROFESSION'], ['Teacher', 'शिक्षक', 'PROFESSION'], ['Lawyer', 'वकील', 'PROFESSION'], ['Engineer', 'इंजीनियर', 'PROFESSION'],
            ['Govt. Employee', 'सरकारी कर्मचारी', 'PROFESSION'], ['Retired Govt. Employee', 'सेवानिवृत्त कर्मचारी', 'PROFESSION'], ['Ex-Serviceman', 'पूर्व सैनिक', 'PROFESSION'],
            ['Police', 'पुलिस', 'PROFESSION'],
            ['Businessman', 'व्यापारी', 'WORK'], ['Shopkeeper', 'दुकानदार', 'WORK'], ['Private Job', 'प्राइवेट नौकरी', 'WORK'], ['Self-employed', 'स्वरोज़गार', 'WORK'],
            ['Farmer', 'किसान', 'WORK'], ['Labourer / Daily Wage', 'मज़दूर', 'WORK'], ['Driver', 'चालक', 'WORK'], ['Domestic Worker', 'घरेलू कामगार', 'WORK'],
            ['Street Vendor', 'रेहड़ी-पटरी', 'WORK'],
            ['Student', 'विद्यार्थी', 'OTHER'], ['Homemaker', 'गृहिणी', 'OTHER'], ['Unemployed', 'बेरोज़गार', 'OTHER'], ['Senior Citizen', 'वरिष्ठ नागरिक', 'OTHER'],
            ['Social Worker', 'सामाजिक कार्यकर्ता', 'OTHER'], ['Self-Help Group Member', 'स्वयं सहायता समूह', 'OTHER'],
        ],
        'political_statuses' => [
            ['Strong Supporter', 'पक्का समर्थक', 'S'], ['Supporter', 'समर्थक', 'S'], ['Party Member', 'पार्टी सदस्य', 'S'], ['Karyakarta', 'कार्यकर्ता', 'S'],
            ['Leaning Towards Us', 'हमारी ओर झुकाव', 'S'],
            ['Neutral / Undecided', 'तटस्थ / अनिश्चित', 'N'], ['Swing Voter', 'स्विंग मतदाता', 'N'], ['Not Interested in Politics', 'राजनीति में रुचि नहीं', 'N'],
            ['Leaning Towards Opposition', 'विपक्ष की ओर झुकाव', 'O'], ['Opposition Supporter', 'विपक्ष समर्थक', 'O'], ['Opposition Worker', 'विपक्ष कार्यकर्ता', 'O'],
            ['Not Met / Not Available', 'नहीं मिले', 'U'],
        ],
        'beneficiaries' => [
            ['Admission (school / college)', 'स्कूल / कॉलेज में दाखिला', 'HELP'], ['Ration Card', 'राशन कार्ड', 'HELP'], ['Ayushman Card', 'आयुष्मान कार्ड', 'HELP'],
            ['Aadhaar Card', 'आधार कार्ड', 'HELP'], ['Voter ID Card', 'वोटर कार्ड', 'HELP'], ['Old Age Pension', 'वृद्धावस्था पेंशन', 'HELP'],
            ['Widow Pension', 'विधवा पेंशन', 'HELP'], ['Disability Pension', 'दिव्यांग पेंशन', 'HELP'], ['Hospital Treatment', 'अस्पताल में इलाज', 'HELP'],
            ['Job / Employment', 'नौकरी / रोज़गार', 'HELP'], ['Birth / Death Certificate', 'जन्म / मृत्यु प्रमाण पत्र', 'HELP'],
            ['Caste / Income Certificate', 'जाति / आय प्रमाण पत्र', 'HELP'], ['Scholarship', 'छात्रवृत्ति', 'HELP'], ['Bank Account', 'बैंक खाता', 'HELP'],
            ['Electricity / Water Connection', 'बिजली / पानी कनेक्शन', 'HELP'], ['Police / Legal Help', 'पुलिस / कानूनी मदद', 'HELP'],
            ['PM Awas Yojana', 'प्रधानमंत्री आवास योजना', 'SCHEME'], ['Ujjwala Gas', 'उज्ज्वला गैस', 'SCHEME'], ['Free Ration', 'मुफ़्त राशन', 'SCHEME'],
            ['PM Kisan', 'पीएम किसान', 'SCHEME'], ['Jan Dhan Account', 'जन धन खाता', 'SCHEME'], ['Mudra Loan', 'मुद्रा लोन', 'SCHEME'],
            ['Ladli / Girl Child Scheme', 'लाडली योजना', 'SCHEME'], ['Free Bus Travel (Women)', 'महिलाओं की मुफ़्त बस यात्रा', 'SCHEME'],
            ['Free Electricity', 'मुफ़्त बिजली', 'SCHEME'], ['Free Water', 'मुफ़्त पानी', 'SCHEME'],
        ],
        'org_statuses' => [
            ['Panna Pramukh', 'पन्ना प्रमुख', 'BOOTH'], ['Booth Committee Member', 'बूथ समिति सदस्य', 'BOOTH'], ['Booth Agent', 'बूथ एजेंट', 'BOOTH'],
            ['Booth President', 'बूथ अध्यक्ष', 'BOOTH'],
            ['Shakti Kendra Pramukh', 'शक्ति केंद्र प्रमुख', 'WARD'], ['Ward Committee Member', 'वार्ड समिति सदस्य', 'WARD'], ['Mandal Office Bearer', 'मंडल पदाधिकारी', 'WARD'],
            ['Mandal President', 'मंडल अध्यक्ष', 'WARD'],
            ['District Office Bearer', 'जिला पदाधिकारी', 'DISTRICT'], ['District President', 'जिला अध्यक्ष', 'DISTRICT'], ['State Office Bearer', 'प्रदेश पदाधिकारी', 'DISTRICT'],
            ['Mahila Morcha', 'महिला मोर्चा', 'FRONT'], ['Yuva Morcha', 'युवा मोर्चा', 'FRONT'], ['Kisan Morcha', 'किसान मोर्चा', 'FRONT'], ['SC Morcha', 'अनुसूचित जाति मोर्चा', 'FRONT'],
            ['OBC Morcha', 'पिछड़ा वर्ग मोर्चा', 'FRONT'], ['Minority Morcha', 'अल्पसंख्यक मोर्चा', 'FRONT'], ['IT / Social Media Cell', 'आईटी सेल', 'FRONT'], ['Legal Cell', 'विधि प्रकोष्ठ', 'FRONT'],
            ['Councillor', 'पार्षद', 'ELECTED'], ['Former Councillor', 'पूर्व पार्षद', 'ELECTED'], ['MLA / Former MLA', 'विधायक / पूर्व विधायक', 'ELECTED'],
        ],
    ];

    private static array $cache = [];

    /** Active master rows for a voter column, in list order. */
    public static function rows(string $column): array
    {
        $column = self::COLUMNS[$column] ?? $column;
        if (!isset(self::TABLES[$column])) return [];
        if (isset(self::$cache[$column])) return self::$cache[$column];
        [$table, $group] = self::TABLES[$column];
        if (!Schema::hasTable($table)) return self::$cache[$column] = [];
        return self::$cache[$column] = DB::table($table)->where('active', true)->orderBy('sort_order')->orderBy('name')
            ->get(['name', 'name_local', $group . ' as grp'])->map(fn ($r) => (array) $r)->all();
    }

    /** Drop-down options [stored value => label]: "Supporter (समर्थक)", plus values already on voters that are not in the master. */
    public static function options(string $column, array $extra = []): array
    {
        $o = [];
        foreach (self::rows($column) as $r) $o[$r['name']] = $r['name'] . ($r['name_local'] && $r['name_local'] !== $r['name'] ? ' (' . $r['name_local'] . ')' : '');
        foreach ($extra as $v) if ($v !== null && $v !== '' && !isset($o[$v])) $o[$v] = $v;
        return $o;
    }

    /** Typed or imported value -> master name (accepts the Hindi name or any letter case); unknown values are kept as typed. */
    public static function normalize(string $column, ?string $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') return null;
        foreach (self::rows($column) as $r) {
            if (mb_strtolower($r['name']) === mb_strtolower($v) || ($r['name_local'] && $r['name_local'] === $v)) return $r['name'];
        }
        return $v;
    }

    /** Is this voter column a drop-down from one of these masters? */
    public static function has(string $column): bool { return isset(self::COLUMNS[$column]) && self::rows($column); }

    /** First political status of each leaning, for the one-tap buttons of the field survey: ['S' => 'Supporter', ...]. */
    public static function quickLeanings(): array
    {
        $q = [];
        foreach (self::rows('political_status') as $r) if ($r['grp'] && !isset($q[$r['grp']])) $q[$r['grp']] = $r['name'];
        // prefer the plain "Supporter" over "Strong Supporter" when both exist
        foreach (self::rows('political_status') as $r) if ($r['grp'] === 'S' && mb_strtolower($r['name']) === 'supporter') $q['S'] = $r['name'];
        return array_intersect_key($q, array_flip(['S', 'N', 'O', 'U']));
    }

    /** Political status name => leaning (S / N / O / U). */
    public static function leanings(): array
    {
        if (isset(self::$cache['_lean'])) return self::$cache['_lean'];
        $m = [];
        foreach (self::rows('political_status') as $r) $m[mb_strtolower($r['name'])] = $r['grp'];
        return self::$cache['_lean'] = $m;
    }

    public static function flush(): void { self::$cache = []; }

    /** Fills an empty master table with the starting values. */
    public static function seed(string $table): void
    {
        if (!Schema::hasTable($table) || DB::table($table)->exists()) return;
        $group = ['social_statuses' => 'group_name', 'political_statuses' => 'leaning', 'org_statuses' => 'level', 'beneficiaries' => 'category'][$table];
        foreach (self::DEFAULTS[$table] as $i => [$name, $local, $g]) {
            DB::table($table)->insert(['name' => $name, 'name_local' => $local, $group => $g, 'sort_order' => ($i + 1) * 10, 'active' => true]);
        }
    }
}
