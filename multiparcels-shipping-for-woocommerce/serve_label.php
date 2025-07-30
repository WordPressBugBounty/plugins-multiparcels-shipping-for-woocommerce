<?php
// Load WordPress environment
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );

// Check user logged in
if ( ! is_user_logged_in() ) {
    auth_redirect(); // redirect guests to login page
    exit;
}

// Sanitize filename from query param
$filename = basename($_GET['file'] ?? '');

$filepath = __DIR__ . '/shipments_labels/' . $filename;

if ( ! file_exists( $filepath ) ) {
    wp_die('File not found.');
}

// Serve PDF file (adjust Content-Type if needed)
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;