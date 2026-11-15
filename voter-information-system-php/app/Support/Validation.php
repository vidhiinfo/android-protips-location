<?php

namespace App\Support;

/** Translated validation messages (same keys as the Java version). */
class Validation
{
    public static function messages(): array
    {
        return [
            'required' => t('validation.required'),
            'max' => t('validation.size', ['max' => ':max']),
            'between' => t('validation.age'),
            'min' => t('validation.positive'),
            'integer' => t('validation.positive'),
            'in' => t('validation.required'),
            'email' => t('validation.email'),
            'pincode.regex' => t('validation.pincode'),
            'mobile.regex' => t('validation.mobile'),
            'alt_mobile.regex' => t('validation.mobile'),
            'color.regex' => t('validation.color'),
            'username.regex' => t('validation.username'),
            'code.regex' => t('validation.roleCode'),
            'election_year.between' => t('validation.year'),
            'unique' => t('validation.duplicate'),
        ];
    }

    /** Request data with native digits converted and empty strings as null. */
    public static function clean(array $data, array $digitFields = ['mobile', 'alt_mobile', 'pincode']): array
    {
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $v = trim($v);
                if (in_array($k, $digitFields, true)) $v = preg_replace('/[\s-]/', '', (string) Text::asciiDigits($v));
                elseif (preg_match('/^\p{Nd}+$/u', $v)) $v = (string) Text::asciiDigits($v);   // ४२ -> 42 for number fields
                $data[$k] = $v === '' ? null : $v;
            }
        }
        return $data;
    }
}
