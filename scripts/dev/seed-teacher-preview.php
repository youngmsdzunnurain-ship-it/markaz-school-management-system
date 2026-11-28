<?php
declare(strict_types=1);

/**
 * Create 20 realistic teacher login records for local UI preview.
 *
 * This script is intentionally not part of a production migration.
 * Run only against the local markaz_result_system database.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

if (strtolower((string) getenv('APP_ENV')) === 'production') {
    fwrite(STDERR, "Refusing to seed teacher preview accounts in production.\n");
    exit(1);
}

if (getenv('MARKAZ_ALLOW_TEACHER_PREVIEW_SEED') !== '1') {
    fwrite(STDERR, "Set MARKAZ_ALLOW_TEACHER_PREVIEW_SEED=1 to confirm this local-only action.\n");
    exit(1);
}

require_once dirname(__DIR__, 2) . '/app/config/app.php';

$pdo = db();
$databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

if ($databaseName !== 'markaz_result_system') {
    fwrite(STDERR, "Refusing to seed: expected local database markaz_result_system, found {$databaseName}.\n");
    exit(1);
}

$genderColumn = $pdo->query("SHOW COLUMNS FROM admins LIKE 'gender'")->fetch(PDO::FETCH_ASSOC);
if (!$genderColumn) {
    fwrite(STDERR, "The admins.gender migration must be applied before running this script.\n");
    exit(1);
}

$teachers = [
    ['name' => 'Aminat Yusuf', 'username' => 'AminatYusuf01', 'gender' => 'female'],
    ['name' => 'Abdulrahman Sani', 'username' => 'AbdulrahmanSani02', 'gender' => 'male'],
    ['name' => 'Hauwa Bello', 'username' => 'HauwaBello03', 'gender' => 'female'],
    ['name' => 'Musa Ibrahim', 'username' => 'MusaIbrahim04', 'gender' => 'male'],
    ['name' => 'Zainab Abdullahi', 'username' => 'ZainabAbdullahi05', 'gender' => 'female'],
    ['name' => 'Ibrahim Lawal', 'username' => 'IbrahimLawal06', 'gender' => 'male'],
    ['name' => 'Fatima Suleiman', 'username' => 'FatimaSuleiman07', 'gender' => 'female'],
    ['name' => 'Abubakar Garba', 'username' => 'AbubakarGarba08', 'gender' => 'male'],
    ['name' => 'Maryam Isah', 'username' => 'MaryamIsah09', 'gender' => 'female'],
    ['name' => 'Yusuf Adamu', 'username' => 'YusufAdamu10', 'gender' => 'male'],
    ['name' => 'Aisha Mohammed', 'username' => 'AishaMohammed11', 'gender' => 'female'],
    ['name' => 'Sulaiman Aliyu', 'username' => 'SulaimanAliyu12', 'gender' => 'male'],
    ['name' => 'Khadija Usman', 'username' => 'KhadijaUsman13', 'gender' => 'female'],
    ['name' => 'Bashir Ahmed', 'username' => 'BashirAhmed14', 'gender' => 'male'],
    ['name' => 'Rukayya Musa', 'username' => 'RukayyaMusa15', 'gender' => 'female'],
    ['name' => 'Mustapha Danjuma', 'username' => 'MustaphaDanjuma16', 'gender' => 'male'],
    ['name' => 'Safiya Umar', 'username' => 'SafiyaUmar17', 'gender' => 'female'],
    ['name' => 'Ismaila Yakubu', 'username' => 'IsmailaYakubu18', 'gender' => 'male'],
    ['name' => 'Rahmat Ojo', 'username' => 'RahmatOjo19', 'gender' => 'female'],
    ['name' => 'Tajudeen Bakare', 'username' => 'TajudeenBakare20', 'gender' => 'male'],
];

$findExisting = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
$insert = $pdo->prepare(
    'INSERT INTO admins
        (name, email, username, role, password_hash, is_active, student_id, gender)
     VALUES (?, NULL, ?, "teacher", ?, 1, NULL, ?)'
);

$created = [];
$skipped = 0;

$pdo->beginTransaction();

try {
    foreach ($teachers as $teacher) {
        $findExisting->execute([$teacher['username']]);
        if ($findExisting->fetchColumn()) {
            $skipped++;
            continue;
        }

        // Passwords are unique and meet the application's complexity rules.
        $plainPassword = 'Mkz!' . bin2hex(random_bytes(8)) . random_int(10, 99);
        $insert->execute([
            $teacher['name'],
            $teacher['username'],
            password_hash($plainPassword, PASSWORD_DEFAULT),
            $teacher['gender'],
        ]);

        $created[] = [
            'name' => $teacher['name'],
            'username' => $teacher['username'],
            'password' => $plainPassword,
            'gender' => ucfirst($teacher['gender']),
        ];
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Teacher account seed failed: {$error->getMessage()}\n");
    exit(1);
}

echo "Created " . count($created) . " teacher accounts; skipped {$skipped} existing usernames.\n";
if ($created) {
    echo "Save these one-time generated login credentials securely:\n";
    foreach ($created as $account) {
        echo $account['name'] . " | " . $account['gender'] . " | "
            . $account['username'] . " | " . $account['password'] . "\n";
    }
} else {
    echo "No new credentials were generated because all 20 usernames already exist.\n";
}
