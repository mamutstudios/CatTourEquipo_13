<?php
include '../config/db.php';

// Validar que venga un ID de viaje
if(!isset($_GET['id'])) { 
    header("Location: ../index.php"); 
    exit; 
}

$idViaje = (int)$_GET['id'];

// Obtener información detallada del viaje
$queryViaje = $conn->query("SELECT * FROM ViajeDetalles WHERE idViaje = $idViaje");
$viaje = $queryViaje->fetch_assoc();

if(!$viaje) { echo "Viaje no encontrado."; exit; }

// Obtener puntos de recolección específicos de este viaje
$queryPuntos = $conn->query("SELECT * FROM PuntosRecoleccion WHERE idViaje = $idViaje");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar: <?php echo $viaje['pais']; ?> - CatTour</title>
    <style>
        :root {
            --morado-principal: #6f42c1;
            --morado-oscuro: #4b2c85;
            --gris-fondo: #f8f9fa;
        }

        body { font-family: 'Segoe UI', sans-serif; background: var(--gris-fondo); margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: auto; display: grid; grid-template-columns: 1fr 350px; gap: 20px; }

        /* Tarjeta de Detalles */
        .detalles-viaje, .formulario-reserva { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        
        .header-reserva { background: var(--morado-principal); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 24px; }
        
        .precio-tag { font-size: 28px; color: var(--morado-principal); font-weight: bold; }
        .itinerario { line-height: 1.6; color: #555; white-space: pre-line; }

        /* Formulario Estilizado */
        label { display: block; margin-top: 15px; font-weight: bold; color: var(--morado-oscuro); }
        input, select { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        
        .btn-cotizar { 
            background: var(--morado-principal); color: white; border: none; padding: 15px; width: 100%; 
            border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; margin-top: 20px;
            transition: 0.3s;
        }
        .btn-cotizar:hover { background: var(--morado-oscuro); }

        /* Panel Dinámico de Pasajeros */
        #panel-pasajeros { display: none; margin-top: 30px; border-top: 2px dashed #ddd; padding-top: 20px; }
        .pasajero-box { background: #f3effb; padding: 15px; border-radius: 10px; margin-bottom: 10px; border-left: 5px solid var(--morado-principal); }

        @media (max-width: 768px) { .container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="detalles-viaje">
        <div class="header-reserva">
            <h1> <?php echo $viaje['pais']; ?></h1>
            <span><?php echo $viaje['ruta']; ?></span>
        </div>
        
        <h3>Sobre este viaje</h3>
        <p class="itinerario"><?php echo $viaje['lugaresVisitar']; ?></p>
        
        <hr>
        <h3>Duración</h3>
        <p>⏱️ <?php echo $viaje['dias']; ?> días y <?php echo $viaje['noches']; ?> noches de aventura.</p>
    </div>

    <div class="formulario-reserva">
        <div class="precio-tag">$<?php echo number_format($viaje['precioBoleto'], 2); ?> <small style="font-size: 12px; color: #999;">MXN p/p</small></div>
        
        <form action="procesar_reserva.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="idViaje" value="<?php echo $idViaje; ?>">
            <input type="hidden" id="precioUnitario" value="<?php echo $viaje['precioBoleto']; ?>">

            <label>Punto de Salida:</label>
            <select name="punto_recoleccion" required>
                <option value="">-- Selecciona donde te subes --</option>
                <?php while($p = $queryPuntos->fetch_assoc()): ?>
                    <option value="<?php echo $p['nombrePunto']; ?>">
                        <?php echo $p['nombrePunto']; ?> (<?php echo date("H:i", strtotime($p['horaCita'])); ?> hrs)
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Boletos a comprar:</label>
            <input type="number" name="cantidad_boletos" id="cant_boletos" min="1" max="10" value="1" onchange="calcularTotal()">

            <div style="margin-top: 20px; font-size: 18px;">
                Total: <span id="total_compra" style="color:var(--morado-principal); font-weight:bold;">$<?php echo number_format($viaje['precioBoleto'], 2); ?></span>
            </div>

            <button type="button" class="btn-cotizar" id="btn-pasajeros" onclick="mostrarPasajeros()">SIGUIENTE: DATOS PASAJEROS</button>

            <div id="panel-pasajeros">
                <label>Tu Correo de Contacto:</label>
                <input type="email" name="correo_cliente" placeholder="Para enviarte tu confirmación" required>
                
                <div id="inputs-pasajeros"></div>
                
                <button type="submit" class="btn-cotizar" style="background: #28a745;">FINALIZAR Y SUBIR DOCUMENTOS</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcularTotal() {
    const cant = document.getElementById('cant_boletos').value;
    const precio = document.getElementById('precioUnitario').value;
    const total = cant * precio;
    document.getElementById('total_compra').innerText = '$' + total.toLocaleString('es-MX');
    
    // Si el usuario cambia la cantidad después de haber abierto el panel, reseteamos el panel
    document.getElementById('panel-pasajeros').style.display = 'none';
}

function mostrarPasajeros() {
    const cant = document.getElementById('cant_boletos').value;
    const contenedor = document.getElementById('inputs-pasajeros');
    contenedor.innerHTML = '<h3>Datos de Viajeros</h3>';

    for(let i = 1; i <= cant; i++) {
        contenedor.innerHTML += `
            <div class="pasajero-box">
                <strong>Pasajero #${i}</strong>
                <input type="text" name="nombres[]" placeholder="Nombre completo según identificación" required>
                <label>Pasaporte / INE (PDF):</label>
                <input type="file" name="documentos[]" accept=".pdf" required>
            </div>
        `;
    }
    document.getElementById('panel-pasajeros').style.display = 'block';
    document.getElementById('btn-pasajeros').style.display = 'none';
}
</script>

</body>
</html>
