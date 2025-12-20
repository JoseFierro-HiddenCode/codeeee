<?php
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Generar folio único para solicitudes VOL
 */
function generarFolioVOL() {
    $año = date('Y');
    
    // Obtener el último folio del año actual
    $sql = "SELECT TOP 1 folio FROM solicitudes_accesos_vol_historial 
            WHERE folio LIKE ? 
            ORDER BY id DESC";
    
    $ultimoFolio = obtenerUno($sql, ['VOL-' . $año . '-%']);
    
    if ($ultimoFolio) {
        // Extraer el número del folio (VOL-2025-001 -> 001)
        $partes = explode('-', $ultimoFolio['folio']);
        $ultimoNumero = intval($partes[2]);
        $nuevoNumero = $ultimoNumero + 1;
    } else {
        // Primer folio del año
        $nuevoNumero = 1;
    }
    
    // Formatear con ceros a la izquierda (001, 002, etc.)
    return 'VOL-' . $año . '-' . str_pad($nuevoNumero, 3, '0', STR_PAD_LEFT);
}

/**
 * Mapear nombres de accesos de BD a variables de plantilla Word
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
 * Generar documento Word desde plantilla (VERSIÓN CON COPIA TEMPORAL)
 */
function generarWordDesdePlantillaVOL($solicitud, $accesosNuevos, $camposAdicionales) {
    $rutaPlantillaOriginal = __DIR__ . '/../documents/templates/plantilla_solicitud_accesos_vol.docx';

    if (!file_exists($rutaPlantillaOriginal)) {
        throw new Exception("Plantilla no encontrada en: " . $rutaPlantillaOriginal);
    }

    // === CREAR COPIA TEMPORAL DE LA PLANTILLA ===
    $dirTemporal = __DIR__ . '/../documents/temp/';
    if (!file_exists($dirTemporal)) {
        mkdir($dirTemporal, 0755, true);
    }
    
    $rutaPlantillaTemporal = $dirTemporal . 'plantilla_temp_' . uniqid() . '.docx';
    
    if (!copy($rutaPlantillaOriginal, $rutaPlantillaTemporal)) {
        throw new Exception("No se pudo crear copia temporal de la plantilla");
    }

    try {
        $templateProcessor = new TemplateProcessor($rutaPlantillaTemporal);

        // === CREAR ARRAY CON TODOS LOS VALORES ===
        
        // Checkboxes marcados
        $checkboxesMarcados = [];
        foreach ($accesosNuevos as $acceso) {
            $variable = obtenerNombreVariable($acceso);
            if ($variable) {
                $checkboxesMarcados[$variable] = true;
            }
        }
        
        // Lista completa de checkboxes
        $todosCheckboxes = [
            'CB_TRUCKS_PORTAL', 'CB_ARGUS_DEALER', 'CB_DYNAFLEET', 'CB_IMPACT_VT',
            'CB_PARTS_ONLINE', 'CB_PRODUCT_HISTORY', 'CB_TECHNICAL_SERVICE', 'CB_TRUCK_CAMPAIGN',
            'CB_TRUCKS_PORTAL_UD', 'CB_UD_PRODUCT_HISTORY', 'CB_VOSP', 'CB_WIRING_DIAGRAMS',
            'CB_MACK_TRUCKS_DEALER', 'CB_MACK_ELECTRONIC_INFO', 'CB_MACK_IMPACT', 'CB_MACK_PRODUCT_HISTORY',
            'CB_VDN', 'CB_CARETRACK', 'CB_CHAIN', 'CB_PROSIS_PRO', 'CB_VLC',
            'CB_TECH_TOOL_MATRIS', 'CB_TT_ACCESOS', 'CB_TT_LICENCIA',
            'CB_VPPN', 'CB_EPC_OFFLINE', 'CB_VODIA5', 'CB_VODIA_ACCESO', 'CB_VODIA_LICENCIA',
            'CB_VTT', 'CB_VTT_NUEVA', 'CB_VTT_RENOVACION',
            'CB_VTT_VOLVO_TRUCKS', 'CB_VTT_VOLVO_BUSES', 'CB_VTT_MACK_TRUCKS', 'CB_VTT_UD_TRUCKS',
            'CB_LDS', 'CB_GDS', 'CB_TIME_RECORDING',
            'CB_UCHP', 'CB_UCHP_VTC', 'CB_UCHP_VBC', 'CB_UCHP_MACK',
            'CB_UCHP_UD', 'CB_UCHP_VCE', 'CB_UCHP_PENTA', 'CB_WARRANTY_BULLETIN', 'CB_VDA_PLUS',
            'CB_TSA'
        ];
        
        // Preparar array completo de valores
        $valores = [
            // Tipo de solicitud
            'TIPO_CREAR' => $solicitud['tipo_solicitud'] == 'crear' ? 'X' : ' ',
            'TIPO_SOLICITAR' => $solicitud['tipo_solicitud'] == 'solicitar' ? 'X' : ' ',
            'TIPO_LICENCIAS' => $solicitud['tipo_solicitud'] == 'licencias' ? 'X' : ' ',
            'TIPO_ELIMINAR' => $solicitud['tipo_solicitud'] == 'eliminar' ? 'X' : ' ',
            
            // Datos del empleado
            'NOMBRE' => $solicitud['nombre'],
            'APELLIDO' => $solicitud['apellido'],
            'ID_USUARIO' => $solicitud['id_usuario'] ?? '',
            'TELEFONO' => $solicitud['telefono'] ?? '',
            'CARGO' => $solicitud['cargo'] ?? '',
            'CONCESIONARIO' => $solicitud['concesionario'] ?? '',
            'SUCURSAL' => $solicitud['sucursal'] ?? '',
            'EMAIL' => $solicitud['correo_corporativo'] ?? '',
            
            // Campos adicionales
            'PROSIS_PRECIO' => $camposAdicionales['prosis_precio'] ?? '',
            'VLC_PRECIO' => $camposAdicionales['vlc_precio'] ?? '',
            'VTT_PRECIO' => $camposAdicionales['vtt_precio'] ?? '',
            'LDS_ROL' => $camposAdicionales['lds_rol'] ?? '',
            'GDS_ROL' => $camposAdicionales['gds_rol'] ?? '',
            'TIME_RECORDING_CODIGO' => $camposAdicionales['time_recording_codigo'] ?? '',
            'OTRO_ACCESO_TEXTO' => $camposAdicionales['otro_acceso_texto'] ?? '',
            
            // Fecha
            'FECHA' => date('d/m/Y')
        ];
        
        // Agregar checkboxes (marcados con X, no marcados con espacio)
        foreach ($todosCheckboxes as $cb) {
            $valores[$cb] = isset($checkboxesMarcados[$cb]) ? 'X' : ' ';
        }
        
        // === REEMPLAZAR TODOS LOS VALORES DE UNA VEZ ===
        $templateProcessor->setValues($valores);

        // === GUARDAR DOCUMENTO WORD ===
        $nombreArchivo = $solicitud['folio'] . '.docx';
        $dirDestino = __DIR__ . '/../documents/solicitudes_vol/';

        if (!file_exists($dirDestino)) {
            mkdir($dirDestino, 0755, true);
        }

        $rutaDestino = $dirDestino . $nombreArchivo;
        $templateProcessor->saveAs($rutaDestino);
        
        // === LIMPIAR PLANTILLA TEMPORAL ===
        unlink($rutaPlantillaTemporal);

        return $rutaDestino;
        
    } catch (Exception $e) {
        // Limpiar plantilla temporal en caso de error
        if (file_exists($rutaPlantillaTemporal)) {
            unlink($rutaPlantillaTemporal);
        }
        throw $e;
    }
}