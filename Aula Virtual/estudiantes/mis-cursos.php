<?php
session_start();
require_once '../config/config.php';

// Verificar si el usuario está logueado y es estudiante
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
    header('Location: /auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Manejar desinscripción
if (isset($_POST['desinscribirse'])) {
    $curso_id = $_POST['curso_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM inscripciones WHERE estudiante_id = ? AND curso_id = ?");
        if ($stmt->execute([$user_id, $curso_id])) {
            setFlashMessage('success', 'Te has desinscrito del curso exitosamente');
        } else {
            setFlashMessage('error', 'Error al procesar la desinscripción');
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Error al procesar la desinscripción: ' . $e->getMessage());
    }
    
    redirect('/estudiantes/mis-cursos.php');
}

// Obtener cursos inscritos del estudiante
$stmt = $pdo->prepare("
    SELECT c.*, 
           u.nombre as docente_nombre,
           u.email as docente_email,
           i.fecha_inscripcion,
           COUNT(DISTINCT cl.id) as total_clases,
           COUNT(DISTINCT otros.id) as total_estudiantes
    FROM cursos c
    INNER JOIN inscripciones i ON c.id = i.curso_id
    INNER JOIN usuarios u ON c.docente_id = u.id
    LEFT JOIN clases cl ON c.id = cl.curso_id
    LEFT JOIN inscripciones otros ON c.id = otros.curso_id
    WHERE i.estudiante_id = ? AND c.activo = 1
    GROUP BY c.id, i.fecha_inscripcion
    ORDER BY i.fecha_inscripcion DESC
");
$stmt->execute([$user_id]);
$mis_cursos = $stmt->fetchAll();

// Obtener estadísticas generales
$stmt = $pdo->prepare("SELECT COUNT(*) as total_cursos FROM inscripciones WHERE estudiante_id = ?");
$stmt->execute([$user_id]);
$total_cursos = $stmt->fetch()['total_cursos'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Cursos - Aula Virtual</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }

        .course-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .course-status {
            background: #d4edda;
            color: #155724;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .course-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .course-code {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }

        .course-teacher {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .course-teacher i {
            color: #667eea;
        }

        .course-description {
            color: #666;
            line-height: 1.5;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: #667eea;
            display: block;
        }

        .info-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }

        .enrollment-date {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #1565c0;
        }

        .course-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 10px 16px;
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
            min-width: 120px;
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

        .action-btn.warning {
            background: #ed8936;
            color: white;
        }

        .action-btn.warning:hover {
            background: #dd6b20;
        }

        .action-btn.danger {
            background: #f56565;
            color: white;
        }

        .action-btn.danger:hover {
            background: #e53e3e;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 5rem;
            color: #cbd5e0;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            color: #4a5568;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #718096;
            margin-bottom: 25px;
            font-size: 1.1rem;
        }

        .empty-state .action-btn {
            display: inline-flex;
            flex: none;
        }

        .progress-bar {
            background: #e2e8f0;
            border-radius: 10px;
            height: 8px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
            width: 0%;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .course-info {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .course-actions {
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
                <a href="../dashboard/estudiante.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="mis-cursos.php" class="nav-item active">
                    <i class="fas fa-book"></i>
                    <span>Mis Cursos</span>
                </a>
                <a href="inscribirse.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Inscribirse</span>
                </a>
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
            <div class="content-header">
                <h1><i class="fas fa-book"></i> Mis Cursos</h1>
                <p>Gestiona y accede a tus cursos inscritos</p>
            </div>

            <?php if (hasFlashMessage()): ?>
                <div class="alert alert-<?php echo getFlashMessage()['type']; ?>">
                    <i class="fas fa-<?php echo getFlashMessage()['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo getFlashMessage()['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_cursos; ?></div>
                    <div class="stat-label">Cursos Inscritos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number">0</div>
                    <div class="stat-label">Clases Completadas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number">0%</div>
                    <div class="stat-label">Progreso Promedio</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-number">0</div>
                    <div class="stat-label">Certificados</div>
                </div>
            </div>

            <!-- Lista de Cursos -->
            <?php if (empty($mis_cursos)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No tienes cursos inscritos</h3>
                    <p>¡Explora nuestro catálogo y únete a cursos increíbles!</p>
                    <a href="inscribirse.php" class="action-btn primary">
                        <i class="fas fa-plus-circle"></i> Explorar Cursos
                    </a>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($mis_cursos as $curso): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <span class="course-status">Inscrito</span>
                            </div>
                            
                            <h3 class="course-title"><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                            <div class="course-code"><?php echo htmlspecialchars($curso['codigo']); ?></div>
                            
                            <div class="course-teacher">
                                <i class="fas fa-user-tie"></i>
                                <span><?php echo htmlspecialchars($curso['docente_nombre']); ?></span>
                            </div>
                            
                            <p class="course-description">
                                <?php echo htmlspecialchars($curso['descripcion']); ?>
                            </p>
                            
                            <div class="enrollment-date">
                                <i class="fas fa-calendar-plus"></i>
                                Inscrito el <?php echo date('d/m/Y', strtotime($curso['fecha_inscripcion'])); ?>
                            </div>
                            
                            <div class="course-info">
                                <div class="info-item">
                                    <span class="info-number"><?php echo $curso['total_estudiantes']; ?></span>
                                    <span class="info-label">Estudiantes</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-number"><?php echo $curso['total_clases']; ?></span>
                                    <span class="info-label">Clases</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-number">0%</span>
                                    <span class="info-label">Progreso</span>
                                </div>
                            </div>
                            
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%"></div>
                            </div>
                            
                            <div class="course-actions">
                                <a href="../cursos/ver-curso.php?id=<?php echo $curso['id']; ?>" class="action-btn primary">
                                    <i class="fas fa-eye"></i> Ver Curso
                                </a>
                                
                                <a href="../clases/ver-clases.php?curso_id=<?php echo $curso['id']; ?>" class="action-btn success">
                                    <i class="fas fa-calendar"></i> Clases
                                </a>
                                
                                <a href="../materiales/ver-materiales.php?curso_id=<?php echo $curso['id']; ?>" class="action-btn warning">
                                    <i class="fas fa-folder"></i> Materiales
                                </a>
                                
                                <form method="POST" style="flex: 1;" onsubmit="return confirm('¿Estás seguro de que deseas desinscribirte de este curso?');">
                                    <input type="hidden" name="curso_id" value="<?php echo $curso['id']; ?>">
                                    <button type="submit" name="desinscribirse" class="action-btn danger">
                                        <i class="fas fa-times"></i> Desinscribirse
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.course-card, .stat-card');
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

        // Simulación de progreso (esto se conectaría con datos reales)
        document.querySelectorAll('.progress-fill').forEach(bar => {
            const randomProgress = Math.floor(Math.random() * 100);
            setTimeout(() => {
                bar.style.width = randomProgress + '%';
            }, 1000);
        });
    </script>
</body>
</html>