<?php
declare(strict_types=1);

function academicArchiveCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$periodApi = file_get_contents(__DIR__ . '/../public/api/settings/academic-period.php');
$archiveRoute = file_get_contents(__DIR__ . '/../public/api/settings/finalize-academic-period.php');
$finalizeApi = file_get_contents(__DIR__ . '/../api/settings/finalize-academic-period.php');
$resultSave = file_get_contents(__DIR__ . '/../api/results/save.php');
$behaviourApi = file_get_contents(__DIR__ . '/../api/results/behavioural.php');
$portalApi = file_get_contents(__DIR__ . '/../api/results/student-portal.php');
$migrationPath = __DIR__ . '/../database/migrations/20261107_academic_period_finalization.sql';
$migration = is_file($migrationPath) ? file_get_contents($migrationPath) : '';
$settingsPage = file_get_contents(__DIR__ . '/../public/settings.html');
$settingsJs = file_get_contents(__DIR__ . '/../public/js/settings.js');
$navigation = file_get_contents(__DIR__ . '/../public/js/settings-panel-navigation.js');

academicArchiveCheck(is_file($migrationPath), 'existing installations must receive the academic-period finalization migration');
academicArchiveCheck(
    str_contains($migration, 'finalized_at') &&
    str_contains($migration, 'finalized_by') &&
    str_contains($migration, 'ADD COLUMN is_finalized'),
    'migration must add explicit finalization metadata without deleting historical records'
);
academicArchiveCheck(
    str_contains($periodApi, 'INSERT INTO academic_periods') &&
    str_contains($periodApi, 'SET is_current = 0') &&
    str_contains($periodApi, 'is_finalized') &&
    !preg_match('/UPDATE academic_periods\\s+SET\\s+term_id\\s*=/i', $periodApi),
    'term switching must create or activate the correct session-term period and never relabel an existing period'
);
academicArchiveCheck(
    str_contains($periodApi, 'beginTransaction') &&
    str_contains($periodApi, 'FOR UPDATE'),
    'term progression must be transactional and serialize concurrent switches'
);
academicArchiveCheck(
    str_contains($archiveRoute, "require_once") &&
    str_contains($archiveRoute, "api/settings/finalize-academic-period.php"),
    'the public Settings URL must resolve to the protected finalization API instead of returning an HTML 404 page'
);
academicArchiveCheck(
    str_contains($finalizeApi, 'password_verify') &&
    str_contains($finalizeApi, 'password_hash') &&
    str_contains($finalizeApi, "['password']"),
    'finalizing a term must require and verify the active senior administrator password on the server'
);
academicArchiveCheck(
    str_contains($settingsPage, 'academicFinalizePasswordDialog') &&
    str_contains($settingsPage, 'type="password"') &&
    str_contains($settingsJs, 'academicFinalizePassword'),
    'the finalization action must require an explicit password entry in the Settings UI'
);
academicArchiveCheck(
    str_contains($finalizeApi, 'is_finalized = 1') &&
    str_contains($finalizeApi, 'finalized_at') &&
    str_contains($finalizeApi, 'finalized_by'),
    'finalization must record who finalized the current period and when'
);
academicArchiveCheck(
    str_contains($resultSave, 'is_finalized') &&
    str_contains($resultSave, 'Results for this academic period are finalized'),
    'score editing must be blocked after a period is finalized'
);
academicArchiveCheck(
    str_contains($behaviourApi, 'is_finalized') &&
    str_contains($behaviourApi, 'Behavioural assessments for this academic period are finalized'),
    'behavioural report editing must be blocked after a period is finalized'
);
academicArchiveCheck(
    preg_match('/beginTransaction\\(\\).*?FOR UPDATE/s', $resultSave) === 1 &&
    str_contains($resultSave, 'commit()') &&
    str_contains($resultSave, 'rollBack()'),
    'score writes must lock and recheck the current period in the same transaction as the write'
);
academicArchiveCheck(
    preg_match('/beginTransaction\\(\\).*?FOR UPDATE/s', $behaviourApi) === 1 &&
    str_contains($behaviourApi, 'commit()') &&
    str_contains($behaviourApi, 'rollBack()'),
    'behavioural writes must lock and recheck the current period in the same transaction as the write'
);
academicArchiveCheck(
    str_contains($portalApi, 'WHERE ap.academic_session_id = ?') &&
    str_contains($portalApi, 'AND ap.term_id = ?') &&
    str_contains($portalApi, 'results_released'),
    'student portal must continue retrieving a selected historical session and term only when released'
);
academicArchiveCheck(
    str_contains($settingsPage, 'academic-archive-settings-card') &&
    str_contains($settingsPage, 'finalizeAcademicPeriodButton'),
    'academic archive controls must be placed inside the existing settings page'
);
academicArchiveCheck(
    str_contains($settingsJs, 'finalize-academic-period.php') &&
    str_contains($settingsJs, 'finalizeAcademicPeriodButton'),
    'settings UI must call the protected finalization endpoint'
);
academicArchiveCheck(
    str_contains($navigation, 'academic-archive-settings-card') &&
    str_contains($navigation, 'Academic Records & Term Archive'),
    'archive controls must appear as a clean category in the existing settings navigation'
);

echo "Academic result archive and term progression regression tests passed.\\n";
