<?php
// ============================================
// FUNCIONES AUXILIARES DEL SISTEMA
// ============================================

// Iniciar sesión si no está iniciada
function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Verificar si el usuario está logueado
function usuarioLogueado() {
    iniciarSesion();
    return isset($_SESSION['user_id']);
}

// Obtener usuario actual
function usuarioActual() {
    iniciarSesion();
    return $_SESSION ?? null;
}

// Redirigir
function redirigir($url) {
    header("Location: $url");
    exit();
}

// Verificar rol
function tieneRol($roles) {
    iniciarSesion();
    if (!usuarioLogueado()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = array($roles);
    }
    
    return in_array($_SESSION['rol'], $roles);
}

// Proteger página (requiere login)
function protegerPagina($rolesPermitidos = null) {
    if (!usuarioLogueado()) {
        redirigir('/sistema-tickets/index.php');
    }
    
    if ($rolesPermitidos !== null) {
        if (!tieneRol($rolesPermitidos)) {
            redirigir('/sistema-tickets/index.php');
        }
    }
}

// Formatear fecha corta (para kanban)
function formatearFechaCorta($fecha) {
    if (empty($fecha)) {
        return '-';
    }
    
    if ($fecha instanceof DateTime) {
        return $fecha->format('d/m');
    }
    
    return date('d/m', strtotime($fecha));
}

// Formatear fecha completa
function formatearFecha($fecha) {
    if (empty($fecha)) {
        return '-';
    }
    
    if ($fecha instanceof DateTime) {
        $fecha = $fecha->format('Y-m-d H:i:s');
    }
    
    if (is_string($fecha)) {
        $timestamp = strtotime($fecha);
        if ($timestamp === false) {
            return $fecha;
        }
        
        $dia = date('d', $timestamp);
        $mes = date('m', $timestamp);
        $anio = date('Y', $timestamp);
        $hora = date('H:i', $timestamp);
        
        $meses = [
            '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'
        ];
        
        return $dia . ' ' . $meses[$mes] . ' ' . $anio . ' ' . $hora;
    }
    
    return '-';
}

// Escapar HTML
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Generar badge HTML para estado
function badgeEstado($estado) {
    $badges = [
        'abierto' => '<span class="badge badge-status-abierto">Abierto</span>',
        'en_progreso' => '<span class="badge badge-status-en_progreso">En Progreso</span>',
        'cerrado' => '<span class="badge badge-status-cerrado">Cerrado</span>'
    ];
    return $badges[$estado] ?? '<span class="badge bg-secondary">' . e($estado) . '</span>';
}

// Generar badge HTML para prioridad
function badgePrioridad($prioridad) {
    $badges = [
        'urgente' => '<span class="badge badge-priority-urgente">🔴 Urgente</span>',
        'alta' => '<span class="badge badge-priority-alta">🟠 Alta</span>',
        'media' => '<span class="badge badge-priority-media">🟡 Media</span>',
        'baja' => '<span class="badge badge-priority-baja">🟢 Baja</span>'
    ];
    return $badges[$prioridad] ?? '<span class="badge bg-secondary">' . e($prioridad) . '</span>';
}

// Subir imagen
function subirImagen($archivo, $carpeta = 'tickets') {
    $directorioDestino = __DIR__ . "/../public/uploads/$carpeta/";
    
    if (!file_exists($directorioDestino)) {
        mkdir($directorioDestino, 0777, true);
    }
    
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    if (!in_array($archivo['type'], $tiposPermitidos)) {
        return ['error' => 'Solo se permiten imágenes (JPG, PNG, GIF)'];
    }
    
    if ($archivo['size'] > 5242880) {
        return ['error' => 'La imagen no debe superar 5MB'];
    }
    
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
    $rutaCompleta = $directorioDestino . $nombreArchivo;
    
    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        return [
            'success' => true,
            'ruta' => "uploads/$carpeta/" . $nombreArchivo,
            'nombre_original' => $archivo['name'],
            'tamanio' => $archivo['size'],
            'mime_type' => $archivo['type']
        ];
    }
    
    return ['error' => 'Error al subir la imagen'];
}

// Obtener iniciales del nombre
function obtenerIniciales($nombre, $apellido = '') {
    $inicialNombre = !empty($nombre) ? strtoupper(substr($nombre, 0, 1)) : '';
    $inicialApellido = !empty($apellido) ? strtoupper(substr($apellido, 0, 1)) : '';
    
    if (empty($apellido) && strpos($nombre, ' ') !== false) {
        $partes = explode(' ', trim($nombre));
        $inicialNombre = strtoupper(substr($partes[0], 0, 1));
        $inicialApellido = count($partes) > 1 ? strtoupper(substr($partes[1], 0, 1)) : '';
    }
    
    return $inicialNombre . $inicialApellido;
}

// ============================================
// FUNCIONES DE ARCHIVADO
// ============================================

// Ejecutar archivado automático si es necesario
function ejecutarArchivoSiNecesario() {
    global $conn;
    
    // Verificar si existe la tabla configuracion
    $checkTable = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'configuracion'");
    if (!$checkTable || !sqlsrv_fetch_array($checkTable)) {
        // Si no existe la tabla, salir sin error
        return;
    }
    
    $ultimaEjecucion = obtenerUno("SELECT valor FROM configuracion WHERE clave = 'ultimo_archivado'");
    
    $debeEjecutar = false;
    
    if (!$ultimaEjecucion || !$ultimaEjecucion['valor']) {
        $debeEjecutar = true;
    } else {
        $ultimaFecha = strtotime($ultimaEjecucion['valor']);
        $ahora = time();
        $diferencia = ($ahora - $ultimaFecha) / 3600;
        
        if ($diferencia >= 1) {
            $debeEjecutar = true;
        }
    }
    
    if ($debeEjecutar) {
        $stmt = sqlsrv_query($conn, "EXEC sp_archivar_tickets_cerrados");
        if ($stmt) {
            sqlsrv_free_stmt($stmt);
        }
    }
}

// Calcular tiempo transcurrido
function calcularTiempo($fecha) {
    if (empty($fecha)) return '';
    
    if ($fecha instanceof DateTime) {
        $fecha = $fecha->format('Y-m-d H:i:s');
    }
    
    $timestamp = strtotime($fecha);
    if ($timestamp === false) return '';
    
    $ahora = time();
    $diferencia = $ahora - $timestamp;
    
    if ($diferencia < 60) {
        return 'hace unos segundos';
    } elseif ($diferencia < 3600) {
        $minutos = floor($diferencia / 60);
        return "hace {$minutos} min";
    } elseif ($diferencia < 86400) {
        $horas = floor($diferencia / 3600);
        return "hace {$horas}h";
    } else {
        $dias = floor($diferencia / 86400);
        return "hace {$dias} día" . ($dias > 1 ? 's' : '');
    }
}

// Calcular tiempo restante hasta archivado
function tiempoRestante($fechaCierre) {
    if (empty($fechaCierre)) return '';
    
    if ($fechaCierre instanceof DateTime) {
        $fechaCierre = $fechaCierre->format('Y-m-d H:i:s');
    }
    
    $timestamp = strtotime($fechaCierre);
    if ($timestamp === false) return '';
    
    $archivadoEn = $timestamp + (24 * 3600);
    $ahora = time();
    $diferencia = $archivadoEn - $ahora;
    
    if ($diferencia <= 0) {
        return 'se archivará pronto';
    }
    
    $horas = floor($diferencia / 3600);
    $minutos = floor(($diferencia % 3600) / 60);
    
    if ($horas > 0) {
        return "{$horas}h {$minutos}min";
    } else {
        return "{$minutos} minutos";
    }
}

// ============================================
// FUNCIONES DE INVENTARIO Y EQUIPOS
// ============================================

/**
 * Generar folio automático para solicitud
 * Formato: SOL-202501-0001
 */
function generarFolioSolicitud() {
    $result = obtenerUno("SELECT dbo.fn_generar_folio_solicitud() as folio");
    return $result['folio'];
}

/**
 * Generar folio automático para carta responsiva
 * Formato: CR-2025-00001
 */
function generarFolioCarta() {
    $result = obtenerUno("SELECT dbo.fn_generar_folio_carta() as folio");
    return $result['folio'];
}

/**
 * Badge HTML para estado de solicitud
 */
function badgeEstadoSolicitud($estado) {
    $badges = [
        'pendiente' => '<span class="badge bg-secondary">Pendiente Jefe</span>',
        'pendiente_gerente' => '<span class="badge bg-warning text-dark">⏳ Pendiente Aprobación</span>',
        'aprobada' => '<span class="badge" style="background-color: #0052CC; color: white;">✅ Aprobada</span>',
        'rechazada' => '<span class="badge bg-danger">❌ Rechazada</span>',
        'completada' => '<span class="badge bg-success">✅ Completada</span>',
        'asignada' => '<span class="badge bg-info">📦 Asignada</span>'
    ];
    return $badges[$estado] ?? '<span class="badge bg-secondary">' . e($estado) . '</span>';
}

/**
 * Badge HTML para disponibilidad de equipo
 */
function badgeDisponibilidad($disponible) {
    return $disponible 
        ? '<span class="badge bg-success">✅ Disponible</span>'
        : '<span class="badge bg-secondary">❌ No Disponible</span>';
}

/**
 * Badge HTML para estado físico del equipo
 */
function badgeEstadoEquipo($estado) {
    $badges = [
        'nuevo' => '<span class="badge bg-success">🆕 Nuevo</span>',
        'bueno' => '<span class="badge bg-primary">👍 Bueno</span>',
        'regular' => '<span class="badge bg-warning">⚠️ Regular</span>',
        'malo' => '<span class="badge bg-danger">❌ Malo</span>',
        'para_reparacion' => '<span class="badge bg-info">🔧 Para Reparación</span>',
        'dado_de_baja' => '<span class="badge bg-dark">🗑️ Dado de Baja</span>'
    ];
    return $badges[$estado] ?? '<span class="badge bg-secondary">' . e($estado) . '</span>';
}

/**
 * Obtener colaboradores de un jefe
 */
function obtenerColaboradoresDeJefe($jefeId) {
    $sql = "
        SELECT 
            u.id,
            u.nombre + ' ' + u.apellido as nombre_completo,
            u.email,
            u.dni,
            u.telefono,
            u.puesto,
            u.sede_id,
            s.nombre as sede_nombre
        FROM users u
        LEFT JOIN sedes s ON u.sede_id = s.id
        WHERE u.jefe_id = ?
          AND u.activo = 1
        ORDER BY u.nombre, u.apellido
    ";
    
    return obtenerTodos($sql, [$jefeId]);
}

/**
 * Obtener equipos disponibles por tipo
 */
function obtenerEquiposDisponiblesPorTipo($tipo = null) {
    if ($tipo) {
        $sql = "
            SELECT * 
            FROM equipos 
            WHERE disponible = 1 
              AND tipo = ?
              AND estado NOT IN ('dado_de_baja')
            ORDER BY tipo, marca, modelo
        ";
        return obtenerTodos($sql, [$tipo]);
    } else {
        $sql = "
            SELECT * 
            FROM equipos 
            WHERE disponible = 1
              AND estado NOT IN ('dado_de_baja')
            ORDER BY tipo, marca, modelo
        ";
        return obtenerTodos($sql);
    }
}

/**
 * Obtener equipos asignados a un usuario
 */
function obtenerEquiposAsignadosUsuario($usuarioId) {
    $sql = "
        SELECT 
            e.*,
            a.id as asignacion_id,
            a.fecha_asignacion,
            a.folio_carta,
            a.ruta_docx,
            a.ruta_pdf,
            a.firmado,
            a.fecha_firma,
            asignador.nombre + ' ' + asignador.apellido as asignado_por_nombre
        FROM asignaciones_equipos a
        INNER JOIN equipos e ON a.equipo_id = e.id
        LEFT JOIN users asignador ON a.asignado_por = asignador.id
        WHERE a.usuario_id = ?
          AND a.estado = 'asignado'
        ORDER BY a.fecha_asignacion DESC
    ";
    return obtenerTodos($sql, [$usuarioId]);
}

/**
 * Contar solicitudes pendientes de aprobación (para gerente)
 */
function contarSolicitudesPendientes() {
    $sql = "SELECT COUNT(*) as total FROM solicitudes_equipos WHERE estado = 'pendiente_gerente'";
    $result = obtenerUno($sql);
    return $result['total'] ?? 0;
}

/**
 * Contar solicitudes aprobadas pendientes de asignación (para TI)
 */
function contarSolicitudesAprobadas() {
    $sql = "SELECT COUNT(*) as total FROM solicitudes_equipos WHERE estado = 'aprobada'";
    $result = obtenerUno($sql);
    return $result['total'] ?? 0;
}

/**
 * Verificar si un usuario es jefe
 */
function esJefe($userId = null) {
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? 0;
    }
    
    $sql = "SELECT es_jefe FROM users WHERE id = ?";
    $result = obtenerUno($sql, [$userId]);
    return ($result && $result['es_jefe'] == 1);
}

/**
 * Obtener información completa de un usuario (incluyendo sede y área)
 */
function obtenerInfoUsuarioCompleta($userId) {
    $sql = "
        SELECT 
            u.*,
            s.nombre as sede_nombre,
            a.nombre as area_nombre,
            jefe.nombre + ' ' + jefe.apellido as jefe_nombre
        FROM users u
        LEFT JOIN sedes s ON u.sede_id = s.id
        LEFT JOIN areas a ON u.area_id = a.id
        LEFT JOIN users jefe ON u.jefe_id = jefe.id
        WHERE u.id = ?
    ";
    return obtenerUno($sql, [$userId]);
}

/**
 * Formatear nombre completo de usuario
 */
function nombreCompletoUsuario($usuario) {
    if (is_array($usuario)) {
        return trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
    }
    return '';
}

/**
 * Obtener estadísticas de inventario
 */
function obtenerEstadisticasInventario() {
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN disponible = 1 THEN 1 ELSE 0 END) as disponibles,
            SUM(CASE WHEN disponible = 0 THEN 1 ELSE 0 END) as asignados,
            SUM(CASE WHEN estado = 'nuevo' THEN 1 ELSE 0 END) as nuevos,
            SUM(CASE WHEN estado = 'bueno' THEN 1 ELSE 0 END) as buenos,
            SUM(CASE WHEN estado = 'regular' THEN 1 ELSE 0 END) as regulares,
            SUM(CASE WHEN estado = 'malo' THEN 1 ELSE 0 END) as malos,
            SUM(CASE WHEN estado = 'para_reparacion' THEN 1 ELSE 0 END) as para_reparacion,
            SUM(CASE WHEN estado = 'dado_de_baja' THEN 1 ELSE 0 END) as dados_baja
        FROM equipos
    ";
    return obtenerUno($sql);
}

/**
 * Marcar equipo como no disponible (cuando se asigna)
 */
function marcarEquipoNoDisponible($equipoId) {
    $sql = "UPDATE equipos SET disponible = 0, updated_at = GETDATE() WHERE id = ?";
    ejecutarQuery($sql, [$equipoId]);
}

/**
 * Marcar equipo como disponible (cuando se devuelve)
 */
function marcarEquipoDisponible($equipoId) {
    $sql = "UPDATE equipos SET disponible = 1, updated_at = GETDATE() WHERE id = ?";
    ejecutarQuery($sql, [$equipoId]);
}

/**
 * Verificar si un equipo está disponible
 */
function equipoEstaDisponible($equipoId) {
    $sql = "SELECT disponible FROM equipos WHERE id = ?";
    $result = obtenerUno($sql, [$equipoId]);
    return ($result && $result['disponible'] == 1);
}

/**
 * Obtener carta responsiva de un usuario (la más reciente)
 */
function obtenerCartaResponsivaUsuario($usuarioId) {
    $sql = "
        SELECT TOP 1
            a.*,
            e.tipo as equipo_tipo,
            e.marca as equipo_marca,
            e.modelo as equipo_modelo
        FROM asignaciones_equipos a
        LEFT JOIN equipos e ON a.equipo_id = e.id
        WHERE a.usuario_id = ?
          AND a.estado = 'asignado'
        ORDER BY a.fecha_asignacion DESC
    ";
    return obtenerUno($sql, [$usuarioId]);
}

/**
 * Formatear fechas que vienen de SQL Server (pueden ser DateTime object o string)
 */
function formatearFechaSQL($fecha) {
    if (!$fecha) {
        return '';
    }
    
    // Si es un objeto DateTime
    if ($fecha instanceof DateTime) {
        return $fecha->format('d/m/Y');
    }
    
    // Si es string
    if (is_string($fecha)) {
        return date('d/m/Y', strtotime($fecha));
    }
    
    return '';
}

/**
 * Firmar carta responsiva (aceptar compromiso)
 */
function firmarCartaResponsiva($folioCarta, $usuarioId) {
    $sql = "
        UPDATE asignaciones_equipos
        SET firmado = 1,
            fecha_firma = GETDATE(),
            updated_at = GETDATE()
        WHERE folio_carta = ?
          AND usuario_id = ?
          AND firmado = 0
    ";
    ejecutarQuery($sql, [$folioCarta, $usuarioId]);
}

/**
 * Contar solicitudes pendientes para Gerente General
 */
function contarSolicitudesPendientesGeneral() {
    $sql = "
        SELECT COUNT(*) as total 
        FROM solicitudes_equipos 
        WHERE estado = 'pendiente_gerente_general'
    ";
    
    $resultado = obtenerUno($sql);
    return $resultado['total'] ?? 0;
}

?>