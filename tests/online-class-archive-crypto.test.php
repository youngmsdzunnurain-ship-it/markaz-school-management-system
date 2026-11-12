<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/helpers/online-class-archives.php';

function archiveCryptoAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

$archive = [
    'format' => 'markaz-online-class-archive-v1',
    'type' => 'results',
    'level_id' => 4,
    'academic_session' => '2026/2027',
    'term' => 'First Term',
    'is_released' => 1,
    'results' => [
        ['student_id' => 92, 'subject_id' => 3, 'attendance_score' => 18, 'test_score' => 19, 'exam_score' => 55]
    ]
];
$password = 'offline-backup-password-strong';
$compressed = onlineClassArchiveCompress($archive);
$encrypted = onlineClassArchiveEncrypt($compressed, $password);
$file = $encrypted['file'];

archiveCryptoAssert(str_starts_with($file, 'MOCBKP1'), 'Encrypted backup has the expected version header');
archiveCryptoAssert(hash_equals($encrypted['sha256'], hash('sha256', $file)), 'Encrypted backup checksum matches its bytes');
$decrypted = onlineClassArchiveDecrypt($file, $password);
archiveCryptoAssert(is_string($decrypted), 'Correct password decrypts the backup');
archiveCryptoAssert(onlineClassArchiveDecode($decrypted) === $archive, 'Decrypted backup restores the exact archive payload');
archiveCryptoAssert(onlineClassArchiveDecrypt($file, 'incorrect-password') === null, 'Incorrect password is rejected');
$tampered = $file;
$tampered[strlen($tampered) - 1] = chr(ord($tampered[strlen($tampered) - 1]) ^ 1);
archiveCryptoAssert(onlineClassArchiveDecrypt($tampered, $password) === null, 'Tampered ciphertext fails authenticated decryption');
archiveCryptoAssert(onlineClassArchiveDecrypt('INVALID' . substr($file, 7), $password) === null, 'Unsupported file headers are rejected');
archiveCryptoAssert(onlineClassArchiveDecode('not-a-gzip-payload') === null, 'Invalid compressed archive payloads are rejected');

echo "Online Class archive encryption, integrity and recovery tests passed (8 checks)." . PHP_EOL;
