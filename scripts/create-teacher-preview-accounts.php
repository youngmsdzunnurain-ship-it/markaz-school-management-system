<?php
declare(strict_types=1);

/**
 * Create 20 fictional teacher login accounts for table/layout testing.
 *
 * Run from the project root:
 *   php scripts/create-teacher-preview-accounts.php --confirm
 *
 * Temporary passwords are generated at runtime and printed once. They are
 * never stored in source control or in a plaintext file.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

if (!in_array('--confirm', $argv, true)) {
    fwrite(STDERR, "This creates 20 teacher login accounts in the current database.\n");
    fwrite(STDERR, "Review the target database, then rerun with --confirm.\n");
    exit(1);
}

require_once __DIR__ . '/../app/config/app.php';

if (!function_exists('db')) {
    fwrite(STDERR, "Database connection helper is unavailable.\n");
    exit(1);
}

$pdo = db();
$genderColumn = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'admins'
       AND COLUMN_NAME = 'gender'"
)->fetchColumn();

if ((int) $genderColumn !== 1) {
    fwrite(STDERR, "Apply database/migrations/20261128_teacher_account_gender.sql first.\n");
    exit(1);
}

$teachers = [
    ['Amina Yusuf', 'female'],
    ['Abdulrahman Bello', 'male'],
    ['Zainab Ibrahim', 'female'],
    ['Musa Abdullahi', 'male'],
    ['Maryam Sani', 'female'],
    ['Ibrahim Musa', 'male'],
    ['Hafsat Lawal', 'female'],
    ['Usman Ahmed', 'male'],
    ['Khadijah Sule', 'female'],
    ['Yahaya Garba', 'male'],
    ['Fatima Ismail', 'female'],
    ['Suleiman Adamu', 'male'],
    ['Aisha Mohammed', 'female'],
    ['Haruna Yakubu', 'male'],
    ['Safiya Umar', 'female'],
    ['Bashir Aliyu', 'male'],
    ['Rukayya Hassan', 'female'],
    ['Nura Danladi', 'male'],
    ['Mariam Shehu', 'female'],
    ['Abubakar Salisu', 'male'],
];

$findExisting = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
$createAccount = $pdo->prepare(
    "INSERT INTO admins
        (name, email, username, role, password_hash, is_active, student_id, gender)
     VALUES (?, NULL, ?, 'teacher', ?, 1, NULL, ?)"
);
$savePermission = $pdo->prepare(
    'INSERT INTO account_permissions (account_id, permission_key, is_allowed)
     VALUES (?, ?, 0)
     ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)'
);

$created = [];
$skipped = 0;

try {
    $pdo->beginTransaction();

    foreach ($teachers as $index => [$name, $gender]) {
        $base = preg_replace('/[^A-Za-z]/', '', str_replace(' ', '', $name));
        $username = $base . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

        $findExisting->execute([$username]);
        if ($findExisting->fetchColumn()) {
            $skipped++;
            continue;
        }

        $temporaryPassword = 'Mz!' . bin2hex(random_bytes(8));
        $createAccount->execute([
            $name,
            $username,
            password_hash($temporaryPassword, PASSWORD_DEFAULT),
            $gender,
        ]);
        $accountId = (int) $pdo->lastInsertId();

        foreach (['attendance_records', 'print_results', 'online_class_access'] as $permission) {
            $savePermission->execute([$accountId, $permission]);
        }

        $created[] = [
            'name' => $name,
            'username' => $username,
            'gender' => ucfirst($gender),
            'password' => $temporaryPassword,
        ];
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Teacher account creation failed: " . $error->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "Created " . count($created) . " teacher accounts; skipped " . $skipped . " existing accounts.\n");
fwrite(STDOUT, "No classes or subjects were assigned. Store these temporary credentials securely.\n\n");
fwrite(STDOUT, "NAME | USERNAME | GENDER | TEMPORARY PASSWORD\n");
foreach ($created as $teacher) {
    fwrite(STDOUT, implode(' | ', $teacher) . "\n");
}
