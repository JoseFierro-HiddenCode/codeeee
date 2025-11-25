<?php
// Configuración de conexión a SQL Server
$serverName = "localhost";
$database = "tickets_ti";

// Autenticación SQL Server (con usuario y contraseña)
$connectionInfo = array(
    "Database" => $database,
    "UID" => "tickets_user",
    "PWD" => "Tickets2024!",
    "CharacterSet" => "UTF-8"
);

// Conectar a SQL Server
$conn = sqlsrv_connect($serverName, $connectionInfo);

// Verificar conexión
if ($conn === false) {
    die("<h3>Error al conectar con SQL Server</h3><pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// Función helper para ejecutar queries
function ejecutarQuery($sql, $params = array()) {
    global $conn;
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        die("<h3>Error en la consulta</h3><pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
    }
    return $stmt;
}

// Función para obtener un solo registro
function obtenerUno($sql, $params = array()) {
    $stmt = ejecutarQuery($sql, $params);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row;
}

// Función para obtener múltiples registros
function obtenerTodos($sql, $params = array()) {
    $stmt = ejecutarQuery($sql, $params);
    $results = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}
?>