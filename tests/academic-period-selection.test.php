<?php
declare(strict_types=1);

function academicPeriodCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$screenPage = file_get_contents(__DIR__ . '/../public/student-result.html');
$migrationPath = __DIR__ . '/../database/migrations/20261106_current_academic_period.sql';
$academicApi = file_get_contents(__DIR__ . '/../public/api/settings/academic-period.php');
$legacyAcademicApi = file_get_contents(__DIR__ . '/../api/settings/academic-period.php');
$studentApi = file_get_contents(__DIR__ . '/../api/results/student.php');
$screenCss = file_get_contents(__DIR__ . '/../public/css/student-result.css');
$printCss = file_get_contents(__DIR__ . '/../public/css/student-result-print.css');
$signatureApi = file_get_contents(__DIR__ . '/../public/api/settings/result-footer.php');

academicPeriodCheck(
    str_contains($screenPage, 'student-result.css?v=20261109-mobile-profile-behaviour-layout'),
    'the Student Management result page must load the updated signature baseline stylesheet'
);

academicPeriodCheck(
    str_contains($schema, 'is_current TINYINT(1) NOT NULL DEFAULT 0'),
    'the academic-period schema must store the explicitly selected current term'
);
academicPeriodCheck(
    is_file($migrationPath),
    'existing installations must receive a migration for the current-term marker'
);

$migration = is_file($migrationPath) ? file_get_contents($migrationPath) : '';
academicPeriodCheck(
    str_contains($migration, 'ADD COLUMN is_current') &&
    str_contains($migration, 'ORDER BY ap2.updated_at DESC, ap2.id DESC') &&
    str_contains($migration, 'SET ap.is_current = 1'),
    'the migration must add the marker and initialize the active period for existing data'
);

academicPeriodCheck(
    str_contains($academicApi, 'AND ap.is_current = 1') &&
    str_contains($academicApi, 'SET is_current = 0') &&
    str_contains($academicApi, 'is_current = 1'),
    'academic settings must load and persist the explicitly selected current term'
);
academicPeriodCheck(
    str_contains($legacyAcademicApi, "public/api/settings/academic-period.php") &&
    str_contains($academicApi, 'AND ap.is_current = 1') &&
    str_contains($academicApi, 'is_current = 1'),
    'the legacy academic-period endpoint must use the same current-term record'
);
academicPeriodCheck(
    str_contains($studentApi, 'AND ap.is_current = 1'),
    'student management and printed results must use the same explicitly selected term'
);

preg_match('/\.signature-space\s*\{([^}]*)\}/s', $screenCss, $screenSignatureRules);
academicPeriodCheck(
    isset($screenSignatureRules[1]) &&
    preg_match('/display\s*:\s*flex\s*;/', $screenSignatureRules[1]) &&
    preg_match('/align-items\s*:\s*flex-end\s*;/', $screenSignatureRules[1]),
    'the on-screen signature and date must align to the bottom of their designated lines'
);
academicPeriodCheck(
    str_contains($printCss, 'align-items: flex-end'),
    'the existing print signature baseline alignment must remain intact'
);

academicPeriodCheck(
    str_contains($signatureApi, 'file_put_contents($destination, $binary)') &&
    str_contains($signatureApi, '$bytesWritten !== strlen($binary)') &&
    !str_contains($signatureApi, 'file_put_contents($destination, $binary, LOCK_EX)'),
    'signature storage must work on Android shared storage without unsupported exclusive file locks'
);

echo "Academic-period selection and result-footer alignment regression tests passed.\n";
