<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Solo Admin y Técnicos pueden gestionar usuarios
protegerPagina(['admin_tecnico', 'tecnico']);

// Obtener todos los usuarios
$sql = "
    SELECT 
        u.*,
        jefe.nombre + ' ' + jefe.apellido as nombre_jefe,
        sede.nombre as sede_nombre,
        area.nombre as area_nombre
    FROM users u
    LEFT JOIN users jefe ON u.jefe_id = jefe.id
    LEFT JOIN sedes sede ON u.sede_id = sede.id
    LEFT JOIN areas area ON u.area_id = area.id
    ORDER BY u.activo DESC, u.nombre, u.apellido
";
$usuarios = obtenerTodos($sql);

// Obtener lista de posibles jefes (usuarios con es_jefe = 1)
$jefes = obtenerTodos("
    SELECT id, nombre + ' ' + apellido as nombre_completo 
    FROM users 
    WHERE es_jefe = 1 AND activo = 1
    ORDER BY nombre, apellido
");

// Obtener sedes y áreas
$sedes = obtenerTodos("SELECT * FROM sedes ORDER BY nombre");
$areas = obtenerTodos("SELECT * FROM areas ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - Sistema de Tickets</title>
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
        
        <div class="sidebar-section-title">Administración</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="gestionar-usuarios.php" class="sidebar-nav-link active">
                    <i class="bi bi-people-fill"></i>
                    <span>Gestionar Usuarios</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Gestión de Usuarios</h1>
                <p class="page-subtitle">Administra usuarios del sistema</p>
            </div>
            <button class="btn btn-primary" onclick="abrirModalCrear()">
                <i class="bi bi-person-plus"></i> Crear Usuario
            </button>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> 
                <?php 
                    if ($_GET['success'] == 'creado') echo 'Usuario creado exitosamente';
                    if ($_GET['success'] == 'editado') echo 'Usuario actualizado exitosamente';
                    if ($_GET['success'] == 'eliminado') echo 'Usuario eliminado exitosamente';
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

        <!-- Tabla de Usuarios -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-people"></i> Usuarios Registrados (<?php echo count($usuarios); ?>)</h5>
    </div>
    <div class="card-body" style="padding: 20px; overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 60px;">ID</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 200px;">NOMBRE</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 180px;">EMAIL</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 100px;">DNI</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">PUESTO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 100px;">ROL</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">JEFE</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 90px;">ES JEFE</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 90px;">ESTADO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usr): ?>
                <tr style="transition: background-color 0.2s ease; <?php echo !$usr['activo'] ? 'opacity: 0.6;' : ''; ?>"
                    onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                    onmouseout="this.style.backgroundColor='transparent'">
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <strong style="color: var(--jira-blue); font-weight: 700;"><?php echo $usr['id']; ?></strong>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                        <strong style="color: var(--text-primary); font-weight: 600;">
                            <?php echo e($usr['nombre'] . ' ' . $usr['apellido']); ?>
                        </strong>
                        <?php if ($usr['sede_nombre']): ?>
                            <br><small style="color: var(--text-muted);">📍 <?php echo e($usr['sede_nombre']); ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                        <?php echo e($usr['email']); ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; color: var(--text-secondary); vertical-align: middle;">
                        <?php echo e($usr['dni'] ?? '-'); ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                        <?php echo e($usr['puesto'] ?? '-'); ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <?php
                        $rolBadges = [
                            'admin_tecnico' => '<span class="badge bg-danger">Admin TI</span>',
                            'tecnico' => '<span class="badge bg-primary">Técnico</span>',
                            'gerente' => '<span class="badge bg-success">Gerente</span>',
                            'usuario' => '<span class="badge bg-secondary">Usuario</span>'
                        ];
                        echo $rolBadges[$usr['rol']] ?? '<span class="badge bg-dark">' . e($usr['rol']) . '</span>';
                        ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                        <?php if ($usr['nombre_jefe']): ?>
                            <small style="color: var(--text-secondary);"><?php echo e($usr['nombre_jefe']); ?></small>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <?php echo $usr['es_jefe'] ? '<span class="badge bg-info">👔 Sí</span>' : '<span style="color: var(--text-muted);">No</span>'; ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <?php echo $usr['activo'] 
                            ? '<span class="badge bg-success">✅ Activo</span>' 
                            : '<span class="badge bg-secondary">❌ Inactivo</span>'; 
                        ?>
                    </td>
                    
                    <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                        <button class="btn btn-sm btn-warning" onclick='editarUsuario(<?php echo json_encode($usr); ?>)' style="margin-right: 4px;">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-<?php echo $usr['activo'] ? 'secondary' : 'success'; ?>" 
                                onclick="toggleEstado(<?php echo $usr['id']; ?>, <?php echo $usr['activo'] ? 0 : 1; ?>)">
                            <i class="bi bi-power"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

    <!-- Modal Crear/Editar Usuario -->
    <div class="modal fade" id="modalUsuario" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tituloModal">
                        <i class="bi bi-person-plus"></i> <span id="textoTitulo">Crear Usuario</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../actions/usuarios/guardar-usuario.php" method="POST" id="formUsuario">
                    <input type="hidden" id="usuario_id" name="usuario_id">
                    <input type="hidden" id="accion" name="accion" value="crear">
                    
                    <div class="modal-body">
                        <div class="row">
                            <!-- Columna 1 -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>

                                <div class="mb-3">
                                    <label for="apellido" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña <span id="passwordLabel">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <small class="text-muted">Mínimo 6 caracteres. <span id="passwordHint">Déjalo vacío al editar para mantener la actual.</span></small>
                                </div>

                                <div class="mb-3">
                                    <label for="dni" class="form-label">DNI</label>
                                    <input type="text" class="form-control" id="dni" name="dni" maxlength="20">
                                </div>

                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" maxlength="20">
                                </div>
                            </div>

                            <!-- Columna 2 -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="puesto" class="form-label">Puesto</label>
                                    <input type="text" class="form-control" id="puesto" name="puesto" maxlength="100">
                                </div>

                                <div class="mb-3">
                                    <label for="rol" class="form-label">Rol *</label>
                                    <select class="form-select" id="rol" name="rol" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="admin_tecnico">Admin TI</option>
                                        <option value="tecnico">Técnico</option>
                                        <option value="gerente">Gerente</option>
                                        <option value="usuario">Usuario</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="sede_id" class="form-label">Sede</label>
                                    <select class="form-select" id="sede_id" name="sede_id">
                                        <option value="">Sin sede</option>
                                        <?php foreach ($sedes as $sede): ?>
                                            <option value="<?php echo $sede['id']; ?>"><?php echo e($sede['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="area_id" class="form-label">Área</label>
                                    <select class="form-select" id="area_id" name="area_id">
                                        <option value="">Sin área</option>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?php echo $area['id']; ?>"><?php echo e($area['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="es_jefe" name="es_jefe" value="1">
                                        <label class="form-check-label" for="es_jefe">
                                            <strong>👔 ¿Es Jefe?</strong>
                                        </label>
                                    </div>
                                    <small class="text-muted">Marcar si este usuario puede solicitar equipos para su equipo</small>
                                </div>

                                <div class="mb-3">
                                    <label for="jefe_id" class="form-label">Jefe Asignado</label>
                                    <select class="form-select" id="jefe_id" name="jefe_id">
                                        <option value="">Sin jefe</option>
                                        <?php foreach ($jefes as $jefe): ?>
                                            <option value="<?php echo $jefe['id']; ?>"><?php echo e($jefe['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Jefe directo de este usuario</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> <span id="btnTexto">Crear Usuario</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/app.js"></script>
    <script>
        let modalUsuario;

        document.addEventListener('DOMContentLoaded', function() {
            modalUsuario = new bootstrap.Modal(document.getElementById('modalUsuario'));
        });

        function abrirModalCrear() {
            document.getElementById('formUsuario').reset();
            document.getElementById('usuario_id').value = '';
            document.getElementById('accion').value = 'crear';
            document.getElementById('textoTitulo').textContent = 'Crear Usuario';
            document.getElementById('btnTexto').textContent = 'Crear Usuario';
            document.getElementById('password').required = true;
            document.getElementById('passwordLabel').textContent = '*';
            document.getElementById('passwordHint').style.display = 'none';
            modalUsuario.show();
        }

        function editarUsuario(usuario) {
            document.getElementById('usuario_id').value = usuario.id;
            document.getElementById('accion').value = 'editar';
            document.getElementById('nombre').value = usuario.nombre;
            document.getElementById('apellido').value = usuario.apellido;
            document.getElementById('email').value = usuario.email;
            document.getElementById('dni').value = usuario.dni || '';
            document.getElementById('telefono').value = usuario.telefono || '';
            document.getElementById('puesto').value = usuario.puesto || '';
            document.getElementById('rol').value = usuario.rol;
            document.getElementById('sede_id').value = usuario.sede_id || '';
            document.getElementById('area_id').value = usuario.area_id || '';
            document.getElementById('es_jefe').checked = usuario.es_jefe == 1;
            document.getElementById('jefe_id').value = usuario.jefe_id || '';
            
            document.getElementById('password').required = false;
            document.getElementById('password').value = '';
            document.getElementById('passwordLabel').textContent = '';
            document.getElementById('passwordHint').style.display = 'inline';
            
            document.getElementById('textoTitulo').textContent = 'Editar Usuario';
            document.getElementById('btnTexto').textContent = 'Guardar Cambios';
            
            modalUsuario.show();
        }

        function toggleEstado(userId, nuevoEstado) {
            const accion = nuevoEstado ? 'activar' : 'desactivar';
            const textoAccion = nuevoEstado ? 'activará' : 'desactivará';
            
            Swal.fire({
                title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} usuario?`,
                text: `Se ${textoAccion} este usuario`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: nuevoEstado ? '#28a745' : '#6c757d',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Sí, ${accion}`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../../actions/usuarios/toggle-estado.php';
                    
                    const inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'usuario_id';
                    inputId.value = userId;
                    form.appendChild(inputId);
                    
                    const inputEstado = document.createElement('input');
                    inputEstado.type = 'hidden';
                    inputEstado.name = 'activo';
                    inputEstado.value = nuevoEstado;
                    form.appendChild(inputEstado);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>