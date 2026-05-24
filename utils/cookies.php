<?php
function setSecureCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = true, $httponly = true) {
    setcookie($name, $value, [
        'expires'  => $expire,
        'path'     => $path,
        'domain'   => $domain ?: $_SERVER['HTTP_HOST'],
        'secure'   => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
    ]);
}
?>