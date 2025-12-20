<?php
/**
 * Funciones Helper para Sistema de Solicitud de Accesos VOL
 */

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Generar folio único para solicitudes VOL
 */
function generarFolioVOL() {
    global $conn;
    
    $anio = date('Y');
    
    // Obtener último folio del año
    $sql = "SELECT TOP 1 folio FROM solicitudes_accesos_vol_historial 
            WHERE folio LIKE 'VOL-" . $anio . "-%' 
            ORDER BY id DESC";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Extraer número
        $ultimo = $row['folio'];
        $numero = intval(substr($ultimo, -3));
        $siguiente = $numero + 1;
    } else {
        $siguiente = 1;
    }
    
    return 'VOL-' . $anio . '-' . str_pad($siguiente, 3, '0', STR_PAD_LEFT);
}

/**
 * Mapear accesos a nombres de variables
 */
function obtenerNombreVariable($acceso) {
    $mapeo = [
        // Volvo Trucks
        'trucks_portal_volvo' => 'CB_TRUCKS_PORTAL',
        'argus_dealer' => 'CB_ARGUS_DEALER',
        'dynafleet' => 'CB_DYNAFLEET',
        'impact_vt' => 'CB_IMPACT_VT',
        'parts_online' => 'CB_PARTS_ONLINE',
        'product_history' => 'CB_PRODUCT_HISTORY',
        'technical_service' => 'CB_TECHNICAL_SERVICE',
        'truck_campaign' => 'CB_TRUCK_CAMPAIGN',
        'trucks_portal_ud' => 'CB_TRUCKS_PORTAL_UD',
        'ud_product_history' => 'CB_UD_PRODUCT_HISTORY',
        'vosp' => 'CB_VOSP',
        'wiring_diagrams' => 'CB_WIRING_DIAGRAMS',
        
        // Mack Trucks
        'mack_trucks_dealer' => 'CB_MACK_TRUCKS_DEALER',
        'mack_electronic_info' => 'CB_MACK_ELECTRONIC_INFO',
        'mack_impact' => 'CB_MACK_IMPACT',
        'mack_product_history' => 'CB_MACK_PRODUCT_HISTORY',
        
        // VCE
        'vdn' => 'CB_VDN',
        'caretrack' => 'CB_CARETRACK',
        'chain' => 'CB_CHAIN',
        'prosis_pro' => 'CB_PROSIS_PRO',
        'vlc' => 'CB_VLC',
        'tech_tool_matris' => 'CB_TECH_TOOL_MATRIS',
        'tt_accesos' => 'CB_TT_ACCESOS',
        'tt_licencia' => 'CB_TT_LICENCIA',
        
        // Volvo Penta
        'vppn' => 'CB_VPPN',
        'epc_offline' => 'CB_EPC_OFFLINE',
        'vodia5' => 'CB_VODIA5',
        'vodia_acceso' => 'CB_VODIA_ACCESO',
        'vodia_licencia' => 'CB_VODIA_LICENCIA',
        
        // Tech Tool 2
        'vtt' => 'CB_VTT',
        'vtt_nueva' => 'CB_VTT_NUEVA',
        'vtt_renovacion' => 'CB_VTT_RENOVACION',
        'vtt_volvo_trucks' => 'CB_VTT_VOLVO_TRUCKS',
        'vtt_volvo_buses' => 'CB_VTT_VOLVO_BUSES',
        'vtt_mack_trucks' => 'CB_VTT_MACK_TRUCKS',
        'vtt_ud_trucks' => 'CB_VTT_UD_TRUCKS',
        
        // Facturación
        'lds' => 'CB_LDS',
        'gds' => 'CB_GDS',
        'time_recording' => 'CB_TIME_RECORDING',
        
        // Garantías
        'uchp' => 'CB_UCHP',
        'uchp_vtc' => 'CB_UCHP_VTC',
        'uchp_vbc' => 'CB_UCHP_VBC',
        'uchp_mack' => 'CB_UCHP_MACK',
        'uchp_ud' => 'CB_UCHP_UD',
        'uchp_vce' => 'CB_UCHP_VCE',
        'uchp_penta' => 'CB_UCHP_PENTA',
        'warranty_bulletin' => 'CB_WARRANTY_BULLETIN',
        'vda_plus' => 'CB_VDA_PLUS',
        
        // Contratos
        'tsa' => 'CB_TSA'
    ];

    return $mapeo[$acceso] ?? null;
}

/**
 * Generar documento Word desde plantilla
 */
function generarWordDesdePlantillaVOL($solicitud, $accesosNuevos, $camposAdicionales) {
    $rutaPlantilla = __DIR__ . '/../documents/templates/plantilla_solicitud_accesos_vol.docx';
    
    if (!file_exists($rutaPlantilla)) {
        throw new Exception("Plantilla no encontrada en: " . $rutaPlantilla);
    }
    
    $templateProcessor = new TemplateProcessor($rutaPlantilla);
    
    // === TIPO DE SOLICITUD ===
    $templateProcessor->setValue('TIPO_CREAR', $solicitud['tipo_solicitud'] == 'crear' ? 'X' : ' ');
    $templateProcessor->setValue('TIPO_SOLICITAR', $solicitud['tipo_solicitud'] == 'solicitar' ? 'X' : ' ');
    $templateProcessor->setValue('TIPO_LICENCIAS', $solicitud['tipo_solicitud'] == 'licencias' ? 'X' : ' ');
    $templateProcessor->setValue('TIPO_ELIMINAR', $solicitud['tipo_solicitud'] == 'eliminar' ? 'X' : ' ');
    
    // === DATOS DEL EMPLEADO ===
    $templateProcessor->setValue('NOMBRE', $solicitud['nombre']);
    $templateProcessor->setValue('APELLIDO', $solicitud['apellido']);
    $templateProcessor->setValue('ID_USUARIO', $solicitud['id_usuario'] ?? '');
    $templateProcessor->setValue('TELEFONO', $solicitud['telefono'] ?? '');
    $templateProcessor->setValue('CARGO', $solicitud['cargo'] ?? '');
    $templateProcessor->setValue('CONCESIONARIO', $solicitud['concesionario'] ?? '');
    $templateProcessor->setValue('SUCURSAL', $solicitud['sucursal'] ?? '');
    $templateProcessor->setValue('EMAIL', $solicitud['correo_corporativo'] ?? '');
    
// === CHECKBOXES (inicializar todos en vacío) ===
$todosCheckboxes = [
    // Volvo Trucks
    'CB_TRUCKS_PORTAL', 'CB_ARGUS_DEALER', 'CB_DYNAFLEET', 'CB_IMPACT_VT',
    'CB_PARTS_ONLINE', 'CB_PRODUCT_HISTORY', 'CB_TECHNICAL_SERVICE', 'CB_TRUCK_CAMPAIGN',
    'CB_TRUCKS_PORTAL_UD', 'CB_UD_PRODUCT_HISTORY', 'CB_VOSP', 'CB_WIRING_DIAGRAMS',
    
    // Mack Trucks
    'CB_MACK_TRUCKS_DEALER', 'CB_MACK_ELECTRONIC_INFO', 'CB_MACK_IMPACT', 'CB_MACK_PRODUCT_HISTORY',
    
    // VCE
    'CB_VDN', 'CB_CARETRACK', 'CB_CHAIN', 'CB_PROSIS_PRO', 'CB_VLC',
    'CB_TECH_TOOL_MATRIS', 'CB_TT_ACCESOS', 'CB_TT_LICENCIA',
    
    // Volvo Penta
    'CB_VPPN', 'CB_EPC_OFFLINE', 'CB_VODIA5', 'CB_VODIA_ACCESO', 'CB_VODIA_LICENCIA',
    
    // Tech Tool 2
    'CB_VTT', 'CB_VTT_NUEVA', 'CB_VTT_RENOVACION',
    'CB_VTT_VOLVO_TRUCKS', 'CB_VTT_VOLVO_BUSES', 'CB_VTT_MACK_TRUCKS', 'CB_VTT_UD_TRUCKS',
    
    // Facturación
    'CB_LDS', 'CB_GDS', 'CB_TIME_RECORDING',
    
    // Garantías
    'CB_UCHP', 'CB_UCHP_VTC', 'CB_UCHP_VBC', 'CB_UCHP_MACK',
    'CB_UCHP_UD', 'CB_UCHP_VCE', 'CB_UCHP_PENTA', 'CB_WARRANTY_BULLETIN', 'CB_VDA_PLUS',
    
    // Contratos
    'CB_TSA'
];

// Inicializar todos en vacío
foreach ($todosCheckboxes as $cb) {
    $templateProcessor->setValue($cb, ' ');
}

// Marcar los seleccionados
foreach ($accesosNuevos as $acceso) {
    $variable = obtenerNombreVariable($acceso);
    if ($variable) {
        $templateProcessor->setValue($variable, 'X');
    }
}
    
    // === CAMPOS DE TEXTO ADICIONALES ===
    $templateProcessor->setValue('PROSIS_PRECIO', $camposAdicionales['prosis_precio'] ?? '');
    $templateProcessor->setValue('VLC_PRECIO', $camposAdicionales['vlc_precio'] ?? '');
    $templateProcessor->setValue('VTT_PRECIO', $camposAdicionales['vtt_precio'] ?? '');
    $templateProcessor->setValue('LDS_ROL', $camposAdicionales['lds_rol'] ?? '');
    $templateProcessor->setValue('GDS_ROL', $camposAdicionales['gds_rol'] ?? '');
    $templateProcessor->setValue('TIME_RECORDING_CODIGO', $camposAdicionales['time_recording_codigo'] ?? '');
    $templateProcessor->setValue('OTRO_ACCESO_TEXTO', $camposAdicionales['otro_acceso_texto'] ?? '');
    
    // === FECHA ===
    $templateProcessor->setValue('FECHA', date('d/m/Y'));
    
    // === GUARDAR ARCHIVO ===
    $nombreArchivo = $solicitud['folio'] . '.docx';
    $dirDestino = __DIR__ . '/../documents/solicitudes_vol/';
    
    if (!file_exists($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }
    
    $rutaDestino = $dirDestino . $nombreArchivo;
        // === GUARDAR WORD TEMPORAL ===
    $templateProcessor->saveAs($rutaDestino);
    
    // === CONVERTIR A PDF ===
    $rutaPDF = str_replace('.docx', '.pdf', $rutaDestino);
    
    try {
    // === CONFIGURAR MPDF COMO RENDERIZADOR ===
    \PhpOffice\PhpWord\Settings::setPdfRendererName(\PhpOffice\PhpWord\Settings::PDF_RENDERER_MPDF);
    \PhpOffice\PhpWord\Settings::setPdfRendererPath(__DIR__ . '/../vendor/mpdf/mpdf');
    
    // Cargar el Word generado
    $phpWord = \PhpOffice\PhpWord\IOFactory::load($rutaDestino);
    
    // Crear escritor PDF
    $pdfWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');
    
    // Guardar como PDF
    $pdfWriter->save($rutaPDF); 
        
        // Opcional: Eliminar el Word temporal (descomenta si quieres)
        // unlink($rutaDestino);
        
        return $rutaPDF;
        
    } catch (Exception $e) {
        // Si falla la conversión a PDF, devolver el Word
        error_log("Error al generar PDF: " . $e->getMessage());
        return $rutaDestino;
    }
}


  /**
 * Generar PDF con diseño exacto de plantilla usando HTML/CSS
 */
function generarPDFDirectoVOL($solicitud, $accesosNuevos, $camposAdicionales) {
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Crear objeto MPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'Letter',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 10,
        'margin_bottom' => 10,
    ]);
    
    // Preparar checkboxes marcados
    $accesos = [];
    foreach ($accesosNuevos as $acc) {
        $accesos[$acc] = true;
    }
    
    // Función helper para checkbox
    $cb = function($nombre) use ($accesos) {
        return isset($accesos[$nombre]) ? 'X' : '&nbsp;';
    };
    
    // Tipo de solicitud
    $tipoCrear = $solicitud['tipo_solicitud'] == 'crear' ? 'X' : '&nbsp;';
    $tipoSolicitar = $solicitud['tipo_solicitud'] == 'solicitar' ? 'X' : '&nbsp;';
    $tipoLicencias = $solicitud['tipo_solicitud'] == 'licencias' ? 'X' : '&nbsp;';
    $tipoEliminar = $solicitud['tipo_solicitud'] == 'eliminar' ? 'X' : '&nbsp;';
    
    // Datos
    $nombre = htmlspecialchars($solicitud['nombre'] ?? '');
    $apellido = htmlspecialchars($solicitud['apellido'] ?? '');
    $idUsuario = htmlspecialchars($solicitud['id_usuario'] ?? '');
    $telefono = htmlspecialchars($solicitud['telefono'] ?? '');
    $cargo = htmlspecialchars($solicitud['cargo'] ?? '');
    $concesionario = htmlspecialchars($solicitud['concesionario'] ?? '');
    $sucursal = htmlspecialchars($solicitud['sucursal'] ?? '');
    $email = htmlspecialchars($solicitud['correo_corporativo'] ?? '');
    
    // Campos adicionales
    $prosisPrec = htmlspecialchars($camposAdicionales['prosis_precio'] ?? '');
    $vlcPrec = htmlspecialchars($camposAdicionales['vlc_precio'] ?? '');
    $vttPrec = htmlspecialchars($camposAdicionales['vtt_precio'] ?? '');
    $ldsRol = htmlspecialchars($camposAdicionales['lds_rol'] ?? '');
    $gdsRol = htmlspecialchars($camposAdicionales['gds_rol'] ?? '');
    $timeCod = htmlspecialchars($camposAdicionales['time_recording_codigo'] ?? '');
    $otroTexto = htmlspecialchars($camposAdicionales['otro_acceso_texto'] ?? '');
    
    $fecha = date('d/m/Y');
    
    // HTML del documento (DISEÑO EXACTO)
    $html = <<<HTML
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 7.5pt; line-height: 1.2; }
    
    .titulo { text-align: center; font-size: 12pt; font-weight: bold; margin-bottom: 8px; }
    
    .tipo-solicitud { text-align: center; margin-bottom: 10px; font-size: 8pt; }
    .tipo-solicitud span { display: inline-block; margin: 0 8px; }
    .cb-inline { display: inline-block; width: 12px; height: 12px; border: 1px solid #000; text-align: center; line-height: 11px; font-family: monospace; font-size: 9pt; margin-right: 3px; }
    
    .datos { margin-bottom: 10px; }
    .fila { margin-bottom: 3px; }
    .fila strong { font-weight: bold; }
    
    .seccion { font-weight: bold; font-size: 8pt; margin-top: 8px; margin-bottom: 4px; border-bottom: 1px solid #ccc; }
    
    table.accesos { width: 100%; margin-bottom: 6px; border-collapse: collapse; }
    table.accesos td { padding: 1px 0; font-size: 7.5pt; vertical-align: top; }
    
    .cb-box { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; text-align: center; line-height: 9px; font-family: monospace; font-size: 8pt; margin-right: 2px; }
    
    .footer { margin-top: 12px; padding: 6px; border: 1px solid #000; font-size: 6.5pt; line-height: 1.4; }
    .footer strong { font-weight: bold; }
    
    .firmas { margin-top: 20px; }
    .firmas table { width: 100%; }
    .firmas td { text-align: center; padding-top: 30px; border-top: 1px solid #000; font-size: 7pt; }
    
    .pie { text-align: right; margin-top: 10px; font-size: 7pt; }
</style>

<div class="titulo">FORMULARIO DE SOLICITUD DE ACCESOS Y LICENCIAS</div>

<div class="tipo-solicitud">
    <span>(<span class="cb-inline">{$tipoCrear}</span>) Crear Usuario</span>
    <span>(<span class="cb-inline">{$tipoSolicitar}</span>) Solicitar Accesos</span>
    <span>(<span class="cb-inline">{$tipoLicencias}</span>) Solicitar Licencias</span>
    <span>(<span class="cb-inline">{$tipoEliminar}</span>) Eliminar Usuario</span>
</div>

<div class="datos">
    <div class="fila"><strong>Nombre:</strong> {$nombre} &nbsp;&nbsp;&nbsp; <strong>Apellido:</strong> {$apellido} &nbsp;&nbsp;&nbsp; <strong>ID Usuario:</strong> {$idUsuario}</div>
    <div class="fila"><strong>Teléfono:</strong> {$telefono} &nbsp;&nbsp;&nbsp; <strong>Cargo:</strong> {$cargo}</div>
    <div class="fila"><strong>Concesionario:</strong> {$concesionario} &nbsp;&nbsp;&nbsp; <strong>Sucursal:</strong> {$sucursal}</div>
    <div class="fila"><strong>Correo corporativo:</strong> {$email}</div>
</div>

<div class="seccion">* VOLVO TRUCKS, VOLVO BUSES & UD TRUCKS</div>
<table class="accesos">
    <tr>
        <td width="33%">(<span class="cb-box">{$cb('trucks_portal_volvo')}</span>) Trucks Portal Volvo</td>
        <td width="33%">(<span class="cb-box">{$cb('argus_dealer')}</span>) Argus Dealer</td>
        <td width="34%">(<span class="cb-box">{$cb('dynafleet')}</span>) Dynafleet</td>
    </tr>
    <tr>
        <td>(<span class="cb-box">{$cb('impact_vt')}</span>) Impact</td>
        <td>(<span class="cb-box">{$cb('parts_online')}</span>) Parts Online</td>
        <td>(<span class="cb-box">{$cb('product_history')}</span>) Product History Viewer</td>
    </tr>
    <tr>
        <td>(<span class="cb-box">{$cb('technical_service')}</span>) Technical Service Bulletin</td>
        <td>(<span class="cb-box">{$cb('truck_campaign')}</span>) Truck Campaign Information</td>
        <td>(<span class="cb-box">{$cb('trucks_portal_ud')}</span>) Trucks Portal UD</td>
    </tr>
    <tr>
        <td>(<span class="cb-box">{$cb('ud_product_history')}</span>) UD Product History Viewer</td>
        <td>(<span class="cb-box">{$cb('vosp')}</span>) VOSP</td>
        <td>(<span class="cb-box">{$cb('wiring_diagrams')}</span>) Wiring Diagrams</td>
    </tr>
</table>

<div class="seccion">* MACK TRUCKS</div>
<table class="accesos">
    <tr>
        <td width="50%">(<span class="cb-box">{$cb('mack_trucks_dealer')}</span>) Trucks Dealer Portal</td>
        <td width="50%">(<span class="cb-box">{$cb('mack_electronic_info')}</span>) Electronic Info System</td>
    </tr>
    <tr>
        <td>(<span class="cb-box">{$cb('mack_impact')}</span>) Impact</td>
        <td>(<span class="cb-box">{$cb('mack_product_history')}</span>) Product History Viewer</td>
    </tr>
</table>

<div class="seccion">* VOLVO CONSTRUCTION EQUIPMENT</div>
<table class="accesos">
    <tr>
        <td width="33%">(<span class="cb-box">{$cb('vdn')}</span>) VDN</td>
        <td width="33%">(<span class="cb-box">{$cb('caretrack')}</span>) CareTrack</td>
        <td width="34%">(<span class="cb-box">{$cb('chain')}</span>) CHAIN</td>
    </tr>
    <tr>
        <td>(<span class="cb-box">{$cb('prosis_pro')}</span>) Prosis Pro <small>Precio: {$prosisPrec}</small></td>
        <td>(<span class="cb-box">{$cb('vlc')}</span>) VLC <small>Precio: {$vlcPrec}</small></td>
        <td>(<span class="cb-box">{$cb('tech_tool_matris')}</span>) Tech Tool 2 / MATRIS 2</td>
    </tr>
    <tr>
        <td colspan="3">
            &nbsp;&nbsp;&nbsp;&nbsp;➝ (<span class="cb-box">{$cb('tt_accesos')}</span>) Accesos &nbsp;&nbsp;
            (<span class="cb-box">{$cb('tt_licencia')}</span>) Licencia
        </td>
    </tr>
</table>

<div class="seccion">* VOLVO PENTA</div>
<table class="accesos">
    <tr>
        <td width="50%">(<span class="cb-box">{$cb('vppn')}</span>) VPPN</td>
        <td width="50%">(<span class="cb-box">{$cb('epc_offline')}</span>) EPC Offline</td>
    </tr>
    <tr>
        <td colspan="2">
            (<span class="cb-box">{$cb('vodia5')}</span>) VODIA 5 &nbsp;&nbsp;➝&nbsp;&nbsp;
            (<span class="cb-box">{$cb('vodia_acceso')}</span>) Acceso &nbsp;&nbsp;
            (<span class="cb-box">{$cb('vodia_licencia')}</span>) Licencia
        </td>
    </tr>
</table>

<div class="seccion">* TECH TOOL 2</div>
<table class="accesos">
    <tr>
        <td colspan="3">
            (<span class="cb-box">{$cb('vtt')}</span>) VTT &nbsp;&nbsp;➝&nbsp;&nbsp;
            (<span class="cb-box">{$cb('vtt_nueva')}</span>) Nueva &nbsp;&nbsp;
            (<span class="cb-box">{$cb('vtt_renovacion')}</span>) Renovación &nbsp;&nbsp;&nbsp;
            <small>Precio: {$vttPrec}</small>
        </td>
    </tr>
    <tr>
        <td colspan="3">
            <strong>Accesos:</strong> &nbsp;
            (<span class="cb-box">{$cb('vtt_volvo_trucks')}</span>) Volvo Trucks &nbsp;&nbsp;
            (<span class="cb-box">{$cb('vtt_volvo_buses')}</span>) Volvo Buses &nbsp;&nbsp;
            (<span class="cb-box">{$cb('vtt_mack_trucks')}</span>) Mack Trucks &nbsp;&nbsp;
            (<span class="cb-box">{$cb('vtt_ud_trucks')}</span>) UD Trucks
        </td>
    </tr>
</table>

<div class="seccion">* FACTURACIÓN</div>
<table class="accesos">
    <tr>
        <td width="50%">(<span class="cb-box">{$cb('lds')}</span>) LDS <small>Rol: {$ldsRol}</small></td>
        <td width="50%">(<span class="cb-box">{$cb('gds')}</span>) GDS <small>Rol: {$gdsRol}</small></td>
    </tr>
    <tr>
        <td colspan="2">(<span class="cb-box">{$cb('time_recording')}</span>) Time Recording <small>Código Dealer: {$timeCod}</small></td>
    </tr>
</table>

<div class="seccion">* GARANTÍAS</div>
<table class="accesos">
    <tr>
        <td colspan="3">
            (<span class="cb-box">{$cb('uchp')}</span>) UCHP &nbsp;&nbsp;➝&nbsp;&nbsp;
            (<span class="cb-box">{$cb('uchp_vtc')}</span>) VTC &nbsp;
            (<span class="cb-box">{$cb('uchp_vbc')}</span>) VBC &nbsp;
            (<span class="cb-box">{$cb('uchp_mack')}</span>) MACK &nbsp;
            (<span class="cb-box">{$cb('uchp_ud')}</span>) UD &nbsp;
            (<span class="cb-box">{$cb('uchp_vce')}</span>) VCE &nbsp;
            (<span class="cb-box">{$cb('uchp_penta')}</span>) PENTA
        </td>
    </tr>
    <tr>
        <td width="50%">(<span class="cb-box">{$cb('warranty_bulletin')}</span>) Warranty Bulletin</td>
        <td width="50%">(<span class="cb-box">{$cb('vda_plus')}</span>) VDA+</td>
    </tr>
</table>

<div class="seccion">* CONTRATOS</div>
<table class="accesos">
    <tr>
        <td>(<span class="cb-box">{$cb('tsa')}</span>) TSA</td>
    </tr>
</table>

<div class="seccion">OTRO ACCESO (JUSTIFICAR)</div>
<div style="margin: 4px 0; font-size: 7pt;">{$otroTexto}</div>

<div class="footer">
    <strong>COMPROMISO PARA AUTORIZACIONES INDIVIDUALES</strong><br>
    El solicitante, jefe y gerente, están de acuerdo con los siguientes puntos:<br>
    ✓ Las informaciones a las que tiene acceso el usuario son propiedad de la empresa y debe usarlas solamente para cumplir sus funciones dentro de VOLVO.<br>
    ✓ El jefe o gerente que aprueba los accesos solicitados debe garantizar que sean correspondientes al perfil del solicitante.<br>
    ✓ La aprobación es señal de conformidad y compromiso de pago asociado.<br>
    ✓ VOLVO PERÚ puede considerar no otorgar accesos que no están de acuerdo al perfil del solicitante.
</div>

<div class="firmas">
    <table>
        <tr>
            <td width="50%">Firma<br>Solicitante</td>
            <td width="50%">Firma Y Sello<br>Jefe / Gerente</td>
        </tr>
    </table>
</div>

<div class="pie">
    <strong>FECHA:</strong> {$fecha} &nbsp;&nbsp;&nbsp; <strong>VERSIÓN:</strong> 3.6
</div>
HTML;
    
    // Escribir HTML al PDF
    $mpdf->WriteHTML($html);
    
    // Guardar
    $nombreArchivo = $solicitud['folio'] . '.pdf';
    $dirDestino = __DIR__ . '/../documents/solicitudes_vol/';
    
    if (!file_exists($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }
    
    $rutaDestino = $dirDestino . $nombreArchivo;
    $mpdf->Output($rutaDestino, \Mpdf\Output\Destination::FILE);
    
    return $rutaDestino;
}


