<?php
require_once '../config/config.php';

// Si ya está logueado, redirigir al dashboard
if (estaLogueado()) {
    redireccionar('Aula/dashboard/index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $apellido = limpiarDatos($_POST['apellido']);
    $email = limpiarDatos($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $tipo_usuario = limpiarDatos($_POST['tipo_usuario']);
    $telefono = limpiarDatos($_POST['telefono']);
    
    // Validaciones
    if (empty($nombre) || empty($apellido) || empty($email) || empty($password) || empty($tipo_usuario)) {
        $error = 'Por favor, complete todos los campos obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo electrónico no es válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!in_array($tipo_usuario, ['docente', 'estudiante'])) {
        $error = 'Tipo de usuario no válido.';
    } else {
        try {
            $pdo = conectarDB();
            
            // Verificar si el email ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Este correo electrónico ya está registrado.';
            } else {
                // Crear nuevo usuario
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, password, tipo_usuario, telefono) VALUES (?, ?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$nombre, $apellido, $email, $password_hash, $tipo_usuario, $telefono])) {
                    // Registro exitoso, redirigir a login con mensaje de éxito
                    mostrarMensaje('success', 'Registro exitoso. Ya puedes iniciar sesión con tu correo y contraseña.');
                    redireccionar('login.php');
                } else {
                    $error = 'Error al crear la cuenta. Intente más tarde.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error en el sistema. Intente más tarde.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/auth.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card register-card">
            <div class="auth-header">
                <i class="fas fa-user-plus"></i>
                <h1>Crear Cuenta</h1>
                <p>Únete a nuestra plataforma educativa</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre">
                            <i class="fas fa-user"></i>
                            Nombre *
                        </label>
                        <input type="text" id="nombre" name="nombre" required 
                               value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="apellido">
                            <i class="fas fa-user"></i>
                            Apellido *
                        </label>
                        <input type="text" id="apellido" name="apellido" required 
                               value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Correo Electrónico *
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="telefono">
                        <i class="fas fa-phone"></i>
                        Teléfono
                    </label>
                    <input type="tel" id="telefono" name="telefono" 
                           value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="tipo_usuario">
                        <i class="fas fa-users"></i>
                        Tipo de Usuario *
                    </label>
                    <select id="tipo_usuario" name="tipo_usuario" required>
                        <option value="">Seleccione una opción</option>
                        <option value="estudiante" <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'estudiante') ? 'selected' : ''; ?>>
                            Estudiante
                        </option>
                        <option value="docente" <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'docente') ? 'selected' : ''; ?>>
                            Docente
                        </option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Contraseña *
                        </label>
                        <input type="password" id="password" name="password" required minlength="6">
                        <small>Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i>
                            Confirmar Contraseña *
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Crear Cuenta
                </button>
            </form>
            
            <div class="auth-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            </div>
        </div>
        
        <div class="auth-info">
            <h2>¿Por qué elegir nuestra plataforma?</h2>
            <div class="features">
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Seguro y Confiable</h3>
                    <p>Tus datos están protegidos con los más altos estándares de seguridad</p>
                </div>
                <div class="feature">
                    <i class="fas fa-clock"></i>
                    <h3>Disponible 24/7</h3>
                    <p>Accede a tus cursos en cualquier momento y desde cualquier lugar</p>
                </div>
                <div class="feature">
                    <i class="fas fa-certificate"></i>
                    <h3>Certificaciones</h3>
                    <p>Obtén certificados al completar tus cursos exitosamente</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Validación en tiempo real de contraseñas
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
