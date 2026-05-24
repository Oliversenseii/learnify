<?php 

function encryptID($id) {
    $key = "b7e1a8f9c3d4e2b69a7f5d8e1c4b3a6d"; 
    return urlencode(base64_encode(openssl_encrypt($id, 'AES-128-ECB', $key, 0, "")));
}

?>