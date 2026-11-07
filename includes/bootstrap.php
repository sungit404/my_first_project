<?php
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// '' for pages in the project root, '../' for pages inside /panel
define('BASE', basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'panel' ? '../' : '');

require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

// CSRF protection for every POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        die('Your session expired. Please go back, refresh the page and try again.');
    }
}

// Keep booking and room statuses in step with today's date
sync_rooms();
