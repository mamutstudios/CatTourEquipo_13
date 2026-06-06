<?php
// Incluimos tu configuración de base de datos
include '../config/db.php';

// Definimos los datos exactos que quieres
$usuario = 'OWNERr';
$password_plana = 'cerro_07i'; // <--- Con la letra 'o' como pediste

// Generamos el hash nuevo
$nuevo_hash = password_hash($password_plana, PASSWORD_BCRYPT);

// Preparamos la actualización
$sql = "UPDATE Empleado SET password = ? WHERE usuario = ?";

echo "<h2>⚙️ Actualizador de Credenciales CatTour</h2>";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $nuevo_hash, $usuario);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ <b>ÉXITO:</b> La base de datos ha sido actualizada.</p>";
        echo "<p><b>Usuario:</b> $usuario</p>";
        echo "<p><b>Contraseña ahora válida:</b> $password_plana</p>";
        echo "<p><b>Hash guardado:</b> $nuevo_hash</p>";
        echo "<hr>";
        echo "<p>👉 <a href='LoginEmploy.php'>Ir al Login de Personal</a></p>";
    } else {
        echo "<p style='color: red;'>❌ Error al ejecutar: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: red;'>❌ Error de conexión: " . $conn->error . "</p>";
}
?>
