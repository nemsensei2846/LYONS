<?php
require_once '../config/config.php';

// Iniciar sesión si no lo está
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verificar si hay usuario logueado
if (estaLogueado()) {
    // Redirigir según tipo de usuario
    switch ($_SESSION['tipo_usuario']) {
        case 'docente':
            redireccionar(SITE_URL . '/dashboard/docente.php');
            break;
        case 'estudiante':
            redireccionar(SITE_URL . '/dashboard/estudiante.php');
            break;
        case 'admin':
            redireccionar(SITE_URL . '/dashboard/admin.php');
            break;
        default:
            // Tipo desconocido, cerrar sesión
            redireccionar(SITE_URL . '/auth/logout.php');
            break;
    }
} else {
    // Si no hay sesión activa, ir al login
    redireccionar(SITE_URL . '/auth/login.php');
}
?>
