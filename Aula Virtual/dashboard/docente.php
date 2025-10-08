<?php
require_once '../config/config.php';

// Verificar si está logueado y es docente
if (!estaLogueado() || !esDocente()) {
    redireccionar('../auth/login.php');
}

try {
    $pdo = conectarDB();
    
    // Obtener estadísticas del docente
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_cursos FROM cursos WHERE docente_id = ? AND activo = 1");
    $stmt->execute([$_SESSION['usuario_id']]);
    $stats_cursos = $stmt->fetch();
    
    // Obtener total de estudiantes inscritos en sus cursos
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT i.estudiante_id) as total_estudiantes 
        FROM inscripciones i 
        INNER JOIN cursos c ON i.curso_id = c.id 
        WHERE c.docente_id = ? AND i.estado = 'activo'
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $stats_estudiantes = $stmt->fetch();
    
    // Obtener cursos recientes del docente
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(i.id) as total_inscritos 
        FROM cursos c 
        LEFT JOIN inscripciones i ON c.id = i.curso_id AND i.estado = 'activo'
        WHERE c.docente_id = ? AND c.activo = 1 
        GROUP BY c.id 
        ORDER BY c.fecha_creacion DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $cursos_recientes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error al cargar los datos.';
}

$mensaje = obtenerMensaje();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Docente - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
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
                <li class="active">
                    <a href="docente.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="../cursos/mis-cursos.php">
                        <i class="fas fa-book"></i>
                        Mis Cursos
                    </a>
                </li>
                <li>
                    <a href="../cursos/crear-curso.php">
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
                    <a href="../materiales/gestionar-materiales.php">
                        <i class="fas fa-folder-open"></i>
                        Materiales
                    </a>
                </li>
                <li>
                    <a href="../perfil/perfil.php">
                        <i class="fas fa-user-cog"></i>
                        Mi Perfil
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
                <h1>Dashboard Docente</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="location.href='../cursos/crear-curso.php'">
                        <i class="fas fa-plus"></i>
                        Crear Nuevo Curso
                    </button>
                </div>
            </header>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $mensaje['tipo']; ?>">
                    <i class="fas fa-<?php echo $mensaje['tipo'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $mensaje['texto']; ?>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_cursos['total_cursos']; ?></h3>
                        <p>Cursos Creados</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_estudiantes['total_estudiantes']; ?></h3>
                        <p>Estudiantes Inscritos</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-info">
                        <h3>0</h3>
                        <p>Clases Activas</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3>4.8</h3>
                        <p>Calificación Promedio</p>
                    </div>
                </div>
            </div>
            
            <!-- Cursos Recientes -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Mis Cursos Recientes</h2>
                    <a href="../cursos/mis-cursos.php" class="btn btn-outline">Ver Todos</a>
                </div>
                
                <?php if (empty($cursos_recientes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>No tienes cursos creados</h3>
                        <p>Comienza creando tu primer curso para compartir conocimiento con tus estudiantes.</p>
                        <a href="../cursos/crear-curso.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Crear Mi Primer Curso
                        </a>
                    </div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php foreach ($cursos_recientes as $curso): ?>
                            <div class="course-card">
                                <div class="course-image">
                                    <?php if ($curso['imagen']): ?>
                                        <img src="../<?php echo htmlspecialchars($curso['imagen']); ?>" alt="<?php echo htmlspecialchars($curso['titulo']); ?>">
                                    <?php else: ?>
                                        <div class="course-placeholder">
                                            <i class="fas fa-book"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="course-content">
                                    <h3><?php echo htmlspecialchars($curso['titulo']); ?></h3>
                                    <p class="course-code">Código: <?php echo htmlspecialchars($curso['codigo_curso']); ?></p>
                                    <p class="course-description"><?php echo htmlspecialchars(substr($curso['descripcion'], 0, 100)) . '...'; ?></p>
                                    
                                    <div class="course-stats">
                                        <span><i class="fas fa-users"></i> <?php echo $curso['total_inscritos']; ?> estudiantes</span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($curso['fecha_creacion'])); ?></span>
                                    </div>
                                    
                                    <div class="course-actions">
                                        <a href="../cursos/ver-curso.php?id=<?php echo $curso['id']; ?>" class="btn btn-sm btn-outline">Ver Curso</a>
                                        <a href="../cursos/editar-curso.php?id=<?php echo $curso['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Acciones Rápidas -->
            <div class="content-section">
                <h2>Acciones Rápidas</h2>
                <div class="quick-actions">
                    <a href="../cursos/crear-curso.php" class="quick-action">
                        <i class="fas fa-plus-circle"></i>
                        <span>Crear Curso</span>
                    </a>
                    <a href="../clases/crear-clase.php" class="quick-action">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Añadir Clase</span>
                    </a>
                    <a href="../materiales/subir-material.php" class="quick-action">
                        <i class="fas fa-upload"></i>
                        <span>Subir Material</span>
                    </a>
                    <a href="../estudiantes/mis-estudiantes.php" class="quick-action">
                        <i class="fas fa-users"></i>
                        <span>Ver Estudiantes</span>
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
