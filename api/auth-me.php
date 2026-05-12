<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(["success" => true, "message" => 'Preflight OK'], 200);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(["success" => false, "message" => 'Método no permitido'], 405);
}

$payload = requireJwtToken();

try {
    $uid = (int) ($payload['uid'] ?? 0);
    $role = (string) ($payload['role'] ?? 'user');
    $email = (string) ($payload['email'] ?? '');

    if ($uid <= 0) {
        // Support the fixed local admin account represented by uid 0.
        if ($role === 'admin' && $email === 'admin@recoleccta.com') {
            respond([
                "success" => true,
                "message" => 'Sesión válida.',
                "data" => [
                    "user" => [
                        "uid" => 0,
                        "email" => $email,
                        "role" => 'admin',
                        "displayName" => $payload['displayName'] ?? 'Administrador',
                        "photoURL" => $payload['photoURL'] ?? null,
                    ],
                    "token" => getBearerToken(),
                ],
            ]);
        }

        respond(["success" => false, "message" => 'Token inválido.'], 401);
    }

    $stmt = $pdo->prepare('SELECT id, email, role, displayName, photoURL FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if (!$user) {
        respond(["success" => false, "message" => 'Usuario no encontrado.'], 404);
    }

    respond([
        "success" => true,
        "message" => 'Sesión válida.',
        "data" => [
            "user" => [
                "uid" => (int) $user['id'],
                "email" => $user['email'],
                "role" => $user['role'],
                "displayName" => $user['displayName'] ?? null,
                "photoURL" => $user['photoURL'] ?? null,
            ],
            "token" => getBearerToken(),
        ],
    ]);
} catch (PDOException $e) {
    respond(["success" => false, "message" => 'Error en el servidor: ' . $e->getMessage()], 500);
}
