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
$role = trim($input['role'] ?? 'user');

$validRoles = ['guardian', 'user'];
if (!in_array($role, $validRoles, true)) {
    $role = 'user';
}

if (!$email || !$password) {
    badRequest('Email, contraseña y rol son obligatorios.');
}

if (strlen($password) < 6) {
    badRequest('La contraseña debe tener al menos 6 caracteres.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$email, $passwordHash, $role]);
    $userId = (int) $pdo->lastInsertId();

    $user = [
        'uid' => $userId,
        'email' => $email,
        'role' => $role,
        'displayName' => null,
        'photoURL' => null,
    ];

    respond([
        "success" => true,
        "message" => 'Registro exitoso.',
        "data" => [
            "user" => $user,
            "token" => createJwtToken($user),
        ],
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        respond(["success" => false, "message" => 'El email ya está registrado.'], 409);
    }
    respond(["success" => false, "message" => 'Error en el servidor: ' . $e->getMessage()], 500);
}
