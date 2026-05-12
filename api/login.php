<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(["success" => true, "message" => 'Preflight OK'], 200);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(["success" => false, "message" => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$rawEmail = strtolower(trim($input['email'] ?? ''));
$email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
$password = trim($input['password'] ?? '');

if (!$email || !$password) {
    badRequest('Email y contraseña son obligatorios.');
}

// Cuenta especial para acceso administrativo del sistema.
$adminEmail = 'admin@recoleccta.com';
$adminPassword = 'Admin2026!';

if ($email === $adminEmail && $password === $adminPassword) {
    $adminUser = [
        'uid' => 0,
        'email' => $adminEmail,
        'role' => 'admin',
        'displayName' => 'Administrador',
        'photoURL' => null,
    ];

    respond([
        "success" => true,
        "message" => 'Inicio de sesión administrativo exitoso.',
        "data" => [
            "user" => $adminUser,
            "token" => createJwtToken($adminUser),
        ],
    ]);
}

try {
    $stmt = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        respond(["success" => false, "message" => 'Email o contraseña incorrectos.'], 401);
    }

    $userPayload = [
        'uid' => (int) $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'displayName' => $user['displayName'] ?? null,
        'photoURL' => $user['photoURL'] ?? null,
    ];

    respond([
        "success" => true,
        "message" => 'Inicio de sesión exitoso.',
        "data" => [
            "user" => $userPayload,
            "token" => createJwtToken($userPayload),
        ],
    ]);
} catch (PDOException $e) {
    respond(["success" => false, "message" => 'Error en el servidor: ' . $e->getMessage()], 500);
}

