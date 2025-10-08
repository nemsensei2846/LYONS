<?php
session_start();
require_once '../config/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipo_usuario'];

// Obtener ID del curso
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Curso no encontrado');
    redirect('/dashboard/index.php');
}

$curso_id = $_GET['id'];

// Obtener información del curso
$stmt = $pdo->prepare("
    SELECT c.*, 
           u.nombre as docente_nombre,
           u.email as docente_email,
           COUNT(DISTINCT i.id) as total_inscritos,
           COUNT(DISTINCT cl.id) as total_clases,
           CASE WHEN mi.curso_id IS NOT NULL THEN 1 ELSE 0 END as ya_inscrito
    FROM cursos c
    INNER JOIN usuarios u ON c.docente_id = u.id
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    LEFT JOIN clases cl ON c.id = cl.curso_id
    LEFT JOIN inscripciones mi ON c.id = mi.curso_id AND mi.estudiante_id = ?
    WHERE c.id = ? AND c.activo = 1
    GROUP BY c.id
");
$stmt->execute([$user_id, $curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    setFlashMessage('error', 'Curso no encontrado o no disponible');
    redirect('/dashboard/index.php');
}

// Verificar permisos: docente del curso, estudiante inscrito, o admin
$tiene_acceso = false;
if ($user_type === 'docente' && $curso['docente_id'] == $user_id) {
    $tiene_acceso = true;
} elseif ($user_type === 'estudiante' && $curso['ya_inscrito']) {
    $tiene_acceso = true;
} elseif ($user_type === 'admin') {
    $tiene_acceso = true;
}

// Obtener clases del curso
$stmt = $pdo->prepare("
    SELECT cl.*, 
           COUNT(DISTINCT a.id) as total_asistencias
    FROM clases cl
    LEFT JOIN asistencias a ON cl.id = a.clase_id
    WHERE cl.curso_id = ?
    GROUP BY cl.id
    ORDER BY cl.fecha_clase ASC, cl.hora_inicio ASC
");
$stmt->execute([$curso_id]);
$clases = $stmt->fetchAll();

// Obtener materiales del curso (si tiene acceso)
$materiales = [];
if ($tiene_acceso) {
    $stmt = $pdo->prepare("
        SELECT m.*
        FROM materiales m
        WHERE m.curso_id = ?
        ORDER BY m.fecha_subida DESC
    ");
    $stmt->execute([$curso_id]);
    $materiales = $stmt->fetchAll();
}

// Obtener estudiantes inscritos (solo para docente del curso)
$estudiantes = [];
if ($user_type === 'docente' && $curso['docente_id'] == $user_id) {
    $stmt = $pdo->prepare("
        SELECT u.nombre, u.email, i.fecha_inscripcion
        FROM inscripciones i
        INNER JOIN usuarios u ON i.estudiante_id = u.id
        WHERE i.curso_id = ?
        ORDER BY i.fecha_inscripcion DESC
    ");
    $stmt->execute([$curso_id]);
    $estudiantes = $stmt->fetchAll();
}

// Manejar inscripción (solo estudiantes)
if (isset($_POST['inscribirse']) && $user_type === 'estudiante') {
    try {
        // Verificar que no esté ya inscrito
        $stmt = $pdo->prepare("SELECT id FROM inscripciones WHERE estudiante_id = ? AND curso_id = ?");
        $stmt->execute([$user_id, $curso_id]);
        
        if ($stmt->rowCount() > 0) {
            setFlashMessage('error', 'Ya estás inscrito en este curso');
        } else {
            // Verificar cupo disponible
            if ($curso['cupo_maximo'] > 0 && $curso['total_inscritos'] >= $curso['cupo_maximo']) {
                setFlashMessage('error', 'El curso ha alcanzado su cupo máximo');
            } else {
                // Realizar inscripción
                $stmt = $pdo->prepare("INSERT INTO inscripciones (estudiante_id, curso_id, fecha_inscripcion) VALUES (?, ?, NOW())");
                if ($stmt->execute([$user_id, $curso_id])) {
                    setFlashMessage('success', 'Te has inscrito exitosamente en el curso');
                } else {
                    setFlashMessage('error', 'Error al procesar la inscripción');
                }
            }
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Error al procesar la inscripción: ' . $e->getMessage());
    }
    
    redirect('/cursos/ver-curso.php?id=' . $curso_id);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($curso['nombre']); ?> - Aula Virtual</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .course-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .course-hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .course-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .course-code {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }

        .course-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            align-items: center;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }

        .meta-item i {
            font-size: 1.2rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .main-content-area {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f4;
        }

        .card-header h3 {
            font-size: 1.3rem;
            color: #2c3e50;
            margin: 0;
        }

        .card-header i {
            color: #667eea;
            font-size: 1.4rem;
        }

        .course-description {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #4a5568;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }

        .enrollment-status {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }

        .enrollment-status.enrolled {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .enrollment-status.available {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b3d7ff;
        }

        .enrollment-status.full {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
            min-width: 140px;
        }

        .action-btn.primary {
            background: #667eea;
            color: white;
        }

        .action-btn.primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .action-btn.success {
            background: #48bb78;
            color: white;
        }

        .action-btn.success:hover {
            background: #38a169;
        }

        .action-btn.secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .action-btn.secondary:hover {
            background: #cbd5e0;
        }

        .classes-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .class-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .class-title {
            font-weight: 600;
            color: #2c3e50;
        }

        .class-date {
            font-size: 0.85rem;
            color: #666;
        }

        .class-time {
            font-size: 0.9rem;
            color: #667eea;
            font-weight: 600;
        }

        .materials-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .material-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .material-item:hover {
            background: #e2e8f0;
        }

        .material-icon {
            width: 40px;
            height: 40px;
            background: #667eea;
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .material-info {
            flex: 1;
        }

        .material-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .material-date {
            font-size: 0.8rem;
            color: #666;
        }

        .students-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .student-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .student-avatar {
            width: 35px;
            height: 35px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .student-info {
            flex: 1;
        }

        .student-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .student-email {
            font-size: 0.8rem;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .course-title {
                font-size: 2rem;
            }
            
            .course-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                flex: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Aula Virtual</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="../dashboard/<?php echo $user_type; ?>.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                
                <?php if ($user_type === 'estudiante'): ?>
                    <a href="../estudiantes/mis-cursos.php" class="nav-item">
                        <i class="fas fa-book"></i>
                        <span>Mis Cursos</span>
                    </a>
                    <a href="../estudiantes/inscribirse.php" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>Inscribirse</span>
                    </a>
                <?php elseif ($user_type === 'docente'): ?>
                    <a href="../cursos/gestionar-cursos.php" class="nav-item">
                        <i class="fas fa-book"></i>
                        <span>Mis Cursos</span>
                    </a>
                    <a href="../cursos/crear-curso.php" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>Crear Curso</span>
                    </a>
                <?php endif; ?>
                
                <a href="../materiales/ver-materiales.php" class="nav-item">
                    <i class="fas fa-folder"></i>
                    <span>Materiales</span>
                </a>
                <a href="../clases/ver-clases.php" class="nav-item">
                    <i class="fas fa-calendar"></i>
                    <span>Clases</span>
                </a>
                <a href="../auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (hasFlashMessage()): ?>
                <div class="alert alert-<?php echo getFlashMessage()['type']; ?>">
                    <i class="fas fa-<?php echo getFlashMessage()['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo getFlashMessage()['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Course Hero Section -->
            <div class="course-hero">
                <div class="hero-content">
                    <h1 class="course-title"><?php echo htmlspecialchars($curso['nombre']); ?></h1>
                    <div class="course-code"><?php echo htmlspecialchars($curso['codigo']); ?></div>
                    
                    <div class="course-meta">
                        <div class="meta-item">
                            <i class="fas fa-user-tie"></i>
                            <span><?php echo htmlspecialchars($curso['docente_nombre']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $curso['total_inscritos']; ?> estudiantes</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo $curso['total_clases']; ?> clases</span>
                        </div>
                        <?php if ($curso['cupo_maximo'] > 0): ?>
                            <div class="meta-item">
                                <i class="fas fa-chair"></i>
                                <span>Cupo: <?php echo $curso['cupo_maximo']; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <!-- Main Content Area -->
                <div class="main-content-area">
                    <!-- Course Description -->
                    <div class="content-card">
                        <div class="card-header">
                            <i class="fas fa-info-circle"></i>
                            <h3>Descripción del Curso</h3>
                        </div>
                        <div class="course-description">
                            <?php echo nl2br(htmlspecialchars($curso['descripcion'])); ?>
                        </div>
                    </div>

                    <!-- Classes -->
                    <div class="content-card">
                        <div class="card-header">
                            <i class="fas fa-calendar-alt"></i>
                            <h3>Clases Programadas</h3>
                        </div>
                        
                        <?php if (empty($clases)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No hay clases programadas aún</p>
                            </div>
                        <?php else: ?>
                            <div class="classes-list">
                                <?php foreach ($clases as $clase): ?>
                                    <div class="class-item">
                                        <div class="class-header">
                                            <div class="class-title"><?php echo htmlspecialchars($clase['titulo']); ?></div>
                                            <div class="class-date"><?php echo date('d/m/Y', strtotime($clase['fecha_clase'])); ?></div>
                                        </div>
                                        <div class="class-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('H:i', strtotime($clase['hora_inicio'])); ?> - 
                                            <?php echo date('H:i', strtotime($clase['hora_fin'])); ?>
                                        </div>
                                        <?php if ($clase['descripcion']): ?>
                                            <div class="class-description" style="margin-top: 8px; color: #666; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($clase['descripcion']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Materials (only if has access) -->
                    <?php if ($tiene_acceso): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <i class="fas fa-folder-open"></i>
                                <h3>Materiales del Curso</h3>
                            </div>
                            
                            <?php if (empty($materiales)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder"></i>
                                    <p>No hay materiales disponibles</p>
                                </div>
                            <?php else: ?>
                                <div class="materials-list">
                                    <?php foreach ($materiales as $material): ?>
                                        <div class="material-item">
                                            <div class="material-icon">
                                                <i class="fas fa-file"></i>
                                            </div>
                                            <div class="material-info">
                                                <div class="material-name"><?php echo htmlspecialchars($material['nombre']); ?></div>
                                                <div class="material-date">
                                                    Subido el <?php echo date('d/m/Y', strtotime($material['fecha_subida'])); ?>
                                                </div>
                                            </div>
                                            <a href="../materiales/descargar.php?id=<?php echo $material['id']; ?>" class="action-btn secondary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Content -->
                <div class="sidebar-content">
                    <!-- Enrollment Status & Actions -->
                    <div class="content-card">
                        <div class="card-header">
                            <i class="fas fa-user-check"></i>
                            <h3>Estado de Inscripción</h3>
                        </div>
                        
                        <?php if ($user_type === 'estudiante'): ?>
                            <?php if ($curso['ya_inscrito']): ?>
                                <div class="enrollment-status enrolled">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>Estás inscrito en este curso</strong>
                                </div>
                            <?php else: ?>
                                <?php if ($curso['cupo_maximo'] > 0 && $curso['total_inscritos'] >= $curso['cupo_maximo']): ?>
                                    <div class="enrollment-status full">
                                        <i class="fas fa-times-circle"></i>
                                        <strong>Cupo completo</strong>
                                    </div>
                                <?php else: ?>
                                    <div class="enrollment-status available">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Disponible para inscripción</strong>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="action-buttons">
                            <?php if ($user_type === 'estudiante'): ?>
                                <?php if ($curso['ya_inscrito']): ?>
                                    <a href="../clases/ver-clases.php?curso_id=<?php echo $curso['id']; ?>" class="action-btn success">
                                        <i class="fas fa-calendar"></i> Ver Clases
                                    </a>
                                    <a href="../materiales/ver-materiales.php?curso_id=<?php echo $curso['id']; ?>" class="action-btn secondary">
                                        <i class="fas fa-folder"></i> Materiales
                                    </a>
                                <?php else: ?>
                                    <?php if ($curso['cupo_maximo'] == 0 || $curso['total_inscritos'] < $curso['cupo_maximo']): ?>
                                        <form method="POST">
                                            <button type="submit" name="inscribirse" class="action-btn primary">
                                                <i class="fas fa-plus"></i> Inscribirse
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php elseif ($user_type === 'docente' && $curso['docente_id'] == $user_id): ?>
                                <a href="../cursos/editar-curso.php?id=<?php echo $curso['id']; ?>" class="action-btn primary">
                                    <i class="fas fa-edit"></i> Editar Curso
                                </a>
                                <a href="../clases/gestionar-clases.php?curso_id=<?php echo $curso['id']; ?>" class="action-btn success">
                                    <i class="fas fa-calendar-plus"></i> Gestionar Clases
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Course Statistics -->
                    <div class="content-card">
                        <div class="card-header">
                            <i class="fas fa-chart-bar"></i>
                            <h3>Estadísticas</h3>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $curso['total_inscritos']; ?></span>
                                <span class="stat-label">Estudiantes</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $curso['total_clases']; ?></span>
                                <span class="stat-label">Clases</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo count($materiales); ?></span>
                                <span class="stat-label">Materiales</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $curso['cupo_maximo'] > 0 ? $curso['cupo_maximo'] : '∞'; ?></span>
                                <span class="stat-label">Cupo Máx</span>
                            </div>
                        </div>
                    </div>

                    <!-- Students List (only for course teacher) -->
                    <?php if ($user_type === 'docente' && $curso['docente_id'] == $user_id && !empty($estudiantes)): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <i class="fas fa-users"></i>
                                <h3>Estudiantes Inscritos</h3>
                            </div>
                            
                            <div class="students-list">
                                <?php foreach ($estudiantes as $estudiante): ?>
                                    <div class="student-item">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($estudiante['nombre'], 0, 1)); ?>
                                        </div>
                                        <div class="student-info">
                                            <div class="student-name"><?php echo htmlspecialchars($estudiante['nombre']); ?></div>
                                            <div class="student-email"><?php echo htmlspecialchars($estudiante['email']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.content-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Confirmación de inscripción
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('button[name="inscribirse"]')) {
                    if (!confirm('¿Estás seguro de que deseas inscribirte en este curso?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>
