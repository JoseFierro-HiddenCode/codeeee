<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['admin_tecnico']);

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

// Todos los tickets ACTIVOS (NO ARCHIVADOS)
$sqlTodosTickets = "
    SELECT 
        t.*,
        c.nombre as categoria_nombre,
        u.nombre + ' ' + u.apellido as usuario_nombre,
        tec.nombre + ' ' + tec.apellido as tecnico_nombre,
        s.nombre as sede_nombre,
        a.nombre as area_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users u ON t.usuario_id = u.id
    LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
    LEFT JOIN sedes s ON u.sede_id = s.id
    LEFT JOIN areas a ON u.area_id = a.id
    WHERE t.archivado = 0
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
$todosTickets = obtenerTodos($sqlTodosTickets);

// Obtener técnicos para asignación
$tecnicos = obtenerTodos("
    SELECT id, nombre + ' ' + apellido as nombre_completo 
    FROM users 
    WHERE rol IN ('tecnico', 'admin_tecnico') AND activo = 1
    ORDER BY nombre_completo
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../public/css/style.css">
    
   
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard-admin.php">
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
                <a href="dashboard-admin.php" class="sidebar-nav-link active">
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
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('sin_asignar'); return false;">
                    <i class="bi bi-person-x"></i>
                    <span>Sin Asignar</span>
                    <span class="ms-auto badge bg-danger"><?php echo $stats['sin_asignar']; ?></span>
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
            <h1 class="page-title">Dashboard Administrativo</h1>
            <p class="page-subtitle">Gestiona todos los tickets del sistema y asigna técnicos</p>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #DEEBFF;">
                            <i class="bi bi-ticket-perforated" style="color: #0052CC;"></i>
                        </div>
                        <h6>Total</h6>
                        <h3><?php echo $stats['total']; ?></h3>
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
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFF0B3;">
                            <i class="bi bi-exclamation-triangle" style="color: #974F0C;"></i>
                        </div>
                        <h6>Urgentes</h6>
                        <h3><?php echo $stats['urgentes']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-table"></i> Todos los Tickets</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Título</th>
                                <th style="width: 150px;">Usuario</th>
                                <th style="width: 150px;">Sede / Área</th>
                                <th style="width: 150px;">Categoría</th>
                                <th style="width: 120px;">Prioridad</th>
                                <th style="width: 120px;">Estado</th>
                                <th style="width: 220px;">Técnico</th>
                                <th style="width: 140px;">Fecha</th>
                                <th style="width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todosTickets as $ticket): ?>
                                <tr class="priority-<?php echo $ticket['prioridad']; ?> ticket-row" 
                                    data-estado="<?php echo $ticket['estado']; ?>"
                                    data-asignado="<?php echo $ticket['tecnico_asignado_id'] ? 'si' : 'no'; ?>"
                                    data-prioridad="<?php echo $ticket['prioridad']; ?>">
                                    <td><strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong></td>
                                    <td>
                                        <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none" style="color: var(--text-primary); font-weight: 600;">
                                            <?php echo e(substr($ticket['titulo'], 0, 40)) . (strlen($ticket['titulo']) > 40 ? '...' : ''); ?>
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
                                        <?php if ($ticket['tecnico_asignado_id']): ?>
                                            <div class="d-flex align-items-center gap-2 justify-content-between">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-initials" style="width: 26px; height: 26px; font-size: 11px;">
                                                        <?php echo obtenerIniciales($ticket['tecnico_nombre'], ''); ?>
                                                    </div>
                                                    <small style="font-weight: 500; color: var(--text-primary);">
                                                        <?php echo e(substr($ticket['tecnico_nombre'], 0, 15)); ?>
                                                    </small>
                                                </div>
                                                <button class="btn btn-sm btn-link p-0 text-muted" 
                                                        onclick="abrirModalAsignar(<?php echo $ticket['id']; ?>, '<?php echo e($ticket['tecnico_nombre']); ?>', <?php echo $ticket['tecnico_asignado_id']; ?>)"
                                                        title="Cambiar técnico">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-danger w-100" 
                                                    onclick="abrirModalAsignar(<?php echo $ticket['id']; ?>, '', null)">
                                                <i class="bi bi-person-plus"></i> Asignar
                                            </button>
                                        <?php endif; ?>
                                    </td>
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

    <!-- Modal Asignar Técnico -->
    <div class="modal fade" id="modalAsignar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-check-fill"></i> Asignar Técnico
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ticketIdAsignar">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">
                            <i class="bi bi-person"></i> Seleccionar técnico
                        </label>
                        <select class="form-select" id="tecnicoAsignar">
                            <option value="">Sin asignar</option>
                            <?php foreach ($tecnicos as $tec): ?>
                                <option value="<?php echo $tec['id']; ?>">
                                    <?php echo e($tec['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Selecciona el técnico responsable de este ticket</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmarAsignacion()">
                        <i class="bi bi-check-circle"></i> Asignar
                    </button>
                </div>
            </div>
        </div>
    </div>

  
    <script>
        console.log('Bootstrap cargado:', typeof bootstrap !== 'undefined' ? 'SÍ ✅' : 'NO ❌');
        
        function abrirModalAsignar(ticketId, tecnicoActual, tecnicoId) {
            document.getElementById('ticketIdAsignar').value = ticketId;
            document.getElementById('tecnicoAsignar').value = tecnicoId || '';
            
            const modalElement = document.getElementById('modalAsignar');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }

        function confirmarAsignacion() {
            const ticketId = document.getElementById('ticketIdAsignar').value;
            const tecnicoId = document.getElementById('tecnicoAsignar').value;
            
            asignarTecnico(ticketId, tecnicoId);
            
            const modalElement = document.getElementById('modalAsignar');
            const modal = bootstrap.Modal.getInstance(modalElement);
            modal.hide();
        }

        function asignarTecnico(ticketId, tecnicoId) {
            const formData = new FormData();
            formData.append('ticket_id', ticketId);
            formData.append('tecnico_id', tecnicoId);
            
            fetch('../actions/asignar-tecnico.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✓ Técnico asignado correctamente', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('✗ Error: ' + (data.message || 'Desconocido'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('✗ Error de conexión', 'danger');
            });
        }
    </script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/app.js"></script>
</body>
</html>