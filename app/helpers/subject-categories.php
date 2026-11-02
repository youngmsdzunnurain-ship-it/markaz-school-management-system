<?php
declare(strict_types=1);

/** Return a stable broad academic grouping without changing school-level eligibility. */
function suggestSubjectGroup(string $name): string
{
    $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim($name)));

    $groups = [
        'Core' => [
            'quran', 'english', 'englishlanguage', 'mathematics', 'math', 'maths',
        ],
        'Science' => [
            'biology', 'chemistry', 'physics', 'basicscience', 'computer',
            'computerstudies', 'ict', 'agric', 'agriculture', 'agriculturalscience',
            'civiceducation', 'civic', 'economics', 'foodandnutrition',
            'foodnutrition', 'animalhusbandry', 'socialstudies', 'geography',
        ],
        'Islamic Studies' => [
            'tawheed', 'fiqh', 'hadith', 'hadeeth', 'islamicstudies',
            'islamicreligiousstudies', 'sirah', 'seerah',
        ],
        'Languages' => [
            'arabic', 'french', 'hausa', 'yoruba', 'igbo',
        ],
        'Humanities' => [
            'history', 'government', 'literature', 'literatureinenglish',
            'socialscience',
        ],
        'Vocational' => [
            'businessstudies', 'commerce', 'accounting', 'bookkeeping',
            'homeeconomics', 'technicaldrawing', 'creativearts', 'finearts',
        ],
    ];

    foreach ($groups as $group => $names) {
        if (in_array($key, $names, true)) {
            return $group;
        }
    }

    return 'Other';
}

/** Build a readable acronym and suffix it until it is unique in the supplied code list. */
function generateUniqueSubjectCode(string $name, array $existingCodes = []): string
{
    $words = preg_split('/[^A-Za-z0-9]+/', strtoupper(trim($name)), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        throw new InvalidArgumentException('A valid subject name is required to generate its code.');
    }

    if (count($words) > 1) {
        $base = '';
        foreach ($words as $word) {
            $base .= $word[0];
        }
    } else {
        $base = substr($words[0], 0, 4);
    }

    $base = substr($base, 0, 12);
    $used = array_fill_keys(array_map(
        static fn($code): string => strtoupper(trim((string) $code)),
        $existingCodes
    ), true);

    $candidate = $base;
    $suffix = 2;
    while (isset($used[$candidate])) {
        $candidate = $base . $suffix++;
        if ($suffix > 100000) {
            throw new RuntimeException('Could not generate a unique subject code.');
        }
    }

    return $candidate;
}
