<?php

if (session_status() == PHP_SESSION_NONE) {

    session_name('supermon61');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

$_SESSION['sm61loggedin'] = $_SESSION['sm61loggedin'] ?? false;

?>