<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/teams_webhook.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/crear-ticket.php');
    exit();
}

protegerPagina(['usuario']);

$userId = $_SESSION['user_id'];
$titulo = $_POST['titulo'] ?? '';
$categoria_id = $_POST['categoria_id'] ?? '';
$categoria_otro = $_POST['categoria_otro'] ?? null;
$prioridad = $_POST['prioridad'] ?? 'media';
$descripcion = $_POST['descripcion'] ?? '';
$equipo_asignado = $_POST['equipo_asignado'] ?? 'soporte'; // NUEVO: Capturar equipo

// Validaciones básicas
if (empty($titulo) || empty($categoria_id) || empty($descripcion)) {
    header('Location: ../views/crear-ticket.php?error=campos_vacios');
    exit();
}

try {
    // Insertar ticket
    $sql = "
        INSERT INTO tickets (titulo, descripcion, usuario_id, categoria_id, categoria_otro, prioridad, estado, equipo_asignado, created_at, updated_at)
        OUTPUT INSERTED.id
        VALUES (?, ?, ?, ?, ?, ?, 'abierto', ?, GETDATE(), GETDATE())
    ";
    
    $stmt = sqlsrv_query($conn, $sql, array($titulo, $descripcion, $userId, $categoria_id, $categoria_otro, $prioridad, $equipo_asignado));
    
    if ($stmt === false) {
        throw new Exception("Error al crear ticket");
    }
    
    // Obtener ID del ticket creado
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $ticketId = $row['id'];
    sqlsrv_free_stmt($stmt);
    
    // Procesar imágenes si hay
    if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
        $totalImagenes = count($_FILES['imagenes']['name']);
        
        for ($i = 0; $i < min($totalImagenes, 5); $i++) {
            if ($_FILES['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
                $archivo = array(
                    'name' => $_FILES['imagenes']['name'][$i],
                    'type' => $_FILES['imagenes']['type'][$i],
                    'tmp_name' => $_FILES['imagenes']['tmp_name'][$i],
                    'error' => $_FILES['imagenes']['error'][$i],
                    'size' => $_FILES['imagenes']['size'][$i]
                );
                
                $resultado = subirImagen($archivo, 'tickets');
                
                if (isset($resultado['success']) && $resultado['success']) {
                    // Guardar en base de datos
                    $sqlImg = "
                        INSERT INTO images (ticket_id, ruta, nombre_original, tamanio, mime_type, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, GETDATE(), GETDATE())
                    ";
                    ejecutarQuery($sqlImg, array(
                        $ticketId,
                        $resultado['ruta'],
                        $resultado['nombre_original'],
                        $resultado['tamanio'],
                        $resultado['mime_type']
                    ));
                }
            }
        }
    }
    
    // Obtener información completa del ticket para notificación
    $sqlTicket = "
        SELECT t.*, c.nombre as categoria_nombre, u.nombre, u.apellido
        FROM tickets t
        LEFT JOIN categories c ON t.categoria_id = c.id
        LEFT JOIN users u ON t.usuario_id = u.id
        WHERE t.id = ?
    ";
    $ticket = obtenerUno($sqlTicket, array($ticketId));
    
    // Enviar notificación a Teams
    if ($ticket) {
        $usuario = array(
            'nombre' => $ticket['nombre'],
            'apellido' => $ticket['apellido']
        );
        
        $ticketData = array(
            'id' => $ticketId,
            'titulo' => $titulo,
            'prioridad' => $prioridad,
            'categoria' => $ticket['categoria_otro'] ? $ticket['categoria_otro'] : $ticket['categoria_nombre'],
            'descripcion' => $descripcion
        );
        
        notificarNuevoTicket($ticketData, $usuario);
    }
    
    // Redirigir al ticket creado
    header("Location: ../views/ver-ticket.php?id=$ticketId&success=1");
    exit();
    
} catch (Exception $e) {
    error_log("Error al crear ticket: " . $e->getMessage());
    header('Location: ../views/crear-ticket.php?error=1');
    exit();
}
?>