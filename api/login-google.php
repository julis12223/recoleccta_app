<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(["success" => true, "message" => 'Preflight OK'], 200);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $idToken = trim($input['idToken'] ?? '');
    $userRole = trim($input['role'] ?? 'user');

    if (!$idToken) {
        badRequest('Token de Google requerido.');
    }

    if (!in_array($userRole, ['admin', 'guardian', 'user'], true)) {
        badRequest('Rol inválido.');
    }

    try {
        // Decodificar token JWT de Google (sin verificar firma - para desarrollo)
        // En producción, verificar con Google's public keys
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new Exception('Token JWT inválido.');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!$payload) {
            throw new Exception('No se pudo decodificar el token.');
        }

        $googleEmail = $payload['email'] ?? null;
        $googleName = $payload['name'] ?? null;
        $googlePhotoUrl = $payload['picture'] ?? null;

        if (!$googleEmail) {
            throw new Exception('El token no contiene email.');
        }

        // Buscar o crear usuario con ese email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$googleEmail]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // Usuario ya existe. Si no coincide el rol solicitado, ignoramos y usamos el existente
            $user = [
                'uid' => (int) $existingUser['id'],
                'email' => $existingUser['email'],
                'role' => $existingUser['role'],
                'displayName' => $existingUser['displayName'] ?? $googleName,
                'photoURL' => $existingUser['photoURL'] ?? $googlePhotoUrl,
            ];
        } else {
            // Crear nuevo usuario con rol solicitado
            try {
                // Usar una contraseña aleatoria (no será usada en OAuth flow, pero la DB lo requiere)
                $randomPassword = bin2hex(random_bytes(16));
                $passwordHash = password_hash($randomPassword, PASSWORD_BCRYPT);

                $insertStmt = $pdo->prepare(
                    "INSERT INTO users (email, password_hash, role, displayName, photoURL) VALUES (?, ?, ?, ?, ?)"
                );
                $insertStmt->execute([$googleEmail, $passwordHash, $userRole, $googleName, $googlePhotoUrl]);

                $newUserId = (int) $pdo->lastInsertId();
                $user = [
                    'uid' => $newUserId,
                    'email' => $googleEmail,
                    'role' => $userRole,
                    'displayName' => $googleName,
                    'photoURL' => $googlePhotoUrl,
                ];
            } catch (PDOException $e) {
                // Si el email ya existe (race condition), intentar recuperarlo
                if (strpos($e->getMessage(), 'UNIQUE') !== false || $e->getCode() == 23505) {
                    $retryStmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                    $retryStmt->execute([$googleEmail]);
                    $existingUser = $retryStmt->fetch();
                    if ($existingUser) {
                        $user = [
                            'uid' => (int) $existingUser['id'],
                            'email' => $existingUser['email'],
                            'role' => $existingUser['role'],
                            'displayName' => $existingUser['displayName'] ?? $googleName,
                            'photoURL' => $existingUser['photoURL'] ?? $googlePhotoUrl,
                        ];
                    } else {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }
        }

        respond([
            "success" => true,
            "message" => 'Login con Google exitoso.',
            "data" => [
                "user" => $user,
                "token" => createJwtToken($user),
            ],
        ]);
    } catch (Exception $e) {
        respond(["success" => false, "message" => 'Error al procesar Google login: ' . $e->getMessage()], 500);
    }
}

respond(["success" => false, "message" => 'Método no permitido'], 405);
