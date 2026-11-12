<?php
declare(strict_types=1);

/**
 * Encode an Online Class archive as compact JSON compressed with gzip.
 */
function onlineClassArchiveCompress(array $archive): string
{
    $json = json_encode(
        $archive,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    );
    $compressed = gzencode($json, 9);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress the Online Class archive.');
    }
    return $compressed;
}

/**
 * Encrypt a compressed device backup using a password-derived AES-256-GCM key.
 * File layout: magic(7) + salt(16) + nonce(12) + tag(16) + ciphertext.
 */
function onlineClassArchiveEncrypt(string $compressed, string $password): array
{
    if ($password === '' || !function_exists('openssl_encrypt')) {
        throw new RuntimeException('A backup password and server encryption support are required.');
    }
    $salt = random_bytes(16);
    $nonce = random_bytes(12);
    $key = hash_pbkdf2('sha256', $password, $salt, 210000, 32, true);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $compressed,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        'MARKAZ-ONLINE-CLASS-ARCHIVE-V1'
    );
    if (!is_string($ciphertext) || strlen($tag) !== 16) {
        throw new RuntimeException('Unable to encrypt the Online Class backup.');
    }
    $file = 'MOCBKP1' . $salt . $nonce . $tag . $ciphertext;
    return ['file' => $file, 'payload' => base64_encode($file), 'sha256' => hash('sha256', $file)];
}

/**
 * Authenticate and decrypt an encrypted device backup. A null result means
 * the password, header, or authentication tag is invalid.
 */
function onlineClassArchiveDecrypt(string $file, string $password): ?string
{
    if ($password === '' || !function_exists('openssl_decrypt')
        || strlen($file) < 52 || substr($file, 0, 7) !== 'MOCBKP1') {
        return null;
    }
    $salt = substr($file, 7, 16);
    $nonce = substr($file, 23, 12);
    $tag = substr($file, 35, 16);
    $ciphertext = substr($file, 51);
    $key = hash_pbkdf2('sha256', $password, $salt, 210000, 32, true);
    $compressed = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        'MARKAZ-ONLINE-CLASS-ARCHIVE-V1'
    );
    return is_string($compressed) ? $compressed : null;
}

/**
 * Decompress and decode a stored archive payload.
 */
function onlineClassArchiveDecode(string $compressed): ?array
{
    $json = @gzdecode($compressed);
    if (!is_string($json)) {
        return null;
    }
    try {
        $archive = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        return null;
    }
    return is_array($archive) ? $archive : null;
}
