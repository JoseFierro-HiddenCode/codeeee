<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['usuario']);

$userId = $_SESSION['user_id'];

// Obtener estadísticas del usuario
$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados
    FROM tickets 
    WHERE usuario_id = ?
";
$stats = obtenerUno($sqlStats, array($userId));

// Obtener tickets del usuario
$sqlTickets = "
    SELECT 
        t.*,
        c.nombre as categoria_nombre,
        tec.nombre + ' ' + tec.apellido as tecnico_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
    WHERE t.usuario_id = ?
    ORDER BY t.created_at DESC
";
$misTickets = obtenerTodos($sqlTickets, array($userId));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets - Sistema de Tickets</title>
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
                <a href="dashboard-usuario.php" class="sidebar-nav-link active">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="crear-ticket.php" class="sidebar-nav-link">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Crear Ticket</span>
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Mis Tickets</h1>
                    <p class="page-subtitle">Bienvenido, <?php echo e($_SESSION['nombre']); ?>. Gestiona tus solicitudes de soporte.</p>
                </div>
                <a href="crear-ticket.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Crear Ticket
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6>Total</h6>
                            <h2><?php echo $stats['total']; ?></h2>
                        </div>
                        <div class="stat-icon" style="background-color: #DEEBFF;">
                            <i class="bi bi-ticket-perforated" style="color: #0052CC;"></i>
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

        <!-- Tickets List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list-ul"></i> Lista de Tickets</h5>
            </div>
            <div class="card-body">
                <?php if (empty($misTickets)): ?>
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle"></i> No has creado ningún ticket aún.
                        <br><br>
                        <a href="crear-ticket.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Crear mi primer ticket
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" id="tablaTickets">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th>Título</th>
                                    <th style="width: 150px;">Categoría</th>
                                    <th style="width: 120px;">Prioridad</th>
                                    <th style="width: 120px;">Estado</th>
                                    <th style="width: 150px;">Técnico</th>
                                    <th style="width: 140px;">Fecha</th>
                                    <th style="width: 100px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($misTickets as $ticket): ?>
                                    <tr class="priority-<?php echo $ticket['prioridad']; ?> ticket-row" 
                                        data-estado="<?php echo $ticket['estado']; ?>">
                                        <td><strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong></td>
                                        <td>
                                            <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none" style="color: var(--text-primary); font-weight: 600;">
                                                <?php echo e($ticket['titulo']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php 
                                            echo $ticket['categoria_otro'] ? e($ticket['categoria_otro']) : e($ticket['categoria_nombre']);
                                            ?>
                                        </td>
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