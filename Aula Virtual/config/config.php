<?php
// ======================================
// CONFIGURACIÓN GLOBAL - AULA VIRTUAL PROFESIONAL
// ======================================

// --- Base de datos ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'aula_virtual');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Sitio ---
define('SITE_NAME', 'Aula Virtual Profesional');
define('SITE_URL', 'http://localhost/aula_virtual');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// --- Zona horaria ---
date_default_timezone_set('America/Mexico_City');

// --- Sesiones ---
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
session_name('AULA_VIRTUAL_SESION');
session_start();

// --- Modo desarrollo ---
define('DEV_MODE', true);

// Medición de rendimiento
$__tiempo_inicio = microtime(true);
$__memoria_inicio = memory_get_usage();

// Mostrar errores solo en desarrollo
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ======================================
// FUNCIÓN DE CONEXIÓN A LA BASE DE DATOS
// ======================================
function conectarDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            if (DEV_MODE) {
                echo "<div style='
                    background:#e6ffed;color:#155724;padding:10px;border-radius:8px;margin:10px;
                    border:1px solid #c3e6cb;text-align:center;font-family:Arial, sans-serif;
                '>✅ Conexión a la base de datos establecida correctamente</div>";
            }

        } catch (PDOException $e) {
            if (DEV_MODE) {
                echo "<div style='
                    background:#f8d7da;color:#721c24;padding:10px;border-radius:8px;margin:10px;
                    border:1px solid #f5c6cb;text-align:center;font-family:Arial, sans-serif;
                '>❌ Error de conexión a la base de datos:<br><b>" . htmlspecialchars($e->getMessage()) . "</b></div>";
            } else {
                die("Error interno del servidor. Intenta más tarde.");
            }
        }
    }
    return $pdo;
}

// ======================================
// FUNCIONES DE SEGURIDAD
// ======================================
function limpiarDatos($data) {
    if (is_array($data)) return array_map('limpiarDatos', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $_POST = limpiarDatos($_POST);
}

// Crear carpeta de uploads si no existe
if (!file_exists(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0775, true);

// ======================================
// FUNCIONES DE SESIÓN Y USUARIO
// ======================================
function estaLogueado() {
    return isset($_SESSION['usuario_id']);
}

function esDocente() {
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'docente';
}

function esEstudiante() {
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'estudiante';
}

function esAdmin() {
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'admin';
}

// ======================================
// UTILIDADES
// ======================================
function redireccionar($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo "<script>window.location.href='" . $url . "';</script>";
        exit();
    }
}

function mostrarMensaje($tipo, $mensaje) {
    $_SESSION['mensaje'] = ['tipo' => $tipo, 'texto' => $mensaje, 'tiempo' => time()];
}

function obtenerMensaje() {
    if (isset($_SESSION['mensaje'])) {
        $msg = $_SESSION['mensaje'];
        unset($_SESSION['mensaje']);
        return $msg;
    }
    return null;
}

// ======================================
// PANEL DE DEPURACIÓN
// ======================================
function mostrarPanelDepuracion($inicio_tiempo, $inicio_memoria) {
    if (!DEV_MODE) return;

    $usuario = $_SESSION['nombre'] ?? 'No conectado';
    $tipo = $_SESSION['tipo_usuario'] ?? 'Desconocido';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $php = PHP_VERSION;
    $tiempo = round((microtime(true) - $inicio_tiempo) * 1000, 2);
    $memoria_usada = round((memory_get_usage() - $inicio_memoria) / 1024, 2);

    echo "<div style='position: fixed; bottom:10px; right:10px;
        background: rgba(0,0,0,0.85); color:#00ff95; padding:12px 15px;
        border-radius:10px; font-family: monospace; font-size:13px;
        z-index:9999; box-shadow:0 0 10px #00ff95; line-height:1.4;'>
        <strong>🔍 MODO DESARROLLO ACTIVO</strong><br>
        👤 Usuario: $usuario<br>
        🧩 Rol: $tipo<br>
        🌐 IP: $ip<br>
        ⚙️ PHP: $php<br>
        💾 DB: " . DB_NAME . "<br>
        ⏱️ Tiempo carga: {$tiempo} ms<br>
        📊 Memoria usada: {$memoria_usada} KB
    </div>";
}

// ======================================
// PRUEBA AUTOMÁTICA EN DEV
// ======================================
if (DEV_MODE) {
    conectarDB();
    register_shutdown_function('mostrarPanelDepuracion', $__tiempo_inicio, $__memoria_inicio);
}
?>
