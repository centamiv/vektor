<?php

require __DIR__ . '/../vendor/autoload.php';

use Centamiv\Vektor\Api\Controller;

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$controller = new Controller();
$controller->handleRequest();
