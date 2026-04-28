<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(["success" => true, "message" => 'Preflight OK'], 200);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(["success" => false, "message" => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = trim($input['password'] ?? '');

if (!$email || !$password) {
    badRequest('Email y contraseña son obligatorios.');
}

// Cuenta especial para acceso administrativo del sistema.
$adminEmail = 'admin@recoleccta.com';
$adminPassword = 'Admin2026!';

if ($email === $adminEmail && $password === $adminPassword) {
    respond([
        "success" => true,
        "message" => 'Inicio de sesión administrativo exitoso.',
        "data" => [
            "user" => [
                "uid" => 0,
                "email" => $adminEmail,
                "role" => 'admin',
            ],
        ],
    ]);
}

try {
    $stmt = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        respond(["success" => false, "message" => 'Email o contraseña incorrectos.'], 401);
    }

    respond([
        "success" => true,
        "message" => 'Inicio de sesión exitoso.',
        "data" => [
            "user" => [
                "uid" => (int) $user['id'],
                "email" => $user['email'],
                "role" => $user['role'],
            ],
        ],
    ]);
} catch (PDOException $e) {
    respond(["success" => false, "message" => 'Error en el servidor: ' . $e->getMessage()], 500);
}

