<?php
require_once '../config/config.php';
session_start();

// Verificar si está logueado y es estudiante
if (!estaLogueado() || !esEstudiante()) {
    header('Location: ../auth/login.php');
    exit();
}

$pdo = conectarDB();
$user_id = $_SESSION['usuario_id'];

// Obtener progreso general del estudiante
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT i.curso_id) as total_cursos,
        COUNT(DISTINCT pe.clase_id) as clases_completadas,
        COUNT(DISTINCT cl.id) as total_clases,
        COALESCE(AVG(i.progreso), 0) as progreso_promedio,
        SUM(pe.tiempo_dedicado) as tiempo_total
    FROM inscripciones i
    LEFT JOIN clases cl ON i.curso_id = cl.curso_id AND cl.activo = 1
    LEFT JOIN progreso_estudiantes pe ON pe.estudiante_id = i.estudiante_id AND pe.clase_id = cl.id AND pe.completado = 1
    WHERE i.estudiante_id = ? AND i.estado = 'activo'
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Obtener cursos con progreso detallado
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.nombre,
        c.codigo,
        c.descripcion,
        u.nombre as docente_nombre,
        cat.nombre as categoria_nombre,
        cat.color as categoria_color,
        i.fecha_inscripcion,
        i.progreso,
        COUNT(DISTINCT cl.id) as total_clases,
        COUNT(DISTINCT pe.clase_id) as clases_completadas,
        SUM(pe.tiempo_dedicado) as tiempo_dedicado
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    INNER JOIN usuarios u ON c.docente_id = u.id
    LEFT JOIN categorias cat ON c.categoria_id = cat.id
    LEFT JOIN clases cl ON c.id = cl.curso_id AND cl.activo = 1
    LEFT JOIN progreso_estudiantes pe ON pe.estudiante_id = i.estudiante_id AND pe.clase_id = cl.id AND pe.completado = 1
    WHERE i.estudiante_id = ? AND i.estado = 'activo'
    GROUP BY c.id
    ORDER BY i.fecha_inscripcion DESC
");
$stmt->execute([$user_id]);
$cursos_progreso = $stmt->fetchAll();

// Obtener actividad reciente
$stmt = $pdo->prepare("
    SELECT 
        cl.titulo as clase_titulo,
        c.nombre as curso_nombre,
        pe.fecha_completado,
        pe.tiempo_dedicado
    FROM progreso_estudiantes pe
    INNER JOIN clases cl ON pe.clase_id = cl.id
    INNER JOIN cursos c ON cl.curso_id = c.id
    WHERE pe.estudiante_id = ? AND pe.completado = 1
    ORDER BY pe.fecha_completado DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$actividad_reciente = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Progreso - Aula Virtual</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <style>
        .progress-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .progress-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .progress-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .progress-card.completed::before {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }

        .progress-card.time::before {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }

        .progress-card.average::before {
            background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
        }

        .progress-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .progress-label {
            color: #666;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .course-progress-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .course-progress-card:hover {
            transform: translateY(-5px);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .course-info h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .course-code {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 10px;
        }

        .course-category {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-left: 10px;
        }

        .course-teacher {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .progress-bar-container {
            margin: 20px 0;
        }

        .progress-bar {
            width: 100%;
            height: 12px;
            background: #f1f3f4;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border-radius: 6px;
            transition: width 0.8s ease;
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #666;
        }

        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .activity-feed {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .activity-course {
            font-size: 0.85rem;
            color: #667eea;
            margin-bottom: 3px;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .course-header {
                flex-direction: column;
                gap: 15px;
            }

            .course-stats {
                grid-template-columns: 1fr;
            }

            .progress-overview {
                grid-template-columns: 1fr;
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
            
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="inscribirse.php"><i class="fas fa-search"></i> Buscar Cursos</a></li>
                    <li><a href="mis-cursos.php"><i class="fas fa-book"></i> Mis Cursos</a></li>
                    <li class="active"><a href="progreso.php"><i class="fas fa-chart-line"></i> Mi Progreso</a></li>
                    <li><a href="materiales.php"><i class="fas fa-folder"></i> Materiales</a></li>
                    <li><a href="perfil.php"><i class="fas fa-user"></i> Mi Perfil</a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-chart-line"></i> Mi Progreso</h1>
            </div>

            <!-- Progress Overview -->
            <div class="progress-overview">
                <div class="progress-card">
                    <div class="progress-number"><?php echo $stats['total_cursos']; ?></div>
                    <div class="progress-label">Cursos Inscritos</div>
                </div>
                
                <div class="progress-card completed">
                    <div class="progress-number"><?php echo $stats['clases_completadas']; ?></div>
                    <div class="progress-label">Clases Completadas</div>
                </div>
                
                <div class="progress-card time">
                    <div class="progress-number"><?php echo round(($stats['tiempo_total'] ?? 0) / 60); ?></div>
                    <div class="progress-label">Horas de Estudio</div>
                </div>
                
                <div class="progress-card average">
                    <div class="progress-number"><?php echo round($stats['progreso_promedio']); ?>%</div>
                    <div class="progress-label">Progreso Promedio</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- Course Progress -->
                <div>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-tasks"></i> Progreso por Curso</h2>
                        </div>

                        <?php if (empty($cursos_progreso)): ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-line"></i>
                                <h3>Sin cursos inscritos</h3>
                                <p>Inscríbete en algunos cursos para ver tu progreso aquí.</p>
                                <a href="inscribirse.php" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Buscar Cursos
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($cursos_progreso as $curso): ?>
                                <div class="course-progress-card">
                                    <div class="course-header">
                                        <div class="course-info">
                                            <h3><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                                            <div class="course-code"><?php echo htmlspecialchars($curso['codigo']); ?></div>
                                            <?php if ($curso['categoria_nombre']): ?>
                                                <div class="course-category" style="background: <?php echo $curso['categoria_color']; ?>; color: white;">
                                                    <?php echo htmlspecialchars($curso['categoria_nombre']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="course-teacher">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($curso['docente_nombre']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="progress-bar-container">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $curso['progreso']; ?>%"></div>
                                        </div>
                                        <div class="progress-text">
                                            <span><?php echo $curso['clases_completadas']; ?> de <?php echo $curso['total_clases']; ?> clases</span>
                                            <span><?php echo round($curso['progreso']); ?>% completado</span>
                                        </div>
                                    </div>

                                    <div class="course-stats">
                                        <div class="stat-item">
                                            <span class="stat-number"><?php echo $curso['total_clases']; ?></span>
                                            <span class="stat-label">Total Clases</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-number"><?php echo $curso['clases_completadas']; ?></span>
                                            <span class="stat-label">Completadas</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-number"><?php echo round(($curso['tiempo_dedicado'] ?? 0) / 60); ?>h</span>
                                            <span class="stat-label">Tiempo Dedicado</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity Feed -->
                <div>
                    <div class="activity-feed">
                        <div class="section-header">
                            <h2><i class="fas fa-clock"></i> Actividad Reciente</h2>
                        </div>

                        <?php if (empty($actividad_reciente)): ?>
                            <div class="empty-state">
                                <i class="fas fa-clock"></i>
                                <h3>Sin actividad reciente</h3>
                                <p>Completa algunas clases para ver tu actividad aquí.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($actividad_reciente as $actividad): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?php echo htmlspecialchars($actividad['clase_titulo']); ?></div>
                                        <div class="activity-course"><?php echo htmlspecialchars($actividad['curso_nombre']); ?></div>
                                        <div class="activity-time">
                                            <?php echo date('d/m/Y H:i', strtotime($actividad['fecha_completado'])); ?>
                                            <?php if ($actividad['tiempo_dedicado']): ?>
                                                • <?php echo round($actividad['tiempo_dedicado'] / 60); ?> min
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.course-progress-card, .progress-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.animation = `fadeInUp 0.6s ease forwards ${index * 0.1}s`;
            });

            // Animar barras de progreso
            setTimeout(() => {
                const progressBars = document.querySelectorAll('.progress-fill');
                progressBars.forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                });
            }, 500);
        });

        // Definir animación CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>