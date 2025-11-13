<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo Admin y TI pueden acceder
protegerPagina(['admin_tecnico', 'tecnico']);

// Obtener todos los equipos asignados
$sqlEquiposAsignados = "
SELECT 
    e.id,
    e.tipo,
    e.marca,
    e.modelo,
    e.numero_serie,
    e.procesador,
    e.sistema_operativo,
    e.color,
    e.estado,
    u.nombre + ' ' + u.apellido as usuario_asignado,
    u.dni as usuario_dni,
    u.puesto as usuario_puesto,
    CONVERT(VARCHAR, ae.fecha_asignacion, 103) as fecha_asignacion,
    ae.notas_asignacion,
    ae.folio_carta,
    s.folio as folio_solicitud,
    sede.nombre as sede_nombre,
    area.nombre as area_nombre
FROM equipos e
INNER JOIN asignaciones_equipos ae ON e.id = ae.equipo_id
INNER JOIN users u ON ae.usuario_id = u.id
LEFT JOIN solicitudes_equipos s ON ae.solicitud_id = s.id
LEFT JOIN sedes sede ON u.sede_id = sede.id
LEFT JOIN areas area ON u.area_id = area.id
WHERE ae.estado = 'asignado'
ORDER BY ae.fecha_asignacion DESC
";
$equiposAsignados = obtenerTodos($sqlEquiposAsignados);


// Contar por tipo
$estadisticas = [
    'laptop' => 0,
    'celular' => 0,
    'pc' => 0,
    'otros' => 0
];

foreach ($equiposAsignados as $eq) {
    $tipoNormalizado = strtolower(trim($eq['tipo']));
    
    if ($tipoNormalizado === 'laptop') {
        $estadisticas['laptop']++;
    } elseif ($tipoNormalizado === 'celular') {
        $estadisticas['celular']++;
    } elseif ($tipoNormalizado === 'pc') {
        $estadisticas['pc']++;
    } else {
        $estadisticas['otros']++;
    }
}

// Obtener sedes únicas para el filtro
$sedesUnicas = [];
foreach ($equiposAsignados as $eq) {
    if ($eq['sede_nombre'] && !in_array($eq['sede_nombre'], $sedesUnicas)) {
        $sedesUnicas[] = $eq['sede_nombre'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipos Asignados - Sistema de Tickets</title>
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
                <a href="asignar-equipos-fisicos.php" class="sidebar-nav-link">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Solicitudes Aprobadas</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="equipos-asignados.php" class="sidebar-nav-link active">
                    <i class="bi bi-diagram-3"></i>
                    <span>Equipos Asignados</span>
                    <?php if (count($equiposAsignados) > 0): ?>
                    <span class="ms-auto badge bg-primary"><?php echo count($equiposAsignados); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Equipos Asignados</h1>
                <p class="page-subtitle">Control de equipos en uso por colaboradores</p>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-laptop text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?php echo $estadisticas['laptop']; ?></h3>
                        <p class="text-muted mb-0">Laptops</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-phone text-success" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?php echo $estadisticas['celular']; ?></h3>
                        <p class="text-muted mb-0">Celulares</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-pc-display text-info" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?php echo $estadisticas['pc']; ?></h3>
                        <p class="text-muted mb-0">PCs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-box text-warning" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?php echo count($equiposAsignados); ?></h3>
                        <p class="text-muted mb-0">Total Asignados</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <?php if (count($equiposAsignados) > 0): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="filtroUsuario" placeholder="🔍 Buscar por nombre de usuario...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filtroTipo">
                            <option value="">Todos los tipos</option>
                            <option value="laptop">Laptop</option>
                            <option value="celular">Celular</option>
                            <option value="pc">PC</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filtroSede">
                            <option value="">Todas las sedes</option>
                            <?php foreach ($sedesUnicas as $sede): ?>
                                <option value="<?php echo e($sede); ?>"><?php echo e($sede); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-secondary w-100" onclick="limpiarFiltros()">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabla de Equipos Asignados -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-table"></i> Listado de Equipos Asignados (<?php echo count($equiposAsignados); ?>)</h5>
    </div>
    <div class="card-body" style="padding: 20px; overflow-x: auto;">
        <?php if (count($equiposAsignados) > 0): ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 80px;">Tipo</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Equipo</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 130px;">Serie</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Usuario</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 100px;">DNI</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Puesto</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center;">Sede</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 140px;">Fecha Asignación</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 130px;">Folio</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equiposAsignados as $eq): ?>
                        <tr style="transition: background-color 0.2s ease;"
                            onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                            onmouseout="this.style.backgroundColor='transparent'">
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <span style="font-size: 1.5rem;">
                                <?php
                                $iconos = [
                                    'laptop' => '💻',
                                    'celular' => '📱',
                                    'pc' => '🖥️'
                                ];
                                echo $iconos[strtolower(trim($eq['tipo']))] ?? '📦';
                                ?>
                                </span>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-primary);">
                                <strong><?php echo e($eq['marca'] . ' ' . $eq['modelo']); ?></strong>
                                <?php if ($eq['procesador']): ?>
                                <br><small style="color: var(--text-muted);"><?php echo e($eq['procesador']); ?></small>
                                <?php endif; ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <code style="background-color: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                    <?php echo e($eq['numero_serie']); ?>
                                </code>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-primary);">
                                <?php echo e($eq['usuario_asignado']); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo e($eq['usuario_dni']); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo e($eq['usuario_puesto'] ?? '-'); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle; color: var(--text-secondary);">
                                <?php echo e($eq['sede_nombre'] ?? '-'); ?>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <small style="color: var(--text-muted);"><?php echo formatearFecha($eq['fecha_asignacion']); ?></small>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <span class="badge" style="background-color: #0052CC; color: white;">
                                    <?php echo e($eq['folio_solicitud'] ?? '-'); ?>
                                </span>
                            </td>
                            
                            <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                                <button class="btn btn-sm btn-info" onclick="verDetalleEquipo(<?php echo $eq['id']; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No hay equipos asignados actualmente.
            </div>
        <?php endif; ?>
    </div>
</div>
       <!-- Modal Detalle Equipo -->
    <div class="modal fade" id="modalDetalleEquipo" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-laptop"></i> Detalle Completo del Equipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Tabs de Navegación -->
                    <ul class="nav nav-tabs mb-3" id="detalleEquipoTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-equipo" data-bs-toggle="tab" data-bs-target="#content-equipo" type="button">
                                <i class="bi bi-laptop"></i> Equipo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-usuario" data-bs-toggle="tab" data-bs-target="#content-usuario" type="button">
                                <i class="bi bi-person"></i> Usuario
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-asignacion" data-bs-toggle="tab" data-bs-target="#content-asignacion" type="button">
                                <i class="bi bi-clipboard-check"></i> Asignación
                            </button>
                        </li>
                    </ul>

                    <!-- Contenido de Tabs -->
                    <div class="tab-content" id="detalleEquipoTabsContent">
                        <!-- Tab: Equipo -->
                        <div class="tab-pane fade show active" id="content-equipo" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Tipo:</strong></td>
                                            <td id="det-eq-tipo">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Marca:</strong></td>
                                            <td id="det-eq-marca">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Modelo:</strong></td>
                                            <td id="det-eq-modelo">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Número de Serie:</strong></td>
                                            <td id="det-eq-serie">-</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Procesador:</strong></td>
                                            <td id="det-eq-procesador">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Sistema Operativo:</strong></td>
                                            <td id="det-eq-so">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Color:</strong></td>
                                            <td id="det-eq-color">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Estado:</strong></td>
                                            <td id="det-eq-estado">-</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Usuario -->
                        <div class="tab-pane fade" id="content-usuario" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Nombre Completo:</strong></td>
                                            <td id="det-user-nombre">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>DNI:</strong></td>
                                            <td id="det-user-dni">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Puesto:</strong></td>
                                            <td id="det-user-puesto">-</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Sede:</strong></td>
                                            <td id="det-user-sede">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Área:</strong></td>
                                            <td id="det-user-area">-</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Asignación -->
                        <div class="tab-pane fade" id="content-asignacion" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Folio Solicitud:</strong></td>
                                            <td id="det-asig-folio">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Folio Carta Responsiva:</strong></td>
                                            <td id="det-asig-carta">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Fecha Asignación:</strong></td>
                                            <td id="det-asig-fecha">-</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Estado:</strong></td>
                                            <td id="det-asig-estado">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Notas:</strong></td>
                                            <td id="det-asig-notas">-</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/app.js"></script>
    <script>
        // Datos de equipos
        const equipos = <?php echo json_encode($equiposAsignados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let modalDetalleEquipo;

        // Inicializar modal cuando DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            modalDetalleEquipo = new bootstrap.Modal(document.getElementById('modalDetalleEquipo'));
            console.log('✅ Modal inicializado');
        });

        // Función para mostrar detalle del equipo
        function verDetalleEquipo(equipoId) {
            const eq = equipos.find(e => e.id == equipoId);
            
            console.log('Buscando equipo ID:', equipoId);
            console.log('Equipo encontrado:', eq);
            
            if (!eq) {
                alert('No se encontró el equipo');
                return;
            }

            // Tab Equipo
            document.getElementById('det-eq-tipo').textContent = eq.tipo || '-';
            document.getElementById('det-eq-marca').textContent = eq.marca || '-';
            document.getElementById('det-eq-modelo').textContent = eq.modelo || '-';
            document.getElementById('det-eq-serie').textContent = eq.numero_serie || '-';
            document.getElementById('det-eq-procesador').textContent = eq.procesador || '-';
            document.getElementById('det-eq-so').textContent = eq.sistema_operativo || '-';
            document.getElementById('det-eq-color').textContent = eq.color || '-';
            document.getElementById('det-eq-estado').textContent = eq.estado || '-';

            // Tab Usuario
            document.getElementById('det-user-nombre').textContent = eq.usuario_asignado || '-';
            document.getElementById('det-user-dni').textContent = eq.usuario_dni || '-';
            document.getElementById('det-user-puesto').textContent = eq.usuario_puesto || '-';
            document.getElementById('det-user-sede').textContent = eq.sede_nombre || '-';
            document.getElementById('det-user-area').textContent = eq.area_nombre || '-';

            // Tab Asignación
            document.getElementById('det-asig-folio').textContent = eq.folio_solicitud || '-';
            document.getElementById('det-asig-carta').textContent = eq.folio_carta || '-';
            document.getElementById('det-asig-fecha').textContent = eq.fecha_asignacion ? new Date(eq.fecha_asignacion).toLocaleDateString('es-MX') : '-';
            document.getElementById('det-asig-estado').textContent = eq.estado || '-';
            document.getElementById('det-asig-notas').textContent = eq.notas_asignacion || 'Sin notas';

            // Mostrar modal
            if (modalDetalleEquipo) {
                modalDetalleEquipo.show();
            } else {
                console.error('❌ Modal no inicializado');
            }
        }

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

        console.log('✅ Modo oscuro inicializado. Tema actual:', currentTheme);

        // Filtros de tabla
        const filtroUsuario = document.getElementById('filtroUsuario');
        const filtroTipo = document.getElementById('filtroTipo');
        const filtroSede = document.getElementById('filtroSede');

        function filtrarTabla() {
            const usuario = filtroUsuario ? filtroUsuario.value.toLowerCase() : '';
            const tipo = filtroTipo ? filtroTipo.value.toLowerCase() : '';
            const sede = filtroSede ? filtroSede.value.toLowerCase() : '';
            const filas = document.querySelectorAll('tbody tr');

            filas.forEach(fila => {
                const textoUsuario = fila.cells[3].textContent.toLowerCase();
                const textoEquipo = fila.cells[1].textContent.toLowerCase();
                const textoSede = fila.cells[6].textContent.toLowerCase();

                const coincideUsuario = textoUsuario.includes(usuario);
                const coincideTipo = tipo === '' || textoEquipo.includes(tipo);
                const coincideSede = sede === '' || textoSede.includes(sede);

                fila.style.display = (coincideUsuario && coincideTipo && coincideSede) ? '' : 'none';
            });
        }

        if (filtroUsuario) filtroUsuario.addEventListener('keyup', filtrarTabla);
        if (filtroTipo) filtroTipo.addEventListener('change', filtrarTabla);
        if (filtroSede) filtroSede.addEventListener('change', filtrarTabla);

        function limpiarFiltros() {
            if (filtroUsuario) filtroUsuario.value = '';
            if (filtroTipo) filtroTipo.value = '';
            if (filtroSede) filtroSede.value = '';
            filtrarTabla();
        }
    </script>
</body>
</html>