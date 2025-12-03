<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificar permisos (solo admin y gerente pueden archivar)
if (!in_array($_SESSION['rol'], ['admin_tecnico', 'gerente'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos para archivar tickets']);
    exit;
}

// Validar datos
if (!isset($_POST['ticket_id'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$ticketId = intval($_POST['ticket_id']);

// Verificar que el ticket existe y obtener su estado
$sqlTicket = "SELECT id, estado, archivado FROM dbo.tickets WHERE id = ?";
$ticket = obtenerUno($sqlTicket, array($ticketId));

if (!$ticket) {
    echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
    exit;
}

// Verificar que no esté ya archivado
if ($ticket['archivado'] == 1) {
    echo json_encode(['success' => false, 'message' => 'El ticket ya está archivado']);
    exit;
}

// Verificar que el ticket esté cerrado
if ($ticket['estado'] != 'cerrado') {
    echo json_encode([
        'success' => false, 
        'message' => 'Solo se pueden archivar tickets cerrados. Este ticket está: ' . $ticket['estado']
    ]);
    exit;
}

// Archivar el ticket
$sqlUpdate = "UPDATE dbo.tickets 
              SET archivado = 1, 
                  fecha_archivado = GETDATE(),
                  updated_at = GETDATE()
              WHERE id = ?";

$stmt = sqlsrv_query($conn, $sqlUpdate, array($ticketId));

if ($stmt === false) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al archivar: ' . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

sqlsrv_free_stmt($stmt);

// Respuesta exitosa
echo json_encode([
    'success' => true,
    'message' => 'Ticket #' . $ticketId . ' archivado correctamente',
    'ticket_id' => $ticketId
]);
exit;