<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$jwtSecret = getenv('JWT_SECRET') ?: 'recoleccta-dev-secret-change-me';

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'recoleccta';
$dbUser = 'root';
$dbPass = ''; // XAMPP local: root sin contraseña

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

    // Ensure required tables exist in cloud databases where schema may not be preloaded.
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','guardian','user') NOT NULL DEFAULT 'user',
        displayName VARCHAR(255) NULL DEFAULT NULL,
        photoURL TEXT NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS guardian_route_assignments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        guardian_user_id INT UNSIGNED NOT NULL,
        route_id VARCHAR(40) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_guardian_user_id (guardian_user_id),
        CONSTRAINT fk_guardian_route_user
            FOREIGN KEY (guardian_user_id) REFERENCES users (id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
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

function base64UrlEncode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64UrlDecode(string $value): string|false {
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/'));
}

function createJwtToken(array $user, int $ttlSeconds = 86400): string {
    global $jwtSecret;

    $now = time();
    $header = ["alg" => "HS256", "typ" => "JWT"];
    $payload = [
        "sub" => (string) ($user['uid'] ?? $user['id'] ?? ''),
        "uid" => (int) ($user['uid'] ?? $user['id'] ?? 0),
        "email" => (string) ($user['email'] ?? ''),
        "role" => (string) ($user['role'] ?? 'user'),
        "displayName" => $user['displayName'] ?? null,
        "photoURL" => $user['photoURL'] ?? null,
        "iat" => $now,
        "exp" => $now + $ttlSeconds,
    ];

    $headerEncoded = base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadEncoded = base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $jwtSecret, true);

    return $headerEncoded . '.' . $payloadEncoded . '.' . base64UrlEncode($signature);
}

function verifyJwtToken(string $token): array|false {
    global $jwtSecret;

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
    $headerJson = base64UrlDecode($headerEncoded);
    $payloadJson = base64UrlDecode($payloadEncoded);
    $signature = base64UrlDecode($signatureEncoded);

    if ($headerJson === false || $payloadJson === false || $signature === false) {
        return false;
    }

    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) {
        return false;
    }

    if (($header['alg'] ?? '') !== 'HS256') {
        return false;
    }

    $expectedSignature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $jwtSecret, true);
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }

    if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
        return false;
    }

    return $payload;
}

function getBearerToken(): ?string {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$authorization && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function requireJwtToken(array $allowedRoles = []): array {
    $token = getBearerToken();
    if (!$token) {
        respond(["success" => false, "message" => 'Token no proporcionado.'], 401);
    }

    $payload = verifyJwtToken($token);
    if (!$payload) {
        respond(["success" => false, "message" => 'Token inválido o expirado.'], 401);
    }

    if ($allowedRoles && !in_array(($payload['role'] ?? ''), $allowedRoles, true)) {
        respond(["success" => false, "message" => 'Acceso denegado.'], 403);
    }

    return $payload;
}
