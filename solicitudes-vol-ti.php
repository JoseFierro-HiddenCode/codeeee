<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo admin y tecnico
protegerPagina(['admin_tecnico', 'admin_global']);

// Filtros
$filtroJefe = $_GET['jefe'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroEstado = $_GET['estado'] ?? 'pendiente'; // Por defecto pendientes
$filtroFechaDesde = $_GET['fecha_desde'] ?? '';
$filtroFechaHasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta con filtros
$sql = "
    SELECT
        h.id,
        h.folio,
        h.tipo_solicitud,
        h.fecha_generacion,
        h.estado,
        h.fecha_entrega,
        h.ruta_documento,
        h.accesos_incluidos,
        s.nombre + ' ' + s.apellido as empleado_nombre,
        s.id_usuario,
        s.cargo,
        s.sucursal,
        s.correo_corporativo,
        jefe.nombre + ' ' + jefe.apellido as jefe_nombre,
        jefe.email as jefe_email,
        u.nombre + ' ' + u.apellido as solicitado_por_nombre
    FROM solicitudes_accesos_vol_historial h
    INNER JOIN solicitudes_accesos_vol s ON h.solicitud_id = s.id
    INNER JOIN users u ON h.generado_por = u.id
    INNER JOIN users jefe ON u.id = jefe.id
    WHERE 1=1
";

$params = [];

// Filtro de estado
if (!empty($filtroEstado)) {
    $sql .= " AND h.estado = ?";
    $params[] = $filtroEstado;
}

// Filtro de jefe
if (!empty($filtroJefe)) {
    $sql .= " AND (jefe.nombre LIKE ? OR jefe.apellido LIKE ?)";
    $params[] = "%$filtroJefe%";
    $params[] = "%$filtroJefe%";
}

// Filtro de tipo
if (!empty($filtroTipo)) {
    $sql .= " AND h.tipo_solicitud = ?";
    $params[] = $filtroTipo;
}

// Filtro de fecha desde
if (!empty($filtroFechaDesde)) {
    $sql .= " AND CAST(h.fecha_generacion AS DATE) >= ?";
    $params[] = $filtroFechaDesde;
}

// Filtro de fecha hasta
if (!empty($filtroFechaHasta)) {
    $sql .= " AND CAST(h.fecha_generacion AS DATE) <= ?";
    $params[] = $filtroFechaHasta;
}

$sql .= " ORDER BY h.fecha_generacion DESC";

$solicitudes = obtenerTodos($sql, $params);

// Contar accesos por solicitud
foreach ($solicitudes as &$sol) {
    $accesosIds = json_decode($sol['accesos_incluidos'], true);
    $sol['cantidad_accesos'] = is_array($accesosIds) ? count($accesosIds) : 0;
}

// Estadísticas
$sqlStats = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregadas
    FROM solicitudes_accesos_vol_historial
";
$stats = obtenerUno($sqlStats);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../img/LogoGris.png">
    <title>Solicitudes VOL - TI - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/style.css">
    <style>
    .badge-tipo {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    /* ESTRUCTURA DEL CARD */
    .card-solicitud {
        transition: all 0.3s ease;
        border-left: 4px solid #dee2e6;
        height: 100%;
        min-height: 500px;
        display: flex;
        flex-direction: column;
    }
    
    .card-solicitud .card-body {
        display: flex;
        flex-direction: column;
        flex: 1;
        padding: 1.25rem;
    }
    
    .card-solicitud:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-4px);
    }
    
    .card-solicitud.pendiente {
        border-left-color: #ffc107;
    }
    
    .card-solicitud.entregado {
        border-left-color: #28a745;
    }
    
    .info-empleado {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .stats-card {
        border-radius: 10px;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    /* CONTENIDO Y ACCIONES */
    .card-content {
        flex: 1;
    }
    
    .card-actions {
        margin-top: auto;
        padding-top: 1rem;
        border-top: 1px solid #e9ecef;
    }
</style>
</head>
<body>
       <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="../dashboard-admin-soporte.php">
                <img src="../../img/LogoGris.png" alt="Logo ACP" style="height: 40px;" class="me-2">
                <span class="fw-semibold">Sistema de Tickets</span>
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
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4 mb-5">
        <div class="row">
            <div class="col-12">

                <!-- Header -->
                <div class="page-header mb-4">
                    <h1 class="page-title">
                        <i class="bi bi-inbox"></i> Solicitudes VOL - Gestión TI
                    </h1>
                    <p class="page-subtitle">Gestiona las solicitudes de accesos Volvo</p>
                </div>

                <!-- Mensajes -->
                <?php if (isset($_SESSION['mensaje_exito'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['mensaje_exito']); ?>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h3 class="text-primary mb-0"><?= $stats['total'] ?></h3>
                                <small class="text-muted">Total Solicitudes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h3 class="text-warning mb-0"><?= $stats['pendientes'] ?></h3>
                                <small class="text-muted">Pendientes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h3 class="text-success mb-0"><?= $stats['entregadas'] ?></h3>
                                <small class="text-muted">Entregadas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Estado</label>
                                    <select class="form-select" name="estado">
                                        <option value="">Todos</option>
                                        <option value="pendiente" <?= $filtroEstado == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="entregado" <?= $filtroEstado == 'entregado' ? 'selected' : '' ?>>Entregado</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Tipo</label>
                                    <select class="form-select" name="tipo">
                                        <option value="">Todos</option>
                                        <option value="crear" <?= $filtroTipo == 'crear' ? 'selected' : '' ?>>Crear</option>
                                        <option value="solicitar" <?= $filtroTipo == 'solicitar' ? 'selected' : '' ?>>Solicitar</option>
                                        <option value="licencias" <?= $filtroTipo == 'licencias' ? 'selected' : '' ?>>Licencias</option>
                                        <option value="eliminar" <?= $filtroTipo == 'eliminar' ? 'selected' : '' ?>>Eliminar</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Jefe</label>
                                    <input type="text" class="form-control" name="jefe" 
                                           value="<?= htmlspecialchars($filtroJefe) ?>"
                                           placeholder="Nombre del jefe">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Desde</label>
                                    <input type="date" class="form-control" name="fecha_desde"
                                           value="<?= htmlspecialchars($filtroFechaDesde) ?>">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Hasta</label>
                                    <input type="date" class="form-control" name="fecha_hasta"
                                           value="<?= htmlspecialchars($filtroFechaHasta) ?>">
                                </div>
                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                            <?php if (!empty($filtroJefe) || !empty($filtroTipo) || !empty($filtroFechaDesde) || !empty($filtroFechaHasta) || $filtroEstado != 'pendiente'): ?>
                                <div class="text-end">
                                    <a href="solicitudes-vol-ti.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Limpiar Filtros
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

              <!-- Lista de Solicitudes -->
<h5 class="mb-3">
    <i class="bi bi-list-ul"></i> Solicitudes 
    <span class="badge bg-secondary"><?= count($solicitudes) ?></span>
</h5>

<?php if (count($solicitudes) > 0): ?>
    <div class="row g-3">
        <?php foreach ($solicitudes as $sol): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-solicitud <?= $sol['estado'] ?>">
                    <div class="card-body">
                        
                        <!-- CONTENIDO DEL CARD -->
                        <div class="card-content">
                            
                            <!-- Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-0">
                                        <strong><?= htmlspecialchars($sol['folio']) ?></strong>
                                    </h6>
                                    <small class="text-muted">
                                        <?php
                                        if ($sol['fecha_generacion'] instanceof DateTime) {
                                            echo $sol['fecha_generacion']->format('d/m/Y H:i');
                                        } else {
                                            echo date('d/m/Y H:i', strtotime($sol['fecha_generacion']));
                                        }
                                        ?>
                                    </small>
                                </div>
                                <div>
                                    <?php
                                    $badges = [
                                        'crear' => 'bg-primary',
                                        'solicitar' => 'bg-info',
                                        'licencias' => 'bg-warning text-dark',
                                        'eliminar' => 'bg-danger'
                                    ];
                                    $badgeClass = $badges[$sol['tipo_solicitud']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $badgeClass ?> badge-tipo">
                                        <?= strtoupper($sol['tipo_solicitud']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Empleado -->
                            <div class="mb-3">
                                <div class="fw-bold text-dark">
                                    <i class="bi bi-person"></i> 
                                    <?= htmlspecialchars($sol['empleado_nombre']) ?>
                                </div>
                                <div class="info-empleado">
                                    <i class="bi bi-briefcase"></i> <?= htmlspecialchars($sol['cargo']) ?><br>
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($sol['sucursal']) ?>
                                </div>
                            </div>

                            <!-- Jefe -->
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-person-badge"></i> Solicitado por:
                                </small>
                                <div class="fw-semibold small text-dark">
                                    <?= htmlspecialchars($sol['jefe_nombre']) ?>
                                </div>
                            </div>

                            <!-- Accesos -->
                            <div class="mb-3">
                                <span class="badge bg-secondary">
                                    <i class="bi bi-key"></i> <?= $sol['cantidad_accesos'] ?> acceso(s)
                                </span>
                            </div>

                            <!-- Estado -->
                            <div class="mb-3">
                                <?php if ($sol['estado'] == 'pendiente'): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-clock"></i> Pendiente
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Entregado
                                    </span>
                                    <?php if ($sol['fecha_entrega']): ?>
                                        <br><small class="text-muted">
                                            <?php
                                            if ($sol['fecha_entrega'] instanceof DateTime) {
                                                echo $sol['fecha_entrega']->format('d/m/Y');
                                            } else {
                                                echo date('d/m/Y', strtotime($sol['fecha_entrega']));
                                            }
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                        </div>
                        <!-- FIN CONTENIDO -->

                        <!-- ACCIONES - SIEMPRE AL FONDO -->
                        <div class="card-actions">
                            <div class="d-grid gap-2">
                                <button onclick="verDetalleSolicitud('<?= htmlspecialchars($sol['folio']) ?>')" 
                                        class="btn btn-sm btn-outline-info w-100">
                                    <i class="bi bi-eye"></i> Ver Detalle
                                </button>
                                
                                <?php if (file_exists($sol['ruta_documento'])): ?>
                                    <a href="../../actions/accesos/descargar-solicitud-vol.php?folio=<?= urlencode($sol['folio']) ?>" 
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-download"></i> Descargar
                                    </a>
                                <?php endif; ?>

                              <?php if ($sol['estado'] == 'pendiente'): ?>
                                <!-- Esperando que el jefe entregue -->
                                <button class="btn btn-sm btn-outline-warning w-100" disabled>
                                    <i class="bi bi-clock"></i> Esperando Entrega del Jefe
                                </button>
                            <?php elseif ($sol['estado'] == 'entregado'): ?>
                                <!-- TI puede marcar como completado -->
                                <button onclick="marcarCompletado('<?= htmlspecialchars($sol['folio']) ?>')"
                                        class="btn btn-sm btn-success w-100">
                                    <i class="bi bi-check-circle-fill"></i> Marcar como Completado
                                </button>
                            <?php elseif ($sol['estado'] == 'completado'): ?>
                                <!-- Ya completado -->
                                <button class="btn btn-sm btn-secondary w-100" disabled>
                                    <i class="bi bi-check-all"></i> Completado
                                </button>
                            <?php endif; ?>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info text-center">
        <i class="bi bi-info-circle"></i> No hay solicitudes con los filtros seleccionados
    </div>
<?php endif; ?>
                </div>

            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/app.js"></script>

<!-- Modal Ver Detalle - DISEÑO LIMPIO -->
<div class="modal fade" id="modalVerDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Detalle de Solicitud VOL
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetalle">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
<style>
/* Modal con fondo oscuro uniforme */
#modalVerDetalle .modal-content {
    background-color: #2b3035;
    color: #ffffff;
}

#modalVerDetalle .modal-header {
    background-color: #2b3035;
    color: #ffffff;
    border-bottom: 1px solid #404449;
}

#modalVerDetalle .modal-body {
    background-color: #2b3035;
    color: #ffffff;
}

#modalVerDetalle .modal-footer {
    background-color: #2b3035;
    border-top: 1px solid #404449;
}

/* Campos de información */
#modalVerDetalle .form-control-plaintext {
    background-color: #1e2125 !important;
    color: #ffffff !important;
    border-color: #404449 !important;
    min-height: 42px;
    display: flex;
    align-items: center;
}

#modalVerDetalle .form-label {
    color: #adb5bd !important;
}

/* Accesos */
#modalVerDetalle .border {
    background-color: #1e2125 !important;
    border-color: #404449 !important;
    color: #ffffff !important;
}

#modalVerDetalle .border small {
    color: #ffffff !important;
}

/* Header del folio */
#modalVerDetalle .bg-primary {
    background-color: #0d6efd !important;
    color: #ffffff !important;
}

/* Badges */
#modalVerDetalle .badge {
    color: #ffffff !important;
}

#modalVerDetalle .badge.bg-warning {
    background-color: #ffc107 !important;
    color: #000000 !important;
}

#modalVerDetalle .badge.bg-success {
    background-color: #198754 !important;
    color: #ffffff !important;
}

/* Texto muted */
#modalVerDetalle .text-muted {
    color: #6c757d !important;
}

#modalVerDetalle em.text-muted {
    color: #adb5bd !important;
}

/* Iconos */
#modalVerDetalle i {
    color: #ffffff;
}

#modalVerDetalle .text-success {
    color: #198754 !important;
}

/* Botones */
#modalVerDetalle .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

/* Spinner */
#modalVerDetalle .spinner-border {
    color: #0d6efd !important;
}
</style>
<script>
function verDetalleSolicitud(folio) {
    const modal = new bootstrap.Modal(document.getElementById('modalVerDetalle'));
    const contenido = document.getElementById('contenidoDetalle');
    
    contenido.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    fetch('../../actions/accesos/obtener-detalle-solicitud-vol.php?folio=' + encodeURIComponent(folio))
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                contenido.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>${data.error}
                    </div>
                `;
                return;
            }
            
            const sol = data.solicitud;
            const accesos = data.accesos;
            
            const tipos = {
                'crear': 'Crear Usuario',
                'solicitar': 'Solicitar Accesos',
                'licencias': 'Solicitar Licencias',
                'eliminar': 'Eliminar Usuario'
            };
            
            const nombresAccesos = {
                'trucks_portal_volvo': 'Trucks Portal Volvo',
                'argus_dealer': 'Argus Dealer',
                'dynafleet': 'Dynafleet',
                'impact_vt': 'Impact',
                'parts_online': 'Parts Online',
                'product_history': 'Product History Viewer',
                'technical_service': 'Technical Service Bulletin',
                'truck_campaign': 'Truck Campaign Information',
                'trucks_portal_ud': 'Trucks Portal UD',
                'ud_product_history': 'UD Product History Viewer',
                'vosp': 'VOSP',
                'wiring_diagrams': 'Wiring Diagrams',
                'mack_trucks_dealer': 'Mack Trucks Dealer Portal',
                'mack_electronic_info': 'Mack Electronic Info System',
                'mack_impact': 'Mack Impact',
                'mack_product_history': 'Mack Product History Viewer',
                'vdn': 'VDN',
                'caretrack': 'CareTrack',
                'chain': 'CHAIN',
                'prosis_pro': 'Prosis Pro',
                'vlc': 'VLC',
                'tech_tool_matris': 'Tech Tool 2 / MATRIS 2',
                'tt_accesos': 'Tech Tool 2 - Accesos',
                'tt_licencia': 'Tech Tool 2 - Licencia',
                'vppn': 'VPPN',
                'epc_offline': 'EPC Offline',
                'vodia5': 'VODIA 5',
                'vodia_acceso': 'VODIA 5 - Acceso',
                'vodia_licencia': 'VODIA 5 - Licencia',
                'vtt': 'VTT',
                'vtt_nueva': 'VTT - Nueva',
                'vtt_renovacion': 'VTT - Renovación',
                'vtt_volvo_trucks': 'VTT - Volvo Trucks',
                'vtt_volvo_buses': 'VTT - Volvo Buses',
                'vtt_mack_trucks': 'VTT - Mack Trucks',
                'vtt_ud_trucks': 'VTT - UD Trucks',
                'lds': 'LDS',
                'gds': 'GDS',
                'time_recording': 'Time Recording',
                'uchp': 'UCHP',
                'uchp_vtc': 'UCHP - VTC',
                'uchp_vbc': 'UCHP - VBC',
                'uchp_mack': 'UCHP - MACK',
                'uchp_ud': 'UCHP - UD',
                'uchp_vce': 'UCHP - VCE',
                'uchp_penta': 'UCHP - PENTA',
                'warranty_bulletin': 'Warranty Bulletin',
                'vda_plus': 'VDA+',
                'tsa': 'TSA'
            };
            
            // Estado
            let estadoBadge = '';
            if (sol.estado === 'pendiente') {
                estadoBadge = '<span class="badge bg-warning text-dark"><i class="bi bi-clock-fill me-1"></i> Pendiente de Entrega</span>';
            } else if (sol.estado === 'entregado') {
                estadoBadge = '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> Entregado a TI</span>';
            } else {
                estadoBadge = `<span class="badge bg-secondary">${sol.estado}</span>`;
            }
            
            // Fecha
            const fecha = new Date(sol.fecha_generacion);
            const fechaStr = fecha.toLocaleDateString('es-PE', {day: '2-digit', month: '2-digit', year: 'numeric'}) + 
                           ' ' + fecha.toLocaleTimeString('es-PE', {hour: '2-digit', minute: '2-digit'});
            
            // Accesos
            let accesosHTML = '';
            if (accesos && accesos.length > 0) {
                accesosHTML = `
                    <h6 class="fw-bold mt-4 mb-3">
                        <i class="bi bi-key-fill me-2"></i>Accesos Incluidos (${accesos.length})
                    </h6>
                    <div class="row g-2">
                `;
                accesos.forEach(acc => {
                    const nombre = nombresAccesos[acc.acceso] || acc.acceso;
                    accesosHTML += `
                        <div class="col-md-6">
                            <div class="border rounded p-2 bg-body">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <small>${nombre}</small>
                            </div>
                        </div>
                    `;
                });
                accesosHTML += '</div>';
            }
            
            // Botón descarga
            let btnDescarga = '';
            if (data.archivoExiste) {
                btnDescarga = `
                    <a href="../../actions/accesos/descargar-solicitud-vol.php?folio=${encodeURIComponent(folio)}" 
                       class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-download me-2"></i>Descargar Documento Word
                    </a>
                `;
            }
            
            contenido.innerHTML = `
                <div class="text-center mb-4 py-3 bg-primary text-white rounded">
                    <h5 class="mb-0">Folio: <strong>${folio}</strong></h5>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted mb-1">
                            <i class="bi bi-person me-1"></i>EMPLEADO
                        </label>
                        <div class="form-control-plaintext border rounded px-3 py-2 bg-body">
                            ${sol.empleado_nombre} ${sol.empleado_apellido}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted mb-1">
                            <i class="bi bi-person-badge me-1"></i>ID USUARIO VOL
                        </label>
                        <div class="form-control-plaintext border rounded px-3 py-2 bg-body">
                            ${sol.id_usuario || '<em class="text-muted">Se asignará después</em>'}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted mb-1">
                            <i class="bi bi-file-text me-1"></i>TIPO DE SOLICITUD
                        </label>
                        <div class="form-control-plaintext border rounded px-3 py-2 bg-body">
                            ${tipos[sol.tipo_solicitud] || sol.tipo_solicitud}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted mb-1">
                            <i class="bi bi-calendar-check me-1"></i>FECHA DE GENERACIÓN
                        </label>
                        <div class="form-control-plaintext border rounded px-3 py-2 bg-body">
                            ${fechaStr}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted mb-1">
                            <i class="bi bi-person-check me-1"></i>GENERADO POR
                        </label>
                        <div class="form-control-plaintext border rounded px-3 py-2 bg-body">
                            ${sol.generado_por_nombre}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted mb-1">
                            <i class="bi bi-flag me-1"></i>ESTADO ACTUAL
                        </label>
                        <div class="form-control-plaintext border rounded px-3 py-2 bg-body">
                            ${estadoBadge}
                        </div>
                    </div>
                </div>
                
                ${accesosHTML}
                
                <div class="mt-4">
                    ${btnDescarga}
                </div>
            `;
        })
        .catch(err => {
            console.error(err);
            contenido.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Error al cargar los detalles
                </div>
            `;
        });
}
</script>
<script>
function marcarCompletado(folio) {
    if (!confirm('¿Confirmar que los accesos fueron habilitados?')) {
        return;
    }

    fetch('../../actions/accesos/completar-solicitud-vol.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'folio=' + encodeURIComponent(folio)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✓ Solicitud completada. Accesos activados.');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(err => alert('Error al procesar la solicitud'));
}
</script>
</body>
</html>