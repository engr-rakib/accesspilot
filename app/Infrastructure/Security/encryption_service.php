<?php

if (!defined('API_GATEWAY') && !defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

/**
 * Encrypts a plaintext password using AES-256-CBC.
 *
 * @param string $plaintext The password to encrypt.
 * @param string $key The encryption key (must be 32 bytes).
 * @return string|false The base64-encoded ciphertext with IV, or false on failure.
 */
function encrypt_password($plaintext, $key) {
    
    if (mb_strlen($key, '8bit') !== 32) {
        error_log('Encryption Error: Key must be 32 bytes.');
        return false;
    }
    
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($iv_length);
    
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    
    if ($ciphertext === false) {
        error_log('Encryption Error: openssl_encrypt failed.');
        return false;
    }
    
    // Prepend the IV to the ciphertext for use during decryption
    return base64_encode($iv . $ciphertext);
}

/**
 * Decrypts a password encrypted with encrypt_password().
 *
 * @param string $ciphertext_base64 The base64-encoded ciphertext with IV.
 * @param string $key The encryption key (must be 32 bytes).
 * @return string|false The decrypted plaintext password, or false on failure.
 */
function decrypt_password($ciphertext_base64, $key) {
    if (mb_strlen($key, '8bit') !== 32) {
        error_log('Decryption Error: Key must be 32 bytes.');
        return false;
    }
    
    $data = base64_decode($ciphertext_base64, true);
    if ($data === false) {
        error_log('Decryption Error: base64_decode failed.');
        return false;
    }
    
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    if (strlen($data) < $iv_length) {
        error_log('Decryption Error: Ciphertext is too short.');
        return false;
    }

    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);
    
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    
    if ($plaintext === false) {
        error_log('Decryption Error: openssl_decrypt failed. Check if the key is correct or data is corrupted.');
        return false;
    }
    
    return $plaintext;
}
