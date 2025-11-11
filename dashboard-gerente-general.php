<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['gerente_general']);

// EJECUTAR ARCHIVADO AUTOMÁTICO
ejecutarArchivoSiNecesario();

// Estadísticas generales (SOLO ACTIVOS, NO ARCHIVADOS)
$sqlStats = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN tecnico_asignado_id IS NULL THEN 1 ELSE 0 END) as sin_asignar,
        SUM(CASE WHEN prioridad = 'urgente' AND estado != 'cerrado' THEN 1 ELSE 0 END) as urgentes
    FROM tickets
    WHERE archivado = 0
";
$stats = obtenerUno($sqlStats);

// Estadísticas históricas (INCLUYE ARCHIVADOS - para reportes)
$sqlStatsHistorico = "
    SELECT
        COUNT(*) as total_historico,
        SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as total_cerrados_historico
    FROM tickets
";
$statsHistorico = obtenerUno($sqlStatsHistorico);

// Tickets por categoría (SOLO ACTIVOS)
$sqlPorCategoria = "
    SELECT
        ISNULL(c.nombre, 'Otro') as categoria,
        COUNT(*) as cantidad
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    WHERE t.archivado = 0
    GROUP BY c.nombre
    ORDER BY cantidad DESC
";
$ticketsPorCategoria = obtenerTodos($sqlPorCategoria);

// Tickets por prioridad (SOLO ACTIVOS)
$sqlPorPrioridad = "
    SELECT
        prioridad,
        COUNT(*) as cantidad
    FROM tickets
    WHERE archivado = 0
    GROUP BY prioridad
    ORDER BY
        CASE prioridad
            WHEN 'urgente' THEN 1
            WHEN 'alta' THEN 2
            WHEN 'media' THEN 3
            WHEN 'baja' THEN 4
        END
";
$ticketsPorPrioridad = obtenerTodos($sqlPorPrioridad);

// Tickets por técnico (SOLO ACTIVOS)
$sqlPorTecnico = "
    SELECT
        ISNULL(u.nombre + ' ' + u.apellido, 'Sin asignar') as tecnico,
        COUNT(*) as cantidad,
        SUM(CASE WHEN t.estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN t.estado != 'cerrado' THEN 1 ELSE 0 END) as pendientes
    FROM tickets t
    LEFT JOIN users u ON t.tecnico_asignado_id = u.id
    WHERE t.archivado = 0
    GROUP BY u.nombre, u.apellido
    ORDER BY cantidad DESC
";
$ticketsPorTecnico = obtenerTodos($sqlPorTecnico);

// Tickets recientes (SOLO ACTIVOS)
$sqlTicketsRecientes = "
    SELECT TOP 10
        t.*,
        c.nombre as categoria_nombre,
        u.nombre + ' ' + u.apellido as usuario_nombre,
        tec.nombre + ' ' + tec.apellido as tecnico_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users u ON t.usuario_id = u.id
    LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
    WHERE t.archivado = 0
    ORDER BY t.created_at DESC
";
$ticketsRecientes = obtenerTodos($sqlTicketsRecientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Gerente General - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard-gerente-general.php">
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
                <a href="dashboard-gerente-general.php" class="sidebar-nav-link active">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-section-title">Analytics</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#estadisticas" class="sidebar-nav-link">
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
        </ul>

        <!-- SECCIÓN EQUIPOS -->
        <div class="sidebar-section-title">Equipos</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="inventario/aprobar-solicitudes-general.php" class="sidebar-nav-link">
                    <i class="bi bi-check2-square"></i>
                    <span>Aprobar Solicitudes</span>
                    <?php
                    $solicitudesPendientes = contarSolicitudesPendientesGeneral();
                    if ($solicitudesPendientes > 0):
                    ?>
                        <span class="ms-auto badge bg-danger"><?php echo $solicitudesPendientes; ?></span>
                    <?php endif; ?>
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
            <h1 class="page-title">Dashboard Gerencia General</h1>
            <p class="page-subtitle">Vista ejecutiva del sistema de tickets y aprobación de solicitudes</p>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4" id="estadisticas">
            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #DEEBFF;">
                            <i class="bi bi-ticket-perforated" style="color: #0052CC;"></i>
                        </div>
                        <h6>Total Activos</h6>
                        <h3><?php echo $stats['total']; ?></h3>
                        <small class="text-muted">
                            <?php echo $statsHistorico['total_historico']; ?> históricos
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFEBE6;">
                            <i class="bi bi-person-x" style="color: #DE350B;"></i>
                        </div>
                        <h6>Sin Asignar</h6>
                        <h3><?php echo $stats['sin_asignar']; ?></h3>
                        <small class="text-muted">Requieren atención</small>
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
                        <small class="text-muted">
                            <?php echo $stats['total'] > 0 ? round(($stats['abiertos'] / $stats['total']) * 100, 1) : 0; ?>%
                        </small>
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
                        <small class="text-muted">
                            <?php echo $stats['total'] > 0 ? round(($stats['en_progreso'] / $stats['total']) * 100, 1) : 0; ?>%
                        </small>
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
                        <small class="text-muted">
                            <?php echo $stats['total'] > 0 ? round(($stats['cerrados'] / $stats['total']) * 100, 1) : 0; ?>%
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFF0B3;">
                            <i class="bi bi-exclamation-triangle" style="color: #974F0C;"></i>
                        </div>
                        <h6>Urgentes</h6>
                        <h3><?php echo $stats['urgentes']; ?></h3>
                        <small class="text-muted">Activos</small>
                    </div>
                </div>
            </div>
        </div>

   <!-- Analytics Row -->
<div class="row mb-4">
    <!-- Tickets por Categoría -->
    <div class="col-lg-6 mb-4" id="categorias">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-pie-chart-fill"></i> Tickets por Categoría</h5>
            </div>
            <div class="card-body" style="padding: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Categoría</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Cantidad</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketsPorCategoria as $cat): ?>
                            <tr style="transition: background-color 0.2s ease;"
                                onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                                onmouseout="this.style.backgroundColor='transparent'">
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-primary);">
                                    <?php echo e($cat['categoria']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <strong style="color: var(--jira-blue); font-weight: 700; font-size: 16px;"><?php echo $cat['cantidad']; ?></strong>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                    <?php echo $stats['total'] > 0 ? round(($cat['cantidad'] / $stats['total']) * 100, 1) : 0; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
              <!-- Tickets por Prioridad -->
<div class="col-lg-6 mb-4">
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-flag-fill"></i> Tickets por Prioridad</h5>
        </div>
        <div class="card-body" style="padding: 20px; overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Prioridad</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Cantidad</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ticketsPorPrioridad as $prior): ?>
                        <tr style="transition: background-color 0.2s ease;"
                            onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                            onmouseout="this.style.backgroundColor='transparent'">
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <?php echo badgePrioridad($prior['prioridad']); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <strong style="color: var(--jira-blue); font-weight: 700; font-size: 16px;"><?php echo $prior['cantidad']; ?></strong>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo $stats['total'] > 0 ? round(($prior['cantidad'] / $stats['total']) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
       <!-- Tickets por Técnico -->
<div class="row mb-4" id="tecnicos">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-people-fill"></i> Rendimiento por Técnico</h5>
            </div>
            <div class="card-body" style="padding: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Técnico</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Total</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Cerrados</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Pendientes</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">% Resueltos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketsPorTecnico as $tec): ?>
                            <tr style="transition: background-color 0.2s ease;"
                                onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                                onmouseout="this.style.backgroundColor='transparent'">
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-primary);">
                                    <?php echo e($tec['tecnico']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <strong style="color: var(--jira-blue); font-weight: 700; font-size: 16px;"><?php echo $tec['cantidad']; ?></strong>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <span class="badge bg-success"><?php echo $tec['cerrados']; ?></span>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <span class="badge bg-warning"><?php echo $tec['pendientes']; ?></span>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                    <?php echo $tec['cantidad'] > 0 ? round(($tec['cerrados'] / $tec['cantidad']) * 100, 1) : 0; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

      <!-- Tickets Recientes -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-clock-history"></i> Tickets Recientes</h5>
            </div>
            <div class="card-body" style="padding: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 80px;">ID</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Título</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">Usuario</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">Categoría</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 110px;">Prioridad</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 110px;">Estado</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">Técnico</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 140px;">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketsRecientes as $ticket): ?>
                            <tr style="transition: background-color 0.2s ease;"
                                onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                                onmouseout="this.style.backgroundColor='transparent'">
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <strong style="color: var(--jira-blue); font-weight: 700;">#<?php echo $ticket['id']; ?></strong>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-primary);">
                                    <?php echo e($ticket['titulo']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                    <?php echo e($ticket['usuario_nombre']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                    <?php echo e($ticket['categoria_nombre'] ?? 'N/A'); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <?php echo badgePrioridad($ticket['prioridad']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <?php echo badgeEstado($ticket['estado']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                    <?php echo e($ticket['tecnico_nombre'] ?? 'Sin asignar'); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <small style="color: var(--text-muted);"><?php echo formatearFecha($ticket['created_at']); ?></small>
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
    <script src="../public/js/theme.js"></script>
</body>
</html>