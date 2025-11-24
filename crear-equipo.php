<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/inventario/gestionar-equipos.php');
    exit();
}

// Solo Admin y TI pueden crear equipos
protegerPagina(['admin_tecnico', 'tecnico']);

// Obtener datos del formulario
$tipo = $_POST['tipo'] ?? '';
$marca = $_POST['marca'] ?? null;
$modelo = $_POST['modelo'] ?? null;
$numero_serie = $_POST['numero_serie'] ?? '';
$procesador = $_POST['procesador'] ?? null;
$sistema_operativo = $_POST['sistema_operativo'] ?? null;
$color = $_POST['color'] ?? null;
$estado = $_POST['estado'] ?? 'bueno';
$notas = $_POST['notas'] ?? null;

// Validaciones
if (empty($tipo) || empty($numero_serie)) {
    header('Location: ../../views/inventario/gestionar-equipos.php?error=' . urlencode('Tipo y número de serie son obligatorios'));
    exit();
}

// Verificar que el número de serie no exista
$sqlVerificar = "SELECT id FROM equipos WHERE numero_serie = ?";
$existe = obtenerUno($sqlVerificar, [$numero_serie]);

if ($existe) {
    header('Location: ../../views/inventario/gestionar-equipos.php?error=' . urlencode('El número de serie ya existe'));
    exit();
}

try {
    // Insertar equipo
    $sql = "
        INSERT INTO equipos (
            tipo, marca, modelo, numero_serie, procesador, 
            sistema_operativo, color, estado, disponible, notas,
            created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, GETDATE(), GETDATE())
    ";
    
    $params = [
        $tipo,
        $marca,
        $modelo,
        $numero_serie,
        $procesador,
        $sistema_operativo,
        $color,
        $estado,
        $notas
    ];
    
    ejecutarQuery($sql, $params);
    
    // Redirigir con éxito
    header('Location: ../../views/inventario/gestionar-equipos.php?success=creado');
    exit();
    
} catch (Exception $e) {
    error_log("Error al crear equipo: " . $e->getMessage());
    header('Location: ../../views/inventario/gestionar-equipos.php?error=' . urlencode('Error al crear el equipo'));
    exit();
}
?>