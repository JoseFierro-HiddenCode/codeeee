<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificar que sea admin o gerente
if (!in_array($_SESSION['rol'], ['admin_tecnico', 'gerente'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

// Validar datos
if (!isset($_POST['ticket_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$ticketId = intval($_POST['ticket_id']);

// NUEVA FUNCIONALIDAD: Detectar auto-asignación
if (isset($_POST['auto_asignar']) && $_POST['auto_asignar'] === 'true') {
    // El admin se auto-asigna el ticket
    $tecnicoId = $_SESSION['user_id'];
    $mensaje = 'Ticket auto-asignado correctamente';
} else {
    // Asignación normal a otro técnico
    $tecnicoId = isset($_POST['tecnico_id']) && $_POST['tecnico_id'] !== '' ? intval($_POST['tecnico_id']) : null;
    $mensaje = $tecnicoId === null ? 'Técnico desasignado correctamente' : 'Técnico asignado correctamente';
}

// Actualizar asignación
if ($tecnicoId === null) {
    // Desasignar técnico
    $sql = "UPDATE tickets SET tecnico_asignado_id = NULL, fecha_asignacion = NULL WHERE id = ?";
    $params = array($ticketId);
} else {
    // Asignar técnico (sea auto-asignación o asignación normal)
    $sql = "UPDATE tickets SET tecnico_asignado_id = ?, fecha_asignacion = GETDATE() WHERE id = ?";
    $params = array($tecnicoId, $ticketId);
}

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error al actualizar: ' . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

sqlsrv_free_stmt($stmt);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => $mensaje,
    'ticket_id' => $ticketId,
    'tecnico_id' => $tecnicoId
]);
exit;