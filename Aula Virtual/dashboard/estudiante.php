<?php
require_once '../config/config.php';

// Verificar si está logueado y es estudiante
if (!estaLogueado() || !esEstudiante()) {
    redireccionar('../auth/login.php');
}

try {
    $pdo = conectarDB();
    
    // Obtener estadísticas del estudiante
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_cursos FROM inscripciones WHERE estudiante_id = ? AND estado = 'activo'");
    $stmt->execute([$_SESSION['usuario_id']]);
    $stats_cursos = $stmt->fetch();
    
    // Obtener cursos completados
    $stmt = $pdo->prepare("SELECT COUNT(*) as cursos_completados FROM inscripciones WHERE estudiante_id = ? AND estado = 'completado'");
    $stmt->execute([$_SESSION['usuario_id']]);
    $stats_completados = $stmt->fetch();
    
    // Obtener progreso promedio
    $stmt = $pdo->prepare("SELECT AVG(progreso) as progreso_promedio FROM inscripciones WHERE estudiante_id = ? AND estado = 'activo'");
    $stmt->execute([$_SESSION['usuario_id']]);
    $stats_progreso = $stmt->fetch();
    
    // Obtener cursos inscritos recientes
    $stmt = $pdo->prepare("
        SELECT c.*, i.fecha_inscripcion, i.progreso, u.nombre as docente_nombre, u.apellido as docente_apellido
        FROM inscripciones i 
        INNER JOIN cursos c ON i.curso_id = c.id 
        INNER JOIN usuarios u ON c.docente_id = u.id
        WHERE i.estudiante_id = ? AND i.estado = 'activo' AND c.activo = 1
        ORDER BY i.fecha_inscripcion DESC 
        LIMIT 6
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $mis_cursos = $stmt->fetchAll();
    
    // Obtener cursos disponibles para inscribirse
    $stmt = $pdo->prepare("
        SELECT c.*, u.nombre as docente_nombre, u.apellido as docente_apellido,
               COUNT(i.id) as total_inscritos
        FROM cursos c 
        INNER JOIN usuarios u ON c.docente_id = u.id
        LEFT JOIN inscripciones i ON c.id = i.curso_id AND i.estado = 'activo'
        WHERE c.activo = 1 
        AND c.id NOT IN (
            SELECT curso_id FROM inscripciones 
            WHERE estudiante_id = ? AND estado IN ('activo', 'completado')
        )
        GROUP BY c.id
        ORDER BY c.fecha_creacion DESC 
        LIMIT 4
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $cursos_disponibles = $stmt->fetchAll();
    
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
    <title>Dashboard Estudiante - <?php echo SITE_NAME; ?></title>
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
                    <span class="user-role">Estudiante</span>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li class="active">
                    <a href="estudiante.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="../cursos/mis-inscripciones.php">
                        <i class="fas fa-book"></i>
                        Mis Cursos
                    </a>
                </li>
                <li>
                    <a href="../cursos/explorar-cursos.php">
                        <i class="fas fa-search"></i>
                        Explorar Cursos
                    </a>
                </li>
                <li>
                    <a href="../progreso/mi-progreso.php">
                        <i class="fas fa-chart-line"></i>
                        Mi Progreso
                    </a>
                </li>
                <li>
                    <a href="../foros/mis-foros.php">
                        <i class="fas fa-comments"></i>
                        Foros
                    </a>
                </li>
                <li>
                    <a href="../certificados/mis-certificados.php">
                        <i class="fas fa-certificate"></i>
                        Certificados
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
                <h1>Mi Dashboard</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="location.href='../cursos/explorar-cursos.php'">
                        <i class="fas fa-search"></i>
                        Explorar Cursos
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
                        <p>Cursos Inscritos</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_completados['cursos_completados']; ?></h3>
                        <p>Cursos Completados</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo round($stats_progreso['progreso_promedio'] ?? 0, 1); ?>%</h3>
                        <p>Progreso Promedio</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_completados['cursos_completados']; ?></h3>
                        <p>Certificados Obtenidos</p>
                    </div>
                </div>
            </div>
            
            <!-- Mis Cursos -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Mis Cursos</h2>
                    <a href="../cursos/mis-inscripciones.php" class="btn btn-outline">Ver Todos</a>
                </div>
                
                <?php if (empty($mis_cursos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>No estás inscrito en ningún curso</h3>
                        <p>Explora nuestro catálogo de cursos y comienza tu aprendizaje hoy mismo.</p>
                        <a href="../cursos/explorar-cursos.php" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Explorar Cursos
                        </a>
                    </div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php foreach ($mis_cursos as $curso): ?>
                            <div class="course-card student-course">
                                <div class="course-image">
                                    <?php if ($curso['imagen']): ?>
                                        <img src="../<?php echo htmlspecialchars($curso['imagen']); ?>" alt="<?php echo htmlspecialchars($curso['titulo']); ?>">
                                    <?php else: ?>
                                        <div class="course-placeholder">
                                            <i class="fas fa-book"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="course-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $curso['progreso']; ?>%"></div>
                                        </div>
                                        <span><?php echo round($curso['progreso'], 1); ?>%</span>
                                    </div>
                                </div>
                                
                                <div class="course-content">
                                    <h3><?php echo htmlspecialchars($curso['titulo']); ?></h3>
                                    <p class="course-instructor">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($curso['docente_nombre'] . ' ' . $curso['docente_apellido']); ?>
                                    </p>
                                    <p class="course-description"><?php echo htmlspecialchars(substr($curso['descripcion'], 0, 100)) . '...'; ?></p>
                                    
                                    <div class="course-stats">
                                        <span><i class="fas fa-calendar"></i> Inscrito: <?php echo date('d/m/Y', strtotime($curso['fecha_inscripcion'])); ?></span>
                                    </div>
                                    
                                    <div class="course-actions">
                                        <a href="../cursos/ver-curso-estudiante.php?id=<?php echo $curso['id']; ?>" class="btn btn-primary">
                                            <?php echo $curso['progreso'] > 0 ? 'Continuar' : 'Comenzar'; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Cursos Disponibles -->
            <?php if (!empty($cursos_disponibles)): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2>Cursos Disponibles</h2>
                        <a href="../cursos/explorar-cursos.php" class="btn btn-outline">Ver Todos</a>
                    </div>
                    
                    <div class="courses-grid">
                        <?php foreach ($cursos_disponibles as $curso): ?>
                            <div class="course-card available-course">
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
                                    <p class="course-instructor">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($curso['docente_nombre'] . ' ' . $curso['docente_apellido']); ?>
                                    </p>
                                    <p class="course-description"><?php echo htmlspecialchars(substr($curso['descripcion'], 0, 100)) . '...'; ?></p>
                                    
                                    <div class="course-stats">
                                        <span><i class="fas fa-users"></i> <?php echo $curso['total_inscritos']; ?> estudiantes</span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($curso['fecha_creacion'])); ?></span>
                                    </div>
                                    
                                    <div class="course-actions">
                                        <a href="../cursos/detalle-curso.php?id=<?php echo $curso['id']; ?>" class="btn btn-outline">Ver Detalles</a>
                                        <a href="../cursos/inscribirse.php?id=<?php echo $curso['id']; ?>" class="btn btn-primary">Inscribirse</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>