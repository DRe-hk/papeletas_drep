<?php
/**
 * Sincroniza el usuario del sistema con un registro de personal.
 *
 * Reglas:
 * - Si el personal NO tiene usuario: lo crea con username = DNI,
 *   password = DNI (bcrypt), rol = 'usuario', must_change = 1.
 * - Si ya existe (por personal_id o por username=DNI):
 *   * sincroniza personal_id y activo
 *   * NO toca la contraseña salvo que $resetPassword = true
 * - Si la personal está inactiva, el usuario también (no puede loguearse).
 *
 * @return array{created:bool, updated:bool, reset:bool, user_id:?int, reason:?string}
 */
declare(strict_types=1);

function sync_user_from_personal(
    PDO $pdo,
    int $personalId,
    string $dni,
    int $activoPersonal,
    bool $resetPassword = false
): array {
    $result = [
        'created' => false,
        'updated' => false,
        'reset'   => false,
        'user_id' => null,
        'reason'  => null,
    ];

    if ($personalId <= 0) {
        $result['reason'] = 'personalId invalido';
        return $result;
    }
    if (!preg_match('/^\d{8}$/', $dni)) {
        $result['reason'] = 'DNI invalido';
        return $result;
    }

    // 1) Buscar usuario ya vinculado al personal
    $st = $pdo->prepare('SELECT * FROM usuarios WHERE personal_id = ? LIMIT 1');
    $st->execute([$personalId]);
    $user = $st->fetch();

    // 2) Si no, buscar por username = DNI (legacy / huérfano)
    if (!$user) {
        $st = $pdo->prepare('SELECT * FROM usuarios WHERE username = ? LIMIT 1');
        $st->execute([$dni]);
        $user = $st->fetch();
    }

    if ($user) {
        $updates = [];
        $params  = [];

        if ((int) $user['personal_id'] !== $personalId) {
            $updates[] = 'personal_id = ?';
            $params[]  = $personalId;
        }
        if ((int) $user['activo'] !== $activoPersonal) {
            $updates[] = 'activo = ?';
            $params[]  = $activoPersonal;
        }
        if ($resetPassword) {
            $updates[] = 'password_hash = ?';
            $params[]  = password_hash($dni, PASSWORD_BCRYPT);
            $updates[] = 'must_change = 1';
        }

        if ($updates) {
            $params[] = (int) $user['id'];
            $sql = 'UPDATE usuarios SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $pdo->prepare($sql)->execute($params);
            $result['updated'] = true;
        }
        if ($resetPassword) {
            $result['reset'] = true;
        }
        $result['user_id'] = (int) $user['id'];
    } else {
        // Crear nuevo
        $hash = password_hash($dni, PASSWORD_BCRYPT);
        $st = $pdo->prepare(
            'INSERT INTO usuarios
                 (personal_id, username, password_hash, rol, activo, must_change)
             VALUES (?, ?, ?, "usuario", ?, 1)'
        );
        $st->execute([$personalId, $dni, $hash, $activoPersonal]);
        $result['created'] = true;
        $result['user_id'] = (int) $pdo->lastInsertId();
    }

    return $result;
}
