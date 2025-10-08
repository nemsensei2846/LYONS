<?php
require_once '../config/config.php';

// Iniciar sesión si no lo está
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Si ya está logueado, redirigir según tipo de usuario
if (estaLogueado()) {
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
            redireccionar(SITE_URL . '/auth/logout.php');
            break;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiarDatos($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        try {
            $pdo = conectarDB();
            $stmt = $pdo->prepare("SELECT id, nombre, apellido, email, password, tipo_usuario FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                if (password_verify($password, $usuario['password'])) {
                    // Guardar datos en sesión
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre'] = $usuario['nombre'];
                    $_SESSION['apellido'] = $usuario['apellido'];
                    $_SESSION['email'] = $usuario['email'];
                    $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];

                    mostrarMensaje('success', '¡Bienvenido ' . $usuario['nombre'] . '!');

                    // Redirigir según tipo de usuario
                    switch ($usuario['tipo_usuario']) {
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
                            redireccionar(SITE_URL . '/auth/logout.php');
                            break;
                    }
                } else {
                    $error = 'Contraseña incorrecta.';
                }
            } else {
                $error = 'No se encontró ningún usuario activo con este correo.';
            }
        } catch (PDOException $e) {
            if (DEV_MODE) {
                $error = 'Error de base de datos: ' . $e->getMessage();
            } else {
                $error = 'Error en el sistema. Intente más tarde.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/auth.css" rel="stylesheet">
    <link href="../assets/css/components.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <i class="fas fa-graduation-cap"></i>
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Accede a tu aula virtual</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Correo Electrónico
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Contraseña
                    </label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Iniciar Sesión
                </button>
            </form>

            <div class="auth-footer">
                <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
                <p><a href="forgot-password.php">¿Olvidaste tu contraseña?</a></p>
            </div>
        </div>
    </div>
</body>
</html>
