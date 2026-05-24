<?php 

header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Feature-Policy: geolocation 'none'; microphone 'none'; camera 'none';");
header("Expect-CT: max-age=86400, enforce, report-uri='https://example.com/report';");
header("Cache-Control: no-store, no-cache, must-revalidate, private");
header("Pragma: no-cache");
header("Authorization-Check: require");
header("Clear-Site-Data: 'cookies', 'storage', 'executionContexts'");
// header("Access-Control-Allow-Origin: https://google.com");

?>