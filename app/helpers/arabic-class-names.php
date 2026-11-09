<?php
declare(strict_types=1);

/**
 * Return the agreed display spelling for Arabic class names.
 * Existing class IDs and student assignments remain unchanged.
 */
function markazCanonicalArabicClassName(?string $name): string
{
    $value = trim((string) $name);
    $key = strtolower(preg_replace('/\\s+/u', ' ', $value) ?? $value);

    $names = [
        'roudoh ula' => 'Roudoh Ula',
        'roudoh sani' => 'Roudoh Thani',
        'roudoh thani' => 'Roudoh Thani',
        'faslu ula' => 'Faslu Awal',
        'faslu awal' => 'Faslu Awal',
        'faslu sani' => 'Faslu Thani',
        'faslu thani' => 'Faslu Thani',
        'faslu salis' => 'Faslu Thalith',
        'faslu thalith' => 'Faslu Thalith',
        'faslu rabia' => 'Faslu Rabia',
        'faslu khamis' => 'Faslu Khamis',
        'faslu sadis' => 'Faslu Sadis',
    ];

    return $names[$key] ?? $value;
}
