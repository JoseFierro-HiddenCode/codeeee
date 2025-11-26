<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo Admin y TI pueden acceder
protegerPagina(['admin_tecnico', 'tecnico']);

// Obtener solicitudes aprobadas pendientes de asignación
$sqlSolicitudes = "
SELECT 
    s.*,
    CONVERT(VARCHAR, s.fecha_aprobacion, 23) as fecha_aprobacion,
    CONVERT(VARCHAR, s.fecha_aprobacion_general, 23) as fecha_aprobacion_general,
    c.nombre + ' ' + c.apellido as colaborador_nombre,
    c.dni as colaborador_dni,
    c.puesto as colaborador_puesto,
    c.telefono as colaborador_telefono,
    j.nombre + ' ' + j.apellido as jefe_nombre,
    gf.nombre + ' ' + gf.apellido as gerente_nombre,
    gg.nombre + ' ' + gg.apellido as gerente_general_nombre,
    sede.nombre as sede_nombre,
    area.nombre as area_nombre
FROM solicitudes_equipos s
LEFT JOIN users c ON s.colaborador_id = c.id
LEFT JOIN users j ON s.solicitante_id = j.id
LEFT JOIN users gf ON s.aprobado_por = gf.id
LEFT JOIN users gg ON s.aprobado_por_general = gg.id
LEFT JOIN sedes sede ON c.sede_id = sede.id
LEFT JOIN areas area ON c.area_id = area.id
WHERE s.estado = 'aprobada'
ORDER BY s.fecha_aprobacion_general DESC, s.fecha_aprobacion DESC
";
$solicitudes = obtenerTodos($sqlSolicitudes);

// Obtener equipos disponibles
$equiposDisponibles = obtenerEquiposDisponiblesPorTipo();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Equipos - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo obtenerDashboardUrl(true); ?>">
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
            <a href="<?php echo obtenerDashboardUrl(true); ?>" class="sidebar-nav-link">
        <i class="bi bi-grid-fill"></i>
        <span>Dashboard Tickets</span>
            </a>
            </li>
        </ul>
        
        <div class="sidebar-section-title">Inventario</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="dashboard-inventario.php" class="sidebar-nav-link">
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
                <a href="asignar-equipos-fisicos.php" class="sidebar-nav-link active">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Solicitudes Aprobadas</span>
                    <?php if (count($solicitudes) > 0): ?>
                        <span class="ms-auto badge bg-warning"><?php echo count($solicitudes); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="sidebar-nav-item">
    <a href="equipos-asignados.php" class="sidebar-nav-link">
        <i class="bi bi-diagram-3"></i>
        <span>Equipos Asignados</span>
    </a>
</li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Asignar Equipos Físicos</h1>
            <p class="page-subtitle">Solicitudes aprobadas pendientes de asignación</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Equipos asignados exitosamente. 
                Carta responsiva generada: <strong><?php echo e($_GET['folio'] ?? ''); ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Solicitudes Aprobadas -->
        <?php if (count($solicitudes) > 0): ?>
            <?php foreach ($solicitudes as $sol): ?>
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Folio: <?php echo e($sol['folio']); ?></strong>
                            <span class="ms-3">|</span>
                            <span class="ms-3">Aprobado: <?php echo formatearFecha($sol['fecha_aprobacion']); ?></span>
                        </div>
                        <?php echo badgeEstadoSolicitud($sol['estado']); ?>
                    </div>
                    <div class="card-body">
                        <!-- Información del Colaborador -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6><i class="bi bi-person"></i> Datos del Colaborador</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Nombre:</strong></td>
                                        <td><?php echo e($sol['colaborador_nombre']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>DNI:</strong></td>
                                        <td><?php echo e($sol['colaborador_dni'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Puesto:</strong></td>
                                        <td><?php echo e($sol['colaborador_puesto'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sede:</strong></td>
                                        <td><?php echo e($sol['sede_nombre'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Área:</strong></td>
                                        <td><?php echo e($sol['area_nombre'] ?? '-'); ?></td>
                                    </tr>
                                </table>
                            </div>

                            <div class="col-md-6">
                                <h6><i class="bi bi-clipboard-check"></i> Información de Aprobación</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Solicitante:</strong></td>
                                        <td><?php echo e($sol['jefe_nombre']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Aprobado por:</strong></td>
                                        <td><?php echo e($sol['gerente_nombre']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha aprobación:</strong></td>
                                        <td><?php echo formatearFecha($sol['fecha_aprobacion']); ?></td>
                                    </tr>
                                    <?php if ($sol['comentario_gerente']): ?>
                                    <tr>
                                        <td><strong>Comentario:</strong></td>
                                        <td><?php echo e($sol['comentario_gerente']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <hr>

                        <!-- Equipos Solicitados -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <h6><i class="bi bi-laptop"></i> Equipos Solicitados</h6>
                                <ul>
                                    <?php if ($sol['solicita_laptop']): ?>
                                        <li>💻 <strong>Laptop</strong></li>
                                    <?php endif; ?>
                                    <?php if ($sol['solicita_celular']): ?>
                                        <li>📱 <strong>Celular</strong></li>
                                    <?php endif; ?>
                                    <?php if ($sol['solicita_pc']): ?>
                                        <li>🖥️ <strong>PC Escritorio</strong></li>
                                    <?php endif; ?>
                                    <?php if ($sol['otros_equipos']): ?>
                                        <li>🗂️ <strong>Otros:</strong> <?php echo e($sol['otros_equipos']); ?></li>
                                    <?php endif; ?>
                                </ul>

                                <?php if ($sol['caracteristicas_equipo']): ?>
                                    <p><strong>Características especiales:</strong><br>
                                    <small><?php echo nl2br(e($sol['caracteristicas_equipo'])); ?></small></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr>

                        <!-- Botón para asignar equipos -->
                    <div class="text-end">
                        <button class="btn btn-info me-2" onclick="verDetalleSolicitud('<?php echo e($sol['folio']); ?>')">
                            <i class="bi bi-eye"></i> Ver Detalle Completo
                        </button>
                        <button class="btn btn-primary btn-lg" onclick="mostrarModalAsignacion(<?php echo $sol['id']; ?>, <?php echo $sol['colaborador_id']; ?>, '<?php echo e($sol['folio']); ?>', '<?php echo e($sol['colaborador_nombre']); ?>')">
                            <i class="bi bi-box-seam"></i> Asignar Equipos Físicos
                        </button>
                    </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No hay solicitudes aprobadas pendientes de asignación.
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Asignar Equipos -->
    <div class="modal fade" id="modalAsignarEquipos" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-box-seam"></i> Asignar Equipos Físicos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../actions/inventario/asignar-equipos.php" method="POST" id="formAsignarEquipos">
                    <input type="hidden" id="solicitud_id" name="solicitud_id">
                    <input type="hidden" id="usuario_id" name="usuario_id">
                    <input type="hidden" id="folio_solicitud" name="folio_solicitud">
                    
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Colaborador:</strong> <span id="nombre_colaborador"></span><br>
                            <strong>Folio Solicitud:</strong> <span id="folio_display"></span>
                        </div>

                        <h6>Selecciona los equipos a asignar:</h6>
                        <p class="text-muted">Marca los equipos que deseas asignar a este colaborador</p>

                        <div class="row">
                            <?php 
                            // Agrupar equipos por tipo
                            $equiposPorTipo = [];
                            foreach ($equiposDisponibles as $equipo) {
                                $equiposPorTipo[$equipo['tipo']][] = $equipo;
                            }
                            ?>

                            <?php foreach ($equiposPorTipo as $tipo => $equipos): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <strong><?php echo ucfirst($tipo); ?> (<?php echo count($equipos); ?> disponibles)</strong>
                                        </div>
                                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($equipos as $eq): ?>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="equipos[]" 
                                                           value="<?php echo $eq['id']; ?>" 
                                                           id="equipo_<?php echo $eq['id']; ?>">
                                                    <label class="form-check-label" for="equipo_<?php echo $eq['id']; ?>">
                                                        <strong><?php echo e($eq['marca'] . ' ' . $eq['modelo']); ?></strong><br>
                                                        <small>
                                                            Serie: <code><?php echo e($eq['numero_serie']); ?></code><br>
                                                            <?php if ($eq['procesador']): ?>
                                                                CPU: <?php echo e($eq['procesador']); ?><br>
                                                            <?php endif; ?>
                                                            <?php if ($eq['sistema_operativo']): ?>
                                                                SO: <?php echo e($eq['sistema_operativo']); ?><br>
                                                            <?php endif; ?>
                                                            <?php if ($eq['color']): ?>
                                                                Color: <?php echo e($eq['color']); ?><br>
                                                            <?php endif; ?>
                                                            Estado: <?php echo badgeEstadoEquipo($eq['estado']); ?>
                                                        </small>
                                                    </label>
                                                </div>
                                                <hr>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($equiposDisponibles) == 0): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> No hay equipos disponibles en el inventario.
                                <a href="gestionar-equipos.php">Agregar equipos</a>
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="mb-3">
                            <label for="notas_asignacion" class="form-label">Notas de Asignación (opcional)</label>
                            <textarea class="form-control" id="notas_asignacion" name="notas_asignacion" rows="3"
                                      placeholder="Observaciones o comentarios sobre esta asignación..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnAsignar">
                            <i class="bi bi-check-circle"></i> Asignar y Generar Carta Responsiva
                        </button>
                    </div>
                </form>
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
    themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}
updateIcon(currentTheme);

themeToggle.addEventListener('click', function() {
    const currentTheme = document.body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateIcon(newTheme);
});

// Datos de solicitudes
// Datos de solicitudes
const solicitudes = <?php echo json_encode($solicitudes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

// DEBUG: Ver qué datos llegan
console.log('Solicitudes cargadas:', solicitudes);

let modalAsignar, modalDetalle;

document.addEventListener('DOMContentLoaded', function() {
    modalAsignar = new bootstrap.Modal(document.getElementById('modalAsignarEquipos'));
    modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalleSolicitud'));
});

function mostrarModalAsignacion(solicitudId, usuarioId, folio, nombreColaborador) {
    document.getElementById('solicitud_id').value = solicitudId;
    document.getElementById('usuario_id').value = usuarioId;
    document.getElementById('folio_solicitud').value = folio;
    document.getElementById('nombre_colaborador').textContent = nombreColaborador;
    document.getElementById('folio_display').textContent = folio;
    
    // Limpiar checkboxes
    document.querySelectorAll('input[name="equipos[]"]').forEach(cb => cb.checked = false);
    document.getElementById('notas_asignacion').value = '';
    
    modalAsignar.show();
}

function verDetalleSolicitud(folio) {
    const sol = solicitudes.find(s => s.folio === folio);
    
    console.log('=== DEBUG DETALLE SOLICITUD ===');
    console.log('Folio buscado:', folio);
    console.log('Solicitud encontrada:', sol);
    
    if (!sol) {
        alert('No se encontró la solicitud');
        return;
    }
    
    // Tab Colaborador
    document.getElementById('det-nombre').textContent = sol.colaborador_nombre || '-';
    document.getElementById('det-dni').textContent = sol.colaborador_dni || '-';
    document.getElementById('det-puesto').textContent = sol.colaborador_puesto || '-';
    document.getElementById('det-telefono').textContent = sol.colaborador_telefono || '-';
    document.getElementById('det-sede').textContent = sol.sede_nombre || '-';
    document.getElementById('det-area').textContent = sol.area_nombre || '-';
    document.getElementById('det-jefe').textContent = sol.jefe_nombre || '-';
    document.getElementById('det-justificacion').textContent = sol.justificacion || 'Sin justificación';
    
    // Tab Equipos - CON DEBUG
    console.log('Laptop:', sol.solicita_laptop, typeof sol.solicita_laptop);
    console.log('Celular:', sol.solicita_celular, typeof sol.solicita_celular);
    console.log('PC:', sol.solicita_pc, typeof sol.solicita_pc);
    console.log('Otros equipos:', sol.otros_equipos);
    
    let equiposHTML = '<ul class="list-group">';
    let tieneEquipos = false;
    
    // Verificar múltiples formas (1, "1", true)
    if (sol.solicita_laptop == 1 || sol.solicita_laptop === true || sol.solicita_laptop === '1') {
        equiposHTML += '<li class="list-group-item" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 12px; margin-bottom: 8px; border-radius: 6px;">💻 <strong>Laptop</strong></li>';
        tieneEquipos = true;
    }
    
    if (sol.solicita_celular == 1 || sol.solicita_celular === true || sol.solicita_celular === '1') {
        equiposHTML += '<li class="list-group-item" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 12px; margin-bottom: 8px; border-radius: 6px;">📱 <strong>Celular</strong></li>';
        tieneEquipos = true;
    }
    
    if (sol.solicita_pc == 1 || sol.solicita_pc === true || sol.solicita_pc === '1') {
        equiposHTML += '<li class="list-group-item" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 12px; margin-bottom: 8px; border-radius: 6px;">🖥️ <strong>PC Escritorio</strong></li>';
        tieneEquipos = true;
    }
    
    if (sol.otros_equipos && sol.otros_equipos.trim() !== '') {
        equiposHTML += `<li class="list-group-item" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 12px; margin-bottom: 8px; border-radius: 6px;">🗂️ <strong>Otros:</strong> ${sol.otros_equipos}</li>`;
        tieneEquipos = true;
    }
    
    if (!tieneEquipos) {
        equiposHTML += '<li class="list-group-item text-warning"><i class="bi bi-exclamation-triangle"></i> No se detectaron equipos solicitados</li>';
    }
    
    equiposHTML += '</ul>';
    document.getElementById('det-equipos-list').innerHTML = equiposHTML;
    document.getElementById('det-caracteristicas').textContent = sol.caracteristicas_equipo || 'Sin características especiales';
    
    // Tab Accesos - SISTEMAS
    console.log('GDS:', sol.solicita_gds);
    console.log('CONTASIS:', sol.solicita_contasis);
    
    let accesosHTML = '<h6 class="mb-3">Sistemas Solicitados</h6><ul class="list-group mb-3">';
    let tieneSistemas = false;
    
    // GDS
    if (sol.solicita_gds == 1 || sol.solicita_gds === true || sol.solicita_gds === '1') {
        accesosHTML += '<li class="list-group-item">';
        accesosHTML += '✅ <strong>GDS (Gestión Documental)</strong><br>';
        if (sol.gds_rol) accesosHTML += `<small>Rol: ${sol.gds_rol}</small><br>`;
        if (sol.gds_usuario) accesosHTML += `<small>Usuario: ${sol.gds_usuario}</small><br>`;
        if (sol.gds_justificacion) accesosHTML += `<small>Justificación: ${sol.gds_justificacion}</small>`;
        accesosHTML += '</li>';
        tieneSistemas = true;
    }
    
    // CONTASIS
    if (sol.solicita_contasis == 1 || sol.solicita_contasis === true || sol.solicita_contasis === '1') {
        accesosHTML += '<li class="list-group-item">✅ <strong>CONTASIS (Sistema Contable)</strong></li>';
        tieneSistemas = true;
    }
    
    accesosHTML += '</ul>';
    
    // Servicios Planta (SP)
    let tieneServicios = false;
    let serviciosHTML = '<h6 class="mb-3">Servicios de Planta</h6><ul class="list-group mb-3">';
    
    const servicios = [
        {campo: 'sp_gestion_mostrador', nombre: 'Gestión Mostrador'},
        {campo: 'sp_gestion_servicio', nombre: 'Gestión Servicio'},
        {campo: 'sp_unidades_nuevas', nombre: 'Unidades Nuevas'},
        {campo: 'sp_contratos_inhouse', nombre: 'Contratos InHouse'},
        {campo: 'sp_admin_finanzas', nombre: 'Admin. Finanzas'},
        {campo: 'sp_contabilidad', nombre: 'Contabilidad'},
        {campo: 'sp_logistica', nombre: 'Logística'},
        {campo: 'sp_sei', nombre: 'SEI'},
        {campo: 'sp_rrhh', nombre: 'RRHH'},
        {campo: 'sp_ssoma', nombre: 'SSOMA'},
        {campo: 'sp_tic', nombre: 'TIC'},
        {campo: 'sp_calidad', nombre: 'Calidad'},
    ];
    
    servicios.forEach(srv => {
        if (sol[srv.campo] == 1 || sol[srv.campo] === true || sol[srv.campo] === '1') {
            serviciosHTML += `<li class="list-group-item">✅ ${srv.nombre}</li>`;
            tieneServicios = true;
        }
    });
    
    if (sol.sp_nivel) {
        serviciosHTML += `<li class="list-group-item"><strong>Nivel:</strong> ${sol.sp_nivel}</li>`;
        tieneServicios = true;
    }
    
    serviciosHTML += '</ul>';
    
    if (tieneServicios) {
        accesosHTML += serviciosHTML;
    }
    
    // ACPCore Roles
    let tieneRoles = false;
    let rolesHTML = '<h6 class="mb-3">Roles ACPCore</h6><ul class="list-group mb-3">';
    
    const roles = [
        {campo: 'acpcore_asesor_contratos', nombre: 'Asesor Contratos'},
        {campo: 'acpcore_asesor_garantias', nombre: 'Asesor Garantías'},
        {campo: 'acpcore_asesor_mostrador', nombre: 'Asesor Mostrador'},
        {campo: 'acpcore_asesor_repuesto', nombre: 'Asesor Repuesto'},
        {campo: 'acpcore_asesor_servicio', nombre: 'Asesor Servicio'},
        {campo: 'acpcore_asist_admin', nombre: 'Asistente Admin'},
        {campo: 'acpcore_asist_contable', nombre: 'Asistente Contable'},
        {campo: 'acpcore_asist_caja', nombre: 'Asistente Caja'},
        {campo: 'acpcore_asist_cobranza', nombre: 'Asistente Cobranza'},
        {campo: 'acpcore_asist_calidad', nombre: 'Asistente Calidad'},
        {campo: 'acpcore_asist_almacen_herr', nombre: 'Asist. Almacén Herramientas'},
        {campo: 'acpcore_asist_almacen_log', nombre: 'Asist. Almacén Logística'},
        {campo: 'acpcore_asist_rrhh', nombre: 'Asistente RRHH'},
        {campo: 'acpcore_asist_infraestructura', nombre: 'Asist. Infraestructura'},
        {campo: 'acpcore_asist_jefe_servicio', nombre: 'Asist. Jefe Servicio'},
        {campo: 'acpcore_asist_planificador', nombre: 'Asist. Planificador'},
        {campo: 'acpcore_coord_contratos', nombre: 'Coordinador Contratos'},
        {campo: 'acpcore_facturador', nombre: 'Facturador'},
        {campo: 'acpcore_instructor_conduccion', nombre: 'Instructor Conducción'},
        {campo: 'acpcore_instructor_tecnico', nombre: 'Instructor Técnico'},
        {campo: 'acpcore_jefe_calidad', nombre: 'Jefe Calidad'},
        {campo: 'acpcore_jefe_contabilidad', nombre: 'Jefe Contabilidad'},
        {campo: 'acpcore_jefe_contratos', nombre: 'Jefe Contratos'},
        {campo: 'acpcore_jefe_contratos_inhouse', nombre: 'Jefe Contratos InHouse'},
        {campo: 'acpcore_jefe_logistica', nombre: 'Jefe Logística'},
        {campo: 'acpcore_jefe_repuestos', nombre: 'Jefe Repuestos'},
        {campo: 'acpcore_jefe_servicio', nombre: 'Jefe Servicio'},
        {campo: 'acpcore_jefe_taller', nombre: 'Jefe Taller'},
        {campo: 'acpcore_jefe_unidades', nombre: 'Jefe Unidades'},
        {campo: 'acpcore_mecanico', nombre: 'Mecánico'},
        {campo: 'acpcore_mecanico_contratos', nombre: 'Mecánico Contratos'},
        {campo: 'acpcore_operador_logistico', nombre: 'Operador Logístico'},
        {campo: 'acpcore_practicante_logistica', nombre: 'Practicante Logística'},
        {campo: 'acpcore_responsable_sst', nombre: 'Responsable SST'},
        {campo: 'acpcore_supervisor', nombre: 'Supervisor'},
    ];
    
    roles.forEach(rol => {
        if (sol[rol.campo] == 1 || sol[rol.campo] === true || sol[rol.campo] === '1') {
            rolesHTML += `<li class="list-group-item">✅ ${rol.nombre}</li>`;
            tieneRoles = true;
        }
    });
    
    if (sol.acpcore_otros) {
        rolesHTML += `<li class="list-group-item"><strong>Otros:</strong> ${sol.acpcore_otros}</li>`;
        tieneRoles = true;
    }
    
    rolesHTML += '</ul>';
    
    if (tieneRoles) {
        accesosHTML += rolesHTML;
    }
    
    if (!tieneSistemas && !tieneServicios && !tieneRoles) {
        accesosHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No se solicitaron accesos a sistemas</div>';
    }
    
    document.getElementById('det-accesos-list').innerHTML = accesosHTML;
    document.getElementById('det-otros-accesos').textContent = sol.otros_accesos || 'Ninguno';
    
    // Tab Aprobaciones
   // Tab Aprobaciones
document.getElementById('det-gerente-finanzas').textContent = sol.gerente_nombre || '-';
document.getElementById('det-fecha-finanzas').textContent = sol.fecha_aprobacion ? new Date(sol.fecha_aprobacion).toLocaleDateString('es-MX') : '-';
document.getElementById('det-gerente-general').textContent = sol.gerente_general_nombre || '-';
document.getElementById('det-fecha-general').textContent = sol.fecha_aprobacion_general ? new Date(sol.fecha_aprobacion_general).toLocaleDateString('es-MX') : '-';
    
    let comentarios = '';
    if (sol.comentario_gerente) comentarios += `<strong>Gerente Finanzas:</strong> ${sol.comentario_gerente}<br>`;
    if (sol.comentario_general) comentarios += `<strong>Gerente General:</strong> ${sol.comentario_general}`;
    
    document.getElementById('det-comentarios').innerHTML = comentarios || 'Sin comentarios';
    
    console.log('=== FIN DEBUG ===');
    
    modalDetalle.show();
}
</script>
</body>
</html>
    <!-- Modal Detalle Completo -->
<div class="modal fade" id="modalDetalleSolicitud" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-file-text"></i> Detalle Completo de Solicitud</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-colaborador">
                            <i class="bi bi-person"></i> Colaborador
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-equipos">
                            <i class="bi bi-laptop"></i> Equipos
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-accesos">
                            <i class="bi bi-key"></i> Accesos y Sistemas
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-aprobaciones">
                            <i class="bi bi-check-circle"></i> Aprobaciones
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Tab Colaborador -->
                    <div class="tab-pane fade show active" id="tab-colaborador">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr><td><strong>Nombre:</strong></td><td id="det-nombre"></td></tr>
                                    <tr><td><strong>DNI:</strong></td><td id="det-dni"></td></tr>
                                    <tr><td><strong>Puesto:</strong></td><td id="det-puesto"></td></tr>
                                    <tr><td><strong>Teléfono:</strong></td><td id="det-telefono"></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr><td><strong>Sede:</strong></td><td id="det-sede"></td></tr>
                                    <tr><td><strong>Área:</strong></td><td id="det-area"></td></tr>
                                    <tr><td><strong>Jefe Directo:</strong></td><td id="det-jefe"></td></tr>
                                </table>
                            </div>
                        </div>
                        <hr>
                        <h6>Justificación</h6>
                        <p id="det-justificacion" class="text-muted"></p>
                    </div>

                        <!-- Tab Equipos -->
                        <div class="tab-pane fade" id="tab-equipos">
                            <h6>Equipos Solicitados</h6>
                            <div id="det-equipos-list"></div>
                            <hr>
                            <h6>Características Especiales</h6>
                            <p id="det-caracteristicas" class="text-muted"></p>
                        </div>

                    <!-- Tab Accesos -->
                    <div class="tab-pane fade" id="tab-accesos">
                        <h6>Accesos y Sistemas Solicitados</h6>
                        <div id="det-accesos-list"></div>
                        <hr>
                        <h6>Otros Accesos</h6>
                        <p id="det-otros-accesos" class="text-muted"></p>
                    </div>

                    <!-- Tab Aprobaciones -->
                    <div class="tab-pane fade" id="tab-aprobaciones">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Gerente de Finanzas</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Aprobado por:</strong></td><td id="det-gerente-finanzas"></td></tr>
                                    <tr><td><strong>Fecha:</strong></td><td id="det-fecha-finanzas"></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Gerente General</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Aprobado por:</strong></td><td id="det-gerente-general"></td></tr>
                                    <tr><td><strong>Fecha:</strong></td><td id="det-fecha-general"></td></tr>
                                </table>
                            </div>
                        </div>
                        <hr>
                        <h6>Comentarios</h6>
                        <p id="det-comentarios" class="text-muted"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>