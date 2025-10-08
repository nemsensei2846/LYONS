<?php
require_once '../config/config.php';

// Verificar si está logueado y es docente
if (!estaLogueado() || !esDocente()) {
    redireccionar('../auth/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = limpiarDatos($_POST['titulo']);
    $descripcion = limpiarDatos($_POST['descripcion']);
    $categoria = limpiarDatos($_POST['categoria']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    
    // Validaciones
    if (empty($titulo) || empty($descripcion)) {
        $error = 'El título y la descripción son obligatorios.';
    } elseif (!empty($fecha_inicio) && !empty($fecha_fin) && $fecha_inicio > $fecha_fin) {
        $error = 'La fecha de inicio no puede ser posterior a la fecha de fin.';
    } else {
        try {
            $pdo = conectarDB();
            
            // Generar código único para el curso
            do {
                $codigo_curso = 'CURSO-' . strtoupper(substr(uniqid(), -6));
                $stmt = $pdo->prepare("SELECT id FROM cursos WHERE codigo_curso = ?");
                $stmt->execute([$codigo_curso]);
            } while ($stmt->fetch());
            
            // Insertar curso
            $stmt = $pdo->prepare("
                INSERT INTO cursos (titulo, descripcion, codigo_curso, docente_id, fecha_inicio, fecha_fin, categoria) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $fecha_inicio_db = !empty($fecha_inicio) ? $fecha_inicio : null;
            $fecha_fin_db = !empty($fecha_fin) ? $fecha_fin : null;
            
            if ($stmt->execute([$titulo, $descripcion, $codigo_curso, $_SESSION['usuario_id'], $fecha_inicio_db, $fecha_fin_db, $categoria])) {
                $curso_id = $pdo->lastInsertId();
                mostrarMensaje('success', 'Curso creado exitosamente. Código: ' . $codigo_curso);
                redireccionar('ver-curso.php?id=' . $curso_id);
            } else {
                $error = 'Error al crear el curso. Intente más tarde.';
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
    <title>Crear Curso - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/forms.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-graduation-cap"></i>
                <h2>Aula Virtual</h2>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?></h3>
                    <span class="user-role">Docente</span>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="../dashboard/docente.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="mis-cursos.php">
                        <i class="fas fa-book"></i>
                        Mis Cursos
                    </a>
                </li>
                <li class="active">
                    <a href="crear-curso.php">
                        <i class="fas fa-plus-circle"></i>
                        Crear Curso
                    </a>
                </li>
                <li>
                    <a href="../clases/gestionar-clases.php">
                        <i class="fas fa-chalkboard-teacher"></i>
                        Gestionar Clases
                    </a>
                </li>
                <li>
                    <a href="../estudiantes/mis-estudiantes.php">
                        <i class="fas fa-users"></i>
                        Mis Estudiantes
                    </a>
                </li>
                <li>
                    <a href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        Cerrar Sesión
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1>Crear Nuevo Curso</h1>
                <div class="header-actions">
                    <a href="mis-cursos.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Volver a Mis Cursos
                    </a>
                </div>
            </header>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <i class="fas fa-plus-circle"></i>
                        <h2>Información del Curso</h2>
                        <p>Complete los datos para crear su nuevo curso</p>
                    </div>
                    
                    <form method="POST" class="course-form">
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="titulo">
                                    <i class="fas fa-book"></i>
                                    Título del Curso *
                                </label>
                                <input type="text" id="titulo" name="titulo" required 
                                       placeholder="Ej: Introducción a la Programación"
                                       value="<?php echo isset($_POST['titulo']) ? htmlspecialchars($_POST['titulo']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="categoria">
                                    <i class="fas fa-tags"></i>
                                    Categoría
                                </label>
                                <select id="categoria" name="categoria">
                                    <option value="">Seleccionar categoría</option>
                                    <option value="Programación" <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === 'Programación') ? 'selected' : ''; ?>>Programación</option>
                                    <option value="Matemáticas" <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === 'Matemáticas') ? 'selected' : ''; ?>>Matemáticas</option>
                                    <option value="Ciencias" <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === 'Ciencias') ? 'selected' : ''; ?>>Ciencias</option>
                                    <option value="Idiomas" <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === 'Idiomas') ? 'selected' : ''; ?>>Idiomas</option>
                                    <option value="Arte y Diseño" <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === 'Arte y Diseño') ? 'selected' : ''; ?>>Arte y Diseño</option>
                                    <option value="Negocios" <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === 'Negocios') ? 'selected' : ''; ?>>Negocios</option>
                                    <option value="Ingeniería" <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === 'Ingeniería') ? 'selected' : ''; ?>>Ingeniería</option>
                                    <option value="Otros" <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === 'Otros') ? 'selected' : ''; ?>>Otros</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="descripcion">
                                    <i class="fas fa-align-left"></i>
                                    Descripción del Curso *
                                </label>
                                <textarea id="descripcion" name="descripcion" rows="6" required 
                                          placeholder="Describe el contenido, objetivos y metodología del curso..."><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fecha_inicio">
                                    <i class="fas fa-calendar-alt"></i>
                                    Fecha de Inicio
                                </label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?php echo isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : ''; ?>">
                                <small>Opcional: Fecha en que comenzará el curso</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="fecha_fin">
                                    <i class="fas fa-calendar-check"></i>
                                    Fecha de Finalización
                                </label>
                                <input type="date" id="fecha_fin" name="fecha_fin" 
                                       value="<?php echo isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : ''; ?>">
                                <small>Opcional: Fecha estimada de finalización</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline" onclick="history.back()">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Crear Curso
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-lightbulb"></i> Consejos para crear un buen curso</h3>
                    <ul>
                        <li><strong>Título claro:</strong> Use un título descriptivo que indique claramente el tema del curso.</li>
                        <li><strong>Descripción detallada:</strong> Explique qué aprenderán los estudiantes y cómo está estructurado el curso.</li>
                        <li><strong>Objetivos específicos:</strong> Defina metas claras y alcanzables para sus estudiantes.</li>
                        <li><strong>Contenido organizado:</strong> Estructure el material de forma lógica y progresiva.</li>
                        <li><strong>Recursos variados:</strong> Incluya diferentes tipos de materiales (videos, textos, ejercicios).</li>
                    </ul>
                    
                    <div class="next-steps">
                        <h4><i class="fas fa-route"></i> Próximos pasos</h4>
                        <p>Después de crear el curso podrá:</p>
                        <ul>
                            <li>Añadir clases y lecciones</li>
                            <li>Subir materiales educativos</li>
                            <li>Configurar evaluaciones</li>
                            <li>Invitar estudiantes</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
