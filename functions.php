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
function obtenerIniciales($nombre, $apellido) {
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
?>