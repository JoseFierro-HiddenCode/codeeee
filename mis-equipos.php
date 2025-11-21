<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo usuarios pueden acceder
protegerPagina(); 

$userId = $_SESSION['user_id'];

// Obtener equipos asignados al usuario
$equiposAsignados = obtenerEquiposAsignadosUsuario($userId);

// Obtener carta responsiva (si existe)
$carta = obtenerCartaResponsivaUsuario($userId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Equipos - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        <div class="sidebar-section-title">Mis Equipos</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="mis-equipos.php" class="sidebar-nav-link active">
                    <i class="bi bi-laptop"></i>
                    <span>Equipos Asignados</span>
                    <?php if (count($equiposAsignados) > 0): ?>
                        <span class="ms-auto badge bg-primary"><?php echo count($equiposAsignados); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <?php if (esJefe()): ?>
        <div class="sidebar-section-title">Gestión de Equipo</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="solicitar-equipos.php" class="sidebar-nav-link">
                    <i class="bi bi-plus-square"></i>
                    <span>Solicitar Equipos</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="mis-solicitudes.php" class="sidebar-nav-link">
                    <i class="bi bi-list-check"></i>
                    <span>Mis Solicitudes</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Mis Equipos Asignados</h1>
            <p class="page-subtitle">Equipos tecnológicos bajo tu responsabilidad</p>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'firmado'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Compromiso aceptado exitosamente. Gracias por tu responsabilidad.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Carta Responsiva -->
        <?php if ($carta): ?>
            <div class="card mb-4 <?php echo $carta['firmado'] ? 'border-success' : 'border-warning'; ?>">
                <div class="card-header <?php echo $carta['firmado'] ? 'bg-success text-white' : 'bg-warning'; ?>">
                    <h5>
                        <i class="bi bi-file-earmark-text"></i> Carta Responsiva
                        <?php if ($carta['firmado']): ?>
                            <span class="badge bg-light text-success ms-2">✓ Firmada</span>
                        <?php else: ?>
                            <span class="badge bg-danger ms-2">⚠️ Pendiente de Firma</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Folio:</strong> <?php echo e($carta['folio_carta']); ?></p>
                            <p><strong>Fecha de Asignación:</strong> <?php echo formatearFecha($carta['fecha_asignacion']); ?></p>
                            <?php if ($carta['firmado']): ?>
                                <p><strong>Fecha de Aceptación:</strong> <?php echo formatearFecha($carta['fecha_firma']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if ($carta['ruta_docx']): ?>
                                <a href="../../<?php echo e($carta['ruta_docx']); ?>" class="btn btn-primary mb-2" download>
                                    <i class="bi bi-file-earmark-word"></i> Descargar DOCX
                                </a>
                            <?php endif; ?>
                            <?php if ($carta['ruta_pdf']): ?>
                                <a href="../../<?php echo e($carta['ruta_pdf']); ?>" class="btn btn-danger mb-2" download>
                                    <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!$carta['firmado']): ?>
                                <br>
                                <button class="btn btn-success btn-lg mt-2" onclick="firmarCarta('<?php echo e($carta['folio_carta']); ?>')">
                                    <i class="bi bi-pen"></i> Aceptar Compromiso
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$carta['firmado']): ?>
                        <hr>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            <strong>Importante:</strong> Debes aceptar el compromiso de uso responsable de los equipos asignados.
                            Al hacer clic en "Aceptar Compromiso", confirmas que:
                            <ul class="mt-2 mb-0">
                                <li>Usarás los equipos exclusivamente para fines laborales</li>
                                <li>Velarás por el buen uso y cuidado de los equipos</li>
                                <li>Reportarás inmediatamente cualquier pérdida o daño</li>
                                <li>Asumirás responsabilidad según la política de la empresa</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Equipos Asignados -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-laptop"></i> Equipos Asignados (<?php echo count($equiposAsignados); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($equiposAsignados) > 0): ?>
                    <div class="row">
                        <?php foreach ($equiposAsignados as $equipo): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <strong><?php echo ucfirst($equipo['tipo']); ?></strong>
                                        <?php echo badgeEstadoEquipo($equipo['estado']); ?>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <td><strong>Marca:</strong></td>
                                                <td><?php echo e($equipo['marca'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Modelo:</strong></td>
                                                <td><?php echo e($equipo['modelo'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>N° Serie:</strong></td>
                                                <td><code><?php echo e($equipo['numero_serie']); ?></code></td>
                                            </tr>
                                            <?php if ($equipo['procesador']): ?>
                                            <tr>
                                                <td><strong>Procesador:</strong></td>
                                                <td><?php echo e($equipo['procesador']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ($equipo['sistema_operativo']): ?>
                                            <tr>
                                                <td><strong>S.O.:</strong></td>
                                                <td><?php echo e($equipo['sistema_operativo']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ($equipo['color']): ?>
                                            <tr>
                                                <td><strong>Color:</strong></td>
                                                <td><?php echo e($equipo['color']); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td><strong>Asignado:</strong></td>
                                                <td><?php echo formatearFecha($equipo['fecha_asignacion']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Asignado por:</strong></td>
                                                <td><?php echo e($equipo['asignado_por_nombre']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No tienes equipos asignados actualmente.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/app.js"></script>
    <script>
        function firmarCarta(folioCarta) {
            Swal.fire({
                title: '¿Aceptar Compromiso?',
                html: `
                    Al aceptar este compromiso confirmas que:<br><br>
                    <ul style="text-align: left;">
                        <li>Usarás los equipos exclusivamente para fines laborales</li>
                        <li>Velarás por el buen uso y cuidado de los equipos</li>
                        <li>Reportarás inmediatamente cualquier pérdida o daño</li>
                        <li>Asumirás responsabilidad según la política de la empresa</li>
                    </ul>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Acepto el Compromiso',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar petición para firmar
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../../actions/inventario/firmar-carta.php';
                    
                    const inputFolio = document.createElement('input');
                    inputFolio.type = 'hidden';
                    inputFolio.name = 'folio_carta';
                    inputFolio.value = folioCarta;
                    form.appendChild(inputFolio);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        function firmarCarta(folioCarta) {
    Swal.fire({
        title: '¿Aceptar Compromiso?',
        html: `
            Al aceptar este compromiso confirmas que:<br><br>
            <ul style="text-align: left;">
                <li>Usarás los equipos exclusivamente para fines laborales</li>
                <li>Velarás por el buen uso y cuidado de los equipos</li>
                <li>Reportarás inmediatamente cualquier pérdida o daño</li>
                <li>Asumirás responsabilidad según la política de la empresa</li>
            </ul>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, Acepto el Compromiso',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Crear formulario y enviar
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../../actions/inventario/aceptar-compromiso.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'folio_carta';
            input.value = folioCarta;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });
}
    </script>
    
</body>
</html>