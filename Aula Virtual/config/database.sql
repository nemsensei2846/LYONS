-- ==========================================================
-- 📘 BASE DE DATOS COMPLETA: Aula Virtual
-- ==========================================================
CREATE DATABASE IF NOT EXISTS aula_virtual;
USE aula_virtual;

-- ==========================================================
-- 🧑‍💻 Tabla de usuarios
-- ==========================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('docente', 'estudiante', 'admin') NOT NULL DEFAULT 'estudiante',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    avatar VARCHAR(255) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    fecha_nacimiento DATE DEFAULT NULL
);

-- ==========================================================
-- 🏷️ Tabla de categorías
-- ==========================================================
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    color VARCHAR(7) DEFAULT '#667eea',
    icono VARCHAR(50) DEFAULT 'fas fa-book',
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================================
-- 📚 Tabla de cursos
-- ==========================================================
CREATE TABLE cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    docente_id INT NOT NULL,
    categoria_id INT DEFAULT NULL,
    cupo_maximo INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio DATE,
    fecha_fin DATE,
    activo BOOLEAN DEFAULT TRUE,
    imagen VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
);

-- ==========================================================
-- 🧾 Tabla de inscripciones
-- ==========================================================
CREATE TABLE inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    curso_id INT NOT NULL,
    fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'completado', 'suspendido') DEFAULT 'activo',
    progreso DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_inscripcion (estudiante_id, curso_id)
);

-- ==========================================================
-- 🎓 Tabla de clases/lecciones
-- ==========================================================
CREATE TABLE clases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    contenido LONGTEXT,
    orden_clase INT NOT NULL,
    tipo_clase ENUM('video', 'texto', 'archivo', 'quiz') DEFAULT 'texto',
    archivo_url VARCHAR(255) DEFAULT NULL,
    duracion_minutos INT DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);

-- ==========================================================
-- 📂 Tabla de materiales
-- ==========================================================
CREATE TABLE materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clase_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo_archivo VARCHAR(50) NOT NULL,
    tamaño_archivo BIGINT DEFAULT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clase_id) REFERENCES clases(id) ON DELETE CASCADE
);

-- ==========================================================
-- 📈 Tabla de progreso de estudiantes
-- ==========================================================
CREATE TABLE progreso_estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    clase_id INT NOT NULL,
    completado BOOLEAN DEFAULT FALSE,
    fecha_completado TIMESTAMP NULL,
    tiempo_dedicado INT DEFAULT 0,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (clase_id) REFERENCES clases(id) ON DELETE CASCADE,
    UNIQUE KEY unique_progreso (estudiante_id, clase_id)
);

-- ==========================================================
-- 💬 Tabla de foros
-- ==========================================================
CREATE TABLE foros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);

-- ==========================================================
-- 💭 Tabla de mensajes del foro
-- ==========================================================
CREATE TABLE mensajes_foro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    foro_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    fecha_mensaje TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mensaje_padre_id INT DEFAULT NULL,
    FOREIGN KEY (foro_id) REFERENCES foros(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (mensaje_padre_id) REFERENCES mensajes_foro(id) ON DELETE CASCADE
);

-- ==========================================================
-- 🕓 Tabla de historial de actividades
-- ==========================================================
CREATE TABLE historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(255),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ==========================================================
-- 🧮 Tabla de calificaciones
-- ==========================================================
CREATE TABLE calificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    curso_id INT NOT NULL,
    nota DECIMAL(5,2) DEFAULT 0.00,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);

-- ==========================================================
-- 🔔 Tabla de notificaciones internas
-- ==========================================================
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255),
    mensaje TEXT,
    leido BOOLEAN DEFAULT FALSE,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ==========================================================
-- ⚡ Índices para rendimiento
-- ==========================================================
CREATE INDEX idx_email ON usuarios(email);
CREATE INDEX idx_nombre_curso ON cursos(nombre);
CREATE INDEX idx_titulo_clase ON clases(titulo);

-- ==========================================================
-- 📦 Datos iniciales
-- ==========================================================
-- Categorías
INSERT INTO categorias (nombre, descripcion, color, icono) VALUES
('Programación', 'Cursos de desarrollo de software y programación', '#667eea', 'fas fa-code'),
('Matemáticas', 'Cursos de matemáticas y ciencias exactas', '#48bb78', 'fas fa-calculator'),
('Ciencias', 'Cursos de ciencias naturales y experimentales', '#ed8936', 'fas fa-flask'),
('Idiomas', 'Cursos de idiomas y comunicación', '#f56565', 'fas fa-language'),
('Arte', 'Cursos de arte, diseño y creatividad', '#9f7aea', 'fas fa-palette'),
('Negocios', 'Cursos de administración y negocios', '#38b2ac', 'fas fa-briefcase');

-- Usuario administrador
INSERT INTO usuarios (nombre, apellido, email, password, tipo_usuario) 
VALUES ('Admin', 'Sistema', 'admin@aulavirtual.com', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Usuarios de ejemplo
INSERT INTO usuarios (nombre, apellido, email, password, tipo_usuario) VALUES
('María', 'García', 'maria.garcia@email.com', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'docente'),
('Juan', 'Pérez', 'juan.perez@email.com', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estudiante'),
('Ana', 'López', 'ana.lopez@email.com', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estudiante');

-- Cursos de ejemplo
INSERT INTO cursos (nombre, descripcion, codigo, docente_id, categoria_id, cupo_maximo) VALUES
('Introducción a PHP', 'Aprende los fundamentos de PHP para desarrollo web', 'PHP101', 2, 1, 30),
('Matemáticas Básicas', 'Curso de matemáticas fundamentales', 'MAT101', 2, 2, 25),
('Inglés Básico', 'Curso introductorio de inglés', 'ENG101', 2, 4, 20);
