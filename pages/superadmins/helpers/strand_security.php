<?php

define('ENCRYPTION_KEY', 'b7e1a8f9c3d4e2b69a7f5d8e1c4b3a6d'); 

function encryptId($id) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($id, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptId($encryptedId) {
    list($encryptedData, $iv) = explode('::', base64_decode($encryptedId), 2);
    return openssl_decrypt($encryptedData, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
}
?>