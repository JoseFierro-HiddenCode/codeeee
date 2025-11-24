<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo Admin y TI pueden acceder
protegerPagina(['admin_tecnico', 'tecnico']);

// Obtener estadísticas
$stats = obtenerEstadisticasInventario();

// Obtener equipos recientes
$sqlEquiposRecientes = "
    SELECT TOP 10 * 
    FROM equipos 
    ORDER BY created_at DESC
";
$equiposRecientes = obtenerTodos($sqlEquiposRecientes);

// Contar solicitudes aprobadas pendientes de asignación
$solicitudesPendientes = contarSolicitudesAprobadas();

// Equipos por tipo
$sqlPorTipo = "
    SELECT 
        tipo,
        COUNT(*) as cantidad,
        SUM(CASE WHEN disponible = 1 THEN 1 ELSE 0 END) as disponibles
    FROM equipos
    WHERE estado NOT IN ('dado_de_baja')
    GROUP BY tipo
    ORDER BY cantidad DESC
";
$equiposPorTipo = obtenerTodos($sqlPorTipo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Inventario - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard-admin.php">
                <i class="bi bi-ticket-perforated-fill"></i> Sistema de Tickets
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
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
                            <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
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
                <a href="../dashboard-admin.php" class="sidebar-nav-link">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard Tickets</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-section-title">Inventario</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="dashboard-inventario.php" class="sidebar-nav-link active">
                    <i class="bi bi-box-seam"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="gestionar-equipos.php" class="sidebar-nav-link">
                    <i class="bi bi-laptop"></i>
                    <span>Gestionar Equipos</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="asignar-equipos-fisicos.php" class="sidebar-nav-link">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Solicitudes Aprobadas</span>
                    <?php if ($solicitudesPendientes > 0): ?>
                        <span class="ms-auto badge bg-warning"><?php echo $solicitudesPendientes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard de Inventario</h1>
            <p class="page-subtitle">Gestión de equipos tecnológicos</p>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #DEEBFF;">
                            <i class="bi bi-box-seam" style="color: #0052CC;"></i>
                        </div>
                        <h6>Total Equipos</h6>
                        <h3><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #E3FCEF;">
                            <i class="bi bi-check-circle" style="color: #006644;"></i>
                        </div>
                        <h6>Disponibles</h6>
                        <h3><?php echo $stats['disponibles']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFF0B3;">
                            <i class="bi bi-person-check" style="color: #974F0C;"></i>
                        </div>
                        <h6>Asignados</h6>
                        <h3><?php echo $stats['asignados']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #E8F5E9;">
                            <i class="bi bi-star-fill" style="color: #36B37E;"></i>
                        </div>
                        <h6>Nuevos</h6>
                        <h3><?php echo $stats['nuevos']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFEBE6;">
                            <i class="bi bi-exclamation-triangle" style="color: #DE350B;"></i>
                        </div>
                        <h6>Para Reparación</h6>
                        <h3><?php echo $stats['para_reparacion']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #F4F5F7;">
                            <i class="bi bi-trash" style="color: #6B778C;"></i>
                        </div>
                        <h6>Dados de Baja</h6>
                        <h3><?php echo $stats['dados_baja']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Equipos por Tipo -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-pie-chart"></i> Equipos por Tipo</h5>
    </div>
    <div class="card-body" style="padding: 20px; overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 250px;">TIPO DE EQUIPO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">TOTAL</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">DISPONIBLES</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">ASIGNADOS</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 200px;">% DISPONIBILIDAD</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $equiposPorTipo = obtenerTodos("
                    SELECT 
                        tipo,
                        COUNT(*) as total,
                        SUM(CASE WHEN disponible = 1 THEN 1 ELSE 0 END) as disponibles,
                        SUM(CASE WHEN disponible = 0 THEN 1 ELSE 0 END) as asignados
                    FROM equipos
                    GROUP BY tipo
                    ORDER BY total DESC
                ");
                
                if (count($equiposPorTipo) > 0):
                    foreach ($equiposPorTipo as $tipo): 
                        $porcentaje = ($tipo['total'] > 0) ? round(($tipo['disponibles'] / $tipo['total']) * 100, 1) : 0;
                        $colorBarra = $porcentaje >= 50 ? 'success' : ($porcentaje >= 25 ? 'warning' : 'danger');
                ?>
                <tr style="transition: background-color 0.2s ease;"
                    onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                    onmouseout="this.style.backgroundColor='transparent'">
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                        <strong style="color: var(--text-primary); font-weight: 600; text-transform: capitalize;">
                            <?php 
                            $iconos = [
                                'laptop' => '💻',
                                'celular' => '📱',
                                'pc' => '🖥️',
                                'tablet' => '📲',
                                'monitor' => '🖥️',
                                'teclado' => '⌨️',
                                'mouse' => '🖱️',
                                'impresora' => '🖨️'
                            ];
                            echo ($iconos[$tipo['tipo']] ?? '📦') . ' ' . e(ucfirst($tipo['tipo'])); 
                            ?>
                        </strong>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <span style="color: var(--jira-blue); font-weight: 700; font-size: 16px;">
                            <?php echo $tipo['total']; ?>
                        </span>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <span class="badge bg-success" style="font-size: 13px; padding: 6px 12px;">
                            ✅ <?php echo $tipo['disponibles']; ?>
                        </span>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <span class="badge bg-secondary" style="font-size: 13px; padding: 6px 12px;">
                            📦 <?php echo $tipo['asignados']; ?>
                        </span>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="flex: 1; background-color: var(--border-color); border-radius: 10px; height: 20px; overflow: hidden;">
                                <div style="background-color: var(--bs-<?php echo $colorBarra; ?>); height: 100%; width: <?php echo $porcentaje; ?>%; transition: width 0.3s ease;"></div>
                            </div>
                            <span style="color: var(--text-primary); font-weight: 700; min-width: 50px; text-align: right;">
                                <?php echo $porcentaje; ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php 
                    endforeach;
                else: 
                ?>
                <tr>
                    <td colspan="5" style="padding: 40px; border: none; text-align: center; color: var(--text-muted);">
                        <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p style="margin: 0;">No hay equipos registrados en el inventario</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

         <!-- Equipos Recientes -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-clock-history"></i> Equipos Agregados Recientemente</h5>
    </div>
    <div class="card-body" style="padding: 20px; overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 60px;">ID</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 100px;">TIPO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">MARCA</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">MODELO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">SERIE</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 110px;">ESTADO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">DISPONIBLE</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">FECHA REGISTRO</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $equiposRecientes = obtenerTodos("
                    SELECT TOP 10 * 
                    FROM equipos 
                    ORDER BY created_at DESC
                ");
                
                if (count($equiposRecientes) > 0):
                    foreach ($equiposRecientes as $equipo): 
                ?>
                <tr style="transition: background-color 0.2s ease;"
                    onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                    onmouseout="this.style.backgroundColor='transparent'">
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <strong style="color: var(--jira-blue); font-weight: 700;">#<?php echo $equipo['id']; ?></strong>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                        <span style="color: var(--text-primary); font-weight: 600; text-transform: capitalize;">
                            <?php 
                            $iconos = [
                                'laptop' => '💻',
                                'celular' => '📱',
                                'pc' => '🖥️',
                                'tablet' => '📲',
                                'monitor' => '🖥️',
                                'teclado' => '⌨️',
                                'mouse' => '🖱️',
                                'impresora' => '🖨️'
                            ];
                            echo ($iconos[$equipo['tipo']] ?? '📦') . ' ' . e(ucfirst($equipo['tipo'])); 
                            ?>
                        </span>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                        <?php echo e($equipo['marca'] ?? '-'); ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                        <?php echo e($equipo['modelo'] ?? '-'); ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                        <code style="background-color: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px; font-size: 12px; color: var(--text-primary);">
                            <?php echo e($equipo['numero_serie']); ?>
                        </code>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <?php echo badgeEstadoEquipo($equipo['estado']); ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <?php echo badgeDisponibilidad($equipo['disponible']); ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                        <small style="color: var(--text-muted);">
                            <?php echo formatearFecha($equipo['created_at']); ?>
                        </small>
                    </td>
                </tr>
                <?php 
                    endforeach;
                else: 
                ?>
                <tr>
                    <td colspan="8" style="padding: 40px; border: none; text-align: center; color: var(--text-muted);">
                        <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p style="margin: 0;">No hay equipos registrados</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

        <!-- Acciones Rápidas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-lightning-fill"></i> Acciones Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-3">
                            <a href="gestionar-equipos.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Agregar Equipo
                            </a>
                            <a href="asignar-equipos-fisicos.php" class="btn btn-success">
                                <i class="bi bi-clipboard-check"></i> Asignar Equipos
                                <?php if ($solicitudesPendientes > 0): ?>
                                    <span class="badge bg-warning"><?php echo $solicitudesPendientes; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/app.js"></script>
</body>
</html>