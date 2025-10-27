<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['tecnico', 'admin_tecnico']);

// EJECUTAR ARCHIVADO AUTOMÁTICO
ejecutarArchivoSiNecesario();

$userId = $_SESSION['user_id'];

// Estadísticas de tickets asignados (SOLO ACTIVOS, NO ARCHIVADOS)
$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN prioridad = 'urgente' AND estado != 'cerrado' THEN 1 ELSE 0 END) as urgentes
    FROM tickets
    WHERE tecnico_asignado_id = ?
      AND archivado = 0
";
$stats = obtenerUno($sqlStats, array($userId));

// Obtener tickets asignados (SOLO ACTIVOS, NO ARCHIVADOS)
$sqlTicketsAsignados = "
    SELECT 
        t.*,
        c.nombre as categoria_nombre,
        u.nombre + ' ' + u.apellido as usuario_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users u ON t.usuario_id = u.id
    WHERE t.tecnico_asignado_id = ?
      AND t.archivado = 0
    ORDER BY 
        CASE t.estado 
            WHEN 'abierto' THEN 1 
            WHEN 'en_progreso' THEN 2 
            WHEN 'cerrado' THEN 3 
        END,
        CASE t.prioridad
            WHEN 'urgente' THEN 1
            WHEN 'alta' THEN 2
            WHEN 'media' THEN 3
            WHEN 'baja' THEN 4
        END,
        t.created_at DESC
";

$ticketsAsignados = obtenerTodos($sqlTicketsAsignados, array($userId));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Técnico - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard-tecnico.php">
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
                <a href="dashboard-tecnico.php" class="sidebar-nav-link active">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-section-title">Gestión</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('todos'); return false;">
                    <i class="bi bi-list-ul"></i>
                    <span>Todos</span>
                    <span class="ms-auto badge bg-secondary"><?php echo $stats['total']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('urgente'); return false;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Urgentes</span>
                    <span class="ms-auto badge bg-warning"><?php echo $stats['urgentes']; ?></span>
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
                    <span>Tickets Archivados</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard Técnico</h1>
            <p class="page-subtitle">Gestiona tus tickets asignados</p>
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
                            <h6 class="mb-0">Total Asignados</h6>
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

        <!-- Kanban Board -->
        <div class="kanban-board">
            <!-- COLUMNA ABIERTO -->
            <div class="kanban-column" data-status="abierto">
                <div class="kanban-header" style="background-color: #DEEBFF;">
                    <h5 style="color: #0052CC;">
                        <i class="bi bi-circle"></i> Abiertos
                        <span class="kanban-count badge bg-primary ms-2">
                            <?php echo count(array_filter($ticketsAsignados, function($t) { 
                                return $t['estado'] == 'abierto'; 
                            })); ?>
                        </span>
                    </h5>
                </div>
                
                <?php foreach ($ticketsAsignados as $ticket): ?>
                    <?php if ($ticket['estado'] == 'abierto'): ?>
                        <div class="kanban-card" 
                             data-ticket-id="<?php echo $ticket['id']; ?>"
                             draggable="true">
                            
                            <div class="kanban-card-header">
                                <strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong>
                                <small style="color: var(--text-muted);">
                                    <?php echo formatearFechaCorta($ticket['created_at']); ?>
                                </small>
                            </div>
                            
                            <div class="kanban-card-title">
                                <?php echo e($ticket['titulo']); ?>
                            </div>
                            
                            <div class="kanban-card-footer">
                                <?php echo badgePrioridad($ticket['prioridad']); ?>
                                <div class="avatar-initials">
                                    <?php echo obtenerIniciales($ticket['usuario_nombre'], ''); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- COLUMNA EN PROGRESO -->
            <div class="kanban-column" data-status="en_progreso">
                <div class="kanban-header" style="background-color: #FFF0B3;">
                    <h5 style="color: #974F0C;">
                        <i class="bi bi-arrow-repeat"></i> En Progreso
                        <span class="kanban-count badge bg-warning ms-2">
                            <?php echo count(array_filter($ticketsAsignados, function($t) { 
                                return $t['estado'] == 'en_progreso'; 
                            })); ?>
                        </span>
                    </h5>
                </div>
                
                <?php foreach ($ticketsAsignados as $ticket): ?>
                    <?php if ($ticket['estado'] == 'en_progreso'): ?>
                        <div class="kanban-card" 
                             data-ticket-id="<?php echo $ticket['id']; ?>"
                             draggable="true">
                            
                            <div class="kanban-card-header">
                                <strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong>
                                <small style="color: var(--text-muted);">
                                    <?php echo formatearFechaCorta($ticket['created_at']); ?>
                                </small>
                            </div>
                            
                            <div class="kanban-card-title">
                                <?php echo e($ticket['titulo']); ?>
                            </div>
                            
                            <div class="kanban-card-footer">
                                <?php echo badgePrioridad($ticket['prioridad']); ?>
                                <div class="avatar-initials">
                                    <?php echo obtenerIniciales($ticket['usuario_nombre'], ''); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- COLUMNA CERRADO -->
            <div class="kanban-column" data-status="cerrado">
                <div class="kanban-header" style="background-color: #E3FCEF;">
                    <h5 style="color: #006644;">
                        <i class="bi bi-check-circle"></i> Cerrados
                        <span class="kanban-count badge bg-success ms-2">
                            <?php echo count(array_filter($ticketsAsignados, function($t) { 
                                return $t['estado'] == 'cerrado'; 
                            })); ?>
                        </span>
                    </h5>
                </div>
                
                <?php foreach ($ticketsAsignados as $ticket): ?>
                    <?php if ($ticket['estado'] == 'cerrado'): ?>
                        <div class="kanban-card" 
                             data-ticket-id="<?php echo $ticket['id']; ?>"
                             draggable="true">
                            
                            <div class="kanban-card-header">
                                <strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong>
                                <small style="color: var(--text-muted);">
                                    <?php echo formatearFechaCorta($ticket['created_at']); ?>
                                </small>
                            </div>
                            
                            <div class="kanban-card-title">
                                <?php echo e($ticket['titulo']); ?>
                            </div>
                            
                            <div class="kanban-card-footer">
                                <?php echo badgePrioridad($ticket['prioridad']); ?>
                                <div class="avatar-initials">
                                    <?php echo obtenerIniciales($ticket['usuario_nombre'], ''); ?>
                                </div>
                            </div>
 
                            <!-- NUEVO: Botones de reabrir -->
                            <div class="d-flex gap-1 mt-2">
                                <button class="btn btn-sm btn-outline-warning flex-fill" 
                                        onclick="event.stopPropagation(); reabrirTicket(<?php echo $ticket['id']; ?>)">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reabrir
                                </button>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="event.stopPropagation(); window.location.href='ver-ticket.php?id=<?php echo $ticket['id']; ?>'">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabla de Tickets -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="bi bi-table"></i> Mis Tickets Asignados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Título</th>
                                <th style="width: 150px;">Usuario</th>
                                <th style="width: 150px;">Categoría</th>
                                <th style="width: 120px;">Prioridad</th>
                                <th style="width: 120px;">Estado</th>
                                <th style="width: 140px;">Fecha</th>
                                <th style="width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ticketsAsignados as $ticket): ?>
                                <tr class="priority-<?php echo $ticket['prioridad']; ?> ticket-row" 
                                    data-estado="<?php echo $ticket['estado']; ?>"
                                    data-prioridad="<?php echo $ticket['prioridad']; ?>">
                                    <td><strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong></td>
                                    <td>
                                        <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none" style="color: var(--text-primary); font-weight: 600;">
                                            <?php echo e(substr($ticket['titulo'], 0, 40)) . (strlen($ticket['titulo']) > 40 ? '...' : ''); ?>
                                        </a>
                                    </td>
                                    <td><?php echo e($ticket['usuario_nombre']); ?></td>
                                    <td>
                                        <?php 
                                        echo $ticket['categoria_otro'] ? e($ticket['categoria_otro']) : e($ticket['categoria_nombre']);
                                        ?>
                                    </td>
                                    <td><?php echo badgePrioridad($ticket['prioridad']); ?></td>
                                    <td><?php echo badgeEstado($ticket['estado']); ?></td>
                                    <td>
                                        <small style="color: var(--text-muted);"><?php echo formatearFecha($ticket['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
</body>
</html>