<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(["success" => true, "message" => 'Preflight OK'], 200);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(["success" => false, "message" => 'Método no permitido'], 405);
}

$guardianId = (int) ($_GET['guardianId'] ?? 0);
if ($guardianId <= 0) {
    badRequest('guardianId es obligatorio.');
}

try {
    $guardianStmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ? LIMIT 1");
    $guardianStmt->execute([$guardianId]);
    $guardian = $guardianStmt->fetch();

    if (!$guardian || $guardian['role'] !== 'guardian') {
        respond(["success" => false, "message" => 'Guardián no encontrado.'], 404);
    }

    $assignmentStmt = $pdo->prepare('SELECT route_id, updated_at FROM guardian_route_assignments WHERE guardian_user_id = ? LIMIT 1');
    $assignmentStmt->execute([$guardianId]);
    $assignment = $assignmentStmt->fetch();

    respond([
        "success" => true,
        "message" => $assignment ? 'Asignación encontrada.' : 'Sin asignación activa.',
        "data" => [
            "guardian" => [
                "uid" => (int) $guardian['id'],
                "email" => $guardian['email'],
            ],
            "assignment" => $assignment
                ? [
                    "routeId" => $assignment['route_id'],
                    "updatedAt" => $assignment['updated_at'],
                ]
                : null,
        ],
    ]);
} catch (PDOException $e) {
    respond(["success" => false, "message" => 'Error al consultar asignación: ' . $e->getMessage()], 500);
}
