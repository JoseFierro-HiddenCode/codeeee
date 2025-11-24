<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo Admin y TI pueden acceder
protegerPagina(['admin_tecnico', 'tecnico']);

// Obtener filtros
$filtroTipo = $_GET['tipo'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';
$filtroDisponibilidad = $_GET['disponibilidad'] ?? '';

// Construir query con filtros
$sql = "SELECT * FROM equipos WHERE 1=1";
$params = [];

if (!empty($filtroTipo)) {
    $sql .= " AND tipo = ?";
    $params[] = $filtroTipo;
}

if (!empty($filtroEstado)) {
    $sql .= " AND estado = ?";
    $params[] = $filtroEstado;
}

if ($filtroDisponibilidad !== '') {
    $sql .= " AND disponible = ?";
    $params[] = $filtroDisponibilidad;
}

$sql .= " ORDER BY tipo, marca, modelo";

$equipos = obtenerTodos($sql, $params);

// Obtener tipos únicos para filtro
$tiposUnicos = obtenerTodos("SELECT DISTINCT tipo FROM equipos ORDER BY tipo");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Equipos - Sistema de Tickets</title>
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
                <a href="gestionar-equipos.php" class="sidebar-nav-link active">
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
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Gestionar Equipos</h1>
                <p class="page-subtitle">Administración del inventario de equipos tecnológicos</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoEquipo">
                <i class="bi bi-plus-circle"></i> Agregar Equipo
            </button>
        </div>

        <!-- Mensajes de éxito/error -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> 
                <?php 
                    if ($_GET['success'] == 'creado') echo 'Equipo creado exitosamente';
                    if ($_GET['success'] == 'editado') echo 'Equipo actualizado exitosamente';
                    if ($_GET['success'] == 'eliminado') echo 'Equipo eliminado exitosamente';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <h6><i class="bi bi-funnel"></i> Filtros</h6>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <?php foreach ($tiposUnicos as $tipo): ?>
                                <option value="<?php echo e($tipo['tipo']); ?>" <?php echo $filtroTipo == $tipo['tipo'] ? 'selected' : ''; ?>>
                                    <?php echo e(ucfirst($tipo['tipo'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="nuevo" <?php echo $filtroEstado == 'nuevo' ? 'selected' : ''; ?>>Nuevo</option>
                            <option value="bueno" <?php echo $filtroEstado == 'bueno' ? 'selected' : ''; ?>>Bueno</option>
                            <option value="regular" <?php echo $filtroEstado == 'regular' ? 'selected' : ''; ?>>Regular</option>
                            <option value="malo" <?php echo $filtroEstado == 'malo' ? 'selected' : ''; ?>>Malo</option>
                            <option value="para_reparacion" <?php echo $filtroEstado == 'para_reparacion' ? 'selected' : ''; ?>>Para Reparación</option>
                            <option value="dado_de_baja" <?php echo $filtroEstado == 'dado_de_baja' ? 'selected' : ''; ?>>Dado de Baja</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="disponibilidad" class="form-label">Disponibilidad</label>
                        <select class="form-select" id="disponibilidad" name="disponibilidad">
                            <option value="">Todos</option>
                            <option value="1" <?php echo $filtroDisponibilidad === '1' ? 'selected' : ''; ?>>Disponible</option>
                            <option value="0" <?php echo $filtroDisponibilidad === '0' ? 'selected' : ''; ?>>No Disponible</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <a href="gestionar-equipos.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

      <!-- Tabla de Equipos -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-table"></i> Listado de Equipos (<?php echo count($equipos); ?>)</h5>
    </div>
    <div class="card-body" style="padding: 20px; overflow-x: auto;">
        <?php if (count($equipos) > 0): ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 100px;">TIPO</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 120px;">MARCA</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">MODELO</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 130px;">Nº SERIE</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">PROCESADOR</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 130px;">S.O.</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 90px;">COLOR</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 110px;">ESTADO</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">DISPONIBLE</th>
                        <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipos as $equipo): ?>
                    <tr style="transition: background-color 0.2s ease;"
                        onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                        onmouseout="this.style.backgroundColor='transparent'">
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                            <strong style="color: var(--text-primary); font-weight: 600; text-transform: capitalize;">
                                <?php echo e(ucfirst($equipo['tipo'])); ?>
                            </strong>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                            <?php echo e($equipo['marca']); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                            <?php echo e($equipo['modelo']); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                            <code style="background-color: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px; font-size: 12px; color: var(--text-primary);">
                                <?php echo e($equipo['numero_serie']); ?>
                            </code>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                            <?php echo e($equipo['procesador'] ?: '-'); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                            <?php echo e($equipo['sistema_operativo'] ?: '-'); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                            <?php echo e($equipo['color'] ?: '-'); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                            <?php echo badgeEstadoEquipo($equipo['estado']); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                            <?php echo badgeDisponibilidad($equipo['disponible']); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                            <button class="btn btn-sm btn-primary" 
                                    onclick="editarEquipo(<?php echo $equipo['id']; ?>)"
                                    style="margin-right: 4px;">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="eliminarEquipo(<?php echo $equipo['id']; ?>, '<?php echo e($equipo['tipo'] . ' ' . $equipo['marca']); ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                <i class="bi bi-info-circle" style="font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.3;"></i>
                <p style="margin: 0;">No hay equipos registrados con los filtros seleccionados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

    <!-- Modal Nuevo Equipo -->
    <div class="modal fade" id="modalNuevoEquipo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Agregar Nuevo Equipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../actions/inventario/crear-equipo.php" method="POST" id="formNuevoEquipo">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
    <label for="tipo" class="form-label">Tipo de Equipo <span class="text-danger">*</span></label>
    <select class="form-select" id="tipo" name="tipo" required>
        <option value="">-- Seleccionar tipo --</option>
        
        <optgroup label="📦 EQUIPOS PRINCIPALES">
            <option value="Laptop">💻 Laptop</option>
            <option value="PC de Escritorio">🖥️ PC de Escritorio</option>
            <option value="Tablet">📲 Tablet</option>
            <option value="Celular">📱 Celular</option>
            <option value="Monitor">🖥️ Monitor</option>
        </optgroup>
        
        <optgroup label="⌨️ PERIFÉRICOS">
            <option value="Teclado">⌨️ Teclado</option>
            <option value="Mouse">🖱️ Mouse</option>
            <option value="Audífonos">🎧 Audífonos</option>
            <option value="Webcam">📷 Webcam</option>
            <option value="Bocinas">🔊 Bocinas</option>
            <option value="Micrófono">🎤 Micrófono</option>
        </optgroup>
        
        <optgroup label="🖨️ EQUIPOS DE OFICINA">
            <option value="Impresora">🖨️ Impresora</option>
            <option value="Escáner">📄 Escáner</option>
            <option value="Proyector">📽️ Proyector</option>
            <option value="Pizarra Digital">📊 Pizarra Digital</option>
        </optgroup>
        
        <optgroup label="🔌 CABLES Y CONECTORES">
            <option value="Cable HDMI">🔌 Cable HDMI</option>
            <option value="Cable VGA">🔌 Cable VGA</option>
            <option value="Cable DisplayPort">🔌 Cable DisplayPort</option>
            <option value="Cable USB">🔌 Cable USB</option>
            <option value="Cable USB-C">🔌 Cable USB-C</option>
            <option value="Cable de Red">🔌 Cable de Red (Ethernet)</option>
            <option value="Cable de Poder">🔌 Cable de Poder</option>
            <option value="Cable de Audio">🔌 Cable de Audio</option>
        </optgroup>
        
        <optgroup label="🔋 ACCESORIOS">
            <option value="Cargador de Laptop">🔌 Cargador de Laptop</option>
            <option value="Cargador de Celular">🔌 Cargador de Celular</option>
            <option value="Adaptador HDMI">🔄 Adaptador HDMI</option>
            <option value="Adaptador VGA">🔄 Adaptador VGA</option>
            <option value="Adaptador USB-C">🔄 Adaptador USB-C</option>
            <option value="Hub USB">🔄 Hub USB</option>
            <option value="Docking Station">🔄 Docking Station</option>
            <option value="Extensión Eléctrica">⚡ Extensión Eléctrica</option>
            <option value="Regulador">⚡ Regulador de Voltaje</option>
        </optgroup>
        
        <optgroup label="📡 NETWORKING">
            <option value="Router">📡 Router</option>
            <option value="Switch">📡 Switch</option>
            <option value="Access Point">📡 Access Point</option>
            <option value="Modem">📡 Modem</option>
            <option value="Patch Panel">📡 Patch Panel</option>
        </optgroup>
        
        <optgroup label="💾 ALMACENAMIENTO">
            <option value="Disco Duro Externo">💾 Disco Duro Externo</option>
            <option value="SSD Externo">💾 SSD Externo</option>
            <option value="USB Flash Drive">💾 USB Flash Drive</option>
            <option value="Tarjeta SD">💾 Tarjeta SD</option>
            <option value="NAS">💾 NAS (Network Storage)</option>
        </optgroup>
        
        <optgroup label="🔒 SEGURIDAD Y ENERGÍA">
            <option value="UPS">🔋 UPS / No-Break</option>
            <option value="PDU">⚡ PDU (Regleta Rack)</option>
            <option value="Candado Kensington">🔒 Candado Kensington</option>
        </optgroup>
        
        <optgroup label="🛠️ HERRAMIENTAS">
            <option value="Multímetro">🔧 Multímetro</option>
            <option value="Probador de Cables">🔧 Probador de Cables</option>
            <option value="Destornilladores">🔧 Kit Destornilladores</option>
            <option value="Pinzas Ponchadora">🔧 Pinzas Ponchadora</option>
        </optgroup>
        
        <optgroup label="📦 OTROS">
            <option value="Rack">🗄️ Rack</option>
            <option value="Organizador de Cables">🗂️ Organizador de Cables</option>
            <option value="Base para Laptop">📐 Base para Laptop</option>
            <option value="Mousepad">🖱️ Mousepad</option>
            <option value="Otro">📦 Otro</option>
        </optgroup>
    </select>
    <small class="text-muted">Selecciona el tipo de equipo que deseas registrar</small>
</div>

                            <div class="col-md-6 mb-3">
                                <label for="marca" class="form-label">Marca</label>
                                <input type="text" class="form-control" id="marca" name="marca" 
                                       placeholder="Ej: Dell, HP, Lenovo">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="modelo" class="form-label">Modelo</label>
                                <input type="text" class="form-control" id="modelo" name="modelo" 
                                       placeholder="Ej: Latitude 5420">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="numero_serie" class="form-label">Número de Serie <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="numero_serie" name="numero_serie" required 
                                       placeholder="Ej: ABC123456">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="procesador" class="form-label">Procesador</label>
                                <input type="text" class="form-control" id="procesador" name="procesador" 
                                       placeholder="Ej: Intel Core i7">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="sistema_operativo" class="form-label">Sistema Operativo</label>
                                <input type="text" class="form-control" id="sistema_operativo" name="sistema_operativo" 
                                       placeholder="Ej: Windows 11">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="color" class="form-label">Color</label>
                                <input type="text" class="form-control" id="color" name="color" 
                                       placeholder="Ej: Negro">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                                <select class="form-select" id="estado" name="estado" required>
                                    <option value="nuevo">Nuevo</option>
                                    <option value="bueno" selected>Bueno</option>
                                    <option value="regular">Regular</option>
                                    <option value="malo">Malo</option>
                                    <option value="para_reparacion">Para Reparación</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label for="notas" class="form-label">Notas/Observaciones</label>
                                <textarea class="form-control" id="notas" name="notas" rows="3" 
                                          placeholder="Información adicional del equipo"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Equipo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Equipo -->
    <div class="modal fade" id="modalEditarEquipo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Equipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../actions/inventario/editar-equipo.php" method="POST" id="formEditarEquipo">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_tipo" name="tipo" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="edit_marca" class="form-label">Marca</label>
                                <input type="text" class="form-control" id="edit_marca" name="marca">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="edit_modelo" class="form-label">Modelo</label>
                                <input type="text" class="form-control" id="edit_modelo" name="modelo">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="edit_numero_serie" class="form-label">Número de Serie <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_numero_serie" name="numero_serie" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="edit_procesador" class="form-label">Procesador</label>
                                <input type="text" class="form-control" id="edit_procesador" name="procesador">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="edit_sistema_operativo" class="form-label">Sistema Operativo</label>
                                <input type="text" class="form-control" id="edit_sistema_operativo" name="sistema_operativo">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="edit_color" class="form-label">Color</label>
                                <input type="text" class="form-control" id="edit_color" name="color">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="edit_estado" class="form-label">Estado <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_estado" name="estado" required>
                                    <option value="nuevo">Nuevo</option>
                                    <option value="bueno">Bueno</option>
                                    <option value="regular">Regular</option>
                                    <option value="malo">Malo</option>
                                    <option value="para_reparacion">Para Reparación</option>
                                    <option value="dado_de_baja">Dado de Baja</option>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="edit_disponible" class="form-label">Disponibilidad</label>
                                <select class="form-select" id="edit_disponible" name="disponible">
                                    <option value="1">Disponible</option>
                                    <option value="0">No Disponible</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label for="edit_notas" class="form-label">Notas/Observaciones</label>
                                <textarea class="form-control" id="edit_notas" name="notas" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/app.js"></script>
    <script>
        // Editar equipo
        function editarEquipo(id) {
            // Hacer petición AJAX para obtener datos del equipo
            fetch(`../../actions/inventario/obtener-equipo.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const equipo = data.equipo;
                        document.getElementById('edit_id').value = equipo.id;
                        document.getElementById('edit_tipo').value = equipo.tipo;
                        document.getElementById('edit_marca').value = equipo.marca || '';
                        document.getElementById('edit_modelo').value = equipo.modelo || '';
                        document.getElementById('edit_numero_serie').value = equipo.numero_serie;
                        document.getElementById('edit_procesador').value = equipo.procesador || '';
                        document.getElementById('edit_sistema_operativo').value = equipo.sistema_operativo || '';
                        document.getElementById('edit_color').value = equipo.color || '';
                        document.getElementById('edit_estado').value = equipo.estado;
                        document.getElementById('edit_disponible').value = equipo.disponible;
                        document.getElementById('edit_notas').value = equipo.notas || '';
                        
                        const modal = new bootstrap.Modal(document.getElementById('modalEditarEquipo'));
                        modal.show();
                    } else {
                        Swal.fire('Error', 'No se pudo obtener la información del equipo', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Ocurrió un error al cargar el equipo', 'error');
                });
        }

        // Eliminar equipo
        function eliminarEquipo(id, nombre) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: `Se eliminará el equipo: ${nombre}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar petición para eliminar
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../../actions/inventario/eliminar-equipo.php';
                    
                    const inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'id';
                    inputId.value = id;
                    form.appendChild(inputId);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>

   <script>
// Mostrar/ocultar campos según tipo de equipo
document.addEventListener('DOMContentLoaded', function() {
    const tipoSelect = document.getElementById('tipo');
    
    if (tipoSelect) {
        // Función para mostrar/ocultar campos
        function toggleCamposComputacionales() {
            const tipo = tipoSelect.value.toLowerCase();
            
            // Buscar los campos por id directamente
            const campoProcesador = document.getElementById('procesador');
            const campoSO = document.getElementById('sistema_operativo');
            
            // Obtener los contenedores (col-md-6)
            const filaProcesador = campoProcesador?.closest('.col-md-6, .mb-3');
            const filaSO = campoSO?.closest('.col-md-6, .mb-3');
            
            // Tipos que necesitan procesador y sistema operativo
            const tiposComputacionales = ['laptop', 'pc de escritorio', 'pc', 'tablet', 'celular'];
            
            // Verificar si el tipo seleccionado es computacional
            const esComputacional = tiposComputacionales.some(t => tipo.includes(t));
            
            // Mostrar u ocultar campos
            if (filaProcesador) {
                filaProcesador.style.display = esComputacional ? '' : 'none';
                if (campoProcesador) {
                    campoProcesador.required = esComputacional;
                    if (!esComputacional) campoProcesador.value = '';
                }
            }
            
            if (filaSO) {
                filaSO.style.display = esComputacional ? '' : 'none';
                if (campoSO) {
                    campoSO.required = esComputacional;
                    if (!esComputacional) campoSO.value = '';
                }
            }
        }
        
        // Ejecutar al cambiar el tipo
        tipoSelect.addEventListener('change', toggleCamposComputacionales);
        
        // Ejecutar al cargar (por si hay valor preseleccionado)
        toggleCamposComputacionales();
    }
});
</script>

</body>
</html>
