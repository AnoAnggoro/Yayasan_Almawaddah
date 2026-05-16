<?php
session_start();

// Destroy all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login page (gunakan absolute path)
header('Location: /PROJECT/yayasan_almawaddah/index.php');
exit;
?>
