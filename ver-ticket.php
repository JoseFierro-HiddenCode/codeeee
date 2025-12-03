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
    <link rel="icon" type="image/png" href="../img/LogoGris.png">
    <title>Ticket #<?php echo $ticketId; ?> - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/components.css"> 
    <link rel="stylesheet" href="../public/css/chat-comentarios.css">
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
            <a class="navbar-brand d-flex align-items-center" href="<?php echo obtenerDashboardUrl(); ?>">
             <img src="../img/LogoGris.png" alt="Logo ACP" style="height: 40px;" class="me-2">
                <span class="fw-semibold">Sistema de Tickets</span>
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
            <a class="nav-link" href="<?php echo obtenerDashboardUrl(); ?>">
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
            <a href="<?php echo obtenerDashboardUrl(); ?>" class="sidebar-nav-link">
             <i class="bi bi-speedometer2"></i>
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
                <li class="breadcrumb-item">
                <a href="<?php echo obtenerDashboardUrl(); ?>">Dashboard</a>
                </li>
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

                <!-- SECCIÓN DE COMENTARIOS ESTILO WHATSAPP -->
                <div class="card" id="comments">
                    <div class="card-header">
                        <h6><i class="bi bi-chat-dots-fill"></i> Actividad</h6>
                    </div>
                    <div class="card-body p-0">
                        <!-- Contenedor del chat -->
                        <div class="chat-container" id="chatContainer">
                            <?php if (empty($comentarios)): ?>
                                <div class="chat-empty">
                                    <i class="bi bi-chat-dots"></i>
                                    <p>No hay comentarios aún. Sé el primero en comentar.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($comentarios as $comentario): ?>
                                    <?php 
                                    $esPropio = ($comentario['user_id'] == $userId);
                                    $clasePropio = $esPropio ? 'own' : 'other';
                                    $iniciales = obtenerIniciales($comentario['nombre'], $comentario['apellido']);
                                    $nombreCompleto = e($comentario['autor']);
                                    $imagenesComentario = obtenerTodos("SELECT * FROM images WHERE comment_id = ?", array($comentario['id']));
                                    ?>
                                    
                                    <div class="chat-message <?php echo $clasePropio; ?>" data-comentario-id="<?php echo $comentario['id']; ?>">
                                        <?php if (!$esPropio): ?>
                                        <div class="chat-avatar"><?php echo $iniciales; ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="chat-bubble-wrapper">
                                            <?php if (!$esPropio): ?>
                                            <div class="chat-user-name">
                                                <?php echo $nombreCompleto; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="chat-bubble">
                                                <div class="chat-text"><?php echo nl2br(e($comentario['mensaje'])); ?></div>
                                                
                                                <?php if (!empty($imagenesComentario)): ?>
                                                <div class="chat-images">
                                                    <?php foreach ($imagenesComentario as $img): ?>
                                                    <div class="chat-image-thumbnail" onclick="abrirImagen('../public/<?php echo e($img['ruta']); ?>')">
                                                        <img src="../public/<?php echo e($img['ruta']); ?>" alt="<?php echo e($img['nombre_original']); ?>">
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="chat-timestamp">
                                                <?php echo formatearFecha($comentario['created_at']); ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($esPropio): ?>
                                        <div class="chat-avatar"><?php echo $iniciales; ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Composer (caja de entrada) -->
                        <?php if ($ticket['estado'] != 'cerrado'): ?>
                        <div class="chat-composer">
                            <form id="formComentario" action="../actions/agregar-comentario.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                                
                                <!-- Preview de imágenes -->
                                <div class="chat-preview-images" id="previewImages" style="display: none;"></div>
                                
                                <!-- Input container -->
                                <div class="chat-input-container">
                                    <!-- Botón adjuntar -->
                                    <button type="button" class="chat-attach-btn" onclick="event.preventDefault(); event.stopPropagation(); document.getElementById('chatFileInput').click();" title="Adjuntar imágenes">
                                        <i class="bi bi-paperclip"></i>
                                    </button>
                                    
                                    <!-- Input de archivo (oculto) -->
                                    <input type="file" 
                                           id="chatFileInput" 
                                           name="imagenes[]" 
                                           class="chat-file-input" 
                                           accept="image/*" 
                                           multiple 
                                           onchange="previewImagenes(this)">
                                    
                                    <!-- Textarea -->
                                    <textarea class="chat-input" 
                                          id="chatInput" 
                                          name="mensaje" 
                                          placeholder="Escribe un mensaje..."
                                          rows="1"></textarea>
                                    
                                    <!-- Botón enviar -->
                                    <button type="submit" class="chat-send-btn" id="chatSendBtn" disabled>
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info m-3">
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
        
        //console.log('🔄 Actualización automática activada - Ticket:', ticketIdGlobal);
        
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

   <script>
// ========================================
// CHAT ESTILO WHATSAPP - FUNCIONALIDADES
// ========================================

const chatInput = document.getElementById('chatInput');
const chatSendBtn = document.getElementById('chatSendBtn');
const chatContainer = document.getElementById('chatContainer');
const formComentario = document.getElementById('formComentario');
const chatFileInput = document.getElementById('chatFileInput');
let ultimoIdComentario = 0; // ID del último comentario cargado
let actualizando = false; // Bandera para evitar actualizaciones simultáneas

if (chatInput && chatSendBtn && chatContainer && formComentario) {
    
    // Obtener el ID del último comentario actual
    function obtenerUltimoId() {
        const mensajes = chatContainer.querySelectorAll('.chat-message');
        if (mensajes.length > 0) {
            const ultimoMensaje = mensajes[mensajes.length - 1];
            const dataId = ultimoMensaje.getAttribute('data-comentario-id');
            if (dataId) {
                ultimoIdComentario = parseInt(dataId);
            }
        }
    }
    
    // Inicializar
    obtenerUltimoId();
    
    // Auto-resize del textarea
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        actualizarBotonEnviar();
    });

    // Actualizar estado del botón enviar
    function actualizarBotonEnviar() {
        const hayTexto = chatInput.value.trim() !== '';
        const hayImagenes = chatFileInput && chatFileInput.files.length > 0;
        chatSendBtn.disabled = !(hayTexto || hayImagenes);
    }

    // Enter para enviar, Shift+Enter para nueva línea
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            e.stopPropagation();
            
            const hayTexto = this.value.trim() !== '';
            const hayImagenes = chatFileInput && chatFileInput.files.length > 0;
            
            if (hayTexto || hayImagenes) {
                enviarComentario();
            }
            
            return false;
        }
    });

    // Prevenir submit tradicional del formulario
    formComentario.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        enviarComentario();
        return false;
    });

    // Función para enviar comentario
    function enviarComentario() {
        const formData = new FormData(formComentario);
        
        // Si no hay mensaje, agregar uno vacío
        if (!chatInput.value.trim() && chatFileInput.files.length > 0) {
            formData.set('mensaje', ' ');
        }
        
        // Deshabilitar botón mientras se envía
        chatSendBtn.disabled = true;
        const textoOriginal = chatSendBtn.innerHTML;
        chatSendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

        fetch('../actions/agregar-comentario.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            //console.log('Comentario enviado');
            
            // Limpiar formulario
            chatInput.value = '';
            chatInput.style.height = 'auto';
            chatFileInput.value = '';
            document.getElementById('previewImages').innerHTML = '';
            document.getElementById('previewImages').style.display = 'none';
            
            // Restaurar botón
            chatSendBtn.innerHTML = textoOriginal;
            chatSendBtn.disabled = true;
            
            // Esperar 500ms y actualizar (para que el servidor procese)
            setTimeout(() => {
                cargarNuevosComentarios();
            }, 500);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Error al enviar el comentario');
            chatSendBtn.innerHTML = textoOriginal;
            actualizarBotonEnviar();
        });
    }

    // Cargar SOLO nuevos comentarios (sin parpadeo)
    function cargarNuevosComentarios() {
        if (actualizando) return; // Evitar múltiples llamadas simultáneas
        actualizando = true;
        
        const wasAtBottom = (chatContainer.scrollHeight - chatContainer.scrollTop) <= (chatContainer.clientHeight + 100);
        
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nuevosChatMessages = doc.querySelectorAll('#chatContainer .chat-message');
                
                if (nuevosChatMessages.length > 0) {
                    // Obtener mensajes actuales
                    const mensajesActuales = chatContainer.querySelectorAll('.chat-message');
                    const cantidadActual = mensajesActuales.length;
                    const cantidadNueva = nuevosChatMessages.length;
                    
                    // Si hay más mensajes nuevos
                    if (cantidadNueva > cantidadActual) {
                        // Remover mensaje de "chat vacío" si existe
                        const chatEmpty = chatContainer.querySelector('.chat-empty');
                        if (chatEmpty) {
                            chatEmpty.remove();
                        }
                        
                        // Agregar SOLO los mensajes nuevos
                        for (let i = cantidadActual; i < cantidadNueva; i++) {
                            const nuevoMensaje = nuevosChatMessages[i].cloneNode(true);
                            
                            // Agregar animación de entrada
                            nuevoMensaje.style.opacity = '0';
                            nuevoMensaje.style.transform = 'translateY(20px)';
                            
                            chatContainer.appendChild(nuevoMensaje);
                            
                            // Animar entrada
                            setTimeout(() => {
                                nuevoMensaje.style.transition = 'all 0.3s ease';
                                nuevoMensaje.style.opacity = '1';
                                nuevoMensaje.style.transform = 'translateY(0)';
                            }, 50);
                        }
                        
                        // Scroll al final si estaba al final
                        if (wasAtBottom) {
                            setTimeout(scrollToBottom, 100);
                        }
                        
                        // Actualizar último ID
                        obtenerUltimoId();
                    }
                }
                
                actualizando = false;
            })
            .catch(error => {
                console.error('Error al actualizar comentarios:', error);
                actualizando = false;
            });
    }

    // Auto-scroll al último mensaje
    function scrollToBottom() {
        chatContainer.scrollTo({
            top: chatContainer.scrollHeight,
            behavior: 'smooth'
        });
    }

    // Scroll al cargar la página
    window.addEventListener('load', scrollToBottom);

    // Auto-actualización cada 2 segundos (más rápido y sin parpadeo)
    setInterval(cargarNuevosComentarios, 2000);
}

// Preview de imágenes
function previewImagenes(input) {
    const previewContainer = document.getElementById('previewImages');
    const files = Array.from(input.files);
    
    // Validar máximo 5 imágenes
    if (files.length > 5) {
        alert('⚠️ Máximo 5 imágenes por comentario');
        input.value = '';
        actualizarBotonEnviar();
        return;
    }
    
    if (files.length === 0) {
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
        actualizarBotonEnviar();
        return;
    }
    
    // Mostrar preview
    previewContainer.style.display = 'flex';
    previewContainer.innerHTML = '';
    
    files.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewItem = document.createElement('div');
            previewItem.className = 'chat-preview-item';
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="chat-preview-remove" onclick="eliminarImagenPreview(${index})">
                    ×
                </button>
            `;
            previewContainer.appendChild(previewItem);
        };
        reader.readAsDataURL(file);
    });
    
    // Actualizar botón enviar
    actualizarBotonEnviar();
}

// Eliminar imagen del preview
function eliminarImagenPreview(index) {
    const input = document.getElementById('chatFileInput');
    const dt = new DataTransfer();
    const filesArray = Array.from(input.files);
    
    filesArray.forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    input.files = dt.files;
    previewImagenes(input);
}

// Función auxiliar para actualizar botón
function actualizarBotonEnviar() {
    const chatInput = document.getElementById('chatInput');
    const chatSendBtn = document.getElementById('chatSendBtn');
    const chatFileInput = document.getElementById('chatFileInput');
    
    if (chatInput && chatSendBtn) {
        const hayTexto = chatInput.value.trim() !== '';
        const hayImagenes = chatFileInput && chatFileInput.files.length > 0;
        chatSendBtn.disabled = !(hayTexto || hayImagenes);
    }
}

// Abrir imagen en nueva pestaña
function abrirImagen(url) {
    window.open(url, '_blank');
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