<?php
declare(strict_types=1);

/**
 * Seed twenty sample teacher login accounts and their independent Finance
 * staff/payroll profiles into a disposable development or test database.
 *
 * Usage:
 *   DB_NAME=markaz_result_system_finance_test php scripts/seed-demo-teachers.php --confirm-demo
 *
 * This deliberately refuses the live school database. It does not create
 * student payments, receipts, payroll periods, or salary payment records.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

if (!in_array('--confirm-demo', $argv, true)) {
    fwrite(STDERR, "Refusing to seed without --confirm-demo.\n");
    exit(2);
}

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/finance.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

if (
    $databaseName === ''
    || !preg_match('/(?:_demo|_test|_dev)$/i', $databaseName)
    || $databaseName === 'markaz_result_system'
) {
    fwrite(STDERR, "Refusing to seed database '{$databaseName}'. Use a separate *_demo, *_test or *_dev database.\n");
    exit(3);
}

$requiredColumns = [
    'admins' => ['name', 'email', 'username', 'password_hash', 'role', 'gender', 'is_active'],
    'finance_staff' => ['staff_number', 'full_name', 'job_title', 'employment_date', 'is_active', 'created_by'],
    'staff_salary_profiles' => [
        'finance_staff_id', 'base_salary', 'currency', 'expected_arrival',
        'attendance_mode', 'lateness_rate_per_minute', 'deduct_lateness',
        'effective_from', 'is_active', 'created_by',
    ],
];

$columnCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        $columnCheck->execute([$table, $column]);
        if ((int) $columnCheck->fetchColumn() !== 1) {
            fwrite(
                STDERR,
                "Missing {$table}.{$column}. Apply the teacher-gender and Finance migrations before seeding.\n"
            );
            exit(4);
        }
    }
}

$actorQuery = $pdo->query("SELECT id FROM admins WHERE role = 'admin' ORDER BY id LIMIT 1");
$actorId = $actorQuery->fetchColumn();
if (!$actorId) {
    fwrite(STDERR, "Create or retain one administrator account in this test database before seeding.\n");
    exit(5);
}

$teachers = [
    ['Amina Sule', 'Amina.Sule01', 'female', 112000],
    ['Fatima Bello', 'Fatima.Bello02', 'female', 118000],
    ['Zainab Musa', 'Zainab.Musa03', 'female', 109000],
    ['Maryam Ibrahim', 'Maryam.Ibrahim04', 'female', 125000],
    ['Hafsat Abdullahi', 'Hafsat.Abdullahi05', 'female', 115000],
    ['Khadija Yusuf', 'Khadija.Yusuf06', 'female', 121000],
    ['Safiya Usman', 'Safiya.Usman07', 'female', 107000],
    ['Aisha Garba', 'Aisha.Garba08', 'female', 130000],
    ['Rahma Sani', 'Rahma.Sani09', 'female', 116000],
    ['Bilkisu Ahmed', 'Bilkisu.Ahmed10', 'female', 123000],
    ['Musa Abdullahi', 'Musa.Abdullahi11', 'male', 128000],
    ['Ibrahim Bello', 'Ibrahim.Bello12', 'male', 135000],
    ['Abdulrahman Sani', 'Abdulrahman.Sani13', 'male', 142000],
    ['Yusuf Garba', 'Yusuf.Garba14', 'male', 119000],
    ['Ahmad Usman', 'Ahmad.Usman15', 'male', 132000],
    ['Ismaila Mohammed', 'Ismaila.Mohammed16', 'male', 145000],
    ['Bashir Aliyu', 'Bashir.Aliyu17', 'male', 126000],
    ['Sulaiman Lawal', 'Sulaiman.Lawal18', 'male', 138000],
    ['Hamza Umar', 'Hamza.Umar19', 'male', 117000],
    ['Kabir Shehu', 'Kabir.Shehu20', 'male', 149000],
];

if (count($teachers) !== 20) {
    throw new RuntimeException('The sample teacher list must contain exactly twenty people.');
}

$usernames = array_column($teachers, 1);
$staffNumbers = [];
foreach (array_keys($teachers) as $index) {
    $staffNumbers[] = sprintf('MZ-TCH-%03d', $index + 1);
}

$existingAccounts = $pdo->prepare(
    'SELECT COUNT(*) FROM admins WHERE username IN (' . implode(',', array_fill(0, count($usernames), '?')) . ')'
);
$existingAccounts->execute($usernames);
$accountCount = (int) $existingAccounts->fetchColumn();

$existingStaff = $pdo->prepare(
    'SELECT COUNT(*) FROM finance_staff WHERE staff_number IN (' . implode(',', array_fill(0, count($staffNumbers), '?')) . ')'
);
$existingStaff->execute($staffNumbers);
$staffCount = (int) $existingStaff->fetchColumn();

if ($accountCount === 20 && $staffCount === 20) {
    echo "The twenty teacher accounts and Finance staff profiles are already present in {$databaseName}. No changes made.\n";
    exit(0);
}
if ($accountCount !== 0 || $staffCount !== 0) {
    fwrite(STDERR, "Partial teacher seed detected ({$accountCount} accounts, {$staffCount} Finance staff records). No changes made to avoid duplicates.\n");
    exit(6);
}

$backupDirectory = __DIR__ . '/../database-backups';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('Could not create the private database-backups directory.');
}
$credentialPath = $backupDirectory . '/teacher-preview-credentials-' . date('Ymd-His') . '.csv';
$tempCredentialPath = tempnam($backupDirectory, '.teacher-credentials-');
if ($tempCredentialPath === false) {
    throw new RuntimeException('Could not create a private credentials file.');
}
chmod($tempCredentialPath, 0600);
$credentialFile = fopen($tempCredentialPath, 'wb');
if ($credentialFile === false) {
    @unlink($tempCredentialPath);
    throw new RuntimeException('Could not open the private credentials file.');
}
fputcsv($credentialFile, ['Teacher name', 'Username', 'Temporary password', 'Gender']);

$insertAccount = $pdo->prepare(
    'INSERT INTO admins (name, email, username, role, gender, password_hash, is_active)
     VALUES (?, ?, ?, \'teacher\', ?, ?, 1)'
);
$insertStaff = $pdo->prepare(
    'INSERT INTO finance_staff (staff_number, full_name, job_title, employment_date, is_active, created_by)
     VALUES (?, ?, \'Teacher\', ?, 1, ?)'
);
$insertSalary = $pdo->prepare(
    'INSERT INTO staff_salary_profiles
        (finance_staff_id, base_salary, currency, expected_arrival, attendance_mode,
         lateness_rate_per_minute, deduct_lateness, effective_from, is_active, created_by)
     VALUES (?, ?, \'NGN\', \'08:00:00\', \'time_based\', 13.00, 1, CURDATE(), 1, ?)'
);

try {
    $pdo->beginTransaction();

    foreach ($teachers as $index => [$name, $username, $gender, $salary]) {
        $password = 'Mkz!' . bin2hex(random_bytes(6)) . '7aA';
        $email = sprintf('teacher%02d@example.test', $index + 1);
        $staffNumber = $staffNumbers[$index];
        $employmentDate = date('Y-m-d', strtotime('-' . (1 + ($index % 4)) . ' years'));

        $insertAccount->execute([
            $name,
            $email,
            $username,
            $gender,
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        $insertStaff->execute([$staffNumber, $name, $employmentDate, $actorId]);
        $financeStaffId = (int) $pdo->lastInsertId();

        $insertSalary->execute([$financeStaffId, $salary, $actorId]);

        fputcsv($credentialFile, [$name, $username, $password, ucfirst($gender)]);
    }

    $pdo->commit();
    fclose($credentialFile);
    $credentialFile = null;

    if (!rename($tempCredentialPath, $credentialPath)) {
        $credentialPath = $tempCredentialPath;
    }
    chmod($credentialPath, 0600);

    echo "Created 20 teacher login accounts, 20 independent Finance staff records, and 20 salary profiles in {$databaseName}.\n";
    echo "Credentials saved privately to: {$credentialPath}\n";
    echo "No student payments, receipts, payroll periods, or salary payouts were created.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_resource($credentialFile)) {
        fclose($credentialFile);
    }
    @unlink($tempCredentialPath);
    throw $error;
}
