<?php
/**
 * E-Find — Restablecer contraseña con un token
 * GET  /api/restablecer.php?token=…              → sólo valida el token
 * POST /api/restablecer.php  body: { token, password }
 *
 * El token viaja en claro en el enlace del correo, pero en la base sólo está
 * su SHA-256: se busca por el hash. Es de un solo uso y vence en una hora.
 */
require_once '../includes/sesion.php';
iniciar_sesion_segura();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';

/** Devuelve la fila del token si sigue siendo utilizable, o null. */
function token_valido(PDO $db, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $stmt = $db->prepare("SELECT id, usuario_id FROM password_resets
                          WHERE token_hash = :hash AND usado_en IS NULL AND expira_en > NOW()");
    $stmt->execute([':hash' => hash('sha256', $token)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    $db     = db_connect();
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        $valido = token_valido($db, $_GET['token'] ?? '') !== null;
        echo json_encode(['ok' => true, 'data' => ['valido' => $valido]]);
        exit;
    }

    if ($metodo !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
        exit;
    }

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $token    = trim($body['token'] ?? '');
    $password = $body['password'] ?? '';

    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.']);
        exit;
    }

    $fila = token_valido($db, $token);
    if (!$fila) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'El enlace no es válido o ya venció. Pedí uno nuevo.']);
        exit;
    }

    $db->beginTransaction();

    /* Mismo algoritmo y costo que usa el registro, para no dejar dos
       familias de hashes conviviendo en la tabla usuarios. */
    $db->prepare("UPDATE usuarios SET password_hash = :hash WHERE id = :uid")
       ->execute([
           ':hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
           ':uid'  => $fila['usuario_id'],
       ]);

    /* El token usado y cualquier otro pendiente del mismo usuario quedan muertos. */
    $db->prepare("UPDATE password_resets SET usado_en = NOW()
                  WHERE usuario_id = :uid AND usado_en IS NULL")
       ->execute([':uid' => $fila['usuario_id']]);

    $db->commit();

    /* Si la cuenta estaba bloqueada por intentos fallidos, se libera: quien
       llegó hasta acá probó que tiene acceso al correo. La tabla la crea
       login.php, así que puede no existir todavía; si falla no importa. */
    try {
        $db->prepare("DELETE li FROM login_intentos li
                      JOIN usuarios u ON u.email = li.email
                      WHERE u.id = :uid")
           ->execute([':uid' => $fila['usuario_id']]);
    } catch (PDOException $e) { /* la contraseña ya se cambió, seguimos */ }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
