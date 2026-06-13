<?php
session_start();

// 1. CONTROL DE ACCESO
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login2.php");
    exit();
}

include '../config/db.php';

$idUsuario = $_SESSION['user_id'];
$user_nombre = isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : "Usuario";

// Validar ID de reserva
if (!isset($_GET['idReserva']) || empty($_GET['idReserva'])) {
    header("Location: mis_reservas.php");
    exit();
}

$idReserva = intval($_GET['idReserva']);

// 2. CONSULTA COMPLETA: Traer datos del usuario, de la reserva, del viaje y del pago aprobado
$sql = "SELECT 
            R.idReserva, R.numeroPersonas, R.puntoRecoleccion, R.fechaReserva,
            V.pais, V.ruta, V.fechaSalida, V.precioBoleto,
            P.idPago, P.montoTotal, P.metodoSeleccionado, P.comprobanteDigital, P.fechaPago,
            U.nombre AS nombreCliente, U.correo AS correoCliente
        FROM ReservaBoleto R
        INNER JOIN ViajeDetalles V ON R.idViaje = V.idViaje
        INNER JOIN Pago P ON R.idReserva = P.idReserva
        INNER JOIN UsuarioCliente U ON R.idUsuario = U.idUsuario
        WHERE R.idReserva = ? AND R.idUsuario = ? AND P.statusPago = 'Aprobado'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $idReserva, $idUsuario);
$stmt->execute();
$resultado = $stmt->get_result();

// Si no hay un pago aprobado para esta reserva y este usuario, denegar acceso
if ($resultado->num_rows === 0) {
    echo "<script>alert('No se encontró un ticket aprobado para esta reservación.'); window.location.href='mis_reservas.php';</script>";
    exit();
}

$ticket = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Reserva #<?php echo $ticket['idReserva']; ?> - CatTour</title>
    <style>
        :root {
            --color-principal: #6f42c1;
            --color-oscuro: #4b2c85;
            --color-suave: #f8f6fc;
            --texto-oscuro: #333;
            --border-color: #e1d8f5;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #f4f3f8;
            color: var(--texto-oscuro);
        }

        /* Navbar (Se ocultará al imprimir) */
        nav {
            background: white;
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo { font-size: 24px; font-weight: bold; color: var(--color-principal); text-decoration: none; }
        .btn-volver { text-decoration: none; color: #666; font-weight: 500; }
        .btn-volver:hover { color: var(--color-principal); }

        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Botón de Imprimir en Pantalla */
        .acciones {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .btn-print {
            background-color: var(--color-principal);
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(111, 66, 193, 0.2);
            transition: background 0.2s;
        }
        .btn-print:hover { background-color: var(--color-oscuro); }

        /* Estilo del Ticket (Simulando un pase de abordar/recibo) */
        .ticket-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 2px solid var(--border-color);
            overflow: hidden;
            position: relative;
        }

        /* Encabezado del ticket */
        .ticket-header {
            background: linear-gradient(135deg, var(--color-principal), var(--color-oscuro));
            color: white;
            padding: 25px;
            text-align: center;
        }
        .ticket-header h1 { margin: 0; font-size: 28px; letter-spacing: 1px; }
        .ticket-header p { margin: 5px 0 0 0; opacity: 0.9; font-size: 14px; }

        .ticket-body {
            padding: 30px;
        }

        .section-title {
            font-size: 12px;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 1px;
            margin-bottom: 10px;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 5px;
            font-weight: bold;
        }

        /* Grid de información */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        .info-item label {
            display: block;
            font-size: 13px;
            color: #777;
            margin-bottom: 2px;
        }
        .info-item span {
            font-size: 15px;
            font-weight: 600;
            color: #222;
        }

        /* Resumen de costos */
        .monto-box {
            background-color: var(--color-suave);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            border: 1px solid var(--border-color);
        }

        .monto-box .total-label { font-size: 16px; font-weight: bold; color: var(--color-oscuro); }
        .monto-box .total-precio { font-size: 22px; font-weight: 800; color: #28a745; }

        /* Código de barra de adorno / QR simulado */
        .ticket-footer {
            background-color: #fafafa;
            border-top: 2px dashed var(--border-color);
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .barcode {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            letter-spacing: 6px;
            font-weight: bold;
            color: #000;
            margin-top: 5px;
        }

        /* =======================================================
           REGLAS DE CSS PARA IMPRESIÓN (MAGIA AQUÍ)
           ======================================================= */
        @media print {
            body { background-color: white; }
            nav, .acciones { display: none !important; } /* Esconde navbar y botón imprimir */
            .container { max-width: 100%; margin: 0; padding: 0; }
            .ticket-box { box-shadow: none; border: 1px solid #000; border-radius: 0; }
            .ticket-header { background: #6f42c1 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .monto-box { background-color: #f8f6fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <nav>
        <a href="../index.php" class="logo">CAT TOUR 🚢</a>
        <a href="mis_reservas.php" class="btn-volver">← Volver a Mis Reservas</a>
    </nav>

    <div class="container">
        
        <div class="acciones">
            <button onclick="window.print();" class="btn-print">🖨️ Imprimir Ticket / Guardar PDF</button>
        </div>

        <div class="ticket-box">
            
            <div class="ticket-header">
                <h1>COMPROBANTE DE ABORDAJE</h1>
                <p>¡Gracias por confiar tu aventura en CatTour!</p>
            </div>

            <div class="ticket-body">
                
                <div class="section-title">Detalles de la Transacción</div>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Folio Digital de Pago:</label>
                        <span><code><?php echo htmlspecialchars($ticket['comprobanteDigital']); ?></code></span>
                    </div>
                    <div class="info-item">
                        <label>Fecha de Pago:</label>
                        <span><?php echo date("d/m/Y H:i:s", strtotime($ticket['fechaPago'])); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Método utilizado:</label>
                        <span>💳 <?php echo htmlspecialchars($ticket['metodoSeleccionado']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>ID de Reservación:</label>
                        <span>#<?php echo $ticket['idReserva']; ?></span>
                    </div>
                </div>

                <div class="section-title">Información del Pasajero Titular</div>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Nombre Completo:</label>
                        <span><?php echo htmlspecialchars($user_nombre); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Punto de Recolección acordado:</label>
                        <span>📍 <?php echo htmlspecialchars($ticket['puntoRecoleccion']); ?></span>
                    </div>
                </div>

                <div class="section-title">Información del Itinerario</div>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Destino Principal:</label>
                        <span style="font-size:16px; color:var(--color-principal);"><?php echo htmlspecialchars($ticket['pais']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Fecha de Salida del Crucero/Viaje:</label>
                        <span>📅 <?php echo date("d/m/Y", strtotime($ticket['fechaSalida'])); ?></span>
                    </div>
                    <div class="info-item" style="grid-column: span 2;">
                        <label>Ruta Planificada:</label>
                        <span>🗺️ <?php echo htmlspecialchars($ticket['ruta']); ?></span>
                    </div>
                </div>

                <div class="section-title">Resumen de Liquidación</div>
                <div class="info-grid" style="margin-bottom: 10px;">
                    <div class="info-item">
                        <label>Costo por Boleto:</label>
                        <span>$<?php echo number_format($ticket['precioBoleto'], 2); ?> MXN</span>
                    </div>
                    <div class="info-item">
                        <label>Cantidad de Pasajeros:</label>
                        <span><?php echo $ticket['numeroPersonas']; ?> Persona(s)</span>
                    </div>
                </div>

                <div class="monto-box">
                    <span class="total-label">Total Pagado:</span>
                    <span class="total-precio">$<?php echo number_format($ticket['montoTotal'], 2); ?> MXN</span>
                </div>

            </div>

            <div class="ticket-footer">
                <p>Presenta este comprobante digital o impreso junto con tu identificación oficial al momento del abordaje.</p>
                <div class="barcode">||| | |||| || ||| | ||| || <?php echo $ticket['idReserva'] . $ticket['idPago']; ?> |||</div>
            </div>

        </div>
    </div>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
