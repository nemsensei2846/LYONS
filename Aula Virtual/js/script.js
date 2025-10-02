// Script principal para el Aula Virtual

// Función para inicializar componentes cuando el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Inicializar validación de formularios
    initFormValidation();

    // Inicializar funcionalidades específicas según la página
    initPageSpecificFunctions();
});

// Validación de formularios
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

// Funciones específicas según la página actual
function initPageSpecificFunctions() {
    const currentPage = window.location.pathname.split('/').pop();
    
    switch(currentPage) {
        case 'login.html':
        case 'registro.html':
            // Código específico para páginas de autenticación
            break;
            
        case 'dashboard.html':
            // Inicializar componentes del dashboard
            initDashboard();
            break;
            
        case 'cursos.html':
            // Inicializar filtros de cursos
            initCourseFilters();
            break;
            
        case 'sala.html':
            // Inicializar componentes de la sala virtual
            initVirtualRoom();
            break;
            
        case 'asistencia.html':
            // Inicializar tabla de asistencia
            initAttendanceTable();
            break;
            
        case 'archivos.html':
            // Inicializar gestor de archivos
            initFileManager();
            break;
            
        case 'pago.html':
            // Inicializar procesador de pagos
            initPaymentProcessor();
            break;
    }
}

// Funciones del Dashboard
function initDashboard() {
    // Aquí se inicializarían gráficos, tablas y otros componentes del dashboard
    console.log('Dashboard inicializado');
}

// Filtros de cursos
function initCourseFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            
            // Remover clase activa de todos los botones
            filterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Agregar clase activa al botón seleccionado
            this.classList.add('active');
            
            // Filtrar cursos
            filterCourses(filter);
        });
    });
}

function filterCourses(filter) {
    const courses = document.querySelectorAll('.course-card');
    
    courses.forEach(course => {
        const category = course.getAttribute('data-category');
        
        if (filter === 'all' || category === filter) {
            course.style.display = 'block';
        } else {
            course.style.display = 'none';
        }
    });
}

// Sala Virtual
function initVirtualRoom() {
    // Aquí se inicializarían componentes de la sala virtual
    console.log('Sala virtual inicializada');
}

// Tabla de Asistencia
function initAttendanceTable() {
    // Inicializar tabla de asistencia con funcionalidades de ordenamiento y filtrado
    console.log('Tabla de asistencia inicializada');
}

// Gestor de Archivos
function initFileManager() {
    // Inicializar gestor de archivos
    const uploadForms = document.querySelectorAll('.file-upload-form');
    
    uploadForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simulación de carga de archivo
            const fileInput = this.querySelector('input[type="file"]');
            if (fileInput.files.length > 0) {
                const fileName = fileInput.files[0].name;
                showUploadingStatus(fileName);
                
                // Aquí se implementaría la lógica real de carga de archivos
                setTimeout(() => {
                    showUploadSuccess(fileName);
                    this.reset();
                }, 2000);
            }
        });
    });
}

function showUploadingStatus(fileName) {
    const statusContainer = document.getElementById('upload-status');
    if (statusContainer) {
        statusContainer.innerHTML = `<div class="alert alert-info">Subiendo archivo: ${fileName}...</div>`;
    }
}

function showUploadSuccess(fileName) {
    const statusContainer = document.getElementById('upload-status');
    if (statusContainer) {
        statusContainer.innerHTML = `<div class="alert alert-success">¡Archivo ${fileName} subido con éxito!</div>`;
        
        // Actualizar lista de archivos (en una implementación real, esto se haría con datos del servidor)
        const filesList = document.getElementById('files-list');
        if (filesList) {
            const newFile = document.createElement('div');
            newFile.className = 'file-item p-3 mb-2 bg-white rounded';
            newFile.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-file file-icon me-3"></i>
                    <div>
                        <h6 class="mb-0">${fileName}</h6>
                        <small class="text-muted">Subido ahora</small>
                    </div>
                    <div class="ms-auto">
                        <button class="btn btn-sm btn-outline-primary me-2"><i class="fas fa-download"></i></button>
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;
            filesList.prepend(newFile);
        }
    }
}

// Procesador de Pagos
function initPaymentProcessor() {
    // Selección de método de pago
    const paymentCards = document.querySelectorAll('.payment-card');
    
    paymentCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remover selección de todas las tarjetas
            paymentCards.forEach(c => c.classList.remove('selected'));
            
            // Seleccionar la tarjeta actual
            this.classList.add('selected');
            
            // Actualizar método de pago seleccionado
            const paymentMethod = this.getAttribute('data-payment');
            document.getElementById('selected-payment-method').value = paymentMethod;
        });
    });
    
    // Validación de formulario de pago
    const paymentForm = document.getElementById('payment-form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Aquí se implementaría la integración real con pasarela de pagos
            showPaymentProcessing();
            
            // Simulación de procesamiento de pago
            setTimeout(() => {
                showPaymentSuccess();
            }, 2000);
        });
    }
}

function showPaymentProcessing() {
    const statusContainer = document.getElementById('payment-status');
    if (statusContainer) {
        statusContainer.innerHTML = `
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    <div>Procesando pago...</div>
                </div>
            </div>
        `;
    }
}

function showPaymentSuccess() {
    const statusContainer = document.getElementById('payment-status');
    if (statusContainer) {
        statusContainer.innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                ¡Pago realizado con éxito! Redirigiendo...
            </div>
        `;
        
        // Redireccionar después de un pago exitoso
        setTimeout(() => {
            window.location.href = 'confirmacion-pago.html';
        }, 2000);
    }
}
