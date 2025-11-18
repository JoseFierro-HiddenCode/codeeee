<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo gerentes pueden acceder
protegerPagina(['gerente']);

// Obtener solicitudes pendientes
$sqlPendientes = "
    SELECT 
        s.*,
        c.nombre + ' ' + c.apellido as colaborador_nombre,
        c.dni as colaborador_dni,
        c.puesto as colaborador_puesto,
        c.telefono as colaborador_telefono,
        j.nombre + ' ' + j.apellido as jefe_nombre,
        sede.nombre as sede_nombre
    FROM solicitudes_equipos s
    LEFT JOIN users c ON s.colaborador_id = c.id
    LEFT JOIN users j ON s.solicitante_id = j.id
    LEFT JOIN sedes sede ON c.sede_id = sede.id
    WHERE s.estado = 'pendiente_gerente'
    ORDER BY s.created_at DESC
";
$solicitudesPendientes = obtenerTodos($sqlPendientes);

// Obtener historial de solicitudes (aprobadas/rechazadas)
$sqlHistorial = "
    SELECT 
        s.*,
        c.nombre + ' ' + c.apellido as colaborador_nombre,
        j.nombre + ' ' + j.apellido as jefe_nombre,
        g.nombre + ' ' + g.apellido as gerente_nombre
    FROM solicitudes_equipos s
    LEFT JOIN users c ON s.colaborador_id = c.id
    LEFT JOIN users j ON s.solicitante_id = j.id
    LEFT JOIN users g ON s.aprobado_por = g.id
    WHERE s.estado IN ('aprobada', 'rechazada')
    ORDER BY s.fecha_aprobacion DESC
";
$historial = obtenerTodos($sqlHistorial);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobar Solicitudes - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard-gerente.php">
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
                <a href="../dashboard-gerente.php" class="sidebar-nav-link">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-section-title">Equipos</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="aprobar-solicitudes.php" class="sidebar-nav-link active">
                    <i class="bi bi-check2-square"></i>
                    <span>Aprobar Solicitudes</span>
                    <?php if (count($solicitudesPendientes) > 0): ?>
                        <span class="ms-auto badge bg-danger"><?php echo count($solicitudesPendientes); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Aprobar Solicitudes de Equipos</h1>
            <p class="page-subtitle">Revisa y aprueba/rechaza solicitudes de equipos</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> 
                <?php 
                    if ($_GET['success'] == 'aprobada') echo 'Solicitud aprobada exitosamente';
                    if ($_GET['success'] == 'rechazada') echo 'Solicitud rechazada';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Solicitudes Pendientes -->
        <div class="card mb-4">
            <div class="card-header bg-warning">
                <h5><i class="bi bi-clock-history"></i> Solicitudes Pendientes (<?php echo count($solicitudesPendientes); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($solicitudesPendientes) > 0): ?>
                    <?php foreach ($solicitudesPendientes as $sol): ?>
                        <div class="card mb-3 border-warning">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Folio: <?php echo e($sol['folio']); ?></strong>
                                    <span class="ms-3">|</span>
                                    <span class="ms-3">Solicitado: <?php echo formatearFecha($sol['created_at']); ?></span>
                                </div>
                                <?php echo badgeEstadoSolicitud($sol['estado']); ?>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-person"></i> Información del Colaborador</h6>
                                        <p class="mb-1"><strong>Nombre:</strong> <?php echo e($sol['colaborador_nombre']); ?></p>
                                        <p class="mb-1"><strong>DNI:</strong> <?php echo e($sol['colaborador_dni'] ?? '-'); ?></p>
                                        <p class="mb-1"><strong>Puesto:</strong> <?php echo e($sol['colaborador_puesto'] ?? '-'); ?></p>
                                        <p class="mb-1"><strong>Teléfono:</strong> <?php echo e($sol['colaborador_telefono'] ?? '-'); ?></p>
                                        <p class="mb-1"><strong>Sede:</strong> <?php echo e($sol['sede_nombre'] ?? '-'); ?></p>
                                    </div>

                                    <div class="col-md-6">
                                        <h6><i class="bi bi-person-badge"></i> Solicitante</h6>
                                        <p class="mb-1"><strong>Jefe:</strong> <?php echo e($sol['jefe_nombre']); ?></p>
                                        <p class="mb-1"><strong>Tipo:</strong> <?php echo ucfirst(str_replace('_', ' ', $sol['tipo_solicitud'])); ?></p>
                                    </div>
                                </div>

                                <hr>

                                <div class="row">
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
                                            <?php echo nl2br(e($sol['caracteristicas_equipo'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <hr>

                                <div class="row">
                                    <div class="col-md-12">
                                        <h6><i class="bi bi-file-text"></i> Justificación</h6>
                                        <p><?php echo nl2br(e($sol['justificacion'])); ?></p>
                                    </div>
                                </div>

                                <hr>

                                <!-- Accesos solicitados (resumido) -->
                                <?php 
                                $tieneAccesos = $sol['sp_gestion_mostrador'] || $sol['sp_gestion_servicio'] || 
                                                $sol['acpcore_asesor_contratos'] || $sol['solicita_gds'] || $sol['solicita_contasis'];
                                ?>
                                <?php if ($tieneAccesos): ?>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h6><i class="bi bi-key"></i> Accesos Solicitados</h6>
                                            
                                            <?php if ($sol['sp_gestion_mostrador'] || $sol['sp_gestion_servicio']): ?>
                                                <p class="mb-1"><strong>SharePoint:</strong> 
                                                <?php 
                                                $accesos_sp = [];
                                                if ($sol['sp_gestion_mostrador']) $accesos_sp[] = 'Gestión por mostrador';
                                                if ($sol['sp_gestion_servicio']) $accesos_sp[] = 'Gestión por servicio';
                                                if ($sol['sp_admin_finanzas']) $accesos_sp[] = 'Admin. y Finanzas';
                                                if ($sol['sp_contabilidad']) $accesos_sp[] = 'Contabilidad';
                                                echo implode(', ', $accesos_sp);
                                                ?>
                                                <?php if ($sol['sp_nivel']): ?>
                                                    (<?php echo ucfirst($sol['sp_nivel']); ?>)
                                                <?php endif; ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if ($sol['acpcore_asesor_contratos'] || $sol['acpcore_otros']): ?>
                                                <p class="mb-1"><strong>ACPCORE:</strong> 
                                                <?php 
                                                $accesos_acp = [];
                                                if ($sol['acpcore_asesor_contratos']) $accesos_acp[] = 'Asesor de Contratos';
                                                if ($sol['acpcore_asesor_garantias']) $accesos_acp[] = 'Asesor de Garantías';
                                                if ($sol['acpcore_asesor_mostrador']) $accesos_acp[] = 'Asesor de Mostrador';
                                                echo implode(', ', $accesos_acp);
                                                ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if ($sol['solicita_gds']): ?>
                                                <p class="mb-1"><strong>GDS:</strong> Sí
                                                <?php if ($sol['gds_rol']): ?>
                                                    - Rol: <?php echo e($sol['gds_rol']); ?>
                                                <?php endif; ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if ($sol['solicita_contasis']): ?>
                                                <p class="mb-1"><strong>CONTASIS:</strong> Sí</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <hr>
                                <?php endif; ?>

                                <!-- Botones de Acción -->
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-sm btn-info" onclick="verDetalle(<?php echo $sol['id']; ?>)">
    <i class="bi bi-eye"></i> Ver Detalle
</button>
                                    <button class="btn btn-danger" onclick="rechazarSolicitud(<?php echo $sol['id']; ?>, '<?php echo e($sol['folio']); ?>')">
                                        <i class="bi bi-x-circle"></i> Rechazar
                                    </button>
                                    <button class="btn btn-success" onclick="aprobarSolicitud(<?php echo $sol['id']; ?>, '<?php echo e($sol['folio']); ?>')">
                                        <i class="bi bi-check-circle"></i> Aprobar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No hay solicitudes pendientes de aprobación.
                    </div>
                <?php endif; ?>
            </div>
        </div>

       <!-- Historial de Solicitudes -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history"></i> Historial de Solicitudes</h5>
    </div>
    <div class="card-body" style="padding: 20px; overflow-x: auto;">
        <?php if (count($historial) > 0): ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 140px;">Folio</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Colaborador</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Solicitante</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 150px;">Estado</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Aprobado/Rechazado Por</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 140px;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                        <tr style="transition: background-color 0.2s ease;"
                            onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                            onmouseout="this.style.backgroundColor='transparent'">
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <strong style="color: var(--jira-blue); font-weight: 700;"><?php echo e($h['folio']); ?></strong>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-primary);">
                                <?php echo e($h['colaborador_nombre']); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo e($h['jefe_nombre']); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <?php echo badgeEstadoSolicitud($h['estado']); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo e($h['gerente_nombre'] ?? '-'); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <small style="color: var(--text-muted);"><?php echo formatearFecha($h['fecha_aprobacion']); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No hay historial de solicitudes.
            </div>
        <?php endif; ?>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/app.js"></script>
    <script>
        function aprobarSolicitud(id, folio) {
            Swal.fire({
                title: '¿Aprobar Solicitud?',
                text: `Se aprobará la solicitud ${folio}`,
                icon: 'question',
                input: 'textarea',
                inputLabel: 'Comentario (opcional)',
                inputPlaceholder: 'Agregar comentario de aprobación...',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Aprobar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../../actions/inventario/aprobar-solicitud.php';
                    
                    const inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'id';
                    inputId.value = id;
                    form.appendChild(inputId);
                    
                    if (result.value) {
                        const inputComentario = document.createElement('input');
                        inputComentario.type = 'hidden';
                        inputComentario.name = 'comentario';
                        inputComentario.value = result.value;
                        form.appendChild(inputComentario);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function rechazarSolicitud(id, folio) {
            Swal.fire({
                title: '¿Rechazar Solicitud?',
                text: `Se rechazará la solicitud ${folio}`,
                icon: 'warning',
                input: 'textarea',
                inputLabel: 'Motivo del rechazo (obligatorio)',
                inputPlaceholder: 'Explica el motivo del rechazo...',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Debes especificar un motivo'
                    }
                },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Rechazar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../../actions/inventario/rechazar-solicitud.php';
                    
                    const inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'id';
                    inputId.value = id;
                    form.appendChild(inputId);
                    
                    const inputMotivo = document.createElement('input');
                    inputMotivo.type = 'hidden';
                    inputMotivo.name = 'motivo';
                    inputMotivo.value = result.value;
                    form.appendChild(inputMotivo);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function verDetalleCompleto(id) {
            // TODO: Abrir modal con TODOS los detalles de la solicitud
            alert('Ver detalle completo de solicitud ID: ' + id);
        }
    </script>
    <script>
let modalDetalle;

document.addEventListener('DOMContentLoaded', function() {
    modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalleSolicitud'));
});

function verDetalle(solicitudId) {
    // Mostrar modal con loading
    document.getElementById('contenidoDetalleSolicitud').innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2">Cargando detalles de la solicitud...</p>
        </div>
    `;
    
    modalDetalle.show();
    
    // Cargar datos vía AJAX
    fetch(`../../actions/inventario/obtener-detalle-solicitud.php?id=${solicitudId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarDetalleSolicitud(data.solicitud);
            } else {
                document.getElementById('contenidoDetalleSolicitud').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> ${data.error || 'Error al cargar la solicitud'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('contenidoDetalleSolicitud').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Error al cargar los detalles
                </div>
            `;
        });
}

function mostrarDetalleSolicitud(sol) {
    const html = `
        <div class="p-3">
            <!-- Header -->
            <div class="text-center mb-4">
                <h4>FORMULARIO DE SOLICITUD DE EQUIPOS Y ACCESOS</h4>
                <p class="text-muted">Tecnologías de la Información y las Comunicaciones</p>
                <div class="badge bg-primary fs-6">Folio: ${sol.folio}</div>
            </div>

            <!-- Sección 1: Solicitado para -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <strong>1. SOLICITADO PARA:</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Colaborador:</strong> ${sol.colaborador_nombre}</p>
                            <p><strong>DNI:</strong> ${sol.colaborador_dni || '-'}</p>
                            <p><strong>Cargo:</strong> ${sol.colaborador_puesto || '-'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Teléfono:</strong> ${sol.colaborador_telefono || '-'}</p>
                            <p><strong>Sede:</strong> ${sol.sede_nombre || '-'}</p>
                            <p><strong>Tipo Solicitud:</strong> <span class="badge bg-info">${formatTipoSolicitud(sol.tipo_solicitud)}</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 2: Equipos Solicitados -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <strong>2. EQUIPOS SOLICITADOS</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            ${sol.solicita_laptop ? '<span class="badge bg-primary me-2">💻 Laptop</span>' : ''}
                            ${sol.solicita_celular ? '<span class="badge bg-primary me-2">📱 Celular</span>' : ''}
                            ${sol.solicita_pc ? '<span class="badge bg-primary me-2">🖥️ PC</span>' : ''}
                            ${sol.otros_equipos ? '<span class="badge bg-secondary me-2">📦 ' + sol.otros_equipos + '</span>' : ''}
                            ${!sol.solicita_laptop && !sol.solicita_celular && !sol.solicita_pc && !sol.otros_equipos ? '<span class="text-muted">Sin equipos solicitados</span>' : ''}
                        </div>
                    </div>
                    ${sol.caracteristicas_equipo ? `
                        <hr>
                        <p><strong>Características Especiales:</strong></p>
                        <p>${sol.caracteristicas_equipo}</p>
                    ` : ''}
                </div>
            </div>

            <!-- Sección 3: SharePoint -->
            ${sol.sp_gestion_mostrador || sol.sp_gestion_servicio || sol.sp_unidades_nuevas || sol.sp_contratos_inhouse || sol.sp_admin_finanzas || sol.sp_contabilidad || sol.sp_logistica || sol.sp_sei || sol.sp_rrhh || sol.sp_ssoma || sol.sp_tic || sol.sp_calidad ? `
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <strong>3. ACCESOS SHAREPOINT</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            ${sol.sp_gestion_mostrador ? '<span class="badge bg-info me-2">✓ Gestión por Mostrador</span>' : ''}
                            ${sol.sp_gestion_servicio ? '<span class="badge bg-info me-2">✓ Gestión de Servicio</span>' : ''}
                            ${sol.sp_unidades_nuevas ? '<span class="badge bg-info me-2">✓ Unidades Nuevas</span>' : ''}
                            ${sol.sp_contratos_inhouse ? '<span class="badge bg-info me-2">✓ Contratos Inhouse</span>' : ''}
                            ${sol.sp_admin_finanzas ? '<span class="badge bg-info me-2">✓ Admin y Finanzas</span>' : ''}
                            ${sol.sp_contabilidad ? '<span class="badge bg-info me-2">✓ Contabilidad</span>' : ''}
                            ${sol.sp_logistica ? '<span class="badge bg-info me-2">✓ Logística</span>' : ''}
                            ${sol.sp_sei ? '<span class="badge bg-info me-2">✓ SEI</span>' : ''}
                            ${sol.sp_rrhh ? '<span class="badge bg-info me-2">✓ RRHH</span>' : ''}
                            ${sol.sp_ssoma ? '<span class="badge bg-info me-2">✓ SSOMA</span>' : ''}
                            ${sol.sp_tic ? '<span class="badge bg-info me-2">✓ TIC</span>' : ''}
                            ${sol.sp_calidad ? '<span class="badge bg-info me-2">✓ Calidad</span>' : ''}
                        </div>
                    </div>
                    ${sol.sp_nivel ? `<p class="mt-2 mb-0"><strong>Nivel de Acceso:</strong> ${sol.sp_nivel}</p>` : ''}
                </div>
            </div>
            ` : ''}

            <!-- Sección 4: ACPCORE -->
            ${sol.acpcore_asesor_contratos || sol.acpcore_asesor_garantias || sol.acpcore_asesor_mostrador || sol.acpcore_asesor_repuesto || sol.acpcore_otros ? `
            <div class="card mb-3">
                <div class="card-header bg-warning">
                    <strong>4. ACCESOS ACPCORE</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            ${sol.acpcore_asesor_contratos ? '<span class="badge bg-warning text-dark me-2">✓ Asesor Contratos</span>' : ''}
                            ${sol.acpcore_asesor_garantias ? '<span class="badge bg-warning text-dark me-2">✓ Asesor Garantías</span>' : ''}
                            ${sol.acpcore_asesor_mostrador ? '<span class="badge bg-warning text-dark me-2">✓ Asesor Mostrador</span>' : ''}
                            ${sol.acpcore_asesor_repuesto ? '<span class="badge bg-warning text-dark me-2">✓ Asesor Repuesto</span>' : ''}
                            ${sol.acpcore_otros ? '<span class="badge bg-secondary me-2">📋 ' + sol.acpcore_otros + '</span>' : ''}
                        </div>
                    </div>
                </div>
            </div>
            ` : ''}

            <!-- Sección 5: GDS -->
            ${sol.solicita_gds ? `
            <div class="card mb-3">
                <div class="card-header bg-dark text-white">
                    <strong>5. GDS (SISTEMAS DE DISTRIBUCIÓN GLOBAL)</strong>
                </div>
                <div class="card-body">
                    ${sol.gds_rol ? `<p><strong>Rol:</strong> ${sol.gds_rol}</p>` : ''}
                    ${sol.gds_usuario ? `<p><strong>Usuario:</strong> ${sol.gds_usuario}</p>` : ''}
                    ${sol.gds_justificacion ? `<p><strong>Justificación:</strong> ${sol.gds_justificacion}</p>` : ''}
                </div>
            </div>
            ` : ''}

            <!-- Sección 6: CONTASIS -->
            ${sol.solicita_contasis ? `
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <strong>6. CONTASIS</strong>
                </div>
                <div class="card-body">
                    <p class="mb-0">✓ Acceso solicitado</p>
                </div>
            </div>
            ` : ''}

            <!-- Sección 7: Servicios Remotos -->
            ${sol.justif_servicios_remotos ? `
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <strong>7. SERVICIOS REMOTOS</strong>
                </div>
                <div class="card-body">
                    <p class="mb-0">${sol.justif_servicios_remotos}</p>
                </div>
            </div>
            ` : ''}

            <!-- Sección 8: Otros Accesos -->
            ${sol.otros_accesos ? `
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <strong>8. OTROS ACCESOS</strong>
                </div>
                <div class="card-body">
                    <p class="mb-0">${sol.otros_accesos}</p>
                </div>
            </div>
            ` : ''}

            <!-- Sección 9: Justificación -->
            ${sol.justificacion ? `
            <div class="card mb-3">
                <div class="card-header bg-danger text-white">
                    <strong>9. JUSTIFICACIÓN</strong>
                </div>
                <div class="card-body">
                    <p class="mb-0">${sol.justificacion}</p>
                </div>
            </div>
            ` : ''}

            <!-- Info adicional -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <p><strong>Solicitado por (Jefe):</strong> ${sol.jefe_nombre}</p>
                    <p><strong>Fecha de Solicitud:</strong> ${formatearFecha(sol.created_at)}</p>
                </div>
                <div class="col-md-6 text-end">
                    <p><strong>Estado:</strong> ${badgeEstadoSolicitud(sol.estado)}</p>
                    ${sol.comentario_gerente ? `<p><strong>Comentarios:</strong> ${sol.comentario_gerente}</p>` : ''}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('contenidoDetalleSolicitud').innerHTML = html;
}

function formatTipoSolicitud(tipo) {
    const tipos = {
        'crear_asignar': 'Crear / Asignar',
        'solicitar_accesos': 'Solicitar Accesos',
        'baja_reemplazo': 'Baja / Reemplazo'
    };
    return tipos[tipo] || tipo;
}

function badgeEstadoSolicitud(estado) {
    const badges = {
        'pendiente': '<span class="badge bg-warning">⏳ Pendiente</span>',
        'aprobada': '<span class="badge bg-success">✅ Aprobada</span>',
        'rechazada': '<span class="badge bg-danger">❌ Rechazada</span>',
        'completada': '<span class="badge bg-primary">✓ Completada</span>'
    };
    return badges[estado] || estado;
}

function formatearFecha(fecha) {
    if (!fecha) return '-';
    const date = new Date(fecha);
    const opciones = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return date.toLocaleDateString('es-ES', opciones);
}
</script>

    <!-- Modal Ver Detalle Completo -->
<div class="modal fade" id="modalDetalleSolicitud" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-text"></i> Detalle Completo de Solicitud
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetalleSolicitud">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando detalles...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
</body>
</html>