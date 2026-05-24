<?php

function decryptID($encryptedID) {
    $key = "b7e1a8f9c3d4e2b69a7f5d8e1c4b3a6d"; 
    return openssl_decrypt(base64_decode(urldecode($encryptedID)), 'AES-128-ECB', $key, 0, "");
}

$userID = decryptID($_GET['userID']);

?>