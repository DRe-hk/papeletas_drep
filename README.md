# Sistema de Papeletas de Salida - DRE Puno

Sistema intranet en PHP para generar papeletas de salida a partir de una plantilla PDF oficial,
con datos de una base de datos y numeración correlativa anual automática.

## Características

- **Login con usuario y contraseña** (sesiones PHP, hash bcrypt).
- **Autollenado** desde la tabla `personal` (DNI, apellidos, régimen, dependencia, cargo).
- **Numeración correlativa anual** `0001-2026`, `0002-2026`... (con control transaccional).
- **Generación de PDF** rellenando la plantilla original (no se redibuja).
- **Gestor de usuarios** (admin crea usuarios, vincula a personal, resetea contraseñas).
- **Importación masiva de personal** desde CSV.
- **Auditoría**: cada papeleta emitida queda registrada en la BD.
- **Sin necesidad de HTTPS** en LAN (HTTP plano, sesiones con cookies HttpOnly).
- **PDF generado al vuelo**, no se guarda en disco (todo en la BD).

## Stack

- PHP 8.5 (probado en 8.5.0)
- MariaDB / MySQL 8
- Apache (Laragon)
- FPDF + FPDI (setasign) para el PDF
- Bootstrap 5 (CDN) para la UI

## Estructura

```
\PAPELETAS\
├── public\                  ← DocumentRoot del VirtualHost
│   ├── index.php            Login
│   ├── dashboard.php        Inicio
│   ├── papeleta_nueva.php   Formulario
│   ├── papeleta_descargar.php  Genera y descarga PDF
│   ├── perfil.php           Cambiar contraseña
│   ├── admin\
│   │   ├── usuarios.php     CRUD usuarios
│   │   ├── personal.php     Listar personal
│   │   ├── importar_personal.php  CSV → BD
│   │   ├── importar_personal_plantilla.php  Descarga CSV ejemplo
│   │   └── papeletas.php    Todas las papeletas
│   └── .htaccess            Bloquea acceso fuera de PHP
├── app\
│   ├── db.php               Conexión PDO singleton
│   ├── auth.php             Sesiones, login, require_admin
│   ├── helpers.php          e(), url(), csrf, layout
│   ├── numeracion.php       Siguiente correlativo anual (FOR UPDATE)
│   └── pdf.php              Clase PapeletaPDF con coordenadas
├── sql\
│   ├── schema.sql           DDL
│   └── personal_ejemplo.csv Plantilla CSV
├── storage\
│   └── plantilla\
│       └── Papeleta-de-salida-2026.pdf
├── tools\
│   └── create_admin.php     Crea/resetea admin (CLI)
├── vendor\                  Dependencias Composer
├── config.php               Credenciales BD y rutas
├── composer.json
└── README.md
```

## Instalación

### 1. Pre-requisitos
- Laragon (Apache + MySQL/MariaDB) instalado.
- PHP 8.1 o superior con extensión `pdo_mysql`.
- Composer.

### 2. Clonar/copiar el proyecto
```cmd
cd C:\laragon\www\
git clone https://github.com/DRe-hk/papeletas_drep
cd papeletas_drep
composer install
```

### 3. Crear la base de datos
Abrir HeidiSQL / phpMyAdmin / MySQL CLI y ejecutar `sql/schema.sql`:
```cmd
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root < sql\schema.sql
```

### 4. Ajustar credenciales
Editar `config.php`:
```php
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'papeletas_db';
const DB_USER = 'root';
const DB_PASS = '';          // vacío por defecto en Laragon
```

### 5. Crear el primer administrador
```cmd
cd C:\laragon\www\papeletas_drep
php tools\create_admin.php admin admin123
```
- Usuario: `admin`
- Contraseña: `admin123` (cambiar en el primer ingreso).

### 6. Importar personal
1. Iniciar sesión como `admin`.
2. Ir a **Administración → Personal → Importar personal (CSV)**.
3. Descargar la plantilla (`Importar personal (CSV) → Descargar plantilla`).
4. Llenar con los datos de los trabajadores. Columnas requeridas:
   ```
   dni,apellidos_nombres,regimen_laboral,regimen,dependencia,cargo
   12345678,PEREZ JUAN,276,D.L. 276,OFICINA DE ADMINISTRACION,TECNICO
   ```
5. Subir el archivo. El sistema inserta nuevos o actualiza existentes (por DNI).

### 6. Crear usuarios (automático)

**Ya no es necesario crear usuarios manualmente.** Cada personal tiene su usuario
del sistema automáticamente:

- **Al guardar o importar un personal** → se crea automáticamente un usuario con
  `username = DNI` y `password = DNI` (ambos son el DNI de 8 dígitos).
- En su **primer ingreso**, el sistema detecta `must_change=1` y redirige a
  `/perfil.php` para que el usuario cambie su contraseña.
- En `Administración → Personal y usuarios` el admin ve para cada persona:
  - Si tiene usuario vinculado
  - Su rol (admin / usuario)
  - Si ya cambió la contraseña o está pendiente
  - Acciones inline: cambiar rol, activar/desactivar, restablecer contraseña
- Botón **"⚡ Sincronizar usuarios (N)"** en el listado crea en bloque los
  usuarios faltantes (los que quedaron sin cuenta por cargas previas).

**Para resetear la contraseña de alguien:**
- Opción A: editar el personal → sección "Usuario asociado" → botón
  "🔑 Restablecer contraseña al DNI".
- Opción B: en el formulario de edición, marcar el checkbox
  "Restablecer contraseña del usuario al DNI al guardar" + Guardar.

**Para personal cargado ANTES de este cambio** (sin usuario):
- Botón "⚡ Sincronizar usuarios (N)" en el listado, o CLI:
  ```cmd
  php tools\sync_all_users.php
  ```
  Crea los usuarios faltantes. Soporta `--dry-run` y `--reset`.

## Acceso por LAN (intranet)

El sistema está pensado para correr en una PC servidor dentro de la red local,
accediéndose desde otras PCs por la IP.

### 7.1 IP fija en el servidor
Panel de control → Red → Adaptador → Propiedades IPv4:
- IP: `192.168.1.100` (la que prefieras, fuera del rango DHCP)
- Máscara: `255.255.255.0`
- Puerta de enlace: `192.168.1.1`

### 7.2 VirtualHost en Laragon
Crear archivo `C:\laragon\etc\apache2\sites-enabled\auto.papeletas.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/laragon/www/papeletas_drep/public"
    ServerName papeletas.local
    ServerAlias 192.168.1.100
    <Directory "C:/laragon/www/papeletas_drep/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Reiniciar Apache desde el panel de Laragon.

### 7.3 Abrir el puerto 80 en el cortafuegos (¡crítico!)
La primera vez **bloquea todo el acceso desde la LAN**. Hay que abrir el puerto:

**Opción A — GUI:**
- Win+R → `wf.msc`
- Reglas de entrada → Nueva regla → Puerto → TCP 80 → Permitir → Nombre: "Papeletas HTTP"

**Opción B — PowerShell (admin):**
```powershell
New-NetFirewallRule -DisplayName "Papeletas HTTP" -Direction Inbound -LocalPort 80 -Protocol TCP -Action Allow
```

### 7.4 Acceso desde los clientes
- En el navegador de cada PC: `http://192.168.1.100`
- Para evitar escribir la IP, en cada PC editar
  `C:\Windows\System32\drivers\etc\hosts` y agregar:
  ```
  192.168.1.100   papeletas.local
  ```
  Luego entrar con `http://papeletas.local`

## Uso diario

1. Usuario accede a `http://192.168.1.100` y entra con **DNI / DNI** (su contraseña inicial).
2. El sistema le pide cambiar la contraseña (forzado en primer ingreso).
3. Click en **Nueva papeleta**.
4. El sistema autollena DNI, Apellidos, Régimen, Dependencia y Cargo desde `personal`.
5. El usuario llena: motivo, fundamentación, lugar, día/mes/año, horas, observaciones.
6. Click en **Generar papeleta** → se asigna correlativo → se descarga el PDF.
7. El PDF queda registrado en la BD (auditoría).
8. El usuario imprime el PDF, lo firma y lo presenta en RRHH.

## Numeración

- Formato: `0001-2026`, `0002-2026`...
- Correlativo único por año, sin reinicio.
- Asignación **transaccional** (`SELECT ... FOR UPDATE`) — soporta peticiones concurrentes.
- Imposible tener dos papeletas con el mismo número.

## Tablas

| Tabla | Propósito |
|---|---|
| `personal` | Datos de los trabajadores (origen del autollenado). |
| `usuarios` | Credenciales de acceso al sistema. |
| `papeletas` | Log de papeletas emitidas (auditoría). |

## Seguridad

- Contraseñas con `password_hash` (bcrypt).
- Sesiones con cookies `HttpOnly`, `SameSite` (configurable), 30 min de inactividad.
- Tokens CSRF en todos los formularios POST.
- Consultas con PDO preparado (sin SQL injection).
- Roles `admin` / `usuario` (el admin no puede eliminarse a sí mismo).
- Auditoría: cada papeleta guarda `usuario_id`, `personal_id` y `fecha_emision`.
- Los directorios `app/`, `sql/`, `storage/`, `tools/` tienen `.htaccess` que deniega acceso web.

## Ajuste fino de coordenadas del PDF

Si los campos no se alinean exactamente con la plantilla, edita el array `POS` en `app/pdf.php`.
Las claves son los nombres de los campos y los valores son `['x' => mm_desde_izq, 'y' => mm_desde_arriba, 'size' => pt]`.

Para volver a calibrar:
1. Abre el PDF plantilla en un visor y mide con regla.
2. Ajusta los valores.
3. Regenera una papeleta de prueba desde el sistema.

## Respaldo

La BD es la única fuente de verdad. Respaldar:
```cmd
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe" -u root papeletas_db > backup_%date%.sql
```
Programar con el programador de tareas de Windows.

## Problemas conocidos / cosas por mejorar

1. **Coordenadas PDF**: ajustadas pero pueden requerir 5-10 minutos de tuning fino
   dependiendo de la versión exacta de la plantilla. Ver sección "Ajuste fino".
2. **Firma digital del PDF**: no implementada. Si la necesitan, hay que migrar a
   `setasign/tcpdf` o usar una librería de firma externa.
3. **Aprobación por jefe**: el sistema actual entrega la papeleta final directo al usuario.
   Si después quieren flujo de aprobación, se agrega una columna `estado` y un rol `jefe`.
4. **HTTPS**: para una intranet pequeña con HTTP en LAN es aceptable. Si se requiere
   cifrado, Laragon puede activar SSL con un click (certificado autofirmado).

## Licencia

Uso interno DRE Puno.
