<?php
declare(strict_types=1);

$host = '127.0.0.1';
$db   = 'markaz_result_system';
$user = 'root';
$pass = '';

$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

/*
|--------------------------------------------------------------------------
| SAFETY CHECK
|--------------------------------------------------------------------------
| This is an initial import. We expect the students table to be empty.
*/
$count = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

if ($count !== 0) {
    throw new RuntimeException(
        "Import stopped: students table already contains {$count} student(s). No data was changed."
    );
}

/*
|--------------------------------------------------------------------------
| REQUIRED CONVENTIONAL CLASSES
|--------------------------------------------------------------------------
*/
$classNames = [
    'Nursery 1',
    'Basic 1',
    'Basic 3',
    'Basic 5',
    'Jss 2',
    'Jss 3',
];

$classIds = [];

$stmt = $pdo->prepare(
    "SELECT id FROM classes WHERE name = ? AND is_active = 1 LIMIT 1"
);

foreach ($classNames as $className) {
    $stmt->execute([$className]);
    $id = $stmt->fetchColumn();

    if (!$id) {
        throw new RuntimeException(
            "Import stopped: conventional class '{$className}' was not found."
        );
    }

    $classIds[$className] = (int) $id;
}

/*
|--------------------------------------------------------------------------
| REQUIRED ARABIC CLASSES
|--------------------------------------------------------------------------
*/
$arabicNames = [
    'Roudoh Ula',
    'Roudoh Sani',
    'Faslu Ula',
    'Faslu Sani',
    'Faslu Salis',
    'Faslu Rabia',
    'Faslu Khamis',
    'Faslu Sadis',
];

$arabicIds = [];

$stmt = $pdo->prepare(
    "SELECT id FROM arabic_classes WHERE name = ? AND is_active = 1 LIMIT 1"
);

foreach ($arabicNames as $arabicName) {
    $stmt->execute([$arabicName]);
    $id = $stmt->fetchColumn();

    if (!$id) {
        throw new RuntimeException(
            "Import stopped: Arabic class '{$arabicName}' was not found."
        );
    }

    $arabicIds[$arabicName] = (int) $id;
}

/*
|--------------------------------------------------------------------------
| STUDENT DATA
|--------------------------------------------------------------------------
|
| Format:
| [
|     'full name',
|     'conventional class',
|     'arabic class'
| ]
|
*/
$students = [

    // NURSERY 1
    ['Sulaiman Shehu Bello', 'Nursery 1', 'Roudoh Sani'],
    ['Abdullahi Zakariyyah', 'Nursery 1', 'Faslu Ula'],
    ['Zainab Shuaibu Tatu', 'Nursery 1', 'Roudoh Sani'],
    ['Maimuna Sulaiman', 'Nursery 1', 'Faslu Ula'],
    ['Buhari Muhammad', 'Nursery 1', 'Faslu Ula'],
    ['Aisha Humaira Jamil', 'Nursery 1', 'Roudoh Sani'],
    ['Zainab Umar Likita', 'Nursery 1', 'Roudoh Sani'],
    ['Kamaludeen Hamza', 'Nursery 1', 'Faslu Ula'],
    ['Mustapha Yakubu', 'Nursery 1', 'Faslu Ula'],
    ['Usman Khalid', 'Nursery 1', 'Faslu Ula'],

    // BASIC 1
    ['Usman Hashim', 'Basic 1', 'Faslu Ula'],
    ['Aliyu Muhammad', 'Basic 1', 'Faslu Sani'],
    ['Aliyu Isah', 'Basic 1', 'Faslu Sani'],
    ['Abdulrahman Abdulrasheed', 'Basic 1', 'Roudoh Sani'],
    ['Abdulsalam Abdulkareem', 'Basic 1', 'Faslu Ula'],
    ['Summayya Alhassan', 'Basic 1', 'Faslu Ula'],
    ['Rofiat Adedayo', 'Basic 1', 'Roudoh Sani'],
    ['Jafar Sani', 'Basic 1', 'Faslu Ula'],
    ['Ahmad Abdullahi', 'Basic 1', 'Roudoh Sani'],
    ['Farhan Saheed', 'Basic 1', 'Roudoh Sani'],

    // BASIC 3
    ['Abdurrahman Teslim', 'Basic 3', 'Faslu Salis'],
    ['Abdulrasheed Teslim', 'Basic 3', 'Faslu Salis'],
    ['Abdullahi Musbahudeen', 'Basic 3', 'Faslu Salis'],
    ['Usman Muhammad', 'Basic 3', 'Faslu Sani'],
    ['Usman Bello', 'Basic 3', 'Faslu Sani'],
    ['Abdulrahman Abdulwasiu', 'Basic 3', 'Faslu Salis'],
    ['Maimuna Shuaib Tatu', 'Basic 3', 'Faslu Sani'],
    ['Saadatu Abubakar', 'Basic 3', 'Faslu Salis'],
    ['Khalid Musa', 'Basic 3', 'Faslu Sani'],
    ['Ramadan Abdullahi', 'Basic 3', 'Faslu Khamis'],
    ['Saeed Ahmad', 'Basic 3', 'Faslu Salis'],
    ['Aisha Abdulrahman', 'Basic 3', 'Roudoh Sani'],
    ['Rayyan Nura', 'Basic 3', 'Faslu Ula'],

    // BASIC 5
    ['Hauwa Tarik M. Daud', 'Basic 5', 'Faslu Salis'],
    ['Hauwa Abubakar', 'Basic 5', 'Faslu Rabia'],
    ['Fatimah Zakariyah', 'Basic 5', 'Faslu Khamis'],
    ['Hibatullah Habib', 'Basic 5', 'Faslu Rabia'],
    ['Muhammad Said A.', 'Basic 5', 'Faslu Salis'],
    ['Muhammad Mas\'ud', 'Basic 5', 'Faslu Rabia'],
    ['Mustapha Mutiullah', 'Basic 5', 'Faslu Rabia'],
    ['Suhail Khalid', 'Basic 5', 'Faslu Salis'],
    ['Abdulhameed Tijani', 'Basic 5', 'Faslu Rabia'],
    ['Abdulhameed Abass', 'Basic 5', 'Faslu Rabia'],
    ['Teslim Umar Adam', 'Basic 5', 'Faslu Salis'],
    ['Sulayman Muhammad', 'Basic 5', 'Faslu Salis'],
    ['Junaid Bankole', 'Basic 5', 'Faslu Salis'],
    ['Maryam Auwal Yunus', 'Basic 5', 'Faslu Salis'],
    ['Aliyu Umar Likita', 'Basic 5', 'Faslu Salis'],
    ['Ayman Salihu', 'Basic 5', 'Faslu Salis'],
    ['Layla Ajani', 'Basic 5', 'Faslu Rabia'],
    ['Fatimah Abdullah', 'Basic 5', 'Faslu Sani'],

    // JSS 2
    ['Abdulmajeed Saeed', 'Jss 2', 'Faslu Sadis'],
    ['Abubakar Shamsudeen', 'Jss 2', 'Faslu Khamis'],
    ['Ubaidullah Abubakar', 'Jss 2', 'Faslu Rabia'],
    ['Muhammad Kabeer Adetayo', 'Jss 2', 'Faslu Rabia'],
    ['Muhammad Kabeer Salawudeen', 'Jss 2', 'Faslu Sani'],
    ['Hanif Tijani', 'Jss 2', 'Faslu Khamis'],
    ['Hassan Mukhlis', 'Jss 2', 'Faslu Sani'],
    ['Abubakar Musa Khalifa', 'Jss 2', 'Faslu Salis'],
    ['Aliyu Abubakar', 'Jss 2', 'Faslu Salis'],
    ['Muhammad Yunus', 'Jss 2', 'Faslu Rabia'],
    ['Ummusalma Attahiru', 'Jss 2', 'Faslu Khamis'],
    ['Rahma Isah Sani', 'Jss 2', 'Faslu Khamis'],
    ['Mukarram Abubakar', 'Jss 2', 'Faslu Khamis'],
    ['Al Ameen Musa Bukhari', 'Jss 2', 'Faslu Khamis'],
    ['Karibullah Abubakar', 'Jss 2', 'Faslu Khamis'],
    ['Abdullahi Abdulwasiu', 'Jss 2', 'Faslu Sadis'],
    ['Muhammad Tariq', 'Jss 2', 'Faslu Rabia'],

    // JSS 3
    ['Abduljalil Salihu', 'Jss 3', 'Faslu Sadis'],
    ['Abdulazeez Saeed', 'Jss 3', 'Faslu Sadis'],
    ['Yunus Abdulrasheed', 'Jss 3', 'Faslu Khamis'],
    ['Abdulwarith Muhammad', 'Jss 3', 'Faslu Rabia'],
    ['Anas Haruna Alfa', 'Jss 3', 'Faslu Khamis'],
    ['Abdulmuqtadir Mutiullah', 'Jss 3', 'Faslu Khamis'],
    ['Yakub Muhammad Khalifa', 'Jss 3', 'Faslu Khamis'],
    ['Sultan Muhamad Bawa', 'Jss 3', 'Faslu Rabia'],
    ['Abubakar Abdulwasiu', 'Jss 3', 'Faslu Khamis'],
    ['Ahmad Zahradeen', 'Jss 3', 'Faslu Khamis'],
    ['Muhammad Faheem Abbas', 'Jss 3', 'Faslu Khamis'],
    ['Munira Abdullah', 'Jss 3', 'Faslu Rabia'],
    ['Ameerah Sulayman', 'Jss 3', 'Faslu Rabia'],
    ['Khalilullah Adebayo', 'Jss 3', 'Faslu Khamis'],
    ['Nana Hauwa Haruna', 'Jss 3', 'Faslu Khamis'],
    ['Zainab Akinade', 'Jss 3', 'Faslu Rabia'],
    ['Khadijah Aminu', 'Jss 3', 'Faslu Rabia'],
    ['Ayatullah Taofeek', 'Jss 3', 'Faslu Khamis'],
];

/*
|--------------------------------------------------------------------------
| VALIDATE EXPECTED TOTAL
|--------------------------------------------------------------------------
*/
if (count($students) !== 86) {
    throw new RuntimeException(
        "Import stopped: expected 86 students, but the script contains " .
        count($students) . "."
    );
}

/*
|--------------------------------------------------------------------------
| NAME SPLITTER
|--------------------------------------------------------------------------
|
| First word  -> first_name
| Last word   -> last_name
| Everything between -> middle_name
|
*/
function splitName(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName));

    if (!$parts || count($parts) < 2) {
        throw new RuntimeException(
            "Invalid student name: {$fullName}"
        );
    }

    $firstName = array_shift($parts);
    $lastName  = array_pop($parts);
    $middleName = count($parts) > 0 ? implode(' ', $parts) : null;

    return [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
    ];
}

/*
|--------------------------------------------------------------------------
| ADMISSION NUMBER GENERATOR
|--------------------------------------------------------------------------
*/
function generateAdmissionNumber(PDO $pdo): string
{
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM students WHERE admission_number = ?"
    );

    do {
        $number = 'MZ' . random_int(100000, 999999);
        $check->execute([$number]);
    } while ((int) $check->fetchColumn() > 0);

    return $number;
}

/*
|--------------------------------------------------------------------------
| IMPORT
|--------------------------------------------------------------------------
*/
$pdo->beginTransaction();

try {
    $insert = $pdo->prepare(
        "INSERT INTO students (
            admission_number,
            first_name,
            middle_name,
            last_name,
            profile_image,
            gender,
            date_of_birth,
            class_id,
            arabic_class_id,
            admission_date,
            is_active
        ) VALUES (
            ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, CURDATE(), 1
        )"
    );

    foreach ($students as $student) {
        [$fullName, $className, $arabicName] = $student;

        if (!isset($classIds[$className])) {
            throw new RuntimeException(
                "Unknown conventional class: {$className}"
            );
        }

        if (!isset($arabicIds[$arabicName])) {
            throw new RuntimeException(
                "Unknown Arabic class: {$arabicName}"
            );
        }

        $name = splitName($fullName);

        $admissionNumber = generateAdmissionNumber($pdo);

        $insert->execute([
            $admissionNumber,
            $name['first_name'],
            $name['middle_name'],
            $name['last_name'],
            $classIds[$className],
            $arabicIds[$arabicName],
        ]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

/*
|--------------------------------------------------------------------------
| FINAL VERIFICATION
|--------------------------------------------------------------------------
*/
$total = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

echo "========================================\n";
echo "MARKAZ STUDENT IMPORT COMPLETED\n";
echo "========================================\n";
echo "Students imported: {$total}\n\n";

echo "Conventional class breakdown:\n";

$stmt = $pdo->query(
    "SELECT c.name, COUNT(s.id) AS total
     FROM classes c
     LEFT JOIN students s ON s.class_id = c.id
     WHERE c.name IN ('Nursery 1','Basic 1','Basic 3','Basic 5','Jss 2','Jss 3')
     GROUP BY c.id, c.name
     ORDER BY c.id"
);

foreach ($stmt as $row) {
    echo "  {$row['name']}: {$row['total']}\n";
}

echo "\nArabic class breakdown:\n";

$stmt = $pdo->query(
    "SELECT a.name, COUNT(s.id) AS total
     FROM arabic_classes a
     LEFT JOIN students s ON s.arabic_class_id = a.id
     GROUP BY a.id, a.name
     ORDER BY a.id"
);

foreach ($stmt as $row) {
    if ((int) $row['total'] > 0) {
        echo "  {$row['name']}: {$row['total']}\n";
    }
}

echo "\nImport verification complete.\n";
