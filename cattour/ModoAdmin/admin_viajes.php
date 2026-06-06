<?php
include 'config/db.php';
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pais   = $conn->real_escape_string($_POST['pais']);
    $ruta   = $conn->real_escape_string($_POST['ruta']);
    $dias   = (int)$_POST['dias'];
    $noches = (int)$_POST['noches'];
    $precio = (float)$_POST['precio'];
    $mes    = (int)$_POST['mes'];
    $anio   = (int)$_POST['anio'];
    $detalles = $conn->real_escape_string($_POST['detalles']);

    $sql = "INSERT INTO ViajeDetalles (pais, ruta, dias, noches, lugaresVisitar, precioBoleto, mesSalida, anioSalida) 
            VALUES ('$pais', '$ruta', $dias, $noches, '$detalles', $precio, $mes, $anio)";

    if ($conn->query($sql) === TRUE) {
        $idViaje = $conn->insert_id;

        // PROCESAR PUNTOS DE RECOLECCIÓN
        if (isset($_POST['lugares']) && isset($_POST['horas'])) {
            $lugares = $_POST['lugares']; // Es un array
            $horas = $_POST['horas'];     // Es un array
            
            for ($i = 0; $i < count($lugares); $i++) {
                $lugar = $conn->real_escape_string($lugares[$i]);
                $hora = $conn->real_escape_string($horas[$i]);
                if (!empty($lugar) && !empty($hora)) {
                    $conn->query("INSERT INTO PuntosRecoleccion (idViaje, nombrePunto, horaCita) VALUES ($idViaje, '$lugar', '$hora')");
                }
            }
        }

        $rutaCarpeta = "uploads/viajes/idViaje_" . $idViaje;
        if (!file_exists($rutaCarpeta)) { mkdir($rutaCarpeta, 0775, true); }
        $mensaje = "<div class='success'>✅ Viaje creado exitosamente con sus puntos de recolección.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - CatTour</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 700px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .punto-row { display: flex; gap: 10px; background: #f9f9f9; padding: 10px; border-radius: 5px; margin-bottom: 10px; border: 1px solid #eee; }
        .btn-add { background: #28a745; color: white; border: none; padding: 10px; cursor: pointer; border-radius: 5px; margin-bottom: 20px; }
        .btn-submit { width: 100%; padding: 15px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🚢 Panel Administrativo CatTour</h2>
        <?php echo $mensaje; ?>
        
        <form method="POST">
            <input type="text" name="pais" placeholder="País Destino" required>
            <input type="text" name="ruta" placeholder="Ruta Completa" required>
            
            <div style="display: flex; gap: 10px;">
                <input type="number" name="dias" placeholder="Días" required>
                <input type="number" name="noches" placeholder="Noches" required>
                <input type="number" step="0.01" name="precio" placeholder="Precio MXN" required>
            </div>

            <label>Mes y Año de Salida:</label>
            <div style="display: flex; gap: 10px;">
                <select name="mes">
                    <option value="6">Junio</option><option value="7">Julio</option>
                    <option value="8">Agosto</option><option value="9">Septiembre</option>
                </select>
                <input type="number" name="anio" value="2026">
            </div>

            <hr>
            <h3>📍 Puntos de Recolección</h3>
            <div id="contenedor-puntos">
                <div class="punto-row">
                    <input type="text" name="lugares[]" placeholder="Lugar (ej. Plaza Américas)" required>
                    <input type="time" name="horas[]" required>
                </div>
            </div>
            <button type="button" class="btn-add" onclick="agregarPunto()">+ Añadir otro punto</button>
            <hr>

            <textarea name="detalles" placeholder="Descripción e Itinerario..." rows="3"></textarea>
            <button type="submit" class="btn-submit">Publicar Viaje</button>
        </form>
    </div>

    <script>
        function agregarPunto() {
            const div = document.createElement('div');
            div.className = 'punto-row';
            div.innerHTML = `
                <input type="text" name="lugares[]" placeholder="Lugar" required>
                <input type="time" name="horas[]" required>
                <button type="button" onclick="this.parentElement.remove()" style="background:red; color:white; border:none; border-radius:5px; padding:5px 10px; cursor:pointer;">X</button>
            `;
            document.getElementById('contenedor-puntos').appendChild(div);
        }
    </script>
</body>
</html>
