<?php
$file = 'users_data.csv';

if (file_exists($file)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_data.csv"');
    readfile($file);
    exit;
} else {
    echo "File not found!";
}
?>
