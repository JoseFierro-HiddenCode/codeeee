<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['usuario']);

// Obtener categorías activas
$categorias = obtenerTodos("SELECT * FROM categories WHERE activo = 1 ORDER BY orden");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Ticket - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard-usuario.php">
                <i class="bi bi-ticket-perforated-fill"></i> Sistema de Tickets
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!-- Theme Toggle -->
                    <li class="nav-item">
                        <button class="theme-toggle" id="theme-toggle" title="Cambiar tema">
                            <i class="bi bi-moon-stars-fill"></i>
                        </button>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo e($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Configuración</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="dashboard-usuario.php" class="sidebar-nav-link">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="crear-ticket.php" class="sidebar-nav-link active">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Crear Ticket</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Crear Nuevo Ticket</h1>
            <p class="page-subtitle">Completa el formulario para registrar tu solicitud de soporte</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body" style="padding: 32px;">
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle-fill"></i> Ticket creado exitosamente. 
                                <a href="ver-ticket.php?id=<?php echo $_GET['ticket_id']; ?>" class="alert-link">Ver ticket #<?php echo $_GET['ticket_id']; ?></a>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle-fill"></i> Error al crear el ticket. Intenta nuevamente.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form action="../actions/crear-ticket.php" method="POST" enctype="multipart/form-data" id="formTicket">
                            <div class="mb-4">
                                <label for="titulo" class="form-label">Título del Ticket <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required 
                                       placeholder="Describe brevemente el problema" maxlength="200">
                                <small class="text-muted">Ej: Problema con impresora del área de ventas</small>
                            </div>

                            <div class="mb-4">
                                <label for="categoria_id" class="form-label">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" id="categoria_id" name="categoria_id" required onchange="toggleCategoriaOtro()">
                                    <option value="">Selecciona una categoría</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" data-permite-otro="<?php echo $cat['permite_texto_manual']; ?>">
                                            <?php echo e($cat['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4" id="categoriaOtroContainer" style="display: none;">
                                <label for="categoria_otro" class="form-label">Especifica la categoría</label>
                                <input type="text" class="form-control" id="categoria_otro" name="categoria_otro" 
                                       placeholder="Ej: Problema con sistema de nómina">
                            </div>

                            <div class="mb-4">
                                <label for="prioridad" class="form-label">Prioridad <span class="text-danger">*</span></label>
                                <select class="form-select" id="prioridad" name="prioridad" required>
                                    <option value="baja">🟢 Baja - Puede esperar varios días</option>
                                    <option value="media" selected>🟡 Media - Importante pero no urgente</option>
                                    <option value="alta">🟠 Alta - Requiere atención pronto</option>
                                    <option value="urgente">🔴 Urgente - Requiere atención inmediata</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="descripcion" class="form-label">Descripción del Problema <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="6" required
                                          placeholder="Describe detalladamente el problema que estás experimentando..."></textarea>
                                <small class="text-muted">Incluye toda la información relevante: qué estabas haciendo, qué esperabas que pasara, y qué pasó en realidad</small>
                            </div>

                            <div class="mb-4">
                                <label for="imagenes" class="form-label">Capturas de Pantalla (Opcional)</label>
                                <input type="file" class="form-control" id="imagenes" name="imagenes[]" multiple accept="image/*"
                                       onchange="previewImagenes()">
                                <small class="text-muted">Adjunta capturas del problema (máximo 5 imágenes, 5MB cada una)</small>
                                <div id="imagenesPreview" class="mt-3 d-flex gap-2 flex-wrap"></div>
                            </div>

                            <div class="d-flex gap-2 justify-content-end pt-3" style="border-top: 1px solid var(--border-color);">
                                <a href="dashboard-usuario.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send-fill"></i> Crear Ticket
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar Tips -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="bi bi-lightbulb"></i> Consejos</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong style="color: var(--jira-blue);">✓ Sé específico</strong>
                            <p class="mb-0 mt-1" style="font-size: 13px; color: var(--text-secondary);">Describe el problema con el mayor detalle posible</p>
                        </div>
                        <div class="mb-3">
                            <strong style="color: var(--jira-blue);">✓ Adjunta capturas</strong>
                            <p class="mb-0 mt-1" style="font-size: 13px; color: var(--text-secondary);">Las imágenes ayudan a resolver el problema más rápido</p>
                        </div>
                        <div class="mb-3">
                            <strong style="color: var(--jira-blue);">✓ Selecciona la prioridad correcta</strong>
                            <p class="mb-0 mt-1" style="font-size: 13px; color: var(--text-secondary);">Esto nos ayuda a atender tu solicitud apropiadamente</p>
                        </div>
                        <div>
                            <strong style="color: var(--jira-blue);">✓ Revisa tu ticket</strong>
                            <p class="mb-0 mt-1" style="font-size: 13px; color: var(--text-secondary);">Podrás ver el progreso y agregar comentarios</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
    <script>
        function toggleCategoriaOtro() {
            const select = document.getElementById('categoria_id');
            const selectedOption = select.options[select.selectedIndex];
            const permiteOtro = selectedOption.getAttribute('data-permite-otro') === '1';
            const container = document.getElementById('categoriaOtroContainer');
            const input = document.getElementById('categoria_otro');
            
            if (permiteOtro) {
                container.style.display = 'block';
                input.required = true;
            } else {
                container.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }

        function previewImagenes() {
            const input = document.getElementById('imagenes');
            const preview = document.getElementById('imagenesPreview');
            preview.innerHTML = '';
            
            if (input.files) {
                Array.from(input.files).forEach((file, index) => {
                    if (index < 5) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            preview.innerHTML += `
                                <div style="position: relative;">
                                    <img src="${e.target.result}" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                                    <div style="position: absolute; top: 4px; right: 4px; background: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 10px;">
                                        ✓
                                    </div>
                                </div>
                            `;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        }
    </script>
</body>
</html>