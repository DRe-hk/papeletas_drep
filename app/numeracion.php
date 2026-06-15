<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Genera el siguiente número correlativo anual de forma transaccional.
 * Devuelve un array con: numero (formato 0001-2026), anio, correlativo.
 */
function siguiente_numero(PDO $pdo): array
{
    $anio = (int) date('Y');

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'SELECT MAX(correlativo) AS maximo FROM papeletas WHERE anio = ? FOR UPDATE'
        );
        $st->execute([$anio]);
        $row = $st->fetch();
        $corr = ((int) ($row['maximo'] ?? 0)) + 1;

        $numero = str_pad((string) $corr, 4, '0', STR_PAD_LEFT) . '-' . $anio;
        $pdo->commit();
        return ['numero' => $numero, 'anio' => $anio, 'correlativo' => $corr];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
