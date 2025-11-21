<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['tecnico', 'admin_tecnico', 'gerente', 'usuario']);

// Determinar query según rol
if ($_SESSION['rol'] == 'usuario') {
    // Usuario solo ve sus archivados
    $sqlArchivados = "
        SELECT 
            t.*,
            c.nombre as categoria_nombre,
            tec.nombre + ' ' + tec.apellido as tecnico_nombre
        FROM tickets t
        LEFT JOIN categories c ON t.categoria_id = c.id
        LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
        WHERE t.usuario_id = ?
          AND t.archivado = 1
        ORDER BY t.fecha_archivado DESC
    ";
    $ticketsArchivados = obtenerTodos($sqlArchivados, array($_SESSION['user_id']));
} elseif ($_SESSION['rol'] == 'tecnico') {
    // Técnico solo ve sus archivados
    $sqlArchivados = "
        SELECT 
            t.*,
            c.nombre as categoria_nombre,
            u.nombre + ' ' + u.apellido as usuario_nombre
        FROM tickets t
        LEFT JOIN categories c ON t.categoria_id = c.id
        LEFT JOIN users u ON t.usuario_id = u.id
        WHERE t.tecnico_asignado_id = ?
          AND t.archivado = 1
        ORDER BY t.fecha_archivado DESC
    ";
    $ticketsArchivados = obtenerTodos($sqlArchivados, array($_SESSION['user_id']));
} else {
    // Admin/Gerente ve todos los archivados
    $sqlArchivados = "
        SELECT 
            t.*,
            c.nombre as categoria_nombre,
            u.nombre + ' ' + u.apellido as usuario_nombre,
            tec.nombre + ' ' + tec.apellido as tecnico_nombre
        FROM tickets t
        LEFT JOIN categories c ON t.categoria_id = c.id
        LEFT JOIN users u ON t.usuario_id = u.id
        LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
        WHERE t.archivado = 1
        ORDER BY t.fecha_archivado DESC
    ";
    $ticketsArchivados = obtenerTodos($sqlArchivados);
}

$totalArchivados = count($ticketsArchivados);

// Determinar dashboard de regreso según rol
$dashboardUrl = match($_SESSION['rol']) {
    'usuario' => 'dashboard-usuario.php',
    'tecnico' => 'dashboard-tecnico.php',
    'admin_tecnico' => 'dashboard-admin.php',
    'gerente' => 'dashboard-gerente.php',
    default => 'dashboard-usuario.php'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets Archivados - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $dashboardUrl; ?>">
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

    <div class="sidebar">
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="<?php echo $dashboardUrl; ?>" class="sidebar-nav-link">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="tickets-archivados.php" class="sidebar-nav-link active">
                    <i class="bi bi-archive-fill"></i>
                    <span>Tickets Archivados</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-archive"></i> Tickets Archivados
            </h1>
            <p class="page-subtitle">
                <?php 
                if ($_SESSION['rol'] == 'usuario') {
                    echo "Tus tickets cerrados hace más de 24 horas (Total: {$totalArchivados})";
                } elseif ($_SESSION['rol'] == 'tecnico') {
                    echo "Tickets asignados cerrados hace más de 24 horas (Total: {$totalArchivados})";
                } else {
                    echo "Todos los tickets cerrados hace más de 24 horas (Total: {$totalArchivados})";
                }
                ?>
            </p>
        </div>

        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle-fill"></i>
            <strong>Información:</strong> Los tickets se archivan automáticamente 24 horas después de ser cerrados.
            <?php if ($_SESSION['rol'] == 'admin_tecnico' || $_SESSION['rol'] == 'gerente'): ?>
                Puedes restaurarlos si es necesario.
            <?php endif; ?>
        </div>

       <div class="card">
    <div class="card-header">
        <h5><i class="bi bi-table"></i> Listado de Tickets Archivados</h5>
    </div>
    <div class="card-body">
        <?php if (count($ticketsArchivados) > 0): ?>
            <div style="padding: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 80px;">ID</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left;">TÍTULO</th>
                            <?php if ($_SESSION['rol'] != 'usuario'): ?>
                                <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">USUARIO</th>
                            <?php endif; ?>
                            <?php if ($_SESSION['rol'] == 'admin_tecnico' || $_SESSION['rol'] == 'gerente'): ?>
                                <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">TÉCNICO</th>
                            <?php endif; ?>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">PRIORIDAD</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">FECHA CIERRE</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">FECHA ARCHIVADO</th>
                            <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketsArchivados as $ticket): ?>
                            <tr style="transition: background-color 0.2s ease;"
                                onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                                onmouseout="this.style.backgroundColor='transparent'">
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <strong style="color: var(--text-muted); font-weight: 700;">#<?php echo $ticket['id']; ?></strong>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                                    <?php echo e(substr($ticket['titulo'], 0, 40)) . (strlen($ticket['titulo']) > 40 ? '...' : ''); ?>
                                </td>
                                
                                <?php if ($_SESSION['rol'] != 'usuario'): ?>
                                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                                        <?php echo e($ticket['usuario_nombre']); ?>
                                    </td>
                                <?php endif; ?>
                                
                                <?php if ($_SESSION['rol'] == 'admin_tecnico' || $_SESSION['rol'] == 'gerente'): ?>
                                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                                        <?php echo e($ticket['tecnico_nombre'] ?? 'Sin asignar'); ?>
                                    </td>
                                <?php endif; ?>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <?php echo badgePrioridad($ticket['prioridad']); ?>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                    <small style="color: var(--text-muted);">
                                        <?php echo formatearFecha($ticket['fecha_cierre']); ?>
                                    </small>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                    <small style="color: var(--text-muted);">
                                        <?php echo calcularTiempo($ticket['fecha_archivado']); ?>
                                    </small>
                                </td>
                                
                                <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                    <div style="display: flex; gap: 4px; justify-content: center;">
                                        <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                        <?php if ($_SESSION['rol'] == 'admin_tecnico' || $_SESSION['rol'] == 'gerente'): ?>
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="restaurarTicket(<?php echo $ticket['id']; ?>)">
                                                <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-archive" style="font-size: 4rem; color: var(--text-muted);"></i>
                <h4 class="mt-3">No hay tickets archivados</h4>
                <p class="text-muted">Los tickets se archivarán automáticamente 24 horas después de ser cerrados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
    <script>
        function restaurarTicket(ticketId) {
            if (!confirm('¿Restaurar este ticket? Volverá a aparecer en el dashboard como ticket abierto.')) return;
            
            const formData = new FormData();
            formData.append('ticket_id', ticketId);
            
            fetch('../actions/restaurar-ticket.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✓ Ticket restaurado correctamente', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('✗ Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('✗ Error de conexión', 'danger');
            });
        }
    </script>
</body>
</html>