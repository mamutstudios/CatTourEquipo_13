<?php
include '../config/db.php';

// Validar que venga un ID de viaje válido
if(!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$idViaje = (int)$_GET['id'];

// =========================================================================
// 1. Obtener información detallada del viaje (BLINDADO CON SENTENCIA PREPARADA)
// =========================================================================
$stmtViaje = $conn->prepare("SELECT * FROM ViajeDetalles WHERE idViaje = ?");
$stmtViaje->bind_param("i", $idViaje);
$stmtViaje->execute();
$resultadoViaje = $stmtViaje->get_result();
$viaje = $resultadoViaje->fetch_assoc();
$stmtViaje->close();

if(!$viaje) {
    echo "Viaje no encontrado.";
    exit;
}

// =========================================================================
// 2. Obtener los puntos de recolección (BLINDADO CON SENTENCIA PREPARADA)
// =========================================================================
$stmtPuntos = $conn->prepare("SELECT * FROM PuntosRecoleccion WHERE idViaje = ? ORDER BY fechaCita ASC, horaCita ASC");
$stmtPuntos->bind_param("i", $idViaje);
$stmtPuntos->execute();
$queryPuntos = $stmtPuntos->get_result();
// Nota: No cerramos $stmtPuntos aquí porque la variable $queryPuntos se recorre más abajo en el HTML

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar: <?php echo htmlspecialchars($viaje['pais']); ?> - CatTour</title>
    <style>
        :root {
            --morado-principal: #6f42c1;
            --morado-oscuro: #4b2c85;
            --gris-fondo: #f8f9fa;
            --lila-suave: #f3effb;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--gris-fondo);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 25px;
            margin-top: 20px;
        }

        .detalles-viaje, .formulario-reserva {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            box-sizing: border-box;
        }

        .header-reserva {
            background: linear-gradient(135deg, var(--morado-principal) 0%, #a29bfe 100%);
            color: white;
            padding: 30px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .header-reserva::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.35);
            z-index: 1;
        }

        .header-reserva * {
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.4);
        }

        h1 { margin: 0; font-size: 28px; text-transform: uppercase; }
        .header-route { display: block; margin-top: 5px; opacity: 0.95; font-size: 15px; font-weight: 500; }
        .header-date { display: block; margin-top: 5px; opacity: 0.9; font-size: 14px; font-weight: bold; color: #a8ffb2; }

        h3 {
            color: var(--morado-oscuro);
            border-bottom: 2px solid var(--lila-suave);
            padding-bottom: 8px;
            margin-top: 25px;
        }

        .texto-bloque { line-height: 1.6; color: #555; white-space: pre-line; font-size: 15px; }

        .precio-tag { font-size: 30px; color: var(--morado-principal); font-weight: bold; text-align: center; margin-bottom: 15px; }

        label { display: block; margin-top: 15px; font-weight: bold; color: var(--morado-oscuro); font-size: 14px; }

        select { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 14px; outline: none; }
        select:focus { border-color: var(--morado-principal); }

        /* ========================================================================= */
        /* MEJORA DE INTERFAZ: CONTROLES DE CANTIDAD GRANDES Y CÓMODOS */
        /* ========================================================================= */
        .selector-cantidad {
            display: flex;
            align-items: center;
            margin-top: 8px;
            gap: 5px;
        }

        .btn-control-boletos {
            background: #fff;
            border: 2px solid var(--morado-principal);
            color: var(--morado-principal);
            font-size: 22px;
            font-weight: bold;
            width: 45px;
            height: 45px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            transition: 0.2s;
        }

        .btn-control-boletos:hover {
            background: var(--morado-principal);
            color: white;
        }

        .input-boletos-ux {
            width: 70px;
            height: 45px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
            outline: none;
            box-sizing: border-box;
            color: #333;
        }

        /* Ocultar las flechas chicas nativas de Chrome, Safari, Edge y Firefox */
        .input-boletos-ux::-webkit-outer-spin-button,
        .input-boletos-ux::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .input-boletos-ux {
            -moz-appearance: textfield;
        }
        /* ========================================================================= */

        .btn-cotizar {
            background: var(--morado-principal);
            color: white;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
        }
        .btn-cotizar:hover { background: var(--morado-oscuro); }

        #panel-pasajeros { display: none; margin-top: 25px; border-top: 2px dashed #ddd; padding-top: 20px; }
        .pasajero-box { background: var(--lila-suave); padding: 18px; border-radius: 10px; margin-bottom: 15px; border-left: 5px solid var(--morado-principal); }

        .proceso-box {
            background: #fff9db;
            border-left: 4px solid #f59f00;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
            line-height: 1.5;
        }
        .proceso-box strong { color: #f59f00; }
        .proceso-steps { margin: 8px 0 0 0; padding-left: 18px; font-weight: 500; color: #444; }
        .proceso-steps li { margin-bottom: 4px; }

        .ayuda-texto {
            font-size: 13px;
            color: #6f42c1;
            font-weight: 500;
            margin-top: 15px;
            text-align: center;
            background: var(--lila-suave);
            padding: 8px;
            border-radius: 6px;
        }

        @media (max-width: 768px) { .container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">

    <div class="detalles-viaje">
        <?php
            $ruta_fisica = '../uploads/imagenesVIAJES/' . $viaje['imagen_url'];
            $url_web = '../uploads/imagenesVIAJES/' . $viaje['imagen_url'];

            $tiene_imagen = (!empty($viaje['imagen_url']) && $viaje['imagen_url'] !== 'default.jpg' && file_exists($ruta_fisica));
            $style_bg = $tiene_imagen ? "style='background-image: url(\"$url_web\"); background-size: cover; background-position: center;'" : "";
        ?>

        <div class="header-reserva" <?php echo $style_bg; ?>>
            <h1><?php echo htmlspecialchars($viaje['pais']); ?></h1>
            <span class="header-route">🗺️ <?php echo htmlspecialchars($viaje['ruta']); ?></span>
            <span class="header-date">🛫 Fecha de Vuelo: <?php echo date("d/m/Y", strtotime($viaje['fechaSalida'])); ?></span>
        </div>

        <h3>📍 Ciudades y Lugares a Visitar</h3>
        <p class="texto-bloque"><?php echo htmlspecialchars($viaje['lugaresVisitar']); ?></p>

        <h3>📅 Itinerario Oficial del Viaje</h3>
        <p class="texto-bloque"><?php echo htmlspecialchars($viaje['itinerario']); ?></p>

        <h3>✅ ¿Qué Incluye el Paquete?</h3>
        <p class="texto-bloque" style="color: #2e7d32; font-weight: 500;"><?php echo !empty($viaje['queIncluye']) ? htmlspecialchars($viaje['queIncluye']) : 'Consultar inclusiones con la agencia.'; ?></p>

        <h3>❌ ¿Qué NO Incluye?</h3>
        <p class="texto-bloque" style="color: #c62828; font-weight: 500;"><?php echo !empty($viaje['queNoIncluye']) ? htmlspecialchars($viaje['queNoIncluye']) : 'Gastos de carácter personal, visados y propinas.'; ?></p>

        <h3>⏱️ Duración del Destino</h3>
        <p class="texto-bloque">✨ <strong><?php echo $viaje['dias']; ?> días</strong> y <strong><?php echo $viaje['noches']; ?> noches</strong> completas de experiencias inolvidables.</p>
    </div>

    <div class="formulario-reserva">
        <div class="precio-tag">$<?php echo number_format($viaje['precioBoleto'], 2); ?> <small style="font-size: 13px; color: #777; font-weight: normal;">MXN p/p</small></div>

        <form action="procesar_reserva.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="idViaje" value="<?php echo $idViaje; ?>">
            <input type="hidden" id="precioUnitario" value="<?php echo $viaje['precioBoleto']; ?>">

            <label>Punto de Encuentro y Salida:</label>
            <select name="punto_recoleccion" required>
                <option value="">-- Selecciona tu punto de abordaje --</option>
                <?php while($p = $queryPuntos->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($p['nombrePunto']); ?>">
                        <?php
                            $fecha_formateada = date("d/m/Y", strtotime($p['fechaCita']));
                            $hora_formateada = date("H:i", strtotime($p['horaCita']));
                            echo htmlspecialchars($p['nombrePunto']) . " — " . $fecha_formateada . " a las " . $hora_formateada . " hrs";
                        ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Boletos a reservar:</label>
            <div class="selector-cantidad">
                <div class="btn-control-boletos" onclick="modificarBoletos(-1)">-</div>
                <input type="number" name="cantidad_boletos" id="cant_boletos" min="1" max="10" value="1" class="input-boletos-ux" oninput="validarYCalcular()" required>
                <div class="btn-control-boletos" onclick="modificarBoletos(1)">+</div>
            </div>

            <div style="margin-top: 22px; font-size: 19px; text-align: right; border-bottom: 2px dashed #eee; padding-bottom: 15px;">
                Total a pagar: <span id="total_compra" style="color:var(--morado-principal); font-weight:bold;">$<?php echo number_format($viaje['precioBoleto'], 2); ?> MXN</span>
            </div>

            <div class="ayuda-texto">
                ℹ️ Selecciona la cantidad de personas que irán al viaje (Máximo 10 pasajeros por reserva).
            </div>

            <button type="button" class="btn-cotizar" id="btn-pasajeros" onclick="mostrarPasajeros()">SIGUIENTE: DATOS PASAJEROS</button>

            <div id="panel-pasajeros">
                <label>Tu Correo Electrónico de Contacto:</label>
                <input type="email" name="correo_cliente" placeholder="Ej. tu-correo@gmail.com" required>

                <div id="inputs-pasajeros"></div>

                <button type="submit" class="btn-cotizar" style="background: #28a745; margin-top: 20px;">FINALIZAR Y SUBIR DOCUMENTOS</button>
            </div>
        </form>

        <div class="proceso-box">
            <strong>📋 Proceso de compra de boletos:</strong>
            <ol class="proceso-steps">
                <li>Registra tus datos y sube los documentos (PDF) de tus pasajeros.</li>
                <li>Un operador auditará y dictaminará la validez de la documentación.</li>
                <li>Una vez verificado, podrás realizar el pago seguro desde <em>"Mis Reservas"</em>.</li>
            </ol>
        </div>
    </div>
</div>

<script>
// Función para dar soporte interactivo a los botones de + y -
function modificarBoletos(cambio) {
    let input = document.getElementById('cant_boletos');
    let valorActual = parseInt(input.value) || 1;
    let nuevoValor = valorActual + cambio;

    if (nuevoValor >= 1 && nuevoValor <= 10) {
        input.value = nuevoValor;
        validarYCalcular();
    }
}

function validarYCalcular() {
    let input = document.getElementById('cant_boletos');
    let cant = parseInt(input.value);

    if (cant > 10) {
        alert("Por cuestiones de cupo y logística, el límite máximo por reservación es de 10 personas.");
        input.value = 10;
        cant = 10;
    } else if (cant < 1 || isNaN(cant)) {
        input.value = 1;
        cant = 1;
    }

    const precio = document.getElementById('precioUnitario').value;
    const total = cant * precio;
    document.getElementById('total_compra').innerText = '$' + total.toLocaleString('es-MX', {minimumFractionDigits: 2}) + ' MXN';

    document.getElementById('panel-pasajeros').style.display = 'none';
    document.getElementById('btn-pasajeros').style.display = 'block';
}

function mostrarPasajeros() {
    const cant = document.getElementById('cant_boletos').value;

    if(cant < 1 || cant > 10) {
        alert("Por favor, introduce una cantidad válida de boletos (Mínimo 1 y máximo 10).");
        return;
    }

    const contenedor = document.getElementById('inputs-pasajeros');
    contenedor.innerHTML = '<h3 style="color:var(--morado-oscuro); margin-bottom: 15px; font-size: 16px;">📋 Documentación de Pasajeros</h3>';

    for(let i = 1; i <= cant; i++) {
        contenedor.innerHTML += `
            <div class="pasajero-box">
                <strong style="display:block; margin-bottom: 10px; color: var(--morado-oscuro);">Pasajero #${i}</strong>

                <label style="margin-top: 5px;">Nombre Completo (Como aparece en identificación):</label>
                <input type="text" name="nombres[]" placeholder="Ej. Juan Pérez López" required>

                <label>Teléfono Celular:</label>
                <input type="tel" name="telefonos[]" placeholder="Ej. 2291234567" required>

                <label>Pasaporte Vigente o INE (Archivo PDF Obligatorio):</label>
                <input type="file" name="documentos[]" accept=".pdf" required style="background: white; border: 1px solid #ccc; padding: 8px;">
            </div>
        `;
    }
    document.getElementById('panel-pasajeros').style.display = 'block';
    document.getElementById('btn-pasajeros').style.display = 'none';
}
</script>

</body>
</html>
