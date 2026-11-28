<?php

declare(strict_types=1);

/**
 * Creates 20 sample teacher login accounts in a dedicated demo database.
 *
 * Safety: this script refuses to run unless the selected database name contains
 * "demo". It never inserts sample teachers into the normal school database.
 *
 * Run from the project root:
 *   php database/seeds/create_demo_teachers.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once __DIR__ . '/../../app/config/app.php';

$pdo = db();
$databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

if (!str_contains(strtolower($databaseName), 'demo')) {
    fwrite(STDERR, "Refusing to seed database '{$databaseName}'. Use a separate database whose name contains 'demo'.\n");
    exit(1);
}

$genderColumn = $pdo->query("SHOW COLUMNS FROM admins LIKE 'gender'")->fetch(PDO::FETCH_ASSOC);
if (!$genderColumn) {
    fwrite(STDERR, "Teacher gender migration is missing. Apply 20261128_teacher_account_gender.sql first.\n");
    exit(1);
}

$teachers = [
    ['Abdulrahman Yusuf', 'male'],
    ['Musa Ibrahim', 'male'],
    ['Hassan Bello', 'male'],
    ['Yusuf Abdullahi', 'male'],
    ['Ibrahim Sani', 'male'],
    ['Abubakar Lawal', 'male'],
    ['Usman Mohammed', 'male'],
    ['Sulaiman Garba', 'male'],
    ['Aminu Isah', 'male'],
    ['Kabir Adamu', 'male'],
    ['Aisha Suleiman', 'female'],
    ['Maryam Abdullahi', 'female'],
    ['Hafsat Ibrahim', 'female'],
    ['Zainab Musa', 'female'],
    ['Fatima Bello', 'female'],
    ['Khadija Umar', 'female'],
    ['Rahmat Sani', 'female'],
    ['Safiya Lawal', 'female'],
    ['Amina Yusuf', 'female'],
    ['Hauwa Garba', 'female'],
];

$insert = $pdo->prepare(
    "INSERT INTO admins
        (name, email, username, role, gender, password_hash, is_active, student_id)
     VALUES (?, NULL, ?, 'teacher', ?, ?, 1, NULL)"
);
$exists = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');

$created = [];
$skipped = 0;
$pdo->beginTransaction();

try {
    foreach ($teachers as $index => [$name, $gender]) {
        $username = sprintf('TchrM%02d', $index + 1);
        $exists->execute([$username]);

        if ($exists->fetchColumn()) {
            $skipped++;
            continue;
        }

        $temporaryPassword = bin2hex(random_bytes(6)) . 'A9';
        $insert->execute([
            $name,
            $username,
            $gender,
            password_hash($temporaryPassword, PASSWORD_DEFAULT),
        ]);

        $created[] = [
            'name' => $name,
            'username' => $username,
            'gender' => ucfirst($gender),
            'temporary_password' => $temporaryPassword,
        ];
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Could not create sample teacher accounts: {$exception->getMessage()}\n");
    exit(1);
}

echo "Created " . count($created) . " teacher account(s); skipped {$skipped} existing account(s).\n";
echo "Save these temporary credentials now. Passwords are not stored in plain text and cannot be displayed again.\n\n";

if ($created) {
    printf("%-24s %-12s %-8s %s\n", 'TEACHER', 'USERNAME', 'GENDER', 'TEMPORARY PASSWORD');
    foreach ($created as $teacher) {
        printf(
            "%-24s %-12s %-8s %s\n",
            $teacher['name'],
            $teacher['username'],
            $teacher['gender'],
            $teacher['temporary_password']
        );
    }
}
