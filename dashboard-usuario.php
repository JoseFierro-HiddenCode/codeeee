<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['usuario']);

// EJECUTAR ARCHIVADO AUTOMÁTICO
ejecutarArchivoSiNecesario();

$userId = $_SESSION['user_id'];

// Estadísticas de tickets del usuario (SOLO ACTIVOS, NO ARCHIVADOS)
$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados
    FROM tickets
    WHERE usuario_id = ?
      AND archivado = 0
";
$stats = obtenerUno($sqlStats, array($userId));

// Obtener mis tickets (SOLO ACTIVOS, NO ARCHIVADOS)
$sqlMisTickets = "
    SELECT 
        t.*,
        c.nombre as categoria_nombre,
        tec.nombre + ' ' + tec.apellido as tecnico_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
    WHERE t.usuario_id = ?
      AND t.archivado = 0
    ORDER BY 
        CASE t.estado 
            WHEN 'abierto' THEN 1 
            WHEN 'en_progreso' THEN 2 
            WHEN 'cerrado' THEN 3 
        END,
        t.created_at DESC
";

$misTickets = obtenerTodos($sqlMisTickets, array($userId));

// Obtener categorías para nuevo ticket
$categorias = obtenerTodos("SELECT * FROM categories WHERE activo = 1 ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Usuario - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/ticket-actions.css">
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
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo e($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
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
                <a href="dashboard-usuario.php" class="sidebar-nav-link active">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" data-bs-toggle="modal" data-bs-target="#modalNuevoTicket">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Nuevo Ticket</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-section-title">Mis Tickets</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('todos'); return false;">
                    <i class="bi bi-list-ul"></i>
                    <span>Todos</span>
                    <span class="ms-auto badge bg-secondary"><?php echo $stats['total']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('abierto'); return false;">
                    <i class="bi bi-circle"></i>
                    <span>Abiertos</span>
                    <span class="ms-auto badge bg-primary"><?php echo $stats['abiertos']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('en_progreso'); return false;">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>En Progreso</span>
                    <span class="ms-auto badge bg-info"><?php echo $stats['en_progreso']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('cerrado'); return false;">
                    <i class="bi bi-check-circle"></i>
                    <span>Cerrados</span>
                    <span class="ms-auto badge bg-success"><?php echo $stats['cerrados']; ?></span>
                </a>
            </li>
        </ul>

        <div class="sidebar-section-title">Archivo</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="tickets-archivados.php" class="sidebar-nav-link">
                    <i class="bi bi-archive"></i>
                    <span>Mis Archivados</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Mis Tickets</h1>
                <p class="page-subtitle">Visualiza y gestiona tus solicitudes</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoTicket">
                <i class="bi bi-plus-circle"></i> Crear Ticket
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon me-3" style="background-color: #DEEBFF;">
                            <i class="bi bi-ticket-perforated" style="color: #0052CC;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Total</h6>
                            <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon me-3" style="background-color: #E3FCEF;">
                            <i class="bi bi-circle" style="color: #006644;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Abiertos</h6>
                            <h3 class="mb-0"><?php echo $stats['abiertos']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon me-3" style="background-color: #FFF0B3;">
                            <i class="bi bi-arrow-repeat" style="color: #974F0C;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">En Progreso</h6>
                            <h3 class="mb-0"><?php echo $stats['en_progreso']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon me-3" style="background-color: #E8F5E9;">
                            <i class="bi bi-check-circle" style="color: #36B37E;"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Cerrados</h6>
                            <h3 class="mb-0"><?php echo $stats['cerrados']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

      <!-- Tabla de Tickets -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-table"></i> Mis Tickets</h5>
    </div>
    <div class="card-body">
        <?php if (count($misTickets) > 0): ?>
            <div style="padding: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 80px;">ID</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left;">TÍTULO</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">CATEGORÍA</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">TÉCNICO ASIGNADO</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">PRIORIDAD</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">ESTADO</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">FECHA</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 100px;">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($misTickets as $ticket): ?>
                            <tr class="priority-<?php echo $ticket['prioridad']; ?> ticket-row" 
                                data-estado="<?php echo $ticket['estado']; ?>"
                                data-prioridad="<?php echo $ticket['prioridad']; ?>"
                                style="transition: background-color 0.2s ease;"
                                onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                                onmouseout="this.style.backgroundColor='transparent'">
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <strong style="color: var(--jira-blue); font-weight: 700;">#<?php echo $ticket['id']; ?></strong>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                    <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" style="color: var(--text-primary); font-weight: 600; text-decoration: none;">
                                        <?php echo e(substr($ticket['titulo'], 0, 50)) . (strlen($ticket['titulo']) > 50 ? '...' : ''); ?>
                                    </a>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                                    <?php echo $ticket['categoria_otro'] ? e($ticket['categoria_otro']) : e($ticket['categoria_nombre']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                    <?php if ($ticket['tecnico_nombre']): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div class="avatar-initials" style="width: 24px; height: 24px; font-size: 10px;">
                                                <?php echo obtenerIniciales($ticket['tecnico_nombre'], ''); ?>
                                            </div>
                                            <small style="color: var(--text-secondary);"><?php echo e($ticket['tecnico_nombre']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <?php echo badgePrioridad($ticket['prioridad']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <?php echo badgeEstado($ticket['estado']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                    <small style="color: var(--text-muted);"><?php echo formatearFecha($ticket['created_at']); ?></small>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 4rem; color: var(--text-muted);"></i>
                <h4 class="mt-3">No tienes tickets aún</h4>
                <p class="text-muted">Crea tu primer ticket para solicitar soporte técnico</p>
                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalNuevoTicket">
                    <i class="bi bi-plus-circle"></i> Crear Primer Ticket
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

    <!-- Modal Nuevo Ticket -->
    <div class="modal fade" id="modalNuevoTicket" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle-fill"></i> Crear Nuevo Ticket
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../actions/crear-ticket.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">
                                <i class="bi bi-card-heading"></i> Título del Ticket *
                            </label>
                            <input type="text" class="form-control" name="titulo" required 
                                   placeholder="Ej: Problema con la impresora de oficina">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600;">
                                    <i class="bi bi-tag"></i> Categoría *
                                </label>
                                <select class="form-select" name="categoria_id" id="categoriaSelect" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo e($cat['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3" id="categoriaOtroDiv" style="display: none;">
                                <label class="form-label" style="font-weight: 600;">Especificar categoría</label>
                                <input type="text" class="form-control" name="categoria_otro" id="categoriaOtro" 
                                       placeholder="Especifica la categoría">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600;">
                                    <i class="bi bi-exclamation-triangle"></i> Prioridad *
                                </label>
                                <select class="form-select" name="prioridad" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="baja">🟢 Baja</option>
                                    <option value="media" selected>🟡 Media</option>
                                    <option value="alta">🟠 Alta</option>
                                    <option value="urgente">🔴 Urgente</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">
                                <i class="bi bi-chat-left-text"></i> Descripción del problema *
                            </label>
                            <textarea class="form-control" name="descripcion" rows="5" required 
                                      placeholder="Describe detalladamente el problema que estás experimentando..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">
                                <i class="bi bi-image"></i> Adjuntar imagen (opcional)
                            </label>
                            <input type="file" class="form-control" name="imagen" accept="image/*">
                            <small class="text-muted">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Crear Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mostrar campo "Otro" cuando se selecciona categoría "Otro"
        document.getElementById('categoriaSelect').addEventListener('change', function() {
            const otroDiv = document.getElementById('categoriaOtroDiv');
            const otroInput = document.getElementById('categoriaOtro');
            
            if (this.value === 'otro') {
                otroDiv.style.display = 'block';
                otroInput.required = true;
            } else {
                otroDiv.style.display = 'none';
                otroInput.required = false;
                otroInput.value = '';
            }
        });
    </script>

    <?php include '../modals/modal-editar-ticket.php'; ?>
<?php include '../modals/modal-confirmar-eliminar.php'; ?>
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
</body>
</html>