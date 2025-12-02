<?php

// Admin authentication check - include dit bestand bovenaan elke beveiligde admin pagina
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Helper functies
function getLoggedInAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

function getLoggedInAdminUsername() {
    return $_SESSION['admin_username'] ?? 'Onbekend';
}

function getLoggedInAdminFullName() {
    return $_SESSION['admin_full_name'] ?? 'Onbekend';
}

