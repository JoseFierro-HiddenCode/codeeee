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


    /**
 * Generar PDF directamente con MPDF (sin plantilla Word)
 */
function generarPDFDirectoVOL($solicitud, $accesosNuevos, $camposAdicionales) {
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Crear objeto MPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'Letter',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
    ]);
    
    // Preparar checkboxes marcados
    $accesos = [];
    foreach ($accesosNuevos as $acc) {
        $accesos[$acc] = true;
    }
    
    // Función helper para checkbox
    function cb($nombre, $accesos) {
        return isset($accesos[$nombre]) ? '[X]' : '[ ]';
    }
    
    // Tipo de solicitud
    $esCrear = $solicitud['tipo_solicitud'] == 'crear' ? '[X]' : '[ ]';
    $esSolicitar = $solicitud['tipo_solicitud'] == 'solicitar' ? '[X]' : '[ ]';
    $esLicencias = $solicitud['tipo_solicitud'] == 'licencias' ? '[X]' : '[ ]';
    $esEliminar = $solicitud['tipo_solicitud'] == 'eliminar' ? '[X]' : '[ ]';
    
    // HTML del documento
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 9pt; }
        h1 { text-align: center; font-size: 14pt; margin-bottom: 20px; }
        .tipo-solicitud { text-align: center; margin-bottom: 15px; font-size: 10pt; }
        .datos-empleado { margin-bottom: 15px; }
        .datos-empleado table { width: 100%; border-collapse: collapse; }
        .datos-empleado td { padding: 3px; font-size: 9pt; }
        .seccion { margin-top: 10px; margin-bottom: 5px; font-weight: bold; font-size: 10pt; }
        .checkbox { font-family: "Courier New", monospace; }
        table.accesos { width: 100%; }
        table.accesos td { padding: 2px; vertical-align: top; width: 33%; }
        .footer { margin-top: 20px; font-size: 8pt; line-height: 1.4; }
        .firmas { margin-top: 30px; }
        .firmas table { width: 100%; }
        .firmas td { text-align: center; padding-top: 40px; border-top: 1px solid #000; }
    </style>
    
    <h1>Formulario De Solicitud De Accesos Y Licencias</h1>
    
    <div class="tipo-solicitud">
        <span class="checkbox">' . $esCrear . '</span> Crear Usuario &nbsp;&nbsp;
        <span class="checkbox">' . $esSolicitar . '</span> Solicitar Accesos &nbsp;&nbsp;
        <span class="checkbox">' . $esLicencias . '</span> Solicitar Licencias &nbsp;&nbsp;
        <span class="checkbox">' . $esEliminar . '</span> Eliminar Usuario
    </div>
    
    <div class="datos-empleado">
        <table>
            <tr>
                <td><strong>Nombre:</strong> ' . htmlspecialchars($solicitud['nombre']) . '</td>
                <td><strong>Apellido:</strong> ' . htmlspecialchars($solicitud['apellido']) . '</td>
            </tr>
            <tr>
                <td><strong>ID Usuario:</strong> ' . htmlspecialchars($solicitud['id_usuario'] ?? '') . '</td>
                <td><strong>Teléfono:</strong> ' . htmlspecialchars($solicitud['telefono'] ?? '') . '</td>
            </tr>
            <tr>
                <td><strong>Cargo:</strong> ' . htmlspecialchars($solicitud['cargo'] ?? '') . '</td>
                <td><strong>Concesionario:</strong> ' . htmlspecialchars($solicitud['concesionario'] ?? '') . '</td>
            </tr>
            <tr>
                <td><strong>Sucursal:</strong> ' . htmlspecialchars($solicitud['sucursal'] ?? '') . '</td>
                <td><strong>Email:</strong> ' . htmlspecialchars($solicitud['correo_corporativo'] ?? '') . '</td>
            </tr>
        </table>
    </div>
    
    <div class="seccion">VOLVO TRUCKS, VOLVO BUSES & UD TRUCKS</div>
    <table class="accesos">
        <tr>
            <td class="checkbox">' . cb('Trucks Portal Volvo', $accesos) . ' Trucks Portal Volvo</td>
            <td class="checkbox">' . cb('Argus Dealer', $accesos) . ' Argus Dealer</td>
            <td class="checkbox">' . cb('Dynafleet', $accesos) . ' Dynafleet</td>
        </tr>
        <tr>
            <td class="checkbox">' . cb('Impact', $accesos) . ' Impact</td>
            <td class="checkbox">' . cb('Parts Online', $accesos) . ' Parts Online</td>
            <td class="checkbox">' . cb('Product History Viewer', $accesos) . ' Product History Viewer</td>
        </tr>
        <tr>
            <td class="checkbox">' . cb('Technical Service Bulletin', $accesos) . ' Technical Service Bulletin</td>
            <td class="checkbox">' . cb('Truck Campaign Information', $accesos) . ' Truck Campaign Information</td>
            <td class="checkbox">' . cb('Trucks Portal UD', $accesos) . ' Trucks Portal UD</td>
        </tr>
        <tr>
            <td class="checkbox">' . cb('UD Product History Viewer', $accesos) . ' UD Product History Viewer</td>
            <td class="checkbox">' . cb('VOSP', $accesos) . ' VOSP</td>
            <td class="checkbox">' . cb('Wiring Diagrams', $accesos) . ' Wiring Diagrams</td>
        </tr>
    </table>
    
    <div class="seccion">MACK TRUCKS</div>
    <table class="accesos">
        <tr>
            <td class="checkbox">' . cb('Mack Trucks Dealer Portal', $accesos) . ' Trucks Dealer Portal</td>
            <td class="checkbox">' . cb('Mack Electronic Info System', $accesos) . ' Electronic Info System</td>
            <td class="checkbox">' . cb('Mack Impact', $accesos) . ' Impact</td>
        </tr>
        <tr>
            <td class="checkbox">' . cb('Mack Product History Viewer', $accesos) . ' Product History Viewer</td>
            <td></td>
            <td></td>
        </tr>
    </table>
    
    <div class="seccion">FACTURACIÓN</div>
    <table class="accesos">
        <tr>
            <td class="checkbox">' . cb('LDS', $accesos) . ' LDS &nbsp; Rol: ' . htmlspecialchars($camposAdicionales['lds_rol'] ?? '') . '</td>
            <td class="checkbox">' . cb('GDS', $accesos) . ' GDS &nbsp; Rol: ' . htmlspecialchars($camposAdicionales['gds_rol'] ?? '') . '</td>
            <td class="checkbox">' . cb('Time Recording', $accesos) . ' Time Recording &nbsp; Código: ' . htmlspecialchars($camposAdicionales['time_recording_codigo'] ?? '') . '</td>
        </tr>
    </table>
    
    <div class="footer">
        <strong>COMPROMISO PARA AUTORIZACIONES INDIVIDUALES</strong><br>
        El solicitante, jefe y gerente, están de acuerdo con los siguientes puntos:<br>
        • Las informaciones son propiedad de la empresa<br>
        • Los accesos deben corresponder al perfil del solicitante<br>
        • La aprobación es señal de conformidad y compromiso de pago
    </div>
    
    <div class="firmas">
        <table>
            <tr>
                <td>Firma Solicitante</td>
                <td>Firma y Sello Jefe/Gerente</td>
            </tr>
        </table>
    </div>
    
    <p style="text-align: right; margin-top: 20px;">
        <strong>FECHA:</strong> ' . date('d/m/Y') . ' &nbsp;&nbsp; <strong>VERSIÓN:</strong> 3.6
    </p>
    ';
    
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
}

