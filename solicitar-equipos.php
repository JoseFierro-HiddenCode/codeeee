<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo usuarios que son jefes o admin_global pueden acceder
protegerPagina(['usuario', 'admin_tecnico','admin_global']);

// Verificar que sea usuario autorizado especial
if (!esUsuarioAutorizadoEspecial()) {
    header('Location: ../dashboard-usuario.php?error=' . urlencode('No tienes permisos para solicitar equipos'));
    exit();
}

// Obtener TODOS los usuarios activos (usuario autorizado puede solicitar para cualquiera)
$colaboradores = obtenerTodos("
    SELECT 
        u.id,
        u.nombre + ' ' + u.apellido as nombre_completo,
        u.dni,
        u.telefono,
        u.puesto,
        u.sede_id,
        s.nombre as sede_nombre
    FROM users u
    LEFT JOIN sedes s ON u.sede_id = s.id
    WHERE u.activo = 1
    ORDER BY u.nombre, u.apellido
");
// Obtener sedes
$sedes = obtenerTodos("SELECT * FROM sedes WHERE activo = 1 ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../img/LogoGris.png">
    <title>Solicitar Equipos - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard-usuario.php">
                <img src="../../img/LogoGris.png" alt="Logo ACP" style="height: 40px;" class="me-2">
                <span class="fw-semibold">Sistema de Tickets</span>
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
                <a href="solicitar-equipos.php" class="sidebar-nav-link active">
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
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Formulario de Solicitud de Equipos y Accesos</h1>
            <p class="page-subtitle">Tecnologías de la Información y las Comunicaciones</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Solicitud creada exitosamente. 
                Folio: <strong><?php echo e($_GET['folio'] ?? ''); ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="../../actions/inventario/crear-solicitud.php" method="POST" id="formSolicitud">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5><i class="bi bi-1-circle-fill"></i> Solicitado para:</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="colaborador_id" class="form-label">
                                👤 Colaborador <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="colaborador_id" name="colaborador_id" required onchange="cargarDatosColaborador()">
    <option value="">Seleccione un colaborador...</option>
    <?php foreach ($colaboradores as $col): ?>
        <option value="<?php echo $col['id']; ?>" 
                data-dni="<?php echo e($col['dni'] ?? ''); ?>"
                data-telefono="<?php echo e($col['telefono'] ?? ''); ?>"
                data-puesto="<?php echo e($col['puesto'] ?? ''); ?>"
                data-sede-id="<?php echo e($col['sede_id'] ?? ''); ?>"
                data-sede-nombre="<?php echo e($col['sede_nombre'] ?? ''); ?>">
            <?php echo e($col['nombre_completo']); ?>
        </option>
    <?php endforeach; ?>
</select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="dni" class="form-label">DNI</label>
                            <input type="text" class="form-control" id="dni" name="dni" readonly>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="telefono" class="form-label">☎️ Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="cargo" class="form-label">🧾 Cargo</label>
                            <input type="text" class="form-control" id="cargo" name="cargo" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="sede_id" class="form-label">🏢 Sede</label>
                            <select class="form-select" id="sede_id" name="sede_id">
                                <option value="">Seleccione...</option>
                                <?php foreach ($sedes as $sede): ?>
                                    <option value="<?php echo $sede['id']; ?>"><?php echo e($sede['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tipo de Solicitud -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5>Tipo de Solicitud</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_solicitud" id="tipo_crear" value="crear_asignar" checked>
                                <label class="form-check-label" for="tipo_crear">
                                    ✅ Crear / Asignar
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_solicitud" id="tipo_accesos" value="solicitar_accesos">
                                <label class="form-check-label" for="tipo_accesos">
                                    🔐 Solicitar Accesos
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_solicitud" id="tipo_baja" value="baja_reemplazo">
                                <label class="form-check-label" for="tipo_baja">
                                    🔄 Baja / Reemplazo
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 2: Solicitud de Equipo -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5><i class="bi bi-2-circle-fill"></i> Solicitud de Equipo</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solicita_laptop" id="solicita_laptop" value="1">
                                <label class="form-check-label" for="solicita_laptop">
                                    💻 Laptop
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solicita_celular" id="solicita_celular" value="1">
                                <label class="form-check-label" for="solicita_celular">
                                    📱 Celular
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solicita_pc" id="solicita_pc" value="1">
                                <label class="form-check-label" for="solicita_pc">
                                    🖥️ Computadora (PC)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="otros_equipos" class="form-label">🗂️ Otros Equipos</label>
                        <input type="text" class="form-control" id="otros_equipos" name="otros_equipos" 
                               placeholder="Ej: Monitor, Mouse, Teclado, etc.">
                    </div>

                    <div class="mb-3">
                        <label for="caracteristicas_equipo" class="form-label">🛠️ Características Especiales</label>
                        <textarea class="form-control" id="caracteristicas_equipo" name="caracteristicas_equipo" rows="3"
                                  placeholder="Especifique características técnicas requeridas..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Sección 3: Acceso a SharePoint -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5><i class="bi bi-3-circle-fill"></i> Solicitud de Acceso al SHAREPOINT</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_gestion_mostrador" value="1">
                                <label class="form-check-label">Gestión por mostrador</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_gestion_servicio" value="1">
                                <label class="form-check-label">Gestión por servicio</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_unidades_nuevas" value="1">
                                <label class="form-check-label">Gestión de unidades nuevas</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_contratos_inhouse" value="1">
                                <label class="form-check-label">Gestión por contratos in house</label>
                            </div>
                        </div>
                    </div>

                    <h6>Áreas:</h6>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_admin_finanzas" value="1">
                                <label class="form-check-label">Admin. y Finanzas</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_contabilidad" value="1">
                                <label class="form-check-label">Contabilidad</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_logistica" value="1">
                                <label class="form-check-label">Logística y compras</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_sei" value="1">
                                <label class="form-check-label">SEI</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_rrhh" value="1">
                                <label class="form-check-label">RRHH</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_ssoma" value="1">
                                <label class="form-check-label">SSOMA</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_tic" value="1">
                                <label class="form-check-label">TIC</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sp_calidad" value="1">
                                <label class="form-check-label">Calidad</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">📈 Tipo de acceso:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sp_nivel" value="nivel3" id="nivel3">
                            <label class="form-check-label" for="nivel3">
                                Nivel 3: Edición (Leer, Cargar, Descargar, Editar, Eliminar)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sp_nivel" value="nivel4" id="nivel4">
                            <label class="form-check-label" for="nivel4">
                                Nivel 4: Actualización (Leer, Cargar, Descargar, Editar)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sp_nivel" value="nivel5" id="nivel5">
                            <label class="form-check-label" for="nivel5">
                                Nivel 5: Visualización (Leer, Cargar)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sp_nivel" value="nivel6" id="nivel6">
                            <label class="form-check-label" for="nivel6">
                                Nivel 6: Vista restringida (Leer)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

         <!-- Sección 4: ACPCORE -->
<div class="card mb-4">
    <div class="card-header bg-warning">
        <h5><i class="bi bi-4-circle-fill"></i> Solicitud de ACPCORE</h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Seleccione los roles necesarios en el sistema ACPCORE:</p>
        
        <div class="row">
            <!-- Asesores -->
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asesor_contratos" id="acpcore_asesor_contratos" value="1">
                    <label class="form-check-label" for="acpcore_asesor_contratos">Asesor de Contratos</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asesor_garantias" id="acpcore_asesor_garantias" value="1">
                    <label class="form-check-label" for="acpcore_asesor_garantias">Asesor de Garantías</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asesor_mostrador" id="acpcore_asesor_mostrador" value="1">
                    <label class="form-check-label" for="acpcore_asesor_mostrador">Asesor de Mostrador</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asesor_repuesto" id="acpcore_asesor_repuesto" value="1">
                    <label class="form-check-label" for="acpcore_asesor_repuesto">Asesor de Repuesto</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asesor_servicio" id="acpcore_asesor_servicio" value="1">
                    <label class="form-check-label" for="acpcore_asesor_servicio">Asesor de Servicio</label>
                </div>
            </div>
            
            <!-- Asistentes -->
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_admin" id="acpcore_asist_admin" value="1">
                    <label class="form-check-label" for="acpcore_asist_admin">Asistente Administrativo</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_contable" id="acpcore_asist_contable" value="1">
                    <label class="form-check-label" for="acpcore_asist_contable">Asistente Contable</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_caja" id="acpcore_asist_caja" value="1">
                    <label class="form-check-label" for="acpcore_asist_caja">Asistente de Caja</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_cobranza" id="acpcore_asist_cobranza" value="1">
                    <label class="form-check-label" for="acpcore_asist_cobranza">Asistente de Cobranza</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_calidad" id="acpcore_asist_calidad" value="1">
                    <label class="form-check-label" for="acpcore_asist_calidad">Asistente de Calidad y Procesos</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_almacen_herr" id="acpcore_asist_almacen_herr" value="1">
                    <label class="form-check-label" for="acpcore_asist_almacen_herr">Asistente de Almacén de Herramientas</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_almacen_log" id="acpcore_asist_almacen_log" value="1">
                    <label class="form-check-label" for="acpcore_asist_almacen_log">Asistente de Almacén-Logística</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_rrhh" id="acpcore_asist_rrhh" value="1">
                    <label class="form-check-label" for="acpcore_asist_rrhh">Asistente de Recursos Humanos</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_infraestructura" id="acpcore_asist_infraestructura" value="1">
                    <label class="form-check-label" for="acpcore_asist_infraestructura">Asistente de Infraestructura</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_jefe_servicio" id="acpcore_asist_jefe_servicio" value="1">
                    <label class="form-check-label" for="acpcore_asist_jefe_servicio">Asistente de Jefe de Servicio</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_asist_planificador" id="acpcore_asist_planificador" value="1">
                    <label class="form-check-label" for="acpcore_asist_planificador">Asistente Planificador</label>
                </div>
            </div>
            
            <!-- Coordinadores -->
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_coord_contratos" id="acpcore_coord_contratos" value="1">
                    <label class="form-check-label" for="acpcore_coord_contratos">Coordinador de Contratos</label>
                </div>
            </div>
            
            <!-- Otros roles -->
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_facturador" id="acpcore_facturador" value="1">
                    <label class="form-check-label" for="acpcore_facturador">Facturador Electrónico</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_instructor_conduccion" id="acpcore_instructor_conduccion" value="1">
                    <label class="form-check-label" for="acpcore_instructor_conduccion">Instructor de Conducción</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_instructor_tecnico" id="acpcore_instructor_tecnico" value="1">
                    <label class="form-check-label" for="acpcore_instructor_tecnico">Instructor Técnico</label>
                </div>
            </div>
            
            <!-- Jefes -->
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_calidad" id="acpcore_jefe_calidad" value="1">
                    <label class="form-check-label" for="acpcore_jefe_calidad">Jefe de Calidad y Procesos</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_contabilidad" id="acpcore_jefe_contabilidad" value="1">
                    <label class="form-check-label" for="acpcore_jefe_contabilidad">Jefe de Contabilidad</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_contratos" id="acpcore_jefe_contratos" value="1">
                    <label class="form-check-label" for="acpcore_jefe_contratos">Jefe de Contratos</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_contratos_inhouse" id="acpcore_jefe_contratos_inhouse" value="1">
                    <label class="form-check-label" for="acpcore_jefe_contratos_inhouse">Jefe de Contratos In House</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_logistica" id="acpcore_jefe_logistica" value="1">
                    <label class="form-check-label" for="acpcore_jefe_logistica">Jefe de Logística</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_repuestos" id="acpcore_jefe_repuestos" value="1">
                    <label class="form-check-label" for="acpcore_jefe_repuestos">Jefe de Repuestos</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_servicio" id="acpcore_jefe_servicio" value="1">
                    <label class="form-check-label" for="acpcore_jefe_servicio">Jefe de Servicio</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_taller" id="acpcore_jefe_taller" value="1">
                    <label class="form-check-label" for="acpcore_jefe_taller">Jefe de Taller</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_jefe_unidades" id="acpcore_jefe_unidades" value="1">
                    <label class="form-check-label" for="acpcore_jefe_unidades">Jefe de Unidades Nuevas</label>
                </div>
            </div>
            
            <!-- Mecánicos y otros -->
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_mecanico" id="acpcore_mecanico" value="1">
                    <label class="form-check-label" for="acpcore_mecanico">Mecánico</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_mecanico_contratos" id="acpcore_mecanico_contratos" value="1">
                    <label class="form-check-label" for="acpcore_mecanico_contratos">Mecánico de Contratos</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_operador_logistico" id="acpcore_operador_logistico" value="1">
                    <label class="form-check-label" for="acpcore_operador_logistico">Operador Logístico</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_practicante_logistica" id="acpcore_practicante_logistica" value="1">
                    <label class="form-check-label" for="acpcore_practicante_logistica">Practicante de Logística</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_responsable_sst" id="acpcore_responsable_sst" value="1">
                    <label class="form-check-label" for="acpcore_responsable_sst">Responsable de SST</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="acpcore_supervisor" id="acpcore_supervisor" value="1">
                    <label class="form-check-label" for="acpcore_supervisor">Supervisor</label>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="acpcore_otros" class="form-label">🗂️ Otros (especificar)</label>
            <input type="text" class="form-control" id="acpcore_otros" name="acpcore_otros" 
                   placeholder="Especifique otros roles no listados">
        </div>
    </div>
</div>
            <!-- Sección 5: Servicios Remotos -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5><i class="bi bi-5-circle-fill"></i> Solicitud de Servicios Remotos</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solicita_gds" id="solicita_gds" value="1">
                                <label class="form-check-label" for="solicita_gds">
                                    🔐 Uso de GDS
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solicita_contasis" id="solicita_contasis" value="1">
                                <label class="form-check-label" for="solicita_contasis">
                                    🔐 Uso de CONTASIS
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="justif_servicios_remotos" class="form-label">📜 Justificación:</label>
                        <textarea class="form-control" id="justif_servicios_remotos" name="justif_servicios_remotos" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <!-- Sección 6: GDS -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5><i class="bi bi-6-circle-fill"></i> Solicitud de GDS</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="gds_rol" class="form-label">👥 Rol:</label>
                            <input type="text" class="form-control" id="gds_rol" name="gds_rol">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="gds_usuario" class="form-label">🧑‍💼 Usuario (ej: MB123456):</label>
                            <input type="text" class="form-control" id="gds_usuario" name="gds_usuario">
                        </div>
                        <div class="col-12 mb-3">
                            <label for="gds_justificacion" class="form-label">📜 Justificación:</label>
                            <textarea class="form-control" id="gds_justificacion" name="gds_justificacion" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Otros Accesos -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5>Otros Accesos (Justificar)</h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="otros_accesos" rows="3" 
                              placeholder="Especifique otros accesos necesarios y su justificación..."></textarea>
                </div>
            </div>

            <!-- Justificación General -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5>Justificación de la Solicitud <span class="text-danger">*</span></h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="justificacion" rows="4" required
                              placeholder="Explique el motivo de esta solicitud (nuevo colaborador, reemplazo de equipo, cambio de área, etc.)"></textarea>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex gap-2 justify-content-end mb-4">
                <a href="mis-solicitudes.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send-fill"></i> Enviar Solicitud
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/app.js"></script>
    <script>
function cargarDatosColaborador() {
    const select = document.getElementById('colaborador_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        // Cargar datos desde los atributos data-*
        document.getElementById('dni').value = selectedOption.getAttribute('data-dni') || '';
        document.getElementById('telefono').value = selectedOption.getAttribute('data-telefono') || '';
        document.getElementById('cargo').value = selectedOption.getAttribute('data-puesto') || '';
        
        // Cargar sede
        const sedeId = selectedOption.getAttribute('data-sede-id');
        const sedeSelect = document.getElementById('sede_id');
        
        if (sedeId && sedeSelect) {
            sedeSelect.value = sedeId;
        } else {
            sedeSelect.value = '';
        }
    } else {
        // Limpiar campos
        document.getElementById('dni').value = '';
        document.getElementById('telefono').value = '';
        document.getElementById('cargo').value = '';
        document.getElementById('sede_id').value = '';
    }
}
</script>
</body>
</html>