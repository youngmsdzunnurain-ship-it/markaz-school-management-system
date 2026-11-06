<?php
declare(strict_types=1);

function publicSeoCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pages = [
    'home.html' => [
        'title' => 'MARKAZ Gwagwalada, Abuja | Qur’an &amp; Academic Learning',
        'description' => 'Markazu Ta’lim Wa Tahfizil Qur’anil Kareem in Gwagwalada, Abuja, combines Qur’an memorisation, Islamic values and conventional academic learning.',
    ],
    'about.html' => [
        'title' => 'About MARKAZ | Qur’an &amp; Academic Learning in Gwagwalada',
        'description' => 'Learn about Markazu Ta’lim Wa Tahfizil Qur’anil Kareem, a Qur’an memorisation and academic learning centre in Gwagwalada, Abuja.',
    ],
    'academics.html' => [
        'title' => 'Academics at MARKAZ | Qur’an &amp; Conventional Education',
        'description' => 'Explore Qur’an memorisation, Islamic learning and conventional academic pathways at MARKAZ in Gwagwalada, Abuja.',
    ],
    'admissions.html' => [
        'title' => 'Admissions at MARKAZ | Gwagwalada, Abuja',
        'description' => 'Find admission guidance for MARKAZ in Gwagwalada, Abuja, where Qur’an memorisation, Islamic values and academic learning develop together.',
    ],
    'facilities.html' => [
        'title' => 'School Facilities | MARKAZ Gwagwalada',
        'description' => 'Explore MARKAZ’s learning spaces and facilities supporting Qur’an memorisation, Islamic education and academic learning in Gwagwalada, Abuja.',
    ],
    'gallery.html' => [
        'title' => 'School Gallery | MARKAZ Gwagwalada, Abuja',
        'description' => 'See school life at MARKAZ in Gwagwalada, Abuja, through photographs of Qur’an learning, academic activities and the school environment.',
    ],
    'faq.html' => [
        'title' => 'FAQs | MARKAZ Gwagwalada, Abuja',
        'description' => 'Find answers about MARKAZ, its Qur’an memorisation and academic programmes, school life and admission enquiries in Gwagwalada, Abuja.',
    ],
    'contact.html' => [
        'title' => 'Contact MARKAZ | Gwagwalada, Abuja',
        'description' => 'Contact MARKAZ in Gwagwalada, Abuja, for information about Qur’an memorisation, academic programmes and school admissions.',
    ],
];

$titles = [];
$descriptions = [];
foreach ($pages as $page => $expected) {
    $html = file_get_contents(__DIR__ . '/../public/' . $page);
    publicSeoCheck(
        preg_match('/<title>(.*?)<\\/title>/is', $html, $titleMatch) === 1,
        "{$page} must have a page title"
    );
    publicSeoCheck(
        preg_match('/<meta\\s+name="description"\\s+content="([^"]+)"/i', $html, $descriptionMatch) === 1,
        "{$page} must have a meta description"
    );
    publicSeoCheck(
        $titleMatch[1] === $expected['title'],
        "{$page} must use its approved unique SEO title"
    );
    publicSeoCheck(
        $descriptionMatch[1] === $expected['description'],
        "{$page} must use its approved page-specific description"
    );
    publicSeoCheck(
        str_contains($html, '<meta property="og:title" content="' . $expected['title'] . '">') &&
        str_contains($html, '<meta property="og:description" content="' . $expected['description'] . '">') &&
        str_contains($html, '<meta name="twitter:card" content="summary">'),
        "{$page} must expose matching social preview text"
    );
    $titles[] = $titleMatch[1];
    $descriptions[] = $descriptionMatch[1];
}

publicSeoCheck(count(array_unique($titles)) === count($pages), 'every public page must have a unique title');
publicSeoCheck(count(array_unique($descriptions)) === count($pages), 'every public page must have a unique description');

$home = file_get_contents(__DIR__ . '/../public/home.html');
publicSeoCheck(
    preg_match('/<script type="application\\/ld\\+json">([\\s\\S]*?)<\\/script>/i', $home, $schemaMatch) === 1,
    'homepage must include school structured data'
);
$schema = json_decode($schemaMatch[1], true);
publicSeoCheck(
    json_last_error() === JSON_ERROR_NONE &&
    ($schema['@type'] ?? null) === 'EducationalOrganization' &&
    ($schema['name'] ?? null) === 'Markazu Ta’lim Wa Tahfizil Qur’anil Kareem' &&
    ($schema['email'] ?? null) === 'markazilquran@gmail.com' &&
    ($schema['address']['addressLocality'] ?? null) === 'Gwagwalada' &&
    ($schema['address']['addressCountry'] ?? null) === 'NG' &&
    count($schema['telephone'] ?? []) === 3,
    'school structured data must contain the verified MARKAZ identity and contact details'
);

echo "Public SEO regression tests passed.\n";
