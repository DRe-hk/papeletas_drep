<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/usuarios_sync.php';
require_admin();

$u   = current_user();
$ok  = null;
$err = null;
$editId = (int) ($_GET['edit'] ?? 0);

$row = [
    'id' => 0, 'dni' => '', 'apellidos_nombres' => '', 'regimen' => '',
    'dependencia' => '', 'cargo' => '', 'activo' => 1,
];
if ($editId) {
    $st = DB::pdo()->prepare('SELECT * FROM personal WHERE id = ?');
    $st->execute([$editId]);
    $r = $st->fetch();
    if (!$r) { $err = 'Registro no encontrado.'; $editId = 0; }
    else $row = array_merge($row, $r);
}

$assocUser = null;
if ($editId) {
    $st = DB::pdo()->prepare('SELECT * FROM usuarios WHERE personal_id = ? LIMIT 1');
    $st->execute([$editId]);
    $assocUser = $st->fetch() ?: null;
    if (!$assocUser && preg_match('/^\d{8}$/', $row['dni'] ?? '')) {
        $st = DB::pdo()->prepare('SELECT * FROM usuarios WHERE username = ? LIMIT 1');
        $st->execute([$row['dni']]);
        $assocUser = $st->fetch() ?: null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset_password' && $editId && $assocUser) {
        $st = DB::pdo()->prepare('UPDATE usuarios SET password_hash = ?, must_change = 1 WHERE id = ?');
        $st->execute([password_hash($row['dni'], PASSWORD_BCRYPT), (int) $assocUser['id']]);
        $ok = 'Contrasena restablecida al DNI. El usuario debera cambiarla en su proximo ingreso.';
        $st = DB::pdo()->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([(int) $assocUser['id']]);
        $assocUser = $st->fetch();
    }
    elseif ($action === 'set_role' && $editId && $assocUser) {
        $newRole = $_POST['rol'] === 'admin' ? 'admin' : 'usuario';
        DB::pdo()->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')
                ->execute([$newRole, (int) $assocUser['id']]);
        $ok = 'Rol actualizado a "' . $newRole . '".';
        $st = DB::pdo()->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([(int) $assocUser['id']]);
        $assocUser = $st->fetch();
    }
    elseif ($action === 'toggle_active' && $editId && $assocUser) {
        $newActive = (int) $assocUser['activo'] ? 0 : 1;
        DB::pdo()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')
                ->execute([$newActive, (int) $assocUser['id']]);
        $ok = $newActive ? 'Usuario activado.' : 'Usuario desactivado.';
        $st = DB::pdo()->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([(int) $assocUser['id']]);
        $assocUser = $st->fetch();
    }
    elseif ($action === 'unlink_user' && $editId && $assocUser) {
        DB::pdo()->prepare('UPDATE usuarios SET personal_id = NULL WHERE id = ?')
                ->execute([(int) $assocUser['id']]);
        $ok = 'Vinculo con usuario eliminado. La cuenta sigue existiendo pero sin datos personales.';
        $assocUser = null;
    }
    elseif ($action === 'link_user' && $editId && !$assocUser && preg_match('/^\d{8}$/', $row['dni'] ?? '')) {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $r = sync_user_from_personal($pdo, $editId, $row['dni'], (int) $row['activo'], true);
            $pdo->commit();
            $ok = 'Usuario creado/vinculado (DNI/DNI, debe cambiar clave).';
            $st = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
            $st->execute([$r['user_id']]);
            $assocUser = $st->fetch();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $err = 'Error: ' . $e->getMessage();
        }
    }
    elseif ($action === 'save') {
        $id          = (int) ($_POST['id'] ?? 0);
        $dni         = trim($_POST['dni']              ?? '');
        $nomb        = trim($_POST['apellidos_nombres'] ?? '');
        $reg         = trim($_POST['regimen']          ?? '');
        $dep         = trim($_POST['dependencia']      ?? '');
        $cgo         = trim($_POST['cargo']            ?? '');
        $activo      = isset($_POST['activo']) ? 1 : 0;
        $resetPwd    = !empty($_POST['reset_password']);

        $row = [
            'id'                => $id,
            'dni'               => $dni,
            'apellidos_nombres' => $nomb,
            'regimen'           => $reg,
            'dependencia'       => $dep,
            'cargo'             => $cgo,
            'activo'            => $activo,
        ];

        if (!preg_match('/^\d{8}$/', $dni))              $err = 'El DNI debe tener exactamente 8 digitos.';
        elseif ($nomb === '' || strlen($nomb) < 5)       $err = 'Indique apellidos y nombres completos.';
        elseif ($reg === '')                             $err = 'Indique el regimen (D.L. 276, D.L. 728, CAS, etc.).';
        elseif ($dep === '')                             $err = 'Indique la dependencia.';
        elseif ($cgo === '')                             $err = 'Indique el cargo.';

        if (!$err) {
            $pdo = DB::pdo();
            $pdo->beginTransaction();
            try {
                if ($id) {
                    $pdo->prepare(
                        'UPDATE personal
                         SET apellidos_nombres=?, regimen=?, dependencia=?, cargo=?, activo=?
                         WHERE id=?'
                    )->execute([$nomb, $reg, $dep, $cgo, $activo, $id]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO personal (dni, apellidos_nombres, regimen, dependencia, cargo, activo)
                         VALUES (?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE
                           apellidos_nombres=VALUES(apellidos_nombres),
                           regimen=VALUES(regimen),
                           dependencia=VALUES(dependencia),
                           cargo=VALUES(cargo),
                           activo=VALUES(activo)'
                    )->execute([$dni, $nomb, $reg, $dep, $cgo, $activo]);
                    $st = $pdo->prepare('SELECT id FROM personal WHERE dni = ?');
                    $st->execute([$dni]);
                    $id = (int) $st->fetchColumn();
                    $row['id'] = $id;
                }

                $r = sync_user_from_personal($pdo, $id, $dni, $activo, $resetPwd);
                $pdo->commit();

                if ($r['created']) {
                    $ok = 'Personal guardado. Usuario creado: ' . $dni . ' (contrasena inicial = DNI, debe cambiarla al primer ingreso).';
                } else {
                    $msgs = ['Personal guardado.'];
                    if ($r['updated']) $msgs[] = 'Usuario sincronizado.';
                    if ($r['reset'])   $msgs[] = 'Contrasena restablecida al DNI.';
                    $ok = implode(' ', $msgs);
                }

                $editId = $id;
                $st = $pdo->prepare('SELECT * FROM personal WHERE id = ?');
                $st->execute([$id]);
                $row = array_merge($row, $st->fetch());
                $st = $pdo->prepare('SELECT * FROM usuarios WHERE personal_id = ? LIMIT 1');
                $st->execute([$id]);
                $assocUser = $st->fetch() ?: null;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $err = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

layout_header($editId ? 'Editar personal' : 'Nuevo personal');
render_flashes();
?>

<div class="page-header">
  <div>
    <h1>
      <i class="bi bi-<?= $editId ? 'pencil-square' : 'plus-circle' ?>"></i>
      <?= $editId ? 'Editar personal' : 'Nuevo personal' ?>
    </h1>
    <div class="page-sub">Complete los datos del trabajador. El usuario del sistema se crea automaticamente con DNI/DNI.</div>
  </div>
  <a href="<?= url('admin/personal.php') ?>" class="btn btn-secondary">
    <i class="bi bi-arrow-left"></i> Volver al listado
  </a>
</div>

<?php if ($ok): ?>
  <div class="alert alert-success">
    <i class="bi bi-check-circle-fill"></i>
    <div><?= e($ok) ?></div>
  </div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><?= e($err) ?></div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="<?= $editId && $assocUser ? 'col-lg-7' : 'col-12' ?>">
    <form method="post" class="form-section">
      <input type="hidden" name="_csrf"   value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action"  value="save">
      <input type="hidden" name="id"      value="<?= (int)($row['id'] ?? 0) ?>">

      <h6 class="form-section-title"><i class="bi bi-person-vcard"></i> Identidad</h6>
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label">DNI <span class="req">*</span></label>
          <input name="dni" class="form-control" required pattern="\d{8}" maxlength="8" inputmode="numeric" value="<?= e($row['dni'] ?? '') ?>" placeholder="8 digitos" <?= !empty($row['id']) ? 'readonly' : '' ?>>
          <div class="form-text">8 digitos exactos.</div>
        </div>
        <div class="col-md-9">
          <label class="form-label">Apellidos y Nombres <span class="req">*</span></label>
          <input name="apellidos_nombres" class="form-control" required maxlength="200" value="<?= e($row['apellidos_nombres'] ?? '') ?>" placeholder="Ej: PEREZ JUAN CARLOS">
        </div>
      </div>

      <h6 class="form-section-title"><i class="bi bi-briefcase"></i> Datos laborales</h6>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Regimen <span class="req">*</span></label>
          <input name="regimen" class="form-control" required maxlength="80" list="reg-names" value="<?= e($row['regimen'] ?? '') ?>" placeholder="D.L. 276">
          <datalist id="reg-names">
            <option value="D.L. 276">
            <option value="D.L. 728">
            <option value="D.L. 1057 - CAS">
            <option value="Ley 30001 - PRONOEI">
            <option value="FAG - Practicante">
            <option value="CAP - PNP">
            <option value="276">
            <option value="728">
            <option value="1057">
            <option value="CAS">
          </datalist>
          <div class="form-text">Codigo corto (276, 728, CAS) o descripcion completa (D.L. 276, etc.).</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Dependencia <span class="req">*</span></label>
          <input name="dependencia" class="form-control" required maxlength="150" value="<?= e($row['dependencia'] ?? '') ?>" placeholder="OFICINA DE ADMINISTRACION">
        </div>
        <div class="col-md-6">
          <label class="form-label">Cargo <span class="req">*</span></label>
          <input name="cargo" class="form-control" required maxlength="150" value="<?= e($row['cargo'] ?? '') ?>" placeholder="TECNICO ADMINISTRATIVO II">
        </div>
      </div>

      <h6 class="form-section-title mt-4"><i class="bi bi-shield-check"></i> Estado y cuenta</h6>
      <div class="row g-3">
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="activo" value="1" id="chk-activo" <?= !isset($row['activo']) || (int)$row['activo'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="chk-activo">
              Activo (desmarque para dar de baja sin eliminar el registro)
            </label>
          </div>
        </div>

        <?php if (!empty($row['id'])): ?>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="reset_password" value="1" id="chk-reset-pwd">
              <label class="form-check-label" for="chk-reset-pwd">
                <i class="bi bi-key"></i> Restablecer contrasena del usuario al DNI al guardar
                <small class="d-block text-muted">La cuenta volvera a tener DNI como contrasena y debera cambiarla en el proximo ingreso.</small>
              </label>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= url('admin/personal.php') ?>" class="btn btn-secondary">Cancelar</a>
        <button class="btn btn-primary">
          <i class="bi bi-save"></i> <?= $editId ? 'Actualizar' : 'Guardar' ?>
        </button>
      </div>
    </form>
  </div>

  <?php if ($editId): ?>
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-person-circle me-1"></i> Usuario asociado</span>
          <?php if ($assocUser): ?>
            <?php if ($assocUser['activo']): ?>
              <span class="badge text-bg-success">Activo</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Inactivo</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <div class="card-body">

          <?php if (!$assocUser): ?>
            <div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <div>Este personal no tiene un usuario del sistema vinculado.</div>
            </div>
            <form method="post">
              <input type="hidden" name="_csrf"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="link_user">
              <p class="small text-muted">Si fue importado en una version anterior del sistema, puede crear su cuenta aqui. La contrasena inicial sera el DNI.</p>
              <button class="btn btn-primary w-100">
                <i class="bi bi-person-plus"></i> Crear usuario (DNI/DNI)
              </button>
            </form>

          <?php else: ?>
            <dl class="row small mb-3">
              <dt class="col-sm-5 text-muted fw-normal">Usuario</dt>
              <dd class="col-sm-7"><code><?= e($assocUser['username']) ?></code></dd>
              <dt class="col-sm-5 text-muted fw-normal">Rol</dt>
              <dd class="col-sm-7">
                <span class="badge text-bg-<?= $assocUser['rol']==='admin'?'danger':'primary' ?>">
                  <?= e($assocUser['rol']) ?>
                </span>
              </dd>
              <dt class="col-sm-5 text-muted fw-normal">Contrasena</dt>
              <dd class="col-sm-7">
                <?php if ((int)$assocUser['must_change']): ?>
                  <span class="badge text-bg-warning">Pendiente primer cambio</span>
                <?php else: ?>
                  <span class="badge text-bg-success">Ya cambiada</span>
                <?php endif; ?>
              </dd>
              <dt class="col-sm-5 text-muted fw-normal">Ultimo acceso</dt>
              <dd class="col-sm-7">
                <small class="text-muted text-mono"><?= e($assocUser['last_login'] ?: 'Nunca') ?></small>
              </dd>
            </dl>

            <hr>

            <form method="post" class="mb-2">
              <input type="hidden" name="_csrf"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="set_role">
              <label class="form-label small text-muted">Cambiar rol</label>
              <div class="input-group">
                <select name="rol" class="form-select form-select-sm">
                  <option value="usuario" <?= $assocUser['rol']==='usuario'?'selected':'' ?>>usuario</option>
                  <option value="admin"  <?= $assocUser['rol']==='admin' ?'selected':'' ?>>admin</option>
                </select>
                <button class="btn btn-sm btn-primary">Aplicar</button>
              </div>
            </form>

            <form method="post" class="mb-2">
              <input type="hidden" name="_csrf"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_active">
              <button class="btn btn-sm btn-outline-<?= $assocUser['activo']?'warning':'success' ?> w-100">
                <i class="bi bi-<?= $assocUser['activo']?'pause':'play' ?>-circle"></i>
                <?= $assocUser['activo'] ? 'Desactivar usuario' : 'Activar usuario' ?>
              </button>
            </form>

            <form method="post" class="mb-2"
                  onsubmit="return confirm('Esto deja la contrasena del usuario en su DNI y le pedira cambiarla al proximo ingreso. Continuar?');">
              <input type="hidden" name="_csrf"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="reset_password">
              <button class="btn btn-sm btn-outline-primary w-100">
                <i class="bi bi-key"></i> Restablecer contrasena al DNI
              </button>
            </form>

            <hr>

            <form method="post"
                  onsubmit="return confirm('Quitar el vinculo entre este personal y su cuenta? La cuenta seguira existiendo pero sin autocompletar.');">
              <input type="hidden" name="_csrf"  value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="unlink_user">
              <button class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-link-45deg"></i> Desvincular usuario
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php layout_footer();
