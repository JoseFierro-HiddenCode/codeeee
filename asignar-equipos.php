<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

// Función para log personalizado
function logDebug($mensaje) {
    $archivo = $_SERVER['DOCUMENT_ROOT'] . '/sistema-tickets/debug_cartas.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($archivo, "[$timestamp] $mensaje\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php');
    exit();
}

// Solo Admin y TI pueden asignar
protegerPagina(['admin_tecnico', 'tecnico']);

// Obtener datos del formulario
$solicitud_id = $_POST['solicitud_id'] ?? 0;
$usuario_id = $_POST['usuario_id'] ?? 0;
$equipos_ids = $_POST['equipos'] ?? [];
$notas_asignacion = $_POST['notas_asignacion'] ?? null;

logDebug("=== INICIO PROCESO ASIGNACIÓN ===");
logDebug("POST recibido - Solicitud ID: $solicitud_id, Usuario ID: $usuario_id");
logDebug("Equipos seleccionados: " . json_encode($equipos_ids));

// Validaciones
if (empty($solicitud_id) || empty($usuario_id) || empty($equipos_ids)) {
    logDebug("ERROR: Datos incompletos");
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php?error=' . urlencode('Datos incompletos'));
    exit();
}

// Verificar que la solicitud exista y esté aprobada
$sqlVerificar = "SELECT * FROM solicitudes_equipos WHERE id = ? AND estado = 'aprobada'";
$solicitud = obtenerUno($sqlVerificar, [$solicitud_id]);

if (!$solicitud) {
    logDebug("ERROR: Solicitud no encontrada o no aprobada");
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php?error=' . urlencode('Solicitud no encontrada o no está aprobada'));
    exit();
}

logDebug("✅ Solicitud verificada: " . $solicitud['folio']);

// Verificar que todos los equipos existan y estén disponibles
foreach ($equipos_ids as $equipo_id) {
    if (!equipoEstaDisponible($equipo_id)) {
        logDebug("ERROR: Equipo $equipo_id no disponible");
        header('Location: ../../views/inventario/asignar-equipos-fisicos.php?error=' . urlencode('Uno o más equipos no están disponibles'));
        exit();
    }
}

logDebug("✅ Todos los equipos están disponibles");

try {
    global $conn;

    // Generar folio de carta responsiva
    $folioCarta = generarFolioCarta();
    logDebug("✅ Folio generado: $folioCarta");

    // Iniciar transacción
    sqlsrv_begin_transaction($conn);
    logDebug("Transacción iniciada");

    // Asignar cada equipo
    foreach ($equipos_ids as $equipo_id) {
        // Insertar asignación
        $sqlAsignar = "
            INSERT INTO asignaciones_equipos (
                solicitud_id, equipo_id, usuario_id, asignado_por,
                folio_carta, fecha_asignacion, estado, firmado,
                notas_asignacion, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, GETDATE(), 'asignado', 0, ?, GETDATE(), GETDATE())
        ";

        ejecutarQuery($sqlAsignar, [
            $solicitud_id,
            $equipo_id,
            $usuario_id,
            $_SESSION['user_id'],
            $folioCarta,
            $notas_asignacion
        ]);

        logDebug("✅ Asignación creada para equipo ID: $equipo_id");

        // Marcar equipo como no disponible
        marcarEquipoNoDisponible($equipo_id);
        logDebug("✅ Equipo $equipo_id marcado como no disponible");
    }

    // Actualizar estado de solicitud a 'asignada'
    $sqlUpdateSolicitud = "
        UPDATE solicitudes_equipos
        SET estado = 'asignada',
            updated_at = GETDATE()
        WHERE id = ?
    ";
    ejecutarQuery($sqlUpdateSolicitud, [$solicitud_id]);
    logDebug("✅ Solicitud marcada como asignada");

    // Commit transacción
    sqlsrv_commit($conn);
    logDebug("✅ Transacción confirmada");

    // GENERAR CARTA RESPONSIVA USANDO PLANTILLA DOCX
    logDebug("Iniciando generación de carta...");
    $rutaDocx = generarCartaResponsiva($folioCarta, $usuario_id, $equipos_ids, $solicitud);

    // Actualizar rutas de documentos en asignaciones
    if ($rutaDocx) {
        $sqlUpdateRutas = "
            UPDATE asignaciones_equipos
            SET ruta_docx = ?,
                updated_at = GETDATE()
            WHERE folio_carta = ?
        ";
        ejecutarQuery($sqlUpdateRutas, [$rutaDocx, $folioCarta]);
        logDebug("✅ Ruta DOCX actualizada en BD: $rutaDocx");
    } else {
        logDebug("⚠️ No se generó la carta DOCX");
    }

    logDebug("=== PROCESO COMPLETADO EXITOSAMENTE ===");

    // Redirigir con éxito
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php?success=1&folio=' . urlencode($folioCarta));
    exit();

} catch (Exception $e) {
    // Rollback en caso de error
    if (isset($conn)) {
        sqlsrv_rollback($conn);
    }

    logDebug("EXCEPCIÓN EN PROCESO: " . $e->getMessage());
    logDebug("Trace: " . $e->getTraceAsString());
    
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php?error=' . urlencode('Error al asignar los equipos: ' . $e->getMessage()));
    exit();
}

/**
 * Generar Carta Responsiva usando plantilla DOCX
 */
/**
 * Generar Carta Responsiva usando plantilla DOCX con tabla dinámica
 */
/**
 * Generar Carta Responsiva usando plantilla DOCX con 2 filas fijas
 */
function generarCartaResponsiva($folioCarta, $usuarioId, $equiposIds, $solicitud) {
    
    logDebug("=== INICIO GENERACIÓN CARTA ===");
    logDebug("Folio: " . $folioCarta);
    logDebug("Usuario ID: " . $usuarioId);
    logDebug("Equipos IDs: " . json_encode($equiposIds));
    
    // Obtener datos del usuario
    $sqlUsuario = "
        SELECT
            u.*,
            s.nombre as sede_nombre,
            a.nombre as area_nombre
        FROM users u
        LEFT JOIN sedes s ON u.sede_id = s.id
        LEFT JOIN areas a ON u.area_id = a.id
        WHERE u.id = ?
    ";
    $usuario = obtenerUno($sqlUsuario, [$usuarioId]);

    if (!$usuario) {
        logDebug("ERROR: Usuario no encontrado");
        return false;
    }
    logDebug("✅ Usuario encontrado: " . $usuario['nombre'] . ' ' . $usuario['apellido']);

    // Obtener equipos asignados
    $equipos = [];
    foreach ($equiposIds as $equipoId) {
        $sqlEquipo = "SELECT * FROM equipos WHERE id = ?";
        $equipo = obtenerUno($sqlEquipo, [$equipoId]);
        if ($equipo) {
            $equipos[] = $equipo;
        }
    }

    if (empty($equipos)) {
        logDebug("ERROR: No se encontraron equipos");
        return false;
    }
    logDebug("✅ Equipos encontrados: " . count($equipos));

    // Obtener datos del gerente que aprobó
    $gerenteId = $solicitud['aprobado_por_gerente_general'] ?? $solicitud['aprobado_por'];
    $gerente = obtenerUno("SELECT nombre + ' ' + apellido as nombre_completo FROM users WHERE id = ?", [$gerenteId]);
    logDebug("✅ Gerente: " . ($gerente['nombre_completo'] ?? 'N/A'));

    // Obtener datos del técnico que asigna
    $tecnico = obtenerUno("SELECT nombre + ' ' + apellido as nombre_completo FROM users WHERE id = ?", [$_SESSION['user_id']]);
    logDebug("✅ Técnico: " . ($tecnico['nombre_completo'] ?? 'N/A'));

    // Ruta de la plantilla
    $plantillaPath = $_SERVER['DOCUMENT_ROOT'] . '/sistema-tickets/documents/templates/carta_responsiva_template.docx';
    logDebug("Ruta plantilla: " . $plantillaPath);
    logDebug("¿Existe plantilla?: " . (file_exists($plantillaPath) ? 'SÍ' : 'NO'));

    if (!file_exists($plantillaPath)) {
        logDebug("ERROR: No se encontró la plantilla");
        return false;
    }

    try {
        logDebug("Intentando cargar plantilla...");
        
        // Cargar plantilla
        $templateProcessor = new TemplateProcessor($plantillaPath);
        logDebug("✅ Plantilla cargada exitosamente");
        
        // Datos del colaborador
        $nombreCompleto = $usuario['nombre'] . ' ' . $usuario['apellido'];
        $dni = $usuario['dni'] ?? 'N/A';
        
        $templateProcessor->setValue('NOMBRE_COMPLETO', $nombreCompleto);
        $templateProcessor->setValue('DNI', $dni);
        $templateProcessor->setValue('PUESTO', $usuario['puesto'] ?? 'Sin especificar');
        logDebug("✅ Variables de usuario reemplazadas");
        
        // Llenar las 2 filas de equipos (celular y laptop)
        for ($i = 1; $i <= 2; $i++) {
            if (isset($equipos[$i - 1])) {
                // Hay equipo para esta fila
                $eq = $equipos[$i - 1];
                $templateProcessor->setValue('marca' . $i, $eq['marca'] ?? '-');
                $templateProcessor->setValue('modelo' . $i, $eq['modelo'] ?? '-');
                $templateProcessor->setValue('serie' . $i, $eq['numero_serie'] ?? '-');
                $templateProcessor->setValue('procesador' . $i, $eq['procesador'] ?? '-');
                $templateProcessor->setValue('so' . $i, $eq['sistema_operativo'] ?? '-');
                $templateProcessor->setValue('color' . $i, $eq['color'] ?? '-');
                $templateProcessor->setValue('tipo' . $i, strtoupper($eq['tipo']));
                $templateProcessor->setValue('estado' . $i, ucfirst($eq['estado']));
                logDebug("✅ Fila $i llenada: " . $eq['marca'] . ' ' . $eq['modelo']);
            } else {
                // No hay más equipos, dejar vacío
                $templateProcessor->setValue('marca' . $i, '-');
                $templateProcessor->setValue('modelo' . $i, '-');
                $templateProcessor->setValue('serie' . $i, '-');
                $templateProcessor->setValue('procesador' . $i, '-');
                $templateProcessor->setValue('so' . $i, '-');
                $templateProcessor->setValue('color' . $i, '-');
                $templateProcessor->setValue('tipo' . $i, '-');
                $templateProcessor->setValue('estado' . $i, '-');
                logDebug("✅ Fila $i vacía");
            }
        }
        
        // Accesorios adicionales (equipos a partir del 3ro - cargadores, mouse, etc)
        if (count($equipos) > 2) {
            $accesorios = [];
            for ($i = 2; $i < count($equipos); $i++) {
                $eq = $equipos[$i];
                $accesorios[] = strtoupper($eq['tipo']) . ' - ' . ($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? '') . ' (S/N: ' . ($eq['numero_serie'] ?? '') . ')';
            }
            $templateProcessor->setValue('LISTA_ACCESORIOS', implode("\n", $accesorios));
            logDebug("✅ Accesorios adicionales: " . count($accesorios));
        } else {
            $templateProcessor->setValue('LISTA_ACCESORIOS', 'Ninguno');
        }
        
        logDebug("✅ Variables de equipos reemplazadas (total: " . count($equipos) . ")");
        
        // Firmas
        $templateProcessor->setValue('FOLIO', $folioCarta);
        $templateProcessor->setValue('APROBADO_POR', $gerente['nombre_completo'] ?? 'Gerencia General');
        
        // Formatear fechas
        $fechaAprobacion = $solicitud['fecha_aprobacion_gerente_general'] ?? $solicitud['fecha_aprobacion'];
        $templateProcessor->setValue('FECHA_APROBACION', formatearFechaSQL($fechaAprobacion));
        
        $templateProcessor->setValue('ASIGNADO_POR', $tecnico['nombre_completo'] ?? $_SESSION['nombre'] . ' ' . $_SESSION['apellido']);
        $templateProcessor->setValue('FECHA_ASIGNACION', date('d/m/Y'));
        logDebug("✅ Variables de firmas reemplazadas");
        
        // Crear carpeta si no existe
        $rutaCarpeta = $_SERVER['DOCUMENT_ROOT'] . '/sistema-tickets/documents/cartas/';
        logDebug("Ruta carpeta: " . $rutaCarpeta);
        
        if (!file_exists($rutaCarpeta)) {
            logDebug("Carpeta no existe, intentando crear...");
            if (!mkdir($rutaCarpeta, 0777, true)) {
                logDebug("ERROR: No se pudo crear la carpeta");
                return false;
            }
            logDebug("✅ Carpeta creada exitosamente");
        } else {
            logDebug("✅ Carpeta ya existe");
        }
        
        logDebug("¿Es escribible la carpeta?: " . (is_writable($rutaCarpeta) ? 'SÍ' : 'NO'));
        
        // Nombre del archivo generado
        $nombreArchivo = "Carta_Responsiva_{$folioCarta}.docx";
        $rutaCompleta = $rutaCarpeta . $nombreArchivo;
        logDebug("Ruta completa archivo: " . $rutaCompleta);
        
        // Guardar el documento
        logDebug("Intentando guardar archivo...");
        $templateProcessor->saveAs($rutaCompleta);
        logDebug("✅ saveAs() ejecutado sin error");
        
        // Verificar que el archivo se haya creado
        if (!file_exists($rutaCompleta)) {
            logDebug("ERROR: El archivo NO se creó después de saveAs()");
            return false;
        }
        
        $tamano = filesize($rutaCompleta);
        logDebug("✅ Archivo creado exitosamente - Tamaño: " . $tamano . " bytes");
        
        // Retornar ruta relativa
        $rutaRelativa = "documents/cartas/{$nombreArchivo}";
        logDebug("Retornando ruta relativa: " . $rutaRelativa);
        logDebug("=== FIN GENERACIÓN CARTA ===");
        
        return $rutaRelativa;
        
    } catch (Exception $e) {
        logDebug("EXCEPCIÓN: " . $e->getMessage());
        logDebug("Trace: " . $e->getTraceAsString());
        return false;
    }
}
?>