<?php
function e($string, $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) {
    return htmlspecialchars($string, $flags, 'UTF-8');
}
?>