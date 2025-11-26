<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo usuarios que son jefes pueden acceder
protegerPagina(['usuario']);

if (!esJefe()) {
    header('Location: ../dashboard-usuario.php?error=' . urlencode('No tienes permisos'));
    exit();
}

// Obtener solicitudes del jefe
$sql = "
    SELECT 
        s.*,
        c.nombre + ' ' + c.apellido as colaborador_nombre,
        c.puesto as colaborador_puesto,
        g.nombre + ' ' + g.apellido as gerente_nombre,
        s.fecha_aprobacion
    FROM solicitudes_equipos s
    LEFT JOIN users c ON s.colaborador_id = c.id
    LEFT JOIN users g ON s.aprobado_por = g.id
    WHERE s.solicitante_id = ?
    ORDER BY s.created_at DESC
";
$solicitudes = obtenerTodos($sql, [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard-usuario.php">
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
                <a href="../dashboard-usuario.php" class="sidebar-nav-link">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="../crear-ticket.php" class="sidebar-nav-link">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Crear Ticket</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-section-title">Gestión de Equipo</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="solicitar-equipos.php" class="sidebar-nav-link">
                    <i class="bi bi-plus-square"></i>
                    <span>Solicitar Equipos</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="mis-solicitudes.php" class="sidebar-nav-link active">
                    <i class="bi bi-list-check"></i>
                    <span>Mis Solicitudes</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Mis Solicitudes de Equipos</h1>
                <p class="page-subtitle">Solicitudes creadas para tu equipo</p>
            </div>
            <a href="solicitar-equipos.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Solicitud
            </a>
        </div>

        <!-- Tabla de Solicitudes -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-table"></i> Listado de Solicitudes (<?php echo count($solicitudes); ?>)</h5>
    </div>
    <div class="card-body" style="padding: 20px; overflow-x: auto;">
        <?php if (count($solicitudes) > 0): ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 140px;">Folio</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Colaborador</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">Puesto</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">Tipo</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 140px;">Estado</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 140px;">Fecha Solicitud</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">Aprobado Por</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $sol): ?>
                        <tr style="transition: background-color 0.2s ease;"
                            onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                            onmouseout="this.style.backgroundColor='transparent'">
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <strong style="color: var(--jira-blue); font-weight: 700;"><?php echo e($sol['folio']); ?></strong>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-primary);">
                                <?php echo e($sol['colaborador_nombre']); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo e($sol['colaborador_puesto'] ?? '-'); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo ucfirst(str_replace('_', ' ', $sol['tipo_solicitud'])); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <?php echo badgeEstadoSolicitud($sol['estado']); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <small style="color: var(--text-muted);"><?php echo formatearFecha($sol['created_at']); ?></small>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo e($sol['gerente_nombre'] ?? '-'); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <button class="btn btn-sm btn-primary" onclick="verDetalle('<?php echo $sol['folio']; ?>')">
                                    <i class="bi bi-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No has creado solicitudes aún.
                <a href="solicitar-equipos.php" class="alert-link">Crear primera solicitud</a>
            </div>
        <?php endif; ?>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/app.js"></script>
    <!-- Modal de Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-text"></i> Detalle de Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalleContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/app.js"></script>

<script>
// Modo Oscuro
const themeToggle = document.getElementById('theme-toggle');
const themeIcon = themeToggle.querySelector('i');

const currentTheme = localStorage.getItem('theme') || 'light';
document.body.setAttribute('data-theme', currentTheme);

function updateIcon(theme) {
    if (theme === 'dark') {
        themeIcon.className = 'bi bi-sun-fill';
    } else {
        themeIcon.className = 'bi bi-moon-stars-fill';
    }
}

updateIcon(currentTheme);

themeToggle.addEventListener('click', function() {
    const currentTheme = document.body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateIcon(newTheme);
});

// Ver Detalle de Solicitud
const solicitudes = <?php echo json_encode($solicitudes); ?>;

function verDetalle(folio) {
    const solicitud = solicitudes.find(s => s.folio === folio);
    
    if (!solicitud) {
        alert('Solicitud no encontrada');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
    const content = document.getElementById('modalDetalleContent');
    
    // Construir HTML del detalle
    content.innerHTML = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted mb-2">FOLIO</h6>
                <p class="fw-bold">${solicitud.folio}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-2">ESTADO</h6>
                <p>${getBadgeEstado(solicitud.estado)}</p>
            </div>
        </div>
        
        <hr>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted mb-2">COLABORADOR</h6>
                <p>${solicitud.colaborador_nombre || '-'}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-2">PUESTO</h6>
                <p>${solicitud.colaborador_puesto || '-'}</p>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted mb-2">TIPO DE SOLICITUD</h6>
                <p>${solicitud.tipo_solicitud.replace('_', ' ').toUpperCase()}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-2">FECHA SOLICITUD</h6>
                <p>${new Date(solicitud.created_at).toLocaleDateString('es-MX')}</p>
            </div>
        </div>
        
        <hr>
        
        <div class="mb-3">
            <h6 class="text-muted mb-2">JUSTIFICACIÓN</h6>
            <p>${solicitud.justificacion || '-'}</p>
        </div>
        
        ${solicitud.estado !== 'pendiente_gerente' ? `
        <hr>
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted mb-2">APROBADO POR</h6>
                <p>${solicitud.gerente_nombre || '-'}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-2">FECHA APROBACIÓN</h6>
                <p>${solicitud.fecha_aprobacion ? new Date(solicitud.fecha_aprobacion).toLocaleDateString('es-MX') : '-'}</p>
            </div>
        </div>
        ` : ''}
        
        ${solicitud.estado === 'aprobada' || solicitud.estado === 'asignada' ? `
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> Esta solicitud ha sido aprobada y está en proceso de asignación.
        </div>
        ` : ''}
        
        ${solicitud.estado === 'rechazada' ? `
        <div class="alert alert-danger">
            <i class="bi bi-x-circle"></i> Esta solicitud fue rechazada.
        </div>
        ` : ''}
    `;
    
    modal.show();
}

function getBadgeEstado(estado) {
    const badges = {
        'pendiente_gerente': '<span class="badge bg-warning">Pendiente Gerente</span>',
        'pendiente_gerente_general': '<span class="badge bg-info">Pendiente General</span>',
        'aprobada': '<span class="badge" style="background-color: #0052CC; color: white;">Aprobada</span>',
        'rechazada': '<span class="badge bg-danger">Rechazada</span>',
        'asignada': '<span class="badge bg-primary">Asignada</span>'
    };
    return badges[estado] || `<span class="badge bg-secondary">${estado}</span>`;
}
</script>
</body>
</html>
