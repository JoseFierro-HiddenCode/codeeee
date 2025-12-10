<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/inventario/solicitar-equipos.php');
    exit();
}

// Solo usuarios que son jefes o admin_global pueden crear solicitudes
protegerPagina(['usuario', 'admin_tecnico', 'admin_global']);

// Verificar que sea usuario autorizado especial
if (!esUsuarioAutorizadoEspecial()) {
    header('Location: ../../views/dashboard-usuario.php?error=' . urlencode('No tienes permisos'));
    exit();
}

// Obtener datos del formulario
$colaborador_id = $_POST['colaborador_id'] ?? 0;
$tipo_solicitud = $_POST['tipo_solicitud'] ?? 'crear_asignar';

// Sección 1: Datos del colaborador
$dni = $_POST['dni'] ?? null;
$telefono = $_POST['telefono'] ?? null;
$cargo = $_POST['cargo'] ?? null;
$sede_id = $_POST['sede_id'] ?? null;

// Sección 2: Equipos
$solicita_laptop = isset($_POST['solicita_laptop']) ? 1 : 0;
$solicita_celular = isset($_POST['solicita_celular']) ? 1 : 0;
$solicita_pc = isset($_POST['solicita_pc']) ? 1 : 0;
$otros_equipos = $_POST['otros_equipos'] ?? null;
$caracteristicas_equipo = $_POST['caracteristicas_equipo'] ?? null;

// Sección 3: SharePoint
$sp_gestion_mostrador = isset($_POST['sp_gestion_mostrador']) ? 1 : 0;
$sp_gestion_servicio = isset($_POST['sp_gestion_servicio']) ? 1 : 0;
$sp_unidades_nuevas = isset($_POST['sp_unidades_nuevas']) ? 1 : 0;
$sp_contratos_inhouse = isset($_POST['sp_contratos_inhouse']) ? 1 : 0;
$sp_admin_finanzas = isset($_POST['sp_admin_finanzas']) ? 1 : 0;
$sp_contabilidad = isset($_POST['sp_contabilidad']) ? 1 : 0;
$sp_logistica = isset($_POST['sp_logistica']) ? 1 : 0;
$sp_sei = isset($_POST['sp_sei']) ? 1 : 0;
$sp_rrhh = isset($_POST['sp_rrhh']) ? 1 : 0;
$sp_ssoma = isset($_POST['sp_ssoma']) ? 1 : 0;
$sp_tic = isset($_POST['sp_tic']) ? 1 : 0;
$sp_calidad = isset($_POST['sp_calidad']) ? 1 : 0;
$sp_nivel = $_POST['sp_nivel'] ?? null;

// Sección 4: ACPCORE (TODOS los campos)
$acpcore_asesor_contratos = isset($_POST['acpcore_asesor_contratos']) ? 1 : 0;
$acpcore_asesor_garantias = isset($_POST['acpcore_asesor_garantias']) ? 1 : 0;
$acpcore_asesor_mostrador = isset($_POST['acpcore_asesor_mostrador']) ? 1 : 0;
$acpcore_asesor_repuesto = isset($_POST['acpcore_asesor_repuesto']) ? 1 : 0;
$acpcore_asesor_servicio = isset($_POST['acpcore_asesor_servicio']) ? 1 : 0;
$acpcore_asist_admin = isset($_POST['acpcore_asist_admin']) ? 1 : 0;
$acpcore_asist_contable = isset($_POST['acpcore_asist_contable']) ? 1 : 0;
$acpcore_asist_caja = isset($_POST['acpcore_asist_caja']) ? 1 : 0;
$acpcore_asist_cobranza = isset($_POST['acpcore_asist_cobranza']) ? 1 : 0;
$acpcore_asist_calidad = isset($_POST['acpcore_asist_calidad']) ? 1 : 0;
$acpcore_asist_almacen_herr = isset($_POST['acpcore_asist_almacen_herr']) ? 1 : 0;
$acpcore_asist_almacen_log = isset($_POST['acpcore_asist_almacen_log']) ? 1 : 0;
$acpcore_asist_rrhh = isset($_POST['acpcore_asist_rrhh']) ? 1 : 0;
$acpcore_asist_infraestructura = isset($_POST['acpcore_asist_infraestructura']) ? 1 : 0;
$acpcore_asist_jefe_servicio = isset($_POST['acpcore_asist_jefe_servicio']) ? 1 : 0;
$acpcore_asist_planificador = isset($_POST['acpcore_asist_planificador']) ? 1 : 0;
$acpcore_coord_contratos = isset($_POST['acpcore_coord_contratos']) ? 1 : 0;
$acpcore_facturador = isset($_POST['acpcore_facturador']) ? 1 : 0;
$acpcore_instructor_conduccion = isset($_POST['acpcore_instructor_conduccion']) ? 1 : 0;
$acpcore_instructor_tecnico = isset($_POST['acpcore_instructor_tecnico']) ? 1 : 0;
$acpcore_jefe_calidad = isset($_POST['acpcore_jefe_calidad']) ? 1 : 0;
$acpcore_jefe_contabilidad = isset($_POST['acpcore_jefe_contabilidad']) ? 1 : 0;
$acpcore_jefe_contratos = isset($_POST['acpcore_jefe_contratos']) ? 1 : 0;
$acpcore_jefe_contratos_inhouse = isset($_POST['acpcore_jefe_contratos_inhouse']) ? 1 : 0;
$acpcore_jefe_logistica = isset($_POST['acpcore_jefe_logistica']) ? 1 : 0;
$acpcore_jefe_repuestos = isset($_POST['acpcore_jefe_repuestos']) ? 1 : 0;
$acpcore_jefe_servicio = isset($_POST['acpcore_jefe_servicio']) ? 1 : 0;
$acpcore_jefe_taller = isset($_POST['acpcore_jefe_taller']) ? 1 : 0;
$acpcore_jefe_unidades = isset($_POST['acpcore_jefe_unidades']) ? 1 : 0;
$acpcore_mecanico = isset($_POST['acpcore_mecanico']) ? 1 : 0;
$acpcore_mecanico_contratos = isset($_POST['acpcore_mecanico_contratos']) ? 1 : 0;
$acpcore_operador_logistico = isset($_POST['acpcore_operador_logistico']) ? 1 : 0;
$acpcore_practicante_logistica = isset($_POST['acpcore_practicante_logistica']) ? 1 : 0;
$acpcore_responsable_sst = isset($_POST['acpcore_responsable_sst']) ? 1 : 0;
$acpcore_supervisor = isset($_POST['acpcore_supervisor']) ? 1 : 0;
$acpcore_otros = $_POST['acpcore_otros'] ?? null;

// Sección 5: Servicios Remotos
$solicita_gds = isset($_POST['solicita_gds']) ? 1 : 0;
$solicita_contasis = isset($_POST['solicita_contasis']) ? 1 : 0;
$justif_servicios_remotos = $_POST['justif_servicios_remotos'] ?? null;

// Sección 6: GDS
$gds_rol = $_POST['gds_rol'] ?? null;
$gds_usuario = $_POST['gds_usuario'] ?? null;
$gds_justificacion = $_POST['gds_justificacion'] ?? null;

// Otros
$otros_accesos = $_POST['otros_accesos'] ?? null;
$justificacion = $_POST['justificacion'] ?? '';

// Validaciones
if (empty($colaborador_id) || empty($justificacion)) {
    header('Location: ../../views/inventario/solicitar-equipos.php?error=' . urlencode('Colaborador y justificación son obligatorios'));
    exit();
}

// Verificar que el usuario seleccionado exista y esté activo
$sqlVerificar = "SELECT id FROM users WHERE id = ? AND activo = 1";
$colaborador = obtenerUno($sqlVerificar, [$colaborador_id]);

if (!$colaborador) {
    header('Location: ../../views/inventario/solicitar-equipos.php?error=' . urlencode('El usuario seleccionado no existe'));
    exit();
}

try {
    // Generar folio
    $folio = generarFolioSolicitud();
    
    // Insertar solicitud
    $sql = "
        INSERT INTO solicitudes_equipos (
            folio, solicitante_id, colaborador_id, tipo_solicitud,
            solicita_laptop, solicita_celular, solicita_pc, otros_equipos, caracteristicas_equipo,
            sp_gestion_mostrador, sp_gestion_servicio, sp_unidades_nuevas, sp_contratos_inhouse,
            sp_admin_finanzas, sp_contabilidad, sp_logistica, sp_sei, sp_rrhh, sp_ssoma, sp_tic, sp_calidad, sp_nivel,
            acpcore_asesor_contratos, acpcore_asesor_garantias, acpcore_asesor_mostrador, acpcore_asesor_repuesto,
            acpcore_asesor_servicio, acpcore_asist_admin, acpcore_asist_contable, acpcore_asist_caja,
            acpcore_asist_cobranza, acpcore_asist_calidad, acpcore_asist_almacen_herr, acpcore_asist_almacen_log,
            acpcore_asist_rrhh, acpcore_asist_infraestructura, acpcore_asist_jefe_servicio, acpcore_asist_planificador,
            acpcore_coord_contratos, acpcore_facturador, acpcore_instructor_conduccion, acpcore_instructor_tecnico,
            acpcore_jefe_calidad, acpcore_jefe_contabilidad, acpcore_jefe_contratos, acpcore_jefe_contratos_inhouse,
            acpcore_jefe_logistica, acpcore_jefe_repuestos, acpcore_jefe_servicio, acpcore_jefe_taller,
            acpcore_jefe_unidades, acpcore_mecanico, acpcore_mecanico_contratos, acpcore_operador_logistico,
            acpcore_practicante_logistica, acpcore_responsable_sst, acpcore_supervisor, acpcore_otros,
            solicita_gds, solicita_contasis, justif_servicios_remotos,
            gds_rol, gds_usuario, gds_justificacion,
            otros_accesos, justificacion, estado,
            created_at, updated_at
        )
        VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, 'pendiente_gerente',
            GETDATE(), GETDATE()
        )
    ";
    
    $params = [
        $folio,
        $_SESSION['user_id'],
        $colaborador_id,
        $tipo_solicitud,
        $solicita_laptop,
        $solicita_celular,
        $solicita_pc,
        $otros_equipos,
        $caracteristicas_equipo,
        $sp_gestion_mostrador,
        $sp_gestion_servicio,
        $sp_unidades_nuevas,
        $sp_contratos_inhouse,
        $sp_admin_finanzas,
        $sp_contabilidad,
        $sp_logistica,
        $sp_sei,
        $sp_rrhh,
        $sp_ssoma,
        $sp_tic,
        $sp_calidad,
        $sp_nivel,
        $acpcore_asesor_contratos,
        $acpcore_asesor_garantias,
        $acpcore_asesor_mostrador,
        $acpcore_asesor_repuesto,
        $acpcore_asesor_servicio,
        $acpcore_asist_admin,
        $acpcore_asist_contable,
        $acpcore_asist_caja,
        $acpcore_asist_cobranza,
        $acpcore_asist_calidad,
        $acpcore_asist_almacen_herr,
        $acpcore_asist_almacen_log,
        $acpcore_asist_rrhh,
        $acpcore_asist_infraestructura,
        $acpcore_asist_jefe_servicio,
        $acpcore_asist_planificador,
        $acpcore_coord_contratos,
        $acpcore_facturador,
        $acpcore_instructor_conduccion,
        $acpcore_instructor_tecnico,
        $acpcore_jefe_calidad,
        $acpcore_jefe_contabilidad,
        $acpcore_jefe_contratos,
        $acpcore_jefe_contratos_inhouse,
        $acpcore_jefe_logistica,
        $acpcore_jefe_repuestos,
        $acpcore_jefe_servicio,
        $acpcore_jefe_taller,
        $acpcore_jefe_unidades,
        $acpcore_mecanico,
        $acpcore_mecanico_contratos,
        $acpcore_operador_logistico,
        $acpcore_practicante_logistica,
        $acpcore_responsable_sst,
        $acpcore_supervisor,
        $acpcore_otros,
        $solicita_gds,
        $solicita_contasis,
        $justif_servicios_remotos,
        $gds_rol,
        $gds_usuario,
        $gds_justificacion,
        $otros_accesos,
        $justificacion
    ];
    
    ejecutarQuery($sql, $params);
    
    // Obtener datos completos para el Excel
    $sqlDatos = "
        SELECT 
            s.*,
            u.nombre + ' ' + u.apellido as colaborador_nombre,
            u.dni as colaborador_dni,
            u.puesto as colaborador_puesto,
            u.telefono as colaborador_telefono,
            jefe.nombre + ' ' + jefe.apellido as jefe_nombre,
            jefe.puesto as jefe_puesto,
            sede.nombre as sede_nombre
        FROM solicitudes_equipos s
        INNER JOIN users u ON s.colaborador_id = u.id
        INNER JOIN users jefe ON s.solicitante_id = jefe.id
        LEFT JOIN sedes sede ON u.sede_id = sede.id
        WHERE s.folio = ?
    ";
    
    $solicitud = obtenerUno($sqlDatos, [$folio]);
    
    // Generar Excel desde plantilla
    $rutaExcel = generarExcelDesdePlantilla($solicitud);
    
    // Actualizar ruta del Excel en BD
    $sqlUpdate = "UPDATE solicitudes_equipos SET ruta_excel = ? WHERE folio = ?";
    ejecutarQuery($sqlUpdate, [$rutaExcel, $folio]);
    
    // Redirigir con éxito
    header('Location: ../../views/inventario/solicitar-equipos.php?success=1&folio=' . urlencode($folio));
    exit();
    
} catch (Exception $e) {
    error_log("Error al crear solicitud: " . $e->getMessage());
    header('Location: ../../views/inventario/solicitar-equipos.php?error=' . urlencode('Error al crear la solicitud: ' . $e->getMessage()));
    exit();
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
        // Tipo de solicitud (marcar con X en NEGRITA)
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
        
        //nivel3, nivel4, etc. (sin guión bajo)
        '${SP_NIVEL3}' => ($sol['sp_nivel'] == 'nivel3') ? 'X' : '',
        '${SP_NIVEL4}' => ($sol['sp_nivel'] == 'nivel4') ? 'X' : '',
        '${SP_NIVEL5}' => ($sol['sp_nivel'] == 'nivel5') ? 'X' : '',
        '${SP_NIVEL6}' => ($sol['sp_nivel'] == 'nivel6') ? 'X' : '',
        
        // Sección 4: ACPCORE (TODOS)
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
        '${ACPCORE_ASIST_CAJA2}' => $sol['acpcore_asist_caja'] ? 'X' : '',
        '${ACPCORE_ASIST_COBRANZA2}' => $sol['acpcore_asist_cobranza'] ? 'X' : '',
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
        
        //Otros Accesos:
        '${OTROS_ACCESOS}' => $sol['otros_accesos'] ?? '',
        '${JUSTIFICACION}' => $sol['justificacion'] ?? '',
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
                
                // Reemplazar todas las variables en la celda
                foreach ($reemplazos as $variable => $valor) {
                    if (strpos($cellValue, $variable) !== false) {
                        $cellValue = str_replace($variable, $valor, $cellValue);
                        
                        // Si el valor reemplazado es 'X', marcar para negrita
                        if ($valor === 'X') {
                            $hasX = true;
                        }
                    }
                }
                
                $sheet->setCellValue($cellCoordinate, $cellValue);
                
                // Si la celda contiene una X, aplicar negrita
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
?>