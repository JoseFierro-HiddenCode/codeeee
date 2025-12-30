<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Proteger endpoint - Permitir jefes Y admin_tecnico
$esAdmin = in_array($_SESSION['rol'] ?? '', ['admin_tecnico', 'admin_global']);
$esJefeUsuario = esJefe();

if (!isset($_SESSION['user_id']) || (!$esAdmin && !$esJefeUsuario)) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$empleadoId = $_GET['id'] ?? 0;

if (!$empleadoId) {
    echo json_encode(['error' => 'ID de empleado requerido']);
    exit();
}

// Query diferente según el rol
if ($esAdmin) {
    // Admin puede ver cualquier usuario activo
    $sql = "
        SELECT 
            u.id,
            u.nombre,
            u.apellido,
            u.email,
            u.telefono,
            u.puesto,
            s.nombre as sede_nombre,
            a.nombre as area_nombre,
            sol.id_usuario as id_usuario_vol
        FROM users u
        LEFT JOIN sedes s ON u.sede_id = s.id
        LEFT JOIN areas a ON u.area_id = a.id
        LEFT JOIN solicitudes_accesos_vol sol ON u.id = sol.empleado_id
        WHERE u.id = ? AND u.activo = 1
    ";
    
    $empleado = obtenerUno($sql, [$empleadoId]);
    
} else {
    // Jefe solo puede ver sus empleados
    $sql = "
        SELECT 
            u.id,
            u.nombre,
            u.apellido,
            u.email,
            u.telefono,
            u.puesto,
            s.nombre as sede_nombre,
            a.nombre as area_nombre,
            sol.id_usuario as id_usuario_vol
        FROM users u
        LEFT JOIN sedes s ON u.sede_id = s.id
        LEFT JOIN areas a ON u.area_id = a.id
        LEFT JOIN solicitudes_accesos_vol sol ON u.id = sol.empleado_id
        WHERE u.id = ? AND u.jefe_id = ? AND u.activo = 1
    ";
    
    $empleado = obtenerUno($sql, [$empleadoId, $_SESSION['user_id']]);
}

if (!$empleado) {
    $mensaje = $esAdmin ? 'Empleado no encontrado o está inactivo' : 'Empleado no encontrado o no pertenece a tu equipo';
    echo json_encode(['error' => $mensaje]);
    exit();
}

// Obtener accesos existentes
$sqlAccesos = "
    SELECT d.acceso
    FROM solicitudes_accesos_vol s
    INNER JOIN solicitudes_accesos_vol_detalle d ON s.id = d.solicitud_id
    WHERE s.empleado_id = ?
";

$accesosExistentes = obtenerTodos($sqlAccesos, [$empleadoId]);

$response = [
    'id' => $empleado['id'],
    'nombre' => $empleado['nombre'],
    'apellido' => $empleado['apellido'],
    'email' => $empleado['email'],
    'telefono' => $empleado['telefono'],
    'puesto' => $empleado['puesto'],
    'sede_nombre' => $empleado['sede_nombre'],
    'area_nombre' => $empleado['area_nombre'],
    'id_usuario_vol' => $empleado['id_usuario_vol'],
    'tiene_accesos' => count($accesosExistentes) > 0,
    'accesos_existentes' => array_column($accesosExistentes, 'acceso')
];

echo json_encode($response);
?>