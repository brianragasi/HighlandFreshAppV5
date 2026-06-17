<?php
/**
 * Highland Fresh System - Entry Point
 * Redirects to the login page
 */

// Redirect to login page (absolute path to avoid relative-redirect loop on hosts
// that fall back to index.php for any non-existent URL).
header('Location: /html/login.html');
exit;
