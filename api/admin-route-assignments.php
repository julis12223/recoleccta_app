<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(["success" => true, "message" => 'Preflight OK'], 200);
}

$adminEmail = 'admin@recoleccta.com';
$adminPassword = 'Admin2026!';
$validRouteIds = ['r-14b', 'r-9a', 'r-5c', 'r-2d'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $providedAdminEmail = trim($_GET['adminEmail'] ?? '');
    $providedAdminPassword = trim($_GET['adminPassword'] ?? '');

    if ($providedAdminEmail !== $adminEmail || $providedAdminPassword !== $adminPassword) {
        respond(["success" => false, "message" => 'Acceso administrativo no autorizado.'], 401);
    }

    try {
        $stmt = $pdo->query('SELECT guardian_user_id, route_id, updated_at FROM guardian_route_assignments ORDER BY guardian_user_id ASC');
        $rows = $stmt->fetchAll();

        $assignments = array_map(function ($row) {
            return [
                "guardianId" => (int) $row['guardian_user_id'],
                "routeId" => $row['route_id'],
                "updatedAt" => $row['updated_at'],
            ];
        }, $rows);

        respond([
            "success" => true,
            "message" => 'Asignaciones cargadas correctamente.',
            "data" => [
                "assignments" => $assignments,
            ],
        ]);
    } catch (PDOException $e) {
        respond(["success" => false, "message" => 'Error al cargar asignaciones: ' . $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $providedAdminEmail = trim($input['adminEmail'] ?? '');
    $providedAdminPassword = trim($input['adminPassword'] ?? '');
    $guardianId = (int) ($input['guardianId'] ?? 0);
    $routeId = trim($input['routeId'] ?? '');

    if ($providedAdminEmail !== $adminEmail || $providedAdminPassword !== $adminPassword) {
        respond(["success" => false, "message" => 'Acceso administrativo no autorizado.'], 401);
    }

    if ($guardianId <= 0 || !in_array($routeId, $validRouteIds, true)) {
        badRequest('Datos inválidos para asignación de ruta.');
    }

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'guardian' LIMIT 1");
        $checkStmt->execute([$guardianId]);
        $guardian = $checkStmt->fetch();

        if (!$guardian) {
            respond(["success" => false, "message" => 'El usuario no existe o no es guardián.'], 404);
        }

        $upsertStmt = $pdo->prepare(
            'INSERT INTO guardian_route_assignments (guardian_user_id, route_id) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE route_id = VALUES(route_id), updated_at = CURRENT_TIMESTAMP'
        );
        $upsertStmt->execute([$guardianId, $routeId]);

        respond([
            "success" => true,
            "message" => 'Ruta asignada correctamente.',
            "data" => [
                "guardianId" => $guardianId,
                "routeId" => $routeId,
            ],
        ]);
    } catch (PDOException $e) {
        respond(["success" => false, "message" => 'Error al guardar asignación: ' . $e->getMessage()], 500);
    }
}

respond(["success" => false, "message" => 'Método no permitido'], 405);
