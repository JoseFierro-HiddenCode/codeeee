<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/inventario/aprobar-solicitudes-general.php');
    exit();
}

protegerPagina(['gerente_general']);

$solicitud_id = $_POST['solicitud_id'] ?? 0;
$accion = $_POST['accion'] ?? '';
$comentario = $_POST['comentario'] ?? '';

// Validaciones
if (empty($solicitud_id) || !in_array($accion, ['aprobar', 'rechazar'])) {
    header('Location: ../../views/inventario/aprobar-solicitudes-general.php?error=' . urlencode('Datos inválidos'));
    exit();
}

if ($accion === 'rechazar' && empty($comentario)) {
    header('Location: ../../views/inventario/aprobar-solicitudes-general.php?error=' . urlencode('Debe indicar el motivo del rechazo'));
    exit();
}

try {
    // Obtener solicitud
    $sqlSolicitud = "
        SELECT s.*, 
               colaborador.nombre + ' ' + colaborador.apellido as colaborador_nombre,
               colaborador.dni as colaborador_dni,
               colaborador.puesto as colaborador_puesto,
               jefe.nombre + ' ' + jefe.apellido as jefe_nombre,
               gerente.nombre + ' ' + gerente.apellido as gerente_nombre,
               sede.nombre as sede_nombre
        FROM solicitudes_equipos s
        INNER JOIN users colaborador ON s.colaborador_id = colaborador.id
        INNER JOIN users jefe ON s.solicitante_id = jefe.id
        LEFT JOIN users gerente ON s.aprobado_por = gerente.id
        LEFT JOIN sedes sede ON colaborador.sede_id = sede.id
        WHERE s.id = ? AND s.estado = 'pendiente_gerente_general'
    ";
    $solicitud = obtenerUno($sqlSolicitud, [$solicitud_id]);

    if (!$solicitud) {
        header('Location: ../../views/inventario/aprobar-solicitudes-general.php?error=' . urlencode('Solicitud no encontrada o ya procesada'));
        exit();
    }

    if ($accion === 'aprobar') {
        // APROBAR: Actualizar estado y registrar aprobación
        $sqlUpdate = "
            UPDATE solicitudes_equipos 
            SET estado = 'aprobada',
                aprobado_por_general = ?,
                fecha_aprobacion_general = GETDATE(),
                comentario_general = ?,
                updated_at = GETDATE()
            WHERE id = ?
        ";
        ejecutarQuery($sqlUpdate, [$_SESSION['user_id'], $comentario, $solicitud_id]);

        // REGENERAR EXCEL con la firma del Gerente General
        $solicitudCompleta = obtenerDatosCompletosSolicitud($solicitud_id);
        $rutaExcel = generarExcelDesdePlantilla($solicitudCompleta);

        // Actualizar ruta del Excel
        ejecutarQuery("UPDATE solicitudes_equipos SET ruta_excel = ? WHERE id = ?", [$rutaExcel, $solicitud_id]);

        header('Location: ../../views/inventario/aprobar-solicitudes-general.php?success=aprobada&folio=' . urlencode($solicitud['folio']));
        exit();

    } else {
        // RECHAZAR
        $sqlUpdate = "
            UPDATE solicitudes_equipos 
            SET estado = 'rechazada',
                aprobado_por_general = ?,
                fecha_aprobacion_general = GETDATE(),
                comentario_general = ?,
                updated_at = GETDATE()
            WHERE id = ?
        ";
        ejecutarQuery($sqlUpdate, [$_SESSION['user_id'], $comentario, $solicitud_id]);

        header('Location: ../../views/inventario/aprobar-solicitudes-general.php?success=rechazada&folio=' . urlencode($solicitud['folio']));
        exit();
    }

} catch (Exception $e) {
    error_log("Error al procesar aprobación general: " . $e->getMessage());
    header('Location: ../../views/inventario/aprobar-solicitudes-general.php?error=' . urlencode('Error al procesar la solicitud: ' . $e->getMessage()));
    exit();
}

/**
 * Obtener datos completos de la solicitud para regenerar el Excel
 */
function obtenerDatosCompletosSolicitud($solicitud_id) {
    $sql = "
        SELECT
            s.*,
            colaborador.nombre + ' ' + colaborador.apellido as colaborador_nombre,
            colaborador.dni as colaborador_dni,
            colaborador.puesto as colaborador_puesto,
            colaborador.telefono as colaborador_telefono,
            jefe.nombre + ' ' + jefe.apellido as jefe_nombre,
            jefe.puesto as jefe_puesto,
            gerente.nombre + ' ' + gerente.apellido as gerente_nombre,
            gerente.puesto as gerente_puesto,
            general.nombre + ' ' + general.apellido as gerente_general_nombre,
            general.puesto as gerente_general_puesto,
            sede.nombre as sede_nombre
        FROM solicitudes_equipos s
        INNER JOIN users colaborador ON s.colaborador_id = colaborador.id
        INNER JOIN users jefe ON s.solicitante_id = jefe.id
        LEFT JOIN users gerente ON s.aprobado_por = gerente.id
        LEFT JOIN users general ON s.aprobado_por_general = general.id
        LEFT JOIN sedes sede ON colaborador.sede_id = sede.id
        WHERE s.id = ?
    ";
    return obtenerUno($sql, [$solicitud_id]);
}

/*
 * Formatear fechas que vienen de SQL Server (pueden ser DateTime object o string)
 
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
 * Genera Excel desde plantilla reemplazando variables y aplicando negrita a las X
 */
function generarExcelDesdePlantilla($sol) {
    // Cargar plantilla
    $rutaPlantilla = '../../documents/templates/plantilla_solicitud_equipos.xlsx';
    
    if (!file_exists($rutaPlantilla)) {
        throw new Exception("No se encuentra la plantilla en: $rutaPlantilla");
    }
    
    $spreadsheet = IOFactory::load($rutaPlantilla);
    $sheet = $spreadsheet->getActiveSheet();
    
    // Preparar datos para reemplazo
    $reemplazos = [
        // Tipo de solicitud
        '${TIPO_CREAR}' => ($sol['tipo_solicitud'] == 'crear_asignar') ? 'X' : '',
        '${TIPO_ACCESOS}' => ($sol['tipo_solicitud'] == 'solicitar_accesos') ? 'X' : '',
        '${TIPO_BAJA}' => ($sol['tipo_solicitud'] == 'baja_reemplazo') ? 'X' : '',
        
        // Sección 1: Datos del colaborador
        '${NOMBRE_COMPLETO}' => $sol['colaborador_nombre'] ?? '',
        '${DNI}' => $sol['colaborador_dni'] ?? '',
        '${TELEFONO}' => $sol['colaborador_telefono'] ?? '',
        '${CARGO}' => $sol['colaborador_puesto'] ?? '',
        '${SEDE}' => $sol['sede_nombre'] ?? '',
        '${FECHA_SOLICITUD}' => date('d/m/Y'),
        
        // Sección 2: Equipos
        '${CHECK_LAPTOP}' => $sol['solicita_laptop'] ? 'X' : '',
        '${CHECK_CELULAR}' => $sol['solicita_celular'] ? 'X' : '',
        '${CHECK_PC}' => $sol['solicita_pc'] ? 'X' : '',
        '${OTROS_EQUIPOS}' => $sol['otros_equipos'] ?? '',
        '${CARACTERISTICAS_EQUIPO}' => $sol['caracteristicas_equipo'] ?? '',
        
        // Sección 3: SharePoint
        '${SP_GESTION_MOSTRADOR}' => $sol['sp_gestion_mostrador'] ? 'X' : '',
        '${SP_GESTION_SERVICIO}' => $sol['sp_gestion_servicio'] ? 'X' : '',
        '${SP_UNIDADES_NUEVAS}' => $sol['sp_unidades_nuevas'] ? 'X' : '',
        '${SP_CONTRATOS_INHOUSE}' => $sol['sp_contratos_inhouse'] ? 'X' : '',
        '${SP_ADMIN_FINANZAS}' => $sol['sp_admin_finanzas'] ? 'X' : '',
        '${SP_CONTABILIDAD}' => $sol['sp_contabilidad'] ? 'X' : '',
        '${SP_LOGISTICA}' => $sol['sp_logistica'] ? 'X' : '',
        '${SP_SEI}' => $sol['sp_sei'] ? 'X' : '',
        '${SP_RRHH}' => $sol['sp_rrhh'] ? 'X' : '',
        '${SP_SSOMA}' => $sol['sp_ssoma'] ? 'X' : '',
        '${SP_TIC}' => $sol['sp_tic'] ? 'X' : '',
        '${SP_CALIDAD}' => $sol['sp_calidad'] ? 'X' : '',
        '${SP_NIVEL3}' => ($sol['sp_nivel'] == 'nivel3') ? 'X' : '',
        '${SP_NIVEL4}' => ($sol['sp_nivel'] == 'nivel4') ? 'X' : '',
        '${SP_NIVEL5}' => ($sol['sp_nivel'] == 'nivel5') ? 'X' : '',
        '${SP_NIVEL6}' => ($sol['sp_nivel'] == 'nivel6') ? 'X' : '',
        
        // Sección 4: ACPCORE
        '${ACPCORE_ASESOR_CONTRATOS}' => $sol['acpcore_asesor_contratos'] ? 'X' : '',
        '${ACPCORE_ASESOR_GARANTIAS}' => $sol['acpcore_asesor_garantias'] ? 'X' : '',
        '${ACPCORE_ASESOR_MOSTRADOR}' => $sol['acpcore_asesor_mostrador'] ? 'X' : '',
        '${ACPCORE_ASESOR_REPUESTO}' => $sol['acpcore_asesor_repuesto'] ? 'X' : '',
        '${ACPCORE_ASESOR_SERVICIO}' => $sol['acpcore_asesor_servicio'] ? 'X' : '',
        '${ACPCORE_ASIST_ADMIN}' => $sol['acpcore_asist_admin'] ? 'X' : '',
        '${ACPCORE_ASIST_CONTABLE}' => $sol['acpcore_asist_contable'] ? 'X' : '',
        '${ACPCORE_ASIST_CAJA}' => $sol['acpcore_asist_caja'] ? 'X' : '',
        '${ACPCORE_ASIST_COBRANZA}' => $sol['acpcore_asist_cobranza'] ? 'X' : '',
        '${ACPCORE_ASIST_CALIDAD}' => $sol['acpcore_asist_calidad'] ? 'X' : '',
        '${ACPCORE_ASIST_ALMACEN_HERR}' => $sol['acpcore_asist_almacen_herr'] ? 'X' : '',
        '${ACPCORE_ASIST_ALMACEN_LOG}' => $sol['acpcore_asist_almacen_log'] ? 'X' : '',
        '${ACPCORE_ASIST_RRHH}' => $sol['acpcore_asist_rrhh'] ? 'X' : '',
        '${ACPCORE_ASIST_INFRAESTRUCTURA}' => $sol['acpcore_asist_infraestructura'] ? 'X' : '',
        '${ACPCORE_ASIST_JEFE_SERVICIO}' => $sol['acpcore_asist_jefe_servicio'] ? 'X' : '',
        '${ACPCORE_ASIST_PLANIFICADOR}' => $sol['acpcore_asist_planificador'] ? 'X' : '',
        '${ACPCORE_COORD_CONTRATOS}' => $sol['acpcore_coord_contratos'] ? 'X' : '',
        '${ACPCORE_FACTURADOR}' => $sol['acpcore_facturador'] ? 'X' : '',
        '${ACPCORE_INSTRUCTOR_CONDUCCION}' => $sol['acpcore_instructor_conduccion'] ? 'X' : '',
        '${ACPCORE_INSTRUCTOR_TECNICO}' => $sol['acpcore_instructor_tecnico'] ? 'X' : '',
        '${ACPCORE_JEFE_CALIDAD}' => $sol['acpcore_jefe_calidad'] ? 'X' : '',
        '${ACPCORE_JEFE_CONTABILIDAD}' => $sol['acpcore_jefe_contabilidad'] ? 'X' : '',
        '${ACPCORE_JEFE_CONTRATOS}' => $sol['acpcore_jefe_contratos'] ? 'X' : '',
        '${ACPCORE_JEFE_CONTRATOS_INHOUSE}' => $sol['acpcore_jefe_contratos_inhouse'] ? 'X' : '',
        '${ACPCORE_JEFE_LOGISTICA}' => $sol['acpcore_jefe_logistica'] ? 'X' : '',
        '${ACPCORE_JEFE_REPUESTOS}' => $sol['acpcore_jefe_repuestos'] ? 'X' : '',
        '${ACPCORE_JEFE_SERVICIO}' => $sol['acpcore_jefe_servicio'] ? 'X' : '',
        '${ACPCORE_JEFE_TALLER}' => $sol['acpcore_jefe_taller'] ? 'X' : '',
        '${ACPCORE_JEFE_UNIDADES}' => $sol['acpcore_jefe_unidades'] ? 'X' : '',
        '${ACPCORE_MECANICO}' => $sol['acpcore_mecanico'] ? 'X' : '',
        '${ACPCORE_MECANICO_CONTRATOS}' => $sol['acpcore_mecanico_contratos'] ? 'X' : '',
        '${ACPCORE_OPERADOR_LOGISTICO}' => $sol['acpcore_operador_logistico'] ? 'X' : '',
        '${ACPCORE_PRACTICANTE_LOGISTICA}' => $sol['acpcore_practicante_logistica'] ? 'X' : '',
        '${ACPCORE_RESPONSABLE_SST}' => $sol['acpcore_responsable_sst'] ? 'X' : '',
        '${ACPCORE_SUPERVISOR}' => $sol['acpcore_supervisor'] ? 'X' : '',
        '${ACPCORE_OTROS}' => $sol['acpcore_otros'] ?? '',
        
        // Sección 5: Servicios Remotos
        '${CHECK_GDS}' => $sol['solicita_gds'] ? 'X' : '',
        '${CHECK_CONTASIS}' => $sol['solicita_contasis'] ? 'X' : '',
        '${JUSTIF_SERVICIOS_REMOTOS}' => $sol['justif_servicios_remotos'] ?? '',
        
        // Sección 6: GDS
        '${GDS_ROL}' => $sol['gds_rol'] ?? '',
        '${GDS_USUARIO}' => $sol['gds_usuario'] ?? '',
        '${GDS_JUSTIFICACION}' => $sol['gds_justificacion'] ?? '',
        
        // Otros
        '${OTROS_ACCESOS}' => $sol['otros_accesos'] ?? '',
        '${JUSTIFICACION}' => $sol['justificacion'] ?? '',
        
        // ⭐ APROBACIONES (CON AMBOS GERENTES - usando función helper)
        '${NOMBRE_GERENTE}' => $sol['gerente_nombre'] ?? '',
        '${FECHA_APROBACION_GERENTE}' => formatearFechaSQL($sol['fecha_aprobacion']),
        '${COMENTARIO_GERENTE}' => $sol['comentario_gerente'] ?? '',
        
        '${NOMBRE_GERENTE_GENERAL}' => $sol['gerente_general_nombre'] ?? '',
        '${FECHA_APROB_GENERAL}' => formatearFechaSQL($sol['fecha_aprobacion_general']),
        '${COMENTARIO_GENERAL}' => $sol['comentario_general'] ?? '',
        '${NOMBRE_JEFE}' => $sol['jefe_nombre'] ?? '',
    ];
    
    // Recorrer todas las celdas y reemplazar variables
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    
    for ($row = 1; $row <= $highestRow; $row++) {
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $cellCoordinate = $col . $row;
            $cellValue = $sheet->getCell($cellCoordinate)->getValue();
            
            if ($cellValue && is_string($cellValue)) {
                $hasX = false;
                
                foreach ($reemplazos as $variable => $valor) {
                    if (strpos($cellValue, $variable) !== false) {
                        $cellValue = str_replace($variable, $valor, $cellValue);
                        
                        if ($valor === 'X') {
                            $hasX = true;
                        }
                    }
                }
                
                $sheet->setCellValue($cellCoordinate, $cellValue);
                
                if ($hasX || $cellValue === 'X') {
                    $sheet->getStyle($cellCoordinate)->getFont()->setBold(true);
                }
            }
        }
    }
    
    // Crear carpeta si no existe
    $carpeta = '../../documents/solicitudes/';
    if (!file_exists($carpeta)) {
        mkdir($carpeta, 0777, true);
    }
    
    // Guardar archivo
    $nombreArchivo = $sol['folio'] . '.xlsx';
    $rutaCompleta = $carpeta . $nombreArchivo;
    
    $writer = new Xlsx($spreadsheet);
    $writer->save($rutaCompleta);
    
    return 'documents/solicitudes/' . $nombreArchivo;
}