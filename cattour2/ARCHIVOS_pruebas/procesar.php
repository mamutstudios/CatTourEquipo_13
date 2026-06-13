<?php
// Carpeta de destino (ya la creamos antes)
$directorio = "uploads/documentos/";
$archivo_final = $directorio . basename($_FILES["archivo_pdf"]["name"]);
$tipoArchivo = strtolower(pathinfo($archivo_final, PATHINFO_EXTENSION));

echo "<h3>Estado de la carga:</h3>";

// Validar que sea PDF
if($tipoArchivo != "pdf") {
    die("❌ Error: Solo se permiten archivos PDF.");
}

// Intentar mover el archivo
if (move_uploaded_file($_FILES["archivo_pdf"]["tmp_name"], $archivo_final)) {
    echo "<p style='color:green;'>✅ ¡ÉXITO! El archivo <b>". htmlspecialchars(basename($_FILES["archivo_pdf"]["name"])). "</b> ha sido subido.</p>";
    echo "<a href='index.php'>Volver a subir otro</a>";
} else {
    echo "❌ Hubo un error al subir el archivo. Revisa los permisos de la carpeta.";
}
?>
