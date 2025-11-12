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
        c.nombre + ' ' + c.apellido as colaborador_nombre,
        c.dni as colaborador_dni,
        c.puesto as colaborador_puesto,
        c.telefono as colaborador_telefono,
        j.nombre + ' ' + j.apellido as jefe_nombre,
        g.nombre + ' ' + g.apellido as gerente_nombre,
        sede.nombre as sede_nombre,
        area.nombre as area_nombre
    FROM solicitudes_equipos s
    LEFT JOIN users c ON s.colaborador_id = c.id
    LEFT JOIN users j ON s.solicitante_id = j.id
    LEFT JOIN users g ON s.aprobado_por = g.id
    LEFT JOIN sedes sede ON c.sede_id = sede.id
    LEFT JOIN areas area ON c.area_id = area.id
    WHERE s.estado = 'aprobada'
    ORDER BY s.fecha_aprobacion DESC
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
        let modalAsignar;

        document.addEventListener('DOMContentLoaded', function() {
            modalAsignar = new bootstrap.Modal(document.getElementById('modalAsignarEquipos'));
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

        // Validar que al menos un equipo esté seleccionado
        document.getElementById('formAsignarEquipos').addEventListener('submit', function(e) {
            const equiposSeleccionados = document.querySelectorAll('input[name="equipos[]"]:checked');
            
            if (equiposSeleccionados.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin equipos seleccionados',
                    text: 'Debes seleccionar al menos un equipo para asignar'
                });
                return false;
            }

            // Confirmar asignación
            e.preventDefault();
            Swal.fire({
                title: '¿Confirmar Asignación?',
                html: `Se asignarán <strong>${equiposSeleccionados.length}</strong> equipo(s) y se generará la carta responsiva.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Asignar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    </script>
</body>
</html>