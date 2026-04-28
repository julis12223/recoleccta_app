<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'recoleccta';
$dbUser = 'root';
$dbPass = 'Admin2026'; // Cambia esto si tu servidor usa contraseña para root

// Allow cloud deployments (Render, Railway, etc.) to inject database credentials
// while preserving local XAMPP defaults.
$dbHost = getenv('DB_HOST') ?: $dbHost;
$dbPort = getenv('DB_PORT') ?: $dbPort;
$dbName = getenv('DB_NAME') ?: $dbName;
$dbUser = getenv('DB_USER') ?: $dbUser;
$dbPass = getenv('DB_PASS') ?: $dbPass;

// Preferred cloud setup: provide a single URL like
// mysql://user:password@host:port/database
// via DB_URL or MYSQL_PUBLIC_URL and auto-parse it.
$dbUrl = getenv('DB_URL') ?: getenv('MYSQL_PUBLIC_URL') ?: '';
if ($dbUrl) {
    $parts = parse_url($dbUrl);
    if (is_array($parts)) {
        $dbHost = $parts['host'] ?? $dbHost;
        $dbPort = isset($parts['port']) ? (string) $parts['port'] : $dbPort;
        $dbUser = $parts['user'] ?? $dbUser;
        $dbPass = $parts['pass'] ?? $dbPass;
        if (!empty($parts['path'])) {
            $dbName = ltrim($parts['path'], '/');
        }
    }
}

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de conexión a la base de datos: " . $e->getMessage(),
        "debug" => [
            "host" => $dbHost,
            "port" => $dbPort,
            "database" => $dbName,
            "usingDbUrl" => $dbUrl ? true : false,
            "hostLooksInternal" => str_contains($dbHost, '.internal'),
        ],
    ]);
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

