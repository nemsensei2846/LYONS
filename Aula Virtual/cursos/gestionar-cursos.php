<?php
session_start();
require_once '../config/config.php';

// ✅ Verificar si el usuario está logueado y tiene rol de docente
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header('Location: /auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Obtener cursos del docente
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(DISTINCT i.id) as total_estudiantes,
           COUNT(DISTINCT cl.id) as total_clases,
           COUNT(DISTINCT m.id) as total_materiales
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    LEFT JOIN clases cl ON c.id = cl.curso_id
    LEFT JOIN materiales m ON c.id = m.curso_id
    WHERE c.docente_id = ?
    GROUP BY c.id
    ORDER BY c.fecha_creacion DESC
");
$stmt->execute([$user_id]);
$cursos = $stmt->fetchAll();

// Manejar eliminación de curso
if (isset($_POST['eliminar_curso'])) {
    $curso_id = $_POST['curso_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Verificar que el curso pertenece al docente
        $stmt = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND docente_id = ?");
        $stmt->execute([$curso_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            // Eliminar inscripciones
            $stmt = $pdo->prepare("DELETE FROM inscripciones WHERE curso_id = ?");
            $stmt->execute([$curso_id]);
            
            // Eliminar materiales
            $stmt = $pdo->prepare("DELETE FROM materiales WHERE curso_id = ?");
            $stmt->execute([$curso_id]);
            
            // Eliminar clases
            $stmt = $pdo->prepare("DELETE FROM clases WHERE curso_id = ?");
            $stmt->execute([$curso_id]);
            
            // Eliminar curso
            $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = ?");
            $stmt->execute([$curso_id]);
            
            $pdo->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Curso eliminado exitosamente'];
        } else {
            $pdo->rollback();
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'No tienes permisos para eliminar este curso'];
        }
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error al eliminar el curso: ' . $e->getMessage()];
    }
    
    header('Location: /cursos/gestionar-cursos.php');
    exit();
}

// Manejar cambio de estado del curso
if (isset($_POST['cambiar_estado'])) {
    $curso_id = $_POST['curso_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    $stmt = $pdo->prepare("UPDATE cursos SET activo = ? WHERE id = ? AND docente_id = ?");
    if ($stmt->execute([$nuevo_estado, $curso_id, $user_id])) {
        $estado_texto = $nuevo_estado ? 'activado' : 'desactivado';
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Curso $estado_texto exitosamente"];
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error al cambiar el estado del curso'];
    }
    
    header('Location: /cursos/gestionar-cursos.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aula Virtual</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 25px;
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

        .course-status.active {
            background: #d4edda;
            color: #155724;
        }

        .course-status.inactive {
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

        .course-description {
            color: #666;
            line-height: 1.5;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
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
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }

        .course-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn.primary {
            background: #667eea;
            color: white;
        }

        .action-btn.primary:hover {
            background: #5a67d8;
        }

        .action-btn.secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .action-btn.secondary:hover {
            background: #cbd5e0;
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
            margin-bottom: 25px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .modal h3 {
            color: #e53e3e;
            margin-bottom: 15px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        @media (max-width: 768px) {
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .course-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .course-actions {
                flex-direction: column;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
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
                <a href="../dashboard/docente.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="gestionar-cursos.php" class="nav-item active">
                    <i class="fas fa-book"></i>
                    <span>Mis Cursos</span>
                </a>
                <a href="crear-curso.php" class="nav-item">
                    <i class="fas fa-plus"></i>
                    <span>Crear Curso</span>
                </a>
                <a href="../estudiantes/lista-estudiantes.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Estudiantes</span>
                </a>
                <a href="../materiales/gestionar-materiales.php" class="nav-item">
                    <i class="fas fa-folder"></i>
                    <span>Materiales</span>
                </a>
                <a href="../clases/gestionar-clases.php" class="nav-item">
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
                <h1><i class="fas fa-book"></i> Gestionar Cursos</h1>
                <p>Administra todos tus cursos creados</p>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?>">
                    <i class="fas fa-<?php echo getFlashMessage()['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo getFlashMessage()['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Filtros y Búsqueda -->
            <div class="filters">
                <div class="filter-group">
                    <label for="statusFilter"><i class="fas fa-filter"></i> Estado:</label>
                    <select id="statusFilter">
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>
                </div>
                
                <div class="search-box">
                    <input type="text" id="searchCourses" placeholder="Buscar cursos por nombre o código...">
                </div>
                
                <a href="crear-curso.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Curso
                </a>
            </div>

            <!-- Lista de Cursos -->
            <?php if (empty($cursos)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No tienes cursos creados</h3>
                    <p>Comienza creando tu primer curso para gestionar estudiantes y materiales educativos.</p>
                    <a href="crear-curso.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Crear Mi Primer Curso
                    </a>
                </div>
            <?php else: ?>
                <div class="courses-grid" id="coursesGrid">
                    <?php foreach ($cursos as $curso): ?>
                        <div class="course-card" data-status="<?php echo $curso['activo'] ? 'active' : 'inactive'; ?>" data-name="<?php echo strtolower($curso['nombre']); ?>" data-code="<?php echo strtolower($curso['codigo']); ?>">
                            <div class="course-header">
                                <span class="course-status <?php echo $curso['activo'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $curso['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </div>
                            
                            <h3 class="course-title"><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                            <div class="course-code"><?php echo htmlspecialchars($curso['codigo']); ?></div>
                            
                            <p class="course-description">
                                <?php echo htmlspecialchars($curso['descripcion']); ?>
                            </p>
                            
                            <div class="course-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $curso['total_estudiantes']; ?></span>
                                    <span class="stat-label">Estudiantes</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $curso['total_clases']; ?></span>
                                    <span class="stat-label">Clases</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $curso['total_materiales']; ?></span>
                                    <span class="stat-label">Materiales</span>
                                </div>
                            </div>
                            
                            <div class="course-actions">
                                <a href="ver-curso.php?id=<?php echo $curso['id']; ?>" class="action-btn primary">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="editar-curso.php?id=<?php echo $curso['id']; ?>" class="action-btn secondary">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="curso_id" value="<?php echo $curso['id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?php echo $curso['activo'] ? '0' : '1'; ?>">
                                    <button type="submit" name="cambiar_estado" class="action-btn <?php echo $curso['activo'] ? 'warning' : 'success'; ?>">
                                        <i class="fas fa-<?php echo $curso['activo'] ? 'pause' : 'play'; ?>"></i>
                                        <?php echo $curso['activo'] ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                                
                                <button type="button" class="action-btn danger" onclick="confirmDelete(<?php echo $curso['id']; ?>, '<?php echo htmlspecialchars($curso['nombre']); ?>')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal de Confirmación -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
            <p>¿Estás seguro de que deseas eliminar el curso <strong id="courseName"></strong>?</p>
            <p><small>Esta acción eliminará permanentemente el curso, todas sus clases, materiales y inscripciones de estudiantes.</small></p>
            
            <div class="modal-actions">
                <button type="button" class="action-btn secondary" onclick="closeModal()">Cancelar</button>
                <form method="POST" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="curso_id" id="courseIdToDelete">
                    <button type="submit" name="eliminar_curso" class="action-btn danger">
                        <i class="fas fa-trash"></i> Eliminar Curso
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Filtros y búsqueda
        const statusFilter = document.getElementById('statusFilter');
        const searchInput = document.getElementById('searchCourses');
        const coursesGrid = document.getElementById('coursesGrid');

        function filterCourses() {
            const statusValue = statusFilter.value;
            const searchValue = searchInput.value.toLowerCase();
            const courseCards = coursesGrid.querySelectorAll('.course-card');

            courseCards.forEach(card => {
                const status = card.dataset.status;
                const name = card.dataset.name;
                const code = card.dataset.code;
                
                const statusMatch = !statusValue || status === statusValue;
                const searchMatch = !searchValue || name.includes(searchValue) || code.includes(searchValue);
                
                if (statusMatch && searchMatch) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        statusFilter.addEventListener('change', filterCourses);
        searchInput.addEventListener('input', filterCourses);

        // Modal de eliminación
        function confirmDelete(courseId, courseName) {
            document.getElementById('courseName').textContent = courseName;
            document.getElementById('courseIdToDelete').value = courseId;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Animaciones
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.course-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>
