<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina();

$ticketId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

// Obtener información del ticket
$sqlTicket = "
    SELECT 
        t.*,
        c.nombre as categoria_nombre,
        u.nombre + ' ' + u.apellido as usuario_nombre,
        u.email as usuario_email,
        s.nombre as sede_nombre,
        a.nombre as area_nombre,
        tec.nombre + ' ' + tec.apellido as tecnico_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users u ON t.usuario_id = u.id
    LEFT JOIN sedes s ON u.sede_id = s.id
    LEFT JOIN areas a ON u.area_id = a.id
    LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
    WHERE t.id = ?
";
$ticket = obtenerUno($sqlTicket, array($ticketId));

if (!$ticket) {
    header('Location: dashboard-usuario.php?error=ticket_no_encontrado');
    exit();
}

// Verificar permisos
$esCreador = ($ticket['usuario_id'] == $userId);
$esTecnico = tieneRol(['tecnico', 'admin_tecnico', 'gerente']);

if (!$esCreador && !$esTecnico) {
    header('Location: dashboard-usuario.php?error=sin_permisos');
    exit();
}

// Obtener imágenes del ticket
$imagenes = obtenerTodos("SELECT * FROM images WHERE ticket_id = ? AND comment_id IS NULL", array($ticketId));

// Obtener comentarios
$sqlComentarios = "
    SELECT 
        c.*,
        u.nombre + ' ' + u.apellido as autor,
        u.nombre as nombre,
        u.apellido as apellido
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.ticket_id = ?
    ORDER BY c.created_at ASC
";
$comentarios = obtenerTodos($sqlComentarios, array($ticketId));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticketId; ?> - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/components.css"> 
    <style>
.info-item-modern {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s ease;
}

.info-item-modern:hover {
    background-color: var(--bg-tertiary);
}

.info-item-modern:last-child {
    border-bottom: none;
}

.info-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.info-label-modern {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.info-value-modern {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.4;
}

/* Avatar mejorado */
.avatar-initials {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
    border-radius: 50%;
    text-transform: uppercase;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Responsive */
@media (max-width: 992px) {
    .info-item-modern {
        padding: 14px 16px;
    }
    
    .info-icon {
        width: 36px;
        height: 36px;
    }
}
</style>
</head>
<body>
    <!-- Variables globales para actualización automática -->
    <script>
        const ticketIdGlobal = <?php echo $ticketId; ?>;
        const userIdActual = <?php echo $_SESSION['user_id']; ?>;
    </script>

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo tieneRol(['usuario']) ? 'dashboard-usuario.php' : (tieneRol(['tecnico']) ? 'dashboard-tecnico.php' : (tieneRol(['admin_tecnico']) ? 'dashboard-admin.php' : 'dashboard-gerente.php')); ?>">
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
        
        <li class="nav-item">
            <a class="nav-link" href="<?php echo tieneRol(['usuario']) ? 'dashboard-usuario.php' : (tieneRol(['tecnico']) ? 'dashboard-tecnico.php' : (tieneRol(['admin_tecnico']) ? 'dashboard-admin.php' : 'dashboard-gerente.php')); ?>">
                <i class="bi bi-arrow-left"></i> Volver al Dashboard
            </a>
        </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo e($_SESSION['nombre']); ?>
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
                <a href="<?php echo tieneRol(['usuario']) ? 'dashboard-usuario.php' : (tieneRol(['tecnico']) ? 'dashboard-tecnico.php' : (tieneRol(['admin_tecnico']) ? 'dashboard-admin.php' : 'dashboard-gerente.php')); ?>" class="sidebar-nav-link">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <?php if (tieneRol(['usuario'])): ?>
            <li class="sidebar-nav-item">
                <a href="crear-ticket.php" class="sidebar-nav-link">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Crear Ticket</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-section-title">Detalles</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#info" class="sidebar-nav-link">
                    <i class="bi bi-info-circle"></i>
                    <span>Información</span>
                </a>
            </li>
            <!-- BOTONES DE ACCIÓN DE ESTADO -->
<?php
// Determinar si el usuario puede cambiar el estado
$puedeEditarEstado = false;

if ($_SESSION['rol'] == 'admin_tecnico') {
    // Admin puede editar si es el técnico asignado O si no está asignado
    $puedeEditarEstado = ($ticket['tecnico_asignado_id'] == $_SESSION['user_id']) || 
                         ($ticket['tecnico_asignado_id'] == null);
} elseif ($_SESSION['rol'] == 'tecnico') {
    // Técnico solo si está asignado a él
    $puedeEditarEstado = ($ticket['tecnico_asignado_id'] == $_SESSION['user_id']);
}

// Admin y gerente pueden archivar
$puedeArchivar = in_array($_SESSION['rol'], ['admin_tecnico', 'gerente']);
?>

<?php if ($puedeEditarEstado || $puedeArchivar): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-gear-fill"></i> Acciones del Ticket</h5>
        <span class="badge <?php echo $ticket['estado'] == 'cerrado' ? 'bg-success' : 'bg-warning'; ?>" data-estado-ticket="<?php echo $ticket['estado']; ?>">
            <?php echo ucfirst(str_replace('_', ' ', $ticket['estado'])); ?>
        </span>
    </div>
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
            
            <?php if ($puedeEditarEstado): ?>
                
                <!-- ESTADO: ABIERTO → Botón "Marcar En Progreso" -->
                <?php if ($ticket['estado'] == 'abierto'): ?>
                    <button class="btn btn-primary" onclick="cambiarEstadoTicket(<?php echo $ticket['id']; ?>, 'en_progreso')">
                        <i class="bi bi-play-circle"></i> Marcar En Progreso
                    </button>
                <?php endif; ?>
                
                <!-- ESTADO: EN PROGRESO → Botón "Cerrar Ticket" -->
                <?php if ($ticket['estado'] == 'en_progreso'): ?>
                    <button class="btn btn-success" onclick="cambiarEstadoTicket(<?php echo $ticket['id']; ?>, 'cerrado')">
                        <i class="bi bi-check-circle"></i> Cerrar Ticket
                    </button>
                <?php endif; ?>
                
                <!-- ESTADO: ABIERTO o EN PROGRESO → Botón rápido "Cerrar Directamente" -->
                <?php if ($ticket['estado'] != 'cerrado'): ?>
                    <button class="btn btn-outline-success" onclick="cambiarEstadoTicket(<?php echo $ticket['id']; ?>, 'cerrado')">
                        <i class="bi bi-check2-all"></i> Cerrar Directamente
                    </button>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <!-- BOTÓN ARCHIVAR (solo si está cerrado y es admin/gerente) -->
            <?php if ($ticket['estado'] == 'cerrado' && $puedeArchivar): ?>
                <button class="btn btn-warning" onclick="archivarTicket(<?php echo $ticket['id']; ?>)">
                    <i class="bi bi-archive"></i> Archivar Ticket
                </button>
            <?php endif; ?>
            
        </div>
        
        <!-- Mensaje informativo -->
        <div class="alert alert-info mt-3 mb-0">
            <small>
                <i class="bi bi-info-circle"></i>
                <?php if ($ticket['estado'] == 'abierto'): ?>
                    Este ticket está pendiente de ser atendido.
                <?php elseif ($ticket['estado'] == 'en_progreso'): ?>
                    Este ticket está siendo atendido actualmente.
                <?php elseif ($ticket['estado'] == 'cerrado'): ?>
                    Este ticket ha sido resuelto y cerrado.
                <?php endif; ?>
            </small>
        </div>
    </div>
</div>
<?php endif; ?>
            <li class="sidebar-nav-item">
                <a href="#comments" class="sidebar-nav-link">
                    <i class="bi bi-chat-dots"></i>
                    <span>Comentarios</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb" style="background: transparent; padding: 0; margin: 0;">
                <li class="breadcrumb-item"><a href="<?php echo tieneRol(['usuario']) ? 'dashboard-usuario.php' : (tieneRol(['tecnico']) ? 'dashboard-tecnico.php' : (tieneRol(['admin_tecnico']) ? 'dashboard-admin.php' : 'dashboard-gerente.php')); ?>" style="color: var(--jira-blue); text-decoration: none;">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Ticket #<?php echo $ticketId; ?></li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="page-header" style="border-bottom: 1px solid var(--gray-200); padding-bottom: 20px;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span style="color: var(--gray-600); font-size: 14px; font-weight: 600;">TICKET-<?php echo $ticketId; ?></span>
                        <?php echo badgeEstado($ticket['estado']); ?>
                        <?php echo badgePrioridad($ticket['prioridad']); ?>
                    </div>
                    <h1 class="page-title" style="font-size: 28px; margin-bottom: 8px;"><?php echo e($ticket['titulo']); ?></h1>
                    <p class="page-subtitle">
                        Creado por <strong><?php echo e($ticket['usuario_nombre']); ?></strong> 
                        el <?php echo formatearFecha($ticket['created_at']); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Columna Principal -->
            <div class="col-lg-8">
                <!-- Información del Ticket -->
                <div class="card mb-4" id="info">
                    <div class="card-header">
                        <h6><i class="bi bi-file-text"></i> Descripción</h6>
                    </div>
                    <div class="card-body" style="padding: 24px;">
                        <div style="line-height: 1.8; color: var(--gray-800); white-space: pre-wrap;"><?php echo nl2br(e($ticket['descripcion'])); ?></div>

                        <?php if (!empty($imagenes)): ?>
                            <div class="mt-4">
                                <strong style="color: var(--gray-900); display: block; margin-bottom: 12px;">Capturas adjuntas:</strong>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach ($imagenes as $img): ?>
                                        <a href="../public/<?php echo e($img['ruta']); ?>" target="_blank" style="display: block;">
                                            <img src="../public/<?php echo e($img['ruta']); ?>" 
                                                 alt="<?php echo e($img['nombre_original']); ?>"
                                                 class="img-thumbnail" 
                                                 style="width: 150px; height: 150px; object-fit: cover; border-radius: 4px; border: 2px solid var(--gray-200);">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Comentarios -->
                <div class="card" id="comments">
                    <div class="card-header">
                        <h6><i class="bi bi-chat-dots-fill"></i> Actividad</h6>
                    </div>
                    <div class="card-body" style="padding: 24px;">
                        <!-- Timeline de comentarios -->
                        <div class="chat-container" id="comentariosContainer" style="max-height: 500px; overflow-y: auto;">
                            <?php if (empty($comentarios)): ?>
                                <div class="text-center py-5" style="color: var(--gray-500);">
                                    <i class="bi bi-chat" style="font-size: 48px; opacity: 0.3;"></i>
                                    <p class="mt-3">No hay comentarios aún. Sé el primero en comentar.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($comentarios as $comentario): ?>
                                    <?php
                                    $esPropio = ($comentario['user_id'] == $userId);
                                    $iniciales = obtenerIniciales($comentario['nombre'], $comentario['apellido']);
                                    $imagenesComentario = obtenerTodos("SELECT * FROM images WHERE comment_id = ?", array($comentario['id']));
                                    ?>
                                    <div class="comment-item <?php echo $esPropio ? 'own-comment' : ''; ?>" data-comentario-id="<?php echo $comentario['id']; ?>">
                                        <div class="avatar-initials avatar-initials-lg">
                                            <?php echo $iniciales; ?>
                                        </div>
                                        <div class="comment-bubble">
                                            <div class="comment-author">
                                                <?php echo e($comentario['autor']); ?>
                                                <?php if ($esPropio): ?>
                                                    <span class="badge" style="background-color: var(--jira-blue); font-size: 9px; margin-left: 6px;">TÚ</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="comment-text"><?php echo nl2br(e($comentario['mensaje'])); ?></div>
                                            
                                            <?php if (!empty($imagenesComentario)): ?>
                                                <div class="comment-images mt-2">
                                                    <?php foreach ($imagenesComentario as $img): ?>
                                                        <a href="../public/<?php echo e($img['ruta']); ?>" target="_blank">
                                                            <img src="../public/<?php echo e($img['ruta']); ?>" 
                                                                 alt="<?php echo e($img['nombre_original']); ?>" 
                                                                 class="img-thumbnail" 
                                                                 style="max-width: 120px; margin-right: 8px;">
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="comment-time"><?php echo formatearFecha($comentario['created_at']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Formulario para agregar comentario -->
                        <?php if ($ticket['estado'] != 'cerrado'): ?>
                            <div style="margin-top: 32px; padding-top: 24px; border-top: 2px solid var(--gray-200);">
                                <form action="../actions/agregar-comentario.php" method="POST" enctype="multipart/form-data" id="form-comentario">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="mensaje" class="form-label" style="font-weight: 600; color: var(--gray-900);">
                                            <i class="bi bi-pencil-square"></i> Agregar Comentario
                                        </label>
                                        <textarea class="form-control" id="mensaje" name="mensaje" rows="4" 
                                                  required placeholder="Escribe tu comentario..." style="resize: vertical;"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="imagenes_comentario" class="form-label" style="font-weight: 600; color: var(--gray-900);">
                                            <i class="bi bi-paperclip"></i> Adjuntar Imágenes (Opcional)
                                        </label>
                                        <input type="file" class="form-control" id="imagenes_comentario" 
                                               name="imagenes[]" multiple accept="image/*">
                                        <small class="text-muted">Puedes adjuntar capturas de pantalla relacionadas</small>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send-fill"></i> Enviar Comentario
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-4">
                                <i class="bi bi-info-circle-fill"></i> Este ticket está cerrado. No se pueden agregar más comentarios.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Columna Lateral -->
<div class="col-lg-4">
    <div class="card" style="border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header" style="background: linear-gradient(135deg, #0052CC 0%, #0065FF 100%); color: white; padding: 16px 20px; border-bottom: none;">
            <h6 style="margin: 0; font-weight: 600; font-size: 14px;">
                <i class="bi bi-info-circle-fill"></i> Información del Ticket
            </h6>
        </div>
        <div class="card-body" style="padding: 0;">
            
            <!-- Estado -->
            <div class="info-item-modern">
                <div class="info-icon" style="background-color: #E3FCEF;">
                    <i class="bi bi-circle-fill" style="color: #006644; font-size: 16px;"></i>
                </div>
                <div>
                    <div class="info-label-modern">Estado</div>
                    <div class="info-value-modern"><?php echo badgeEstado($ticket['estado']); ?></div>
                </div>
            </div>

            <!-- Prioridad -->
            <div class="info-item-modern">
                <div class="info-icon" style="background-color: #FFF0B3;">
                    <i class="bi bi-exclamation-triangle-fill" style="color: #974F0C; font-size: 16px;"></i>
                </div>
                <div>
                    <div class="info-label-modern">Prioridad</div>
                    <div class="info-value-modern"><?php echo badgePrioridad($ticket['prioridad']); ?></div>
                </div>
            </div>

            <!-- Categoría -->
            <div class="info-item-modern">
                <div class="info-icon" style="background-color: #DEEBFF;">
                    <i class="bi bi-tag-fill" style="color: #0052CC; font-size: 16px;"></i>
                </div>
                <div>
                    <div class="info-label-modern">Categoría</div>
                    <div class="info-value-modern" style="font-weight: 500; color: var(--text-primary);">
                        <?php echo $ticket['categoria_otro'] ? e($ticket['categoria_otro']) : e($ticket['categoria_nombre']); ?>
                    </div>
                </div>
            </div>

            <!-- Creado por -->
            <div class="info-item-modern">
                <div class="info-icon" style="background-color: #E8E8E8;">
                    <i class="bi bi-person-fill" style="color: #42526E; font-size: 16px;"></i>
                </div>
                <div style="flex: 1;">
                    <div class="info-label-modern">Creado por</div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 6px;">
                        <div class="avatar-initials" style="width: 32px; height: 32px; font-size: 13px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <?php echo obtenerIniciales($ticket['usuario_nombre'], ''); ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: var(--text-primary); font-size: 14px;">
                                <?php echo e($ticket['usuario_nombre']); ?>
                            </div>
                            <small style="color: var(--text-muted); font-size: 12px;">
                                <?php echo e($ticket['usuario_email']); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sede -->
            <div class="info-item-modern">
                <div class="info-icon" style="background-color: #FFF4E6;">
                    <i class="bi bi-building" style="color: #FF8B00; font-size: 16px;"></i>
                </div>
                <div>
                    <div class="info-label-modern">Sede</div>
                    <div class="info-value-modern" style="font-weight: 500; color: var(--text-primary);">
                        <?php echo e($ticket['sede_nombre']); ?>
                    </div>
                </div>
            </div>

            <!-- Área -->
            <div class="info-item-modern">
                <div class="info-icon" style="background-color: #E6FCFF;">
                    <i class="bi bi-diagram-3-fill" style="color: #00B8D9; font-size: 16px;"></i>
                </div>
                <div>
                    <div class="info-label-modern">Área</div>
                    <div class="info-value-modern" style="font-weight: 500; color: var(--text-primary);">
                        <?php echo e($ticket['area_nombre']); ?>
                    </div>
                </div>
            </div>

            <!-- Técnico Asignado -->
            <div class="info-item-modern">
                <div class="info-icon" style="background-color: <?php echo $ticket['tecnico_asignado_id'] ? '#E3FCEF' : '#FFEBE6'; ?>;">
                    <i class="bi bi-person-check-fill" style="color: <?php echo $ticket['tecnico_asignado_id'] ? '#006644' : '#DE350B'; ?>; font-size: 16px;"></i>
                </div>
                <div style="flex: 1;">
                    <div class="info-label-modern">Técnico Asignado</div>
                    <?php if ($ticket['tecnico_asignado_id']): ?>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 6px;">
                            <div class="avatar-initials" style="width: 32px; height: 32px; font-size: 13px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <?php echo obtenerIniciales($ticket['tecnico_nombre'], ''); ?>
                            </div>
                            <div style="font-weight: 600; color: var(--text-primary); font-size: 14px;">
                                <?php echo e($ticket['tecnico_nombre']); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 6px;">
                            <span class="badge bg-secondary" style="font-size: 11px; padding: 4px 10px;">
                                <i class="bi bi-exclamation-circle"></i> Sin asignar
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fecha de Creación -->
            <div class="info-item-modern">
                <div class="info-icon" style="background-color: #F4F5F7;">
                    <i class="bi bi-calendar-plus" style="color: #42526E; font-size: 16px;"></i>
                </div>
                <div>
                    <div class="info-label-modern">Fecha de Creación</div>
                    <div class="info-value-modern" style="font-weight: 500; color: var(--text-primary);">
                        <?php echo formatearFecha($ticket['created_at']); ?>
                    </div>
                </div>
            </div>

            <?php if ($ticket['fecha_cierre']): ?>
            <!-- Fecha de Cierre -->
            <div class="info-item-modern" style="border-bottom: none;">
                <div class="info-icon" style="background-color: #E8F5E9;">
                    <i class="bi bi-calendar-check-fill" style="color: #36B37E; font-size: 16px;"></i>
                </div>
                <div>
                    <div class="info-label-modern">Fecha de Cierre</div>
                    <div class="info-value-modern" style="font-weight: 500; color: #36B37E;">
                        <?php echo formatearFecha($ticket['fecha_cierre']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
    
    <!-- SISTEMA DE ACTUALIZACIÓN AUTOMÁTICA -->
    <script>
    let ultimoComentarioId = 0;
    let estadoActual = '<?php echo $ticket['estado']; ?>';
    
    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        // Obtener último comentario ID
        const comentarios = document.querySelectorAll('[data-comentario-id]');
        if (comentarios.length > 0) {
            ultimoComentarioId = parseInt(comentarios[comentarios.length - 1].getAttribute('data-comentario-id'));
        }
        
        console.log('🔄 Actualización automática activada - Ticket:', ticketIdGlobal);
        
        // Polling cada 3 segundos
        setInterval(verificarActualizaciones, 3000);
    });
    
    function verificarActualizaciones() {
        fetch(`../actions/obtener-actualizaciones-ticket.php?ticket_id=${ticketIdGlobal}&ultimo_comentario_id=${ultimoComentarioId}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success) return;
                
                // Actualizar estado si cambió
                if (data.estado !== estadoActual) {
                    actualizarEstadoVisual(data.estado);
                    estadoActual = data.estado;
                }
                
                // Agregar nuevos comentarios
                if (data.total_nuevos > 0) {
                    agregarNuevosComentarios(data.nuevos_comentarios);
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function actualizarEstadoVisual(nuevoEstado) {
        const badge = document.querySelector('[data-estado-ticket]');
        if (badge) {
            badge.classList.remove('bg-primary', 'bg-warning', 'bg-success');
            badge.setAttribute('data-estado-ticket', nuevoEstado);
            
            if (nuevoEstado === 'abierto') {
                badge.classList.add('bg-primary');
                badge.textContent = 'Abierto';
            } else if (nuevoEstado === 'en_progreso') {
                badge.classList.add('bg-warning');
                badge.textContent = 'En progreso';
            } else if (nuevoEstado === 'cerrado') {
                badge.classList.add('bg-success');
                badge.textContent = 'Cerrado';
            }
        }
        
        // Recargar después de 2 segundos
        setTimeout(() => location.reload(), 2000);
    }
    
    function agregarNuevosComentarios(comentarios) {
        const container = document.getElementById('comentariosContainer');
        if (!container) return;
        
        comentarios.forEach(comentario => {
            if (comentario.id > ultimoComentarioId) {
                ultimoComentarioId = comentario.id;
            }
            
            const esPropio = comentario.user_id == userIdActual;
            const iniciales = (comentario.nombre.charAt(0) + comentario.apellido.charAt(0)).toUpperCase();
            
            const div = document.createElement('div');
            div.className = `comment-item ${esPropio ? 'own-comment' : ''}`;
            div.setAttribute('data-comentario-id', comentario.id);
            div.style.animation = 'slideIn 0.3s ease';
            
            div.innerHTML = `
                <div class="avatar-initials avatar-initials-lg">${iniciales}</div>
                <div class="comment-bubble">
                    <div class="comment-author">
                        ${escapeHtml(comentario.autor)}
                        ${esPropio ? '<span class="badge" style="background-color: var(--jira-blue); font-size: 9px; margin-left: 6px;">TÚ</span>' : ''}
                    </div>
                    <div class="comment-text">${escapeHtml(comentario.mensaje).replace(/\n/g, '<br>')}</div>
                    <div class="comment-time">Ahora mismo</div>
                </div>
            `;
            
            container.appendChild(div);
        });
        
        container.scrollTop = container.scrollHeight;
        
        document.title = `(${comentarios.length}) Nuevo comentario`;
        setTimeout(() => document.title = 'Ticket #<?php echo $ticketId; ?> - Sistema de Tickets', 3000);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
    
    <style>
    @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    </style>
</body>
</html>
