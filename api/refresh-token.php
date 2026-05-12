<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(["success" => true, "message" => 'Preflight OK'], 200);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(["success" => false, "message" => 'Método no permitido'], 405);
}

$payload = requireJwtToken();

$user = [
    'uid' => (int) ($payload['uid'] ?? 0),
    'email' => (string) ($payload['email'] ?? ''),
    'role' => (string) ($payload['role'] ?? 'user'),
    'displayName' => $payload['displayName'] ?? null,
    'photoURL' => $payload['photoURL'] ?? null,
];

if ($user['uid'] <= 0 || !$user['email']) {
    if (!($user['uid'] === 0 && $user['role'] === 'admin' && $user['email'] === 'admin@recoleccta.com')) {
        respond(["success" => false, "message" => 'Token inválido.'], 401);
    }
}

$newToken = createJwtToken($user);

respond([
    "success" => true,
    "message" => 'Token renovado correctamente.',
    "data" => [
        "user" => $user,
        "token" => $newToken,
    ],
]);
