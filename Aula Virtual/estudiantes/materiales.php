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

// Obtener materiales de cursos inscritos
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.nombre_archivo,
        m.ruta_archivo,
        m.tipo_archivo,
        m.tamaño_archivo,
        m.fecha_subida,
        cl.titulo as clase_titulo,
        c.nombre as curso_nombre,
        c.codigo as curso_codigo,
        cat.nombre as categoria_nombre,
        cat.color as categoria_color,
        u.nombre as docente_nombre
    FROM materiales m
    INNER JOIN clases cl ON m.clase_id = cl.id
    INNER JOIN cursos c ON cl.curso_id = c.id
    INNER JOIN usuarios u ON c.docente_id = u.id
    LEFT JOIN categorias cat ON c.categoria_id = cat.id
    INNER JOIN inscripciones i ON c.id = i.curso_id
    WHERE i.estudiante_id = ? AND i.estado = 'activo' AND cl.activo = 1
    ORDER BY m.fecha_subida DESC
");
$stmt->execute([$user_id]);
$materiales = $stmt->fetchAll();

// Agrupar materiales por curso
$materiales_por_curso = [];
foreach ($materiales as $material) {
    $curso_id = $material['curso_nombre'];
    if (!isset($materiales_por_curso[$curso_id])) {
        $materiales_por_curso[$curso_id] = [
            'info' => $material,
            'materiales' => []
        ];
    }
    $materiales_por_curso[$curso_id]['materiales'][] = $material;
}

// Función para obtener icono según tipo de archivo
function getFileIcon($tipo_archivo) {
    $iconos = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'txt' => 'fas fa-file-alt',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image',
        'mp4' => 'fas fa-file-video',
        'avi' => 'fas fa-file-video',
        'mov' => 'fas fa-file-video',
        'mp3' => 'fas fa-file-audio',
        'wav' => 'fas fa-file-audio',
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive'
    ];
    
    return $iconos[strtolower($tipo_archivo)] ?? 'fas fa-file';
}

// Función para formatear tamaño de archivo
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiales - Aula Virtual</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <style>
        .materials-container {
            display: grid;
            gap: 30px;
        }

        .course-materials {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .course-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f3f4;
        }

        .course-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .course-info h3 {
            font-size: 1.4rem;
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
            margin-right: 10px;
        }

        .course-category {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            color: white;
        }

        .course-teacher {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
        }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .material-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .material-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .material-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .material-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .material-icon.pdf { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        .material-icon.doc { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); }
        .material-icon.xls { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
        .material-icon.ppt { background: linear-gradient(135deg, #fd7e14 0%, #e55a00 100%); }
        .material-icon.image { background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%); }
        .material-icon.video { background: linear-gradient(135deg, #e83e8c 0%, #d91a72 100%); }
        .material-icon.audio { background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%); }
        .material-icon.archive { background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); }
        .material-icon.default { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        .material-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            line-height: 1.3;
        }

        .material-class {
            color: #667eea;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .material-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #666;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .material-size {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 500;
        }

        .search-filter {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .filter-select select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            min-width: 150px;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
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
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .materials-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .course-header {
                flex-direction: column;
                text-align: center;
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
                    <li><a href="progreso.php"><i class="fas fa-chart-line"></i> Mi Progreso</a></li>
                    <li class="active"><a href="materiales.php"><i class="fas fa-folder"></i> Materiales</a></li>
                    <li><a href="perfil.php"><i class="fas fa-user"></i> Mi Perfil</a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-folder"></i> Materiales de Curso</h1>
            </div>

            <?php if (!empty($materiales)): ?>
                <!-- Stats Summary -->
                <div class="stats-summary">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($materiales); ?></div>
                        <div class="stat-label">Total Materiales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($materiales_por_curso); ?></div>
                        <div class="stat-label">Cursos con Materiales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            $total_size = array_sum(array_column($materiales, 'tamaño_archivo'));
                            echo formatFileSize($total_size);
                            ?>
                        </div>
                        <div class="stat-label">Tamaño Total</div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filter">
                    <div class="filter-row">
                        <div class="search-box">
                            <input type="text" id="searchMaterials" placeholder="Buscar materiales...">
                        </div>
                        <div class="filter-select">
                            <select id="courseFilter">
                                <option value="">Todos los cursos</option>
                                <?php foreach ($materiales_por_curso as $curso_nombre => $data): ?>
                                    <option value="<?php echo htmlspecialchars($curso_nombre); ?>">
                                        <?php echo htmlspecialchars($curso_nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-select">
                            <select id="typeFilter">
                                <option value="">Todos los tipos</option>
                                <option value="pdf">PDF</option>
                                <option value="doc">Documentos</option>
                                <option value="image">Imágenes</option>
                                <option value="video">Videos</option>
                                <option value="audio">Audio</option>
                                <option value="archive">Archivos</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Materials by Course -->
                <div class="materials-container">
                    <?php foreach ($materiales_por_curso as $curso_nombre => $data): ?>
                        <div class="course-materials" data-course="<?php echo htmlspecialchars($curso_nombre); ?>">
                            <div class="course-header">
                                <div class="course-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="course-info">
                                    <h3><?php echo htmlspecialchars($data['info']['curso_nombre']); ?></h3>
                                    <div>
                                        <span class="course-code"><?php echo htmlspecialchars($data['info']['curso_codigo']); ?></span>
                                        <?php if ($data['info']['categoria_nombre']): ?>
                                            <span class="course-category" style="background: <?php echo $data['info']['categoria_color']; ?>">
                                                <?php echo htmlspecialchars($data['info']['categoria_nombre']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="course-teacher">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($data['info']['docente_nombre']); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="materials-grid">
                                <?php foreach ($data['materiales'] as $material): ?>
                                    <?php 
                                    $tipo_clase = '';
                                    $extension = strtolower($material['tipo_archivo']);
                                    if (in_array($extension, ['pdf'])) $tipo_clase = 'pdf';
                                    elseif (in_array($extension, ['doc', 'docx'])) $tipo_clase = 'doc';
                                    elseif (in_array($extension, ['xls', 'xlsx'])) $tipo_clase = 'xls';
                                    elseif (in_array($extension, ['ppt', 'pptx'])) $tipo_clase = 'ppt';
                                    elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) $tipo_clase = 'image';
                                    elseif (in_array($extension, ['mp4', 'avi', 'mov'])) $tipo_clase = 'video';
                                    elseif (in_array($extension, ['mp3', 'wav'])) $tipo_clase = 'audio';
                                    elseif (in_array($extension, ['zip', 'rar'])) $tipo_clase = 'archive';
                                    else $tipo_clase = 'default';
                                    ?>
                                    
                                    <div class="material-card" 
                                         data-name="<?php echo strtolower($material['nombre_archivo']); ?>"
                                         data-class="<?php echo strtolower($material['clase_titulo']); ?>"
                                         data-type="<?php echo $tipo_clase; ?>"
                                         onclick="downloadMaterial('<?php echo $material['ruta_archivo']; ?>', '<?php echo $material['nombre_archivo']; ?>')">
                                        
                                        <div class="material-header">
                                            <div class="material-icon <?php echo $tipo_clase; ?>">
                                                <i class="<?php echo getFileIcon($material['tipo_archivo']); ?>"></i>
                                            </div>
                                            <div class="material-info">
                                                <h4><?php echo htmlspecialchars($material['nombre_archivo']); ?></h4>
                                                <div class="material-class"><?php echo htmlspecialchars($material['clase_titulo']); ?></div>
                                            </div>
                                        </div>

                                        <div class="material-meta">
                                            <span><?php echo date('d/m/Y', strtotime($material['fecha_subida'])); ?></span>
                                            <span class="material-size">
                                                <?php echo formatFileSize($material['tamaño_archivo']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No hay materiales disponibles</h3>
                    <p>Los materiales de tus cursos aparecerán aquí cuando los docentes los suban.</p>
                    <a href="inscribirse.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar Cursos
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Filtros y búsqueda
        const searchInput = document.getElementById('searchMaterials');
        const courseFilter = document.getElementById('courseFilter');
        const typeFilter = document.getElementById('typeFilter');

        function filterMaterials() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCourse = courseFilter.value;
            const selectedType = typeFilter.value;

            const courseSections = document.querySelectorAll('.course-materials');
            
            courseSections.forEach(section => {
                const courseName = section.dataset.course;
                const materialCards = section.querySelectorAll('.material-card');
                let visibleCards = 0;

                materialCards.forEach(card => {
                    const name = card.dataset.name;
                    const className = card.dataset.class;
                    const type = card.dataset.type;

                    const matchesSearch = !searchTerm || 
                        name.includes(searchTerm) || 
                        className.includes(searchTerm);
                    
                    const matchesCourse = !selectedCourse || courseName === selectedCourse;
                    const matchesType = !selectedType || type === selectedType;

                    if (matchesSearch && matchesCourse && matchesType) {
                        card.style.display = 'block';
                        visibleCards++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Mostrar/ocultar sección completa si no hay materiales visibles
                section.style.display = visibleCards > 0 ? 'block' : 'none';
            });
        }

        if (searchInput) searchInput.addEventListener('input', filterMaterials);
        if (courseFilter) courseFilter.addEventListener('change', filterMaterials);
        if (typeFilter) typeFilter.addEventListener('change', filterMaterials);

        // Función para descargar material
        function downloadMaterial(ruta, nombre) {
            // Crear enlace temporal para descarga
            const link = document.createElement('a');
            link.href = '../' + ruta;
            link.download = nombre;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.material-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.animation = `fadeInUp 0.6s ease forwards ${index * 0.05}s`;
            });
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