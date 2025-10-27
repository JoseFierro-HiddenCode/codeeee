<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['tecnico']);

$userId = $_SESSION['user_id'];

// Obtener estadísticas del técnico
$sqlStats = "
    SELECT 
        COUNT(*) as total_asignados,
        SUM(CASE WHEN estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados
    FROM tickets 
    WHERE tecnico_asignado_id = ?
";
$stats = obtenerUno($sqlStats, array($userId));

// Obtener tickets asignados al técnico
$sqlTickets = "
    SELECT 
        t.*,
        c.nombre as categoria_nombre,
        u.nombre + ' ' + u.apellido as usuario_nombre,
        s.nombre as sede_nombre,
        a.nombre as area_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users u ON t.usuario_id = u.id
    LEFT JOIN sedes s ON u.sede_id = s.id
    LEFT JOIN areas a ON u.area_id = a.id
    WHERE t.tecnico_asignado_id = ?
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
$ticketsAsignados = obtenerTodos($sqlTickets, array($userId));

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
                <a href="dashboard-tecnico.php" class="sidebar-nav-link active">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-section-title">Mis Tickets</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('todos'); return false;">
                    <i class="bi bi-list-ul"></i>
                    <span>Todos</span>
                    <span class="ms-auto badge bg-secondary"><?php echo $stats['total_asignados']; ?></span>
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
                    <span class="ms-auto badge bg-warning"><?php echo $stats['en_progreso']; ?></span>
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
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Panel de Técnico</h1>
            <p class="page-subtitle">Bienvenido, <?php echo e($_SESSION['nombre']); ?>. Gestiona tus tickets asignados.</p>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6>Asignados</h6>
                            <h2><?php echo $stats['total_asignados']; ?></h2>
                        </div>
                        <div class="stat-icon" style="background-color: #DEEBFF;">
                            <i class="bi bi-person-check" style="color: #0052CC;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6>Abiertos</h6>
                            <h2><?php echo $stats['abiertos']; ?></h2>
                        </div>
                        <div class="stat-icon" style="background-color: #E3FCEF;">
                            <i class="bi bi-circle" style="color: #006644;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6>En Progreso</h6>
                            <h2><?php echo $stats['en_progreso']; ?></h2>
                        </div>
                        <div class="stat-icon" style="background-color: #FFF0B3;">
                            <i class="bi bi-arrow-repeat" style="color: #974F0C;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6>Cerrados</h6>
                            <h2><?php echo $stats['cerrados']; ?></h2>
                        </div>
                        <div class="stat-icon" style="background-color: #E8F5E9;">
                            <i class="bi bi-check-circle" style="color: #36B37E;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanban Board with Drag & Drop -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 style="margin: 0; color: var(--text-primary);"><i class="bi bi-kanban"></i> Mis Tickets</h5>
                <small class="text-muted">💡 Arrastra las tarjetas para cambiar su estado</small>
            </div>
            
            <div class="kanban-board">
                <!-- Columna Abiertos -->
                <div class="kanban-column" data-status="abierto">
                    <div class="kanban-header">
                        <div class="kanban-title">
                            <i class="bi bi-circle-fill" style="color: #0052CC; font-size: 8px;"></i>
                            Abiertos
                        </div>
                        <div class="kanban-count"><?php echo $stats['abiertos']; ?></div>
                    </div>
                    <?php foreach ($ticketsAsignados as $ticket): ?>
                        <?php if ($ticket['estado'] == 'abierto'): ?>
                            <div class="kanban-card priority-<?php echo $ticket['prioridad']; ?>" 
                                 draggable="true"
                                 data-ticket-id="<?php echo $ticket['id']; ?>">
                                <div class="kanban-card-id">#<?php echo $ticket['id']; ?></div>
                                <div class="kanban-card-title"><?php echo e($ticket['titulo']); ?></div>
                                <div style="margin: 8px 0;">
                                    <?php echo badgePrioridad($ticket['prioridad']); ?>
                                </div>
                                <div class="kanban-card-meta">
                                    <small style="color: var(--text-muted);">
                                        <i class="bi bi-person"></i> <?php echo e(substr($ticket['usuario_nombre'], 0, 15)); ?>
                                    </small>
                                    <small style="color: var(--text-muted);">
                                        <?php echo formatearFechaCorta($ticket['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($stats['abiertos'] == 0): ?>
                        <p class="text-muted text-center mt-3" style="font-size: 13px;">Sin tickets</p>
                    <?php endif; ?>
                </div>

                <!-- Columna En Progreso -->
                <div class="kanban-column" data-status="en_progreso">
                    <div class="kanban-header">
                        <div class="kanban-title">
                            <i class="bi bi-arrow-repeat" style="color: #FFAB00; font-size: 12px;"></i>
                            En Progreso
                        </div>
                        <div class="kanban-count"><?php echo $stats['en_progreso']; ?></div>
                    </div>
                    <?php foreach ($ticketsAsignados as $ticket): ?>
                        <?php if ($ticket['estado'] == 'en_progreso'): ?>
                            <div class="kanban-card priority-<?php echo $ticket['prioridad']; ?>" 
                                 draggable="true"
                                 data-ticket-id="<?php echo $ticket['id']; ?>">
                                <div class="kanban-card-id">#<?php echo $ticket['id']; ?></div>
                                <div class="kanban-card-title"><?php echo e($ticket['titulo']); ?></div>
                                <div style="margin: 8px 0;">
                                    <?php echo badgePrioridad($ticket['prioridad']); ?>
                                </div>
                                <div class="kanban-card-meta">
                                    <small style="color: var(--text-muted);">
                                        <i class="bi bi-person"></i> <?php echo e(substr($ticket['usuario_nombre'], 0, 15)); ?>
                                    </small>
                                    <small style="color: var(--text-muted);">
                                        <?php echo formatearFechaCorta($ticket['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($stats['en_progreso'] == 0): ?>
                        <p class="text-muted text-center mt-3" style="font-size: 13px;">Sin tickets</p>
                    <?php endif; ?>
                </div>

                <!-- Columna Cerrados -->
                <div class="kanban-column" data-status="cerrado">
                    <div class="kanban-header">
                        <div class="kanban-title">
                            <i class="bi bi-check-circle-fill" style="color: #36B37E; font-size: 12px;"></i>
                            Cerrados
                        </div>
                        <div class="kanban-count"><?php echo $stats['cerrados']; ?></div>
                    </div>
                    <?php 
                    $cerradosCount = 0;
                    foreach ($ticketsAsignados as $ticket): 
                        if ($ticket['estado'] == 'cerrado' && $cerradosCount < 5): 
                            $cerradosCount++;
                    ?>
                            <div class="kanban-card" 
                                 data-ticket-id="<?php echo $ticket['id']; ?>"
                                 style="cursor: pointer;"
                                 onclick="window.location.href='ver-ticket.php?id=<?php echo $ticket['id']; ?>'">
                                <div class="kanban-card-id">#<?php echo $ticket['id']; ?></div>
                                <div class="kanban-card-title"><?php echo e($ticket['titulo']); ?></div>
                                <div class="kanban-card-meta">
                                    <small style="color: var(--text-muted);">
                                        <i class="bi bi-person"></i> <?php echo e(substr($ticket['usuario_nombre'], 0, 15)); ?>
                                    </small>
                                    <small style="color: var(--text-muted);">
                                        <?php echo formatearFechaCorta($ticket['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    <?php if ($stats['cerrados'] == 0): ?>
                        <p class="text-muted text-center mt-3" style="font-size: 13px;">Sin tickets</p>
                    <?php elseif ($stats['cerrados'] > 5): ?>
                        <p class="text-muted text-center mt-2" style="font-size: 12px;">+<?php echo ($stats['cerrados'] - 5); ?> más</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-table"></i> Vista Detallada</h5>
            </div>
            <div class="card-body">
                <?php if (empty($ticketsAsignados)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No tienes tickets asignados actualmente.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" id="tablaTickets">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th>Título</th>
                                    <th style="width: 150px;">Usuario</th>
                                    <th style="width: 150px;">Sede / Área</th>
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
                                        data-estado="<?php echo $ticket['estado']; ?>">
                                        <td><strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong></td>
                                        <td>
                                            <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none" style="color: var(--text-primary); font-weight: 600;">
                                                <?php echo e($ticket['titulo']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo e($ticket['usuario_nombre']); ?></td>
                                        <td>
                                            <small>
                                                <?php echo e($ticket['sede_nombre']); ?><br>
                                                <em style="color: var(--text-muted);"><?php echo e($ticket['area_nombre']); ?></em>
                                            </small>
                                        </td>
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
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
</body>
</html>