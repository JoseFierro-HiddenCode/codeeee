<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['gerente']);

// Estadísticas generales
$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN prioridad = 'urgente' AND estado != 'cerrado' THEN 1 ELSE 0 END) as urgentes,
        AVG(CASE 
            WHEN estado = 'cerrado' AND fecha_cierre IS NOT NULL 
            THEN DATEDIFF(hour, created_at, fecha_cierre) 
        END) as tiempo_promedio_cierre
    FROM tickets
";
$stats = obtenerUno($sqlStats);

// Estadísticas por categoría
$sqlPorCategoria = "
    SELECT 
        c.nombre as categoria,
        COUNT(*) as total,
        SUM(CASE WHEN t.estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    GROUP BY c.nombre
    ORDER BY total DESC
";
$porCategoria = obtenerTodos($sqlPorCategoria);

// Estadísticas por técnico
$sqlPorTecnico = "
    SELECT 
        u.nombre + ' ' + u.apellido as tecnico,
        COUNT(*) as total_asignados,
        SUM(CASE WHEN t.estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN t.estado IN ('abierto', 'en_progreso') THEN 1 ELSE 0 END) as pendientes
    FROM tickets t
    LEFT JOIN users u ON t.tecnico_asignado_id = u.id
    WHERE t.tecnico_asignado_id IS NOT NULL
    GROUP BY u.nombre, u.apellido
    ORDER BY total_asignados DESC
";
$porTecnico = obtenerTodos($sqlPorTecnico);

// Tickets recientes
$sqlRecientes = "
    SELECT TOP 10
        t.*,
        c.nombre as categoria_nombre,
        u.nombre + ' ' + u.apellido as usuario_nombre,
        tec.nombre + ' ' + tec.apellido as tecnico_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users u ON t.usuario_id = u.id
    LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
    ORDER BY t.created_at DESC
";
$ticketsRecientes = obtenerTodos($sqlRecientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Gerente - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard-gerente.php">
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
                <a href="dashboard-gerente.php" class="sidebar-nav-link active">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-section-title">Analytics</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#stats" class="sidebar-nav-link">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span>Estadísticas</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#categorias" class="sidebar-nav-link">
                    <i class="bi bi-pie-chart-fill"></i>
                    <span>Por Categoría</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#tecnicos" class="sidebar-nav-link">
                    <i class="bi bi-people-fill"></i>
                    <span>Por Técnico</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#recientes" class="sidebar-nav-link">
                    <i class="bi bi-clock-history"></i>
                    <span>Recientes</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard Gerencial</h1>
            <p class="page-subtitle">Vista general del sistema de tickets y métricas de rendimiento</p>
        </div>

        <!-- KPIs Principales -->
        <div class="row mb-4" id="stats">
            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #DEEBFF;">
                            <i class="bi bi-ticket-perforated" style="color: #0052CC;"></i>
                        </div>
                        <h6>Total Tickets</h6>
                        <h3><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #E3FCEF;">
                            <i class="bi bi-circle" style="color: #006644;"></i>
                        </div>
                        <h6>Abiertos</h6>
                        <h3><?php echo $stats['abiertos']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFF0B3;">
                            <i class="bi bi-arrow-repeat" style="color: #974F0C;"></i>
                        </div>
                        <h6>En Progreso</h6>
                        <h3><?php echo $stats['en_progreso']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #E8F5E9;">
                            <i class="bi bi-check-circle" style="color: #36B37E;"></i>
                        </div>
                        <h6>Cerrados</h6>
                        <h3><?php echo $stats['cerrados']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFEBE6;">
                            <i class="bi bi-exclamation-triangle" style="color: #DE350B;"></i>
                        </div>
                        <h6>Urgentes</h6>
                        <h3><?php echo $stats['urgentes']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #F3E5F5;">
                            <i class="bi bi-clock-history" style="color: #9C27B0;"></i>
                        </div>
                        <h6>Tiempo Promedio</h6>
                        <h3><?php echo round($stats['tiempo_promedio_cierre'] ?? 0); ?>h</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Tickets por categoría -->
            <div class="col-lg-6 mb-4" id="categorias">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="bi bi-pie-chart-fill"></i> Tickets por Categoría</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th style="width: 80px;">Total</th>
                                        <th style="width: 80px;">Cerrados</th>
                                        <th style="width: 200px;">% Resolución</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($porCategoria as $cat): ?>
                                        <?php $porcentaje = $cat['total'] > 0 ? round(($cat['cerrados'] / $cat['total']) * 100) : 0; ?>
                                        <tr>
                                            <td><strong><?php echo e($cat['categoria']); ?></strong></td>
                                            <td><span class="badge" style="background-color: var(--jira-blue);"><?php echo $cat['total']; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $cat['cerrados']; ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo $porcentaje; ?>%">
                                                            <span style="font-size: 11px; font-weight: 700;"><?php echo $porcentaje; ?>%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rendimiento por técnico -->
            <div class="col-lg-6 mb-4" id="tecnicos">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="bi bi-people-fill"></i> Rendimiento por Técnico</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Técnico</th>
                                        <th style="width: 100px;">Asignados</th>
                                        <th style="width: 100px;">Cerrados</th>
                                        <th style="width: 100px;">Pendientes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($porTecnico as $tec): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-initials" style="width: 28px; height: 28px; font-size: 11px;">
                                                        <?php echo obtenerIniciales($tec['tecnico'], ''); ?>
                                                    </div>
                                                    <strong style="font-size: 13px;"><?php echo e($tec['tecnico']); ?></strong>
                                                </div>
                                            </td>
                                            <td><span class="badge" style="background-color: var(--jira-blue);"><?php echo $tec['total_asignados']; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $tec['cerrados']; ?></span></td>
                                            <td><span class="badge bg-warning text-dark"><?php echo $tec['pendientes']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets recientes -->
        <div class="card" id="recientes">
            <div class="card-header">
                <h5><i class="bi bi-clock-history"></i> Tickets Recientes</h5>
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
                                <th style="width: 150px;">Técnico</th>
                                <th style="width: 140px;">Fecha</th>
                                <th style="width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ticketsRecientes as $ticket): ?>
                                <tr class="priority-<?php echo $ticket['prioridad']; ?>">
                                    <td><strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong></td>
                                    <td>
                                        <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none" style="color: var(--text-primary); font-weight: 600;">
                                            <?php echo e(substr($ticket['titulo'], 0, 50)) . (strlen($ticket['titulo']) > 50 ? '...' : ''); ?>
                                        </a>
                                    </td>
                                    <td><?php echo e($ticket['usuario_nombre']); ?></td>
                                    <td><?php echo $ticket['categoria_otro'] ? e($ticket['categoria_otro']) : e($ticket['categoria_nombre']); ?></td>
                                    <td><?php echo badgePrioridad($ticket['prioridad']); ?></td>
                                    <td><?php echo badgeEstado($ticket['estado']); ?></td>
                                    <td>
                                        <?php if ($ticket['tecnico_nombre']): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar-initials" style="width: 24px; height: 24px; font-size: 10px;">
                                                    <?php echo obtenerIniciales($ticket['tecnico_nombre'], ''); ?>
                                                </div>
                                                <small><?php echo e($ticket['tecnico_nombre']); ?></small>
                                            </div>
                                        <?php else: ?>
                                            <em class="text-muted" style="font-size: 12px;">Sin asignar</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><small style="color: var(--text-muted);"><?php echo formatearFecha($ticket['created_at']); ?></small></td>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
</body>
</html>