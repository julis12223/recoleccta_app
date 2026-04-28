<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$dbHost = '127.0.0.1';
$dbName = 'recoleccta';
$dbUser = 'root';
$dbPass = ''; // Cambia esto si tu servidor usa contraseña para root

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos: " . $e->getMessage()]);
    exit;
}

function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function badRequest($message = 'Solicitud incorrecta') {
    respond(["success" => false, "message" => $message], 400);
}
