<?php
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . " year" . ($diff->y > 1 ? "s" : "") . " ago";
    if ($diff->m > 0) return $diff->m . " month" . ($diff->m > 1 ? "s" : "") . " ago";
    if ($diff->d > 0) return $diff->d . " day" . ($diff->d > 1 ? "s" : "") . " ago";
    if ($diff->h > 0) return $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
    if ($diff->i > 0) return $diff->i . " min" . ($diff->i > 1 ? "s" : "") . " ago";
    return $diff->s . " sec" . ($diff->s > 1 ? "s" : "") . " ago";
}
?>