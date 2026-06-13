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

// 2. VERIFICAR QUE LA RESERVA EXISTA, SEA DEL USUARIO Y ESTÉ VERIFICADA
$sql = "SELECT R.idReserva, R.numeroPersonas, V.pais, V.ruta, V.precioBoleto 
        FROM ReservaBoleto R
        INNER JOIN ViajeDetalles V ON R.idViaje = V.idViaje
        WHERE R.idReserva = ? AND R.idUsuario = ? AND R.statusVerificado = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $idReserva, $idUsuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header("Location: mis_reservas.php");
    exit();
}

$reserva = $resultado->fetch_assoc();
$monto_total = $reserva['precioBoleto'] * $reserva['numeroPersonas'];

// 3. PROCESAR LA SIMULACIÓN DE PAGO (POST)
$pago_exitoso = false;
$folio_digital = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $metodo = $_POST['metodoSeleccionado'];
    
    // Generamos un folio digital simulado para el campo comprobanteDigital
    $folio_digital = "CAT-" . strtoupper(substr($metodo, 0, 3)) . "-" . rand(100000, 999999);

    // Insertamos directamente el pago como 'Aprobado' para saltarnos la revisión del operador
    $sql_pago = "INSERT INTO Pago (idReserva, montoTotal, metodoSeleccionado, comprobanteDigital, fechaPago, statusPago) 
                 VALUES (?, ?, ?, ?, NOW(), 'Aprobado')";
    
    $stmt_pago = $conn->prepare($sql_pago);
    $stmt_pago->bind_param("idss", $idReserva, $monto_total, $metodo, $folio_digital);

    if ($stmt_pago->execute()) {
        $pago_exitoso = true;
    } else {
        $mensaje_error = "Hubo un problema al procesar la transacción simulada.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasarela de Pago - CatTour</title>
    <style>
        :root {
            --color-principal: #6f42c1;
            --color-oscuro: #4b2c85;
            --color-suave: #f3effb;
            --texto-oscuro: #333;
            --success: #28a745;
            --info: #17a2b8;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            margin: 0;
            background-color: #fcfcfd;
            color: var(--texto-oscuro);
        }

        nav {
            background: white;
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo { font-size: 24px; font-weight: bold; color: var(--color-principal); text-decoration: none; }

        .container { max-width: 550px; margin: 40px auto; padding: 20px; }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid #efebf7;
            text-align: center;
        }

        h2 { color: var(--color-oscuro); margin-bottom: 20px; }

        .resumen {
            background-color: var(--color-suave);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: left;
        }
        .resumen p { margin: 8px 0; font-size: 15px; }

        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            background: white;
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            box-sizing: border-box;
            cursor: pointer;
            transition: background 0.2s;
            border: none;
        }
        .btn-pagar { background-color: var(--success); color: white; }
        .btn-pagar:hover { background-color: #218838; }
        
        .btn-ticket { background-color: var(--info); color: white; margin-bottom: 10px; }
        .btn-ticket:hover { background-color: #138496; }

        .success-icon { font-size: 50px; color: var(--success); margin-bottom: 15px; }
    </style>
</head>
<body>

    <nav>
        <a href="../index.php" class="logo">CAT TOUR 🚢</a>
        <div style="font-weight: 600; color: var(--color-oscuro);">👤 <?php echo htmlspecialchars($user_nombre); ?></div>
    </nav>

    <div class="container">
        <div class="card">
            
            <?php if (!$pago_exitoso): ?>
                <h2>Pasarela de Pago Segura</h2>
                <p style="color: #666; margin-bottom: 20px;">Estás a un paso de confirmar tu gran viaje.</p>

                <div class="resumen">
                    <p><strong>Destino:</strong> <?php echo htmlspecialchars($reserva['pais']); ?></p>
                    <p><strong>Ruta:</strong> <?php echo htmlspecialchars($reserva['ruta']); ?></p>
                    <p><strong>Pasajeros:</strong> <?php echo $reserva['numeroPersonas']; ?></p>
                    <hr style="border: 0; border-top: 1px solid #efebf7; margin: 12px 0;">
                    <p style="font-size: 18px; color: var(--color-oscuro);">
                        <strong>Total a abonar:</strong> <span style="color: var(--success); font-weight: bold;">$<?php echo number_format($monto_total, 2); ?> MXN</span>
                    </p>
                </div>

                <form action="" method="POST">
                    <div class="form-group">
                        <label for="metodoSeleccionado">Selecciona tu método de pago simulado:</label>
                        <select name="metodoSeleccionado" id="metodoSeleccionado" required>
                            <option value="Tarjeta">💳 Tarjeta de Crédito / Débito (Simulado)</option>
                            <option value="Transferencia">🏦 Transferencia Interbancaria SPEI</option>
                            <option value="Deposito">💵 Depósito en Ventanilla / OXXO</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-pagar">🔒 Confirmar Pago Exitoso</button>
                    <a href="mis_reservas.php" style="display:block; margin-top:15px; color:#666; text-decoration:none; font-size:14px;">Cancelar y volver</a>
                </form>

            <?php else: ?>
                <div class="success-icon">🎉</div>
                <h2 style="color: var(--success); margin-top:0;">¡Pago Procesado con Éxito!</h2>
                <p>Tu pago ha sido validado de forma automática por el sistema.</p>
                
                <div class="resumen" style="background-color: #e8f5e9; border: 1px solid #c8e6c9;">
                    <p><strong>Código de Autorización:</strong> <code><?php echo $folio_digital; ?></code></p>
                    <p><strong>Monto Liquidado:</strong> $<?php echo number_format($monto_total, 2); ?> MXN</p>
                    <p><strong>Estatus:</strong> <span style="color:var(--success); font-weight:bold;">Aprobado</span></p>
                </div>

                <a href="consultar_ticket.php?idReserva=<?php echo $idReserva; ?>" class="btn btn-ticket" target="_blank">🎫 Imprimir / Ver Ticket</a>
                
                <a href="mis_reservas.php" style="display:block; margin-top:15px; color: var(--color-principal); text-decoration:none; font-weight:600;">Ir a mi historial de reservas</a>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>
<?php
$stmt->close();
if (isset($stmt_pago)) $stmt_pago->close();
$conn->close();
?>
