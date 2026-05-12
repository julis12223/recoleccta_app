<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(["success" => true, "message" => 'Preflight OK'], 200);
}

$adminEmail = 'admin@recoleccta.com';
$adminPassword = 'Admin2026!';
$validRoles = ['admin', 'guardian', 'user'];

function authorizeAdminLegacyOrJwt(array $input = []): void {
    $token = getBearerToken();
    if ($token) {
        $payload = verifyJwtToken($token);
        if (!$payload || ($payload['role'] ?? '') !== 'admin') {
            respond(["success" => false, "message" => 'Acceso administrativo no autorizado.'], 403);
        }

        return;
    }

    $providedAdminEmail = trim($input['adminEmail'] ?? ($_GET['adminEmail'] ?? ''));
    $providedAdminPassword = trim($input['adminPassword'] ?? ($_GET['adminPassword'] ?? ''));

    if ($providedAdminEmail !== 'admin@recoleccta.com' || $providedAdminPassword !== 'Admin2026!') {
        respond(["success" => false, "message" => 'Acceso administrativo no autorizado.'], 401);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    authorizeAdminLegacyOrJwt();

    try {
        $stmt = $pdo->query('SELECT id, email, role, created_at FROM users ORDER BY id DESC');
        $users = $stmt->fetchAll();

        respond([
            "success" => true,
            "message" => 'Usuarios cargados correctamente.',
            "data" => [
                "users" => array_map(function ($user) {
                    return [
                        "uid" => (int) $user['id'],
                        "email" => $user['email'],
                        "role" => $user['role'],
                        "createdAt" => $user['created_at'],
                    ];
                }, $users),
            ],
        ]);
    } catch (PDOException $e) {
        respond(["success" => false, "message" => 'Error al cargar usuarios: ' . $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = (int) ($input['userId'] ?? 0);
    $role = trim($input['role'] ?? '');

    authorizeAdminLegacyOrJwt($input);

    if ($userId <= 0 || !in_array($role, $validRoles, true)) {
        badRequest('Datos inválidos para actualizar el rol.');
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $userId]);

        if ($stmt->rowCount() === 0) {
            respond(["success" => false, "message" => 'No se encontró el usuario o no hubo cambios.'], 404);
        }

        respond([
            "success" => true,
            "message" => 'Rol actualizado correctamente.',
            "data" => [
                "uid" => $userId,
                "role" => $role,
            ],
        ]);
    } catch (PDOException $e) {
        respond(["success" => false, "message" => 'Error al actualizar rol: ' . $e->getMessage()], 500);
    }
}

respond(["success" => false, "message" => 'Método no permitido'], 405);
