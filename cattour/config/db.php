<?php
$host = "localhost";
$user = "user_cattour";
$pass = "1234"; // La contraseña que asignaste
$db   = "agendaVIAJE";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
