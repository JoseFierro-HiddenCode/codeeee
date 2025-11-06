<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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

// Validaciones
if (empty($solicitud_id) || empty($usuario_id) || empty($equipos_ids)) {
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php?error=' . urlencode('Datos incompletos'));
    exit();
}

// Verificar que la solicitud exista y esté aprobada
$sqlVerificar = "SELECT * FROM solicitudes_equipos WHERE id = ? AND estado = 'aprobada'";
$solicitud = obtenerUno($sqlVerificar, [$solicitud_id]);

if (!$solicitud) {
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php?error=' . urlencode('Solicitud no encontrada o no está aprobada'));
    exit();
}

// Verificar que todos los equipos existan y estén disponibles
foreach ($equipos_ids as $equipo_id) {
    if (!equipoEstaDisponible($equipo_id)) {
        header('Location: ../../views/inventario/asignar-equipos-fisicos.php?error=' . urlencode('Uno o más equipos no están disponibles'));
        exit();
    }
}

try {
    global $conn;
    
    // Generar folio de carta responsiva
    $folioCarta = generarFolioCarta();
    
    // Iniciar transacción
    sqlsrv_begin_transaction($conn);
    
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
        
        // Marcar equipo como no disponible
        marcarEquipoNoDisponible($equipo_id);
    }
    
    // Actualizar estado de solicitud a 'asignada'
    $sqlUpdateSolicitud = "
        UPDATE solicitudes_equipos 
        SET estado = 'asignada',
            updated_at = GETDATE()
        WHERE id = ?
    ";
    ejecutarQuery($sqlUpdateSolicitud, [$solicitud_id]);
    
    // Commit transacción
    sqlsrv_commit($conn);
    
    // GENERAR CARTA RESPONSIVA (DOCX)
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
    }
    
    // Redirigir con éxito
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php?success=1&folio=' . urlencode($folioCarta));
    exit();
    
} catch (Exception $e) {
    // Rollback en caso de error
    if (isset($conn)) {
        sqlsrv_rollback($conn);
    }
    
    error_log("Error al asignar equipos: " . $e->getMessage());
    header('Location: ../../views/inventario/asignar-equipos-fisicos.php?error=' . urlencode('Error al asignar los equipos'));
    exit();
}

/**
 * Generar Carta Responsiva en formato DOCX usando PHPWord
 */
function generarCartaResponsiva($folioCarta, $usuarioId, $equiposIds, $solicitud) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
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
        error_log("Error: Usuario no encontrado para generar carta responsiva");
        return false;
    }
    
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
        error_log("Error: No se encontraron equipos para la carta responsiva");
        return false;
    }
    
    // Obtener datos del gerente que aprobó
    $gerente = obtenerUno("SELECT nombre + ' ' + apellido as nombre_completo FROM users WHERE id = ?", [$solicitud['aprobado_por']]);
    
    // Obtener datos del técnico que asigna
    $tecnico = obtenerUno("SELECT nombre + ' ' + apellido as nombre_completo FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Crear PHPWord
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    
    // Configurar documento
    $section = $phpWord->addSection();
    
    // Título
    $section->addText(
        'COMPROMISO DE USO Y CUIDADO DE EQUIPO TECNOLÓGICO',
        ['bold' => true, 'size' => 14],
        ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
    );
    $section->addTextBreak(1);
    
    // Párrafo introductorio
    $nombreCompleto = $usuario['nombre'] . ' ' . $usuario['apellido'];
    $dni = $usuario['dni'] ?? 'N/A';
    
    $section->addText(
        "Yo, {$nombreCompleto} con DNI N.º {$dni}, me comprometo a hacer uso responsable de los equipos tecnológicos entregados por Automotriz Central del Perú S.A.C., exclusivamente para fines laborales. Asimismo, me comprometo a velar por el buen uso del equipo en el área correspondiente, con las siguientes características:",
        ['size' => 11]
    );
    $section->addTextBreak(1);
    
    // Tabla de equipos
    $tableStyle = [
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 80,
        'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
        'width' => 100 * 50
    ];
    
    $phpWord->addTableStyle('EquiposTable', $tableStyle);
    $table = $section->addTable('EquiposTable');
    
    // Encabezados de tabla
    $table->addRow();
    $table->addCell(1500)->addText('TIPO', ['bold' => true, 'size' => 9]);
    $table->addCell(2000)->addText('MARCA', ['bold' => true, 'size' => 9]);
    $table->addCell(2000)->addText('MODELO', ['bold' => true, 'size' => 9]);
    $table->addCell(2000)->addText('SERIE', ['bold' => true, 'size' => 9]);
    $table->addCell(2000)->addText('PROCES.', ['bold' => true, 'size' => 9]);
    $table->addCell(2000)->addText('S.O.', ['bold' => true, 'size' => 9]);
    $table->addCell(1500)->addText('COLOR', ['bold' => true, 'size' => 9]);
    $table->addCell(1500)->addText('ESTADO', ['bold' => true, 'size' => 9]);
    
    // Filas de equipos (dinámico)
    foreach ($equipos as $equipo) {
        $table->addRow();
        $table->addCell(1500)->addText(ucfirst($equipo['tipo']), ['size' => 9]);
        $table->addCell(2000)->addText($equipo['marca'] ?? '', ['size' => 9]);
        $table->addCell(2000)->addText($equipo['modelo'] ?? '', ['size' => 9]);
        $table->addCell(2000)->addText($equipo['numero_serie'] ?? '', ['size' => 9]);
        $table->addCell(2000)->addText($equipo['procesador'] ?? '', ['size' => 9]);
        $table->addCell(2000)->addText($equipo['sistema_operativo'] ?? '', ['size' => 9]);
        $table->addCell(1500)->addText($equipo['color'] ?? '', ['size' => 9]);
        $table->addCell(1500)->addText(ucfirst($equipo['estado']), ['size' => 9]);
    }
    
    $section->addTextBreak(1);
    
    // Accesorios adicionales (lista de equipos)
    $listaAccesorios = [];
    foreach ($equipos as $equipo) {
        $listaAccesorios[] = ucfirst($equipo['tipo']) . ' ' . ($equipo['marca'] ?? '') . ' ' . ($equipo['modelo'] ?? '');
    }
    $section->addText('ACCESORIOS ADICIONALES:', ['bold' => true, 'size' => 11]);
    $section->addText(implode(', ', $listaAccesorios), ['size' => 10]);
    $section->addTextBreak(1);
    
    // Responsabilidades
    $section->addText('Ante una eventual pérdida del equipo, el colaborador se compromete a:', ['bold' => true, 'size' => 11]);
    $section->addListItem('Reportar inmediatamente a la Gerencia de Administración y Finanzas.', 0, ['size' => 10]);
    $section->addListItem('Asumir el costo total de reposición de un equipo nuevo de similares características.', 0, ['size' => 10]);
    $section->addTextBreak(1);
    
    $section->addText('En caso de robo, mi responsabilidad:', ['bold' => true, 'size' => 11]);
    $section->addListItem('Presentar la denuncia policial de robo y evidencias.', 0, ['size' => 10]);
    $section->addTextBreak(1);
    
    $section->addText(
        'En señal de conformidad y aceptación firmo el presente documento.',
        ['size' => 10, 'italic' => true]
    );
    $section->addTextBreak(1);
    
    $section->addText(
        'NOTA: El uso del equipo y del acceso a Internet es exclusivo para actividades laborales. En caso de incumplimiento, me someto a las disposiciones disciplinarias conforme a la política de confidencialidad de la empresa.',
        ['size' => 9, 'bold' => true]
    );
    $section->addTextBreak(2);
    
    // Firmas
    $section->addText("Folio: {$folioCarta}", ['bold' => true, 'size' => 10]);
    $section->addTextBreak(1);
    
    $section->addText('Aprobado por:', ['size' => 10]);
    $section->addText(($gerente['nombre_completo'] ?? 'N/A') . ' - Fecha: ' . date('d/m/Y', strtotime($solicitud['fecha_aprobacion'])), ['size' => 9]);
    $section->addTextBreak(1);
    
    $section->addText('Asignado por:', ['size' => 10]);
    $section->addText(($tecnico['nombre_completo'] ?? 'N/A') . ' - Fecha: ' . date('d/m/Y'), ['size' => 9]);
    $section->addTextBreak(2);
    
    $section->addText('Firma:______________________________', ['size' => 10]);
    $section->addText("DNI N.º: {$dni}", ['size' => 10]);
    $section->addText("Nombre: {$nombreCompleto}", ['size' => 10]);
    $section->addText("Puesto de Trabajo: " . ($usuario['puesto'] ?? 'N/A'), ['size' => 10]);
    
    // Guardar documento
    $rutaCarpeta = __DIR__ . '/../../documents/asignaciones/';
    if (!file_exists($rutaCarpeta)) {
        if (!mkdir($rutaCarpeta, 0777, true)) {
            error_log("Error: No se pudo crear la carpeta de asignaciones");
            return false;
        }
    }
    
    // Validar que la carpeta sea escribible
    if (!is_writable($rutaCarpeta)) {
        error_log("Error: La carpeta de asignaciones no tiene permisos de escritura");
        return false;
    }
    
    $nombreArchivo = "carta-responsiva-{$folioCarta}.docx";
    $rutaCompleta = $rutaCarpeta . $nombreArchivo;
    
    try {
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($rutaCompleta);
    } catch (Exception $e) {
        error_log("Error al guardar DOCX: " . $e->getMessage());
        return false;
    }
    
    // Verificar que el archivo se haya creado
    if (!file_exists($rutaCompleta)) {
        error_log("Error: El archivo DOCX no se generó correctamente");
        return false;
    }
    
    // Retornar ruta relativa
    return "documents/asignaciones/{$nombreArchivo}";
}
?>