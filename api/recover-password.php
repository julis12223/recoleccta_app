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

if (!$email) {
    badRequest('Debes ingresar un correo válido.');
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        respond(["success" => false, "message" => 'No existe una cuenta asociada a ese correo.'], 404);
    }

    $temporaryPassword = 'Rc' . random_int(100000, 999999);
    $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $updateStmt->execute([$passwordHash, (int) $user['id']]);

    respond([
        "success" => true,
        "message" => 'Se generó una contraseña temporal. Inicia sesión y luego cámbiala.',
        "data" => [
            "temporaryPassword" => $temporaryPassword,
        ],
    ]);
} catch (PDOException $e) {
    respond(["success" => false, "message" => 'Error en el servidor: ' . $e->getMessage()], 500);
}
