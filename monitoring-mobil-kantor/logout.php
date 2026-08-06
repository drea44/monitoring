<?php
session_start();
session_unset();
session_destroy();
// Use absolute path to ensure redirect works regardless of subdirectory depth
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
header('Location: ' . $base . 'index.php');
exit;
