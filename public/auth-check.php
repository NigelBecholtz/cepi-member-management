<?php

// Authentication check - include dit bestand bovenaan elke beveiligde pagina
session_start();

if (!isset($_SESSION['organisation_id'])) {
    header('Location: login.php');
    exit;
}

// Helper functies
function getLoggedInOrganisationId() {
    return $_SESSION['organisation_id'] ?? null;
}

function getLoggedInOrganisationName() {
    return $_SESSION['organisation_name'] ?? 'Onbekend';
}

function getLoggedInUsername() {
    return $_SESSION['username'] ?? 'Onbekend';
}



