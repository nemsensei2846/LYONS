<?php
session_start();
require_once '../config/config.php';

// Verificar si el usuario está logueado y es estudiante
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
header('Location: /auth/login.php');
exit();
}

$user_id = $_SESSION['user_id'];

// Manejar inscripción
if (isset($_POST['inscribirse'])) {
    $curso_id = $_POST['curso_id'];
    
    try {
        // Verificar que el curso existe y está activo
        $stmt = $pdo->prepare("SELECT id, nombre, cupo_maximo FROM cursos WHERE id = ? AND activo = 1");
        $stmt->execute([$curso_id]);
        $curso = $stmt->fetch();
        
        if (!$curso) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'El curso no existe o no está disponible'];
            header('Location: /estudiantes/inscribirse.php');
            exit();
        }
        
        // Verificar que no esté ya inscrito
        $stmt = $pdo->prepare("SELECT id FROM inscripciones WHERE estudiante_id = ? AND curso_id = ?");
        $stmt->execute([$user_id, $curso_id]);
        
        if ($stmt->rowCount() > 0) {
            setFlashMessage('error', 'Ya estás inscrito en este curso');
            redirect('/estudiantes/inscribirse.php');
        }
        
        // Verificar cupo disponible
        $stmt = $pdo->prepare("SELECT COUNT(*) as inscritos FROM inscripciones WHERE curso_id = ?");
        $stmt->execute([$curso_id]);
        $inscritos = $stmt->fetch()['inscritos'];
        
        if ($curso['cupo_maximo'] > 0 && $inscritos >= $curso['cupo_maximo']) {
            setFlashMessage('error', 'El curso ha alcanzado su cupo máximo');
            redirect('/estudiantes/inscribirse.php');
        }
        
        // Realizar inscripción
        $stmt = $pdo->prepare("INSERT INTO inscripciones (estudiante_id, curso_id, fecha_inscripcion) VALUES (?, ?, NOW())");
        if ($stmt->execute([$user_id, $curso_id])) {
            setFlashMessage('success', 'Te has inscrito exitosamente en el curso: ' . $curso['nombre']);
        } else {
            setFlashMessage('error', 'Error al procesar la inscripción');
        }
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Error al procesar la inscripción: ' . $e->getMessage());
    }
    
    header('Location: /estudiantes/inscribirse.php');
    exit();
}

// Obtener cursos disponibles (no inscritos)
$stmt = $pdo->prepare("
    SELECT c.*, 
           u.nombre as docente_nombre,
           cat.nombre as categoria_nombre,
           cat.color as categoria_color,
           cat.icono as categoria_icono,
           COUNT(DISTINCT i.id) as total_inscritos,
           COUNT(DISTINCT cl.id) as total_clases,
           CASE WHEN mi.curso_id IS NOT NULL THEN 1 ELSE 0 END as ya_inscrito
    FROM cursos c
    INNER JOIN usuarios u ON c.docente_id = u.id
    LEFT JOIN categorias cat ON c.categoria_id = cat.id
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    LEFT JOIN clases cl ON c.id = cl.curso_id
    LEFT JOIN inscripciones mi ON c.id = mi.curso_id AND mi.estudiante_id = ?
    WHERE c.activo = 1
    GROUP BY c.id
    ORDER BY c.fecha_creacion DESC
");
$stmt->execute([$user_id]);
$cursos = $stmt->fetchAll();

// Obtener categorías para el filtro
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");
$stmt->execute();
$categorias = $stmt->fetchAll();

// Separar cursos disponibles y ya inscritos
$cursos_disponibles = array_filter($cursos, function($curso) {
    return $curso['ya_inscrito'] == 0;
});

$cursos_inscritos = array_filter($cursos, function($curso) {
    return $curso['ya_inscrito'] == 1;
});
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscribirse a Cursos - Aula Virtual</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .courses-section {
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f4;
        }

        .section-header h2 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin: 0;
        }

        .section-header .badge {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
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

        .course-card.enrolled {
            border-left: 4px solid #48bb78;
            background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .course-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .course-status.available {
            background: #d4edda;
            color: #155724;
        }

        .course-status.enrolled {
            background: #cce5ff;
            color: #004085;
        }

        .course-status.full {
            background: #f8d7da;
            color: #721c24;
        }

        .course-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .course-code {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
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

        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
            display: block;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }

        .course-capacity {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .capacity-bar {
            background: #e2e8f0;
            border-radius: 10px;
            height: 8px;
            margin-top: 8px;
            overflow: hidden;
        }

        .capacity-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
            transition: width 0.3s ease;
        }

        .capacity-fill.warning {
            background: linear-gradient(90deg, #ed8936 0%, #dd6b20 100%);
        }

        .capacity-fill.danger {
            background: linear-gradient(90deg, #f56565 0%, #e53e3e 100%);
        }

        .course-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
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

        .action-btn.secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .action-btn.secondary:hover {
            background: #cbd5e0;
        }

        .action-btn:disabled {
            background: #cbd5e0;
            color: #a0aec0;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #4a5568;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #718096;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .course-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .course-actions {
                flex-direction: column;
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
                <a href="mis-cursos.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Mis Cursos</span>
                </a>
                <a href="inscribirse.php" class="nav-item active">
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
                <h1><i class="fas fa-plus-circle"></i> Inscribirse a Cursos</h1>
                <p>Explora y únete a los cursos disponibles</p>
            </div>

            <?php if (hasFlashMessage()): ?>
                <div class="alert alert-<?php echo getFlashMessage()['type']; ?>">
                    <i class="fas fa-<?php echo getFlashMessage()['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo getFlashMessage()['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Filtros y Búsqueda -->
            <div class="filters">
                <div class="search-box">
                    <input type="text" id="searchCourses" placeholder="Buscar cursos por nombre, código o docente...">
                </div>
                
                <div class="filter-group">
                    <select id="categoryFilter">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>">
                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Cursos Ya Inscritos -->
            <?php if (!empty($cursos_inscritos)): ?>
                <div class="courses-section">
                    <div class="section-header">
                        <h2><i class="fas fa-check-circle"></i> Mis Cursos Actuales</h2>
                        <span class="badge"><?php echo count($cursos_inscritos); ?></span>
                    </div>
                    
                    <div class="courses-grid">
                        <?php foreach ($cursos_inscritos as $curso): ?>
                            <div class="course-card enrolled">
                                <div class="course-header">
                                    <span class="course-status enrolled">Inscrito</span>
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
                                
                                <div class="course-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $curso['total_inscritos']; ?></span>
                                        <span class="stat-label">Estudiantes</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $curso['total_clases']; ?></span>
                                        <span class="stat-label">Clases</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number">0%</span>
                                        <span class="stat-label">Progreso</span>
                                    </div>
                                </div>
                                
                                <div class="course-actions">
                                    <a href="../cursos/ver-curso.php?id=<?php echo $curso['id']; ?>" class="action-btn success">
                                        <i class="fas fa-eye"></i> Ver Curso
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cursos Disponibles -->
            <div class="courses-section">
                <div class="section-header">
                    <h2><i class="fas fa-book-open"></i> Cursos Disponibles</h2>
                    <span class="badge"><?php echo count($cursos_disponibles); ?></span>
                </div>
                
                <?php if (empty($cursos_disponibles)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No hay cursos disponibles</h3>
                        <p>No se encontraron cursos disponibles para inscripción en este momento.</p>
                    </div>
                <?php else: ?>
                    <div class="courses-grid" id="availableCoursesGrid">
                        <?php foreach ($cursos_disponibles as $curso): ?>
                            <?php
                            $porcentaje_ocupacion = 0;
                            $cupo_disponible = true;
                            
                            if ($curso['cupo_maximo'] > 0) {
                                $porcentaje_ocupacion = ($curso['total_inscritos'] / $curso['cupo_maximo']) * 100;
                                $cupo_disponible = $curso['total_inscritos'] < $curso['cupo_maximo'];
                            }
                            ?>
                            
                            <div class="course-card" data-name="<?php echo strtolower($curso['nombre']); ?>" data-code="<?php echo strtolower($curso['codigo']); ?>" data-teacher="<?php echo strtolower($curso['docente_nombre']); ?>" data-category="<?php echo $curso['categoria_id'] ?? ''; ?>">
                                <div class="course-header">
                                    <span class="course-status <?php echo $cupo_disponible ? 'available' : 'full'; ?>">
                                        <?php echo $cupo_disponible ? 'Disponible' : 'Cupo Lleno'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($curso['categoria_nombre']): ?>
                                    <div class="course-category" style="background: <?php echo $curso['categoria_color']; ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: inline-block; margin-bottom: 10px;">
                                        <i class="<?php echo $curso['categoria_icono']; ?>"></i>
                                        <?php echo htmlspecialchars($curso['categoria_nombre']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <h3 class="course-title"><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                                <div class="course-code"><?php echo htmlspecialchars($curso['codigo']); ?></div>
                                
                                <div class="course-teacher">
                                    <i class="fas fa-user-tie"></i>
                                    <span><?php echo htmlspecialchars($curso['docente_nombre']); ?></span>
                                </div>
                                
                                <p class="course-description">
                                    <?php echo htmlspecialchars($curso['descripcion']); ?>
                                </p>
                                
                                <?php if ($curso['cupo_maximo'] > 0): ?>
                                    <div class="course-capacity">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span><i class="fas fa-users"></i> Cupo:</span>
                                            <span><strong><?php echo $curso['total_inscritos']; ?>/<?php echo $curso['cupo_maximo']; ?></strong></span>
                                        </div>
                                        <div class="capacity-bar">
                                            <div class="capacity-fill <?php echo $porcentaje_ocupacion >= 90 ? 'danger' : ($porcentaje_ocupacion >= 70 ? 'warning' : ''); ?>" 
                                                 style="width: <?php echo $porcentaje_ocupacion; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="course-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $curso['total_inscritos']; ?></span>
                                        <span class="stat-label">Estudiantes</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $curso['total_clases']; ?></span>
                                        <span class="stat-label">Clases</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $curso['cupo_maximo'] > 0 ? $curso['cupo_maximo'] : '∞'; ?></span>
                                        <span class="stat-label">Cupo Máx</span>
                                    </div>
                                </div>
                                
                                <div class="course-actions">
                                    <a href="../cursos/ver-curso.php?id=<?php echo $curso['id']; ?>" class="action-btn secondary">
                                        <i class="fas fa-eye"></i> Ver Detalles
                                    </a>
                                    
                                    <?php if ($cupo_disponible): ?>
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="curso_id" value="<?php echo $curso['id']; ?>">
                                            <button type="submit" name="inscribirse" class="action-btn primary">
                                                <i class="fas fa-plus"></i> Inscribirse
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="action-btn" disabled>
                                            <i class="fas fa-times"></i> Sin Cupo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Búsqueda y filtros
        const searchInput = document.getElementById('searchCourses');
        const categoryFilter = document.getElementById('categoryFilter');
        const coursesGrid = document.getElementById('availableCoursesGrid');

        function filterCourses() {
            const searchValue = searchInput.value.toLowerCase();
            const categoryValue = categoryFilter.value;
            
            if (coursesGrid) {
                const courseCards = coursesGrid.querySelectorAll('.course-card');

                courseCards.forEach(card => {
                    const name = card.dataset.name;
                    const code = card.dataset.code;
                    const teacher = card.dataset.teacher;
                    const category = card.dataset.category;
                    
                    const searchMatch = !searchValue || 
                        name.includes(searchValue) || 
                        code.includes(searchValue) || 
                        teacher.includes(searchValue);
                    
                    const categoryMatch = !categoryValue || category === categoryValue;
                    
                    if (searchMatch && categoryMatch) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterCourses);
        if (categoryFilter) categoryFilter.addEventListener('change', filterCourses);

        // Animaciones
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.course-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Confirmación de inscripción
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('button[name="inscribirse"]')) {
                    const courseName = this.closest('.course-card').querySelector('.course-title').textContent;
                    if (!confirm(`¿Estás seguro de que deseas inscribirte en el curso "${courseName}"?`)) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>
