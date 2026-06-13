<?php
session_start();

// 1. CONTROL DE ACCESO: SI NO HAY SESIÓN ACTIVA, EXPULSAR AL LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login2.php");
    exit();
}

include '../config/db.php';

$idUsuario = $_SESSION['user_id'];
$user_nombre = isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : "Usuario";

// 2. CONSULTA AVANZADA CON LEFT JOIN
$sql = "SELECT
            R.idReserva,
            R.numeroPersonas,
            R.puntoRecoleccion,
            R.statusVerificado,
            R.fechaReserva,
            V.pais,
            V.ruta,
            V.precioBoleto,
            P.idPago,
            P.statusPago
        FROM ReservaBoleto R
        INNER JOIN ViajeDetalles V ON R.idViaje = V.idViaje
        LEFT JOIN Pago P ON R.idReserva = P.idReserva
        WHERE R.idUsuario = ?
        ORDER BY R.idReserva DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reservas - CatTour</title>
    <style>
        :root {
            --color-principal: #6f42c1;
            --color-oscuro: #4b2c85;
            --color-suave: #f3effb;
            --texto-oscuro: #333;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
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
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--color-principal);
            text-decoration: none;
        }
        .menu a {
            text-decoration: none;
            color: var(--texto-oscuro);
            font-weight: 500;
            margin-left: 20px;
            transition: color 0.3s;
        }
        .menu a:hover { color: var(--color-principal); }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        h2 {
            color: var(--color-oscuro);
            border-left: 6px solid var(--color-principal);
            padding-left: 15px;
            margin-bottom: 30px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            font-weight: 500;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid #efebf7;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }

        th {
            background-color: var(--color-suave);
            color: var(--color-oscuro);
            padding: 15px;
            font-weight: 600;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #efebf7;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }
        .badge-warning { background-color: #fff3cd; color: #856404; }
        .badge-info { background-color: #d1ecf1; color: #0c5460; }
        .badge-danger { background-color: #f8d7da; color: #721c24; }
        .badge-success { background-color: #d4edda; color: #155724; }
        .badge-secondary { background-color: #e2e3e5; color: #383d41; }

        .btn-pagar {
            background-color: var(--success);
            color: white !important;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            transition: background 0.2s;
            box-shadow: 0 2px 5px rgba(40, 167, 69, 0.2);
        }
        .btn-pagar:hover { background-color: #218838; }

        .btn-ticket {
            background-color: var(--info);
            color: white !important;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            transition: background 0.2s;
            box-shadow: 0 2px 5px rgba(23, 162, 184, 0.2);
        }
        .btn-ticket:hover { background-color: #138496; }

        .btn-cancelar {
            background-color: var(--danger);
            color: white !important;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
            transition: background 0.2s;
            box-shadow: 0 2px 5px rgba(220, 53, 69, 0.2);
        }
        .btn-cancelar:hover { background-color: #bd2130; }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #777;
        }
    </style>

    <script>
        function confirmarCancelacion(id) {
            if (confirm("¿Estás seguro de que deseas cancelar la reserva #" + id + "? Esta acción no se puede revertir.")) {
                window.location.href = "cancelar_reserva.php?id=" + id;
            }
        }
    </script>
</head>
<body>

    <nav>
        <a href="../index.php" class="logo">CAT TOUR 🚢</a>
        <div class="menu">
            <span style="font-weight: 600; color: var(--color-oscuro);">👤 <?php echo htmlspecialchars($user_nombre); ?></span>
            <a href="../index.php">🏠 Inicio</a>
            <a href="correos.php">📨 Correos</a>
            <a href="../auth/logout.php" style="color: #dc3545; font-size: 14px; font-weight: bold;">Cerrar Sesión</a>
        </div>
    </nav>

    <div class="container">
        <h2>Mi Historial de Reservas</h2>

        <?php if (isset($_GET['cancelado']) && $_GET['cancelado'] == 1): ?>
            <div class="alert-success">✅ La reservación ha sido cancelada correctamente.</div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID Reserva</th>
                        <th>Destino</th>
                        <th>Fecha Reserva</th>
                        <th>Personas</th>
                        <th>Total a Pagar</th>
                        <th>Estado de Verificación</th>
                        <th>Acción / Pago</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado->num_rows > 0): ?>
                        <?php while($reserva = $resultado->fetch_assoc()):
                            $monto_calculado = $reserva['precioBoleto'] * $reserva['numeroPersonas'];
                        ?>
                            <tr>
                                <td><strong>#<?php echo $reserva['idReserva']; ?></strong></td>
                                <td>
                                    <span style="font-weight: 600; color: var(--color-oscuro);"><?php echo htmlspecialchars($reserva['pais']); ?></span><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($reserva['ruta']); ?></small>
                                </td>
                                <td><?php echo date("d/m/Y", strtotime($reserva['fechaReserva'])); ?></td>
                                <td><?php echo $reserva['numeroPersonas']; ?></td>
                                <td><strong>$<?php echo number_format($monto_calculado, 2); ?> MXN</strong></td>

                                <td>
                                    <?php if ($reserva['statusVerificado'] == 0): ?>
                                        <span class="badge badge-warning">⏳ Pendiente de verificar</span>
                                    <?php elseif ($reserva['statusVerificado'] == 1): ?>
                                        <span class="badge badge-success">✅ Verificado</span>
                                    <?php elseif ($reserva['statusVerificado'] == 2): ?>
                                        <span class="badge badge-danger">❌ Rechazada por Operador</span>
                                    <?php elseif ($reserva['statusVerificado'] == 3): ?>
                                        <span class="badge badge-secondary">🚫 Cancelada por ti</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php
                                    if ($reserva['statusVerificado'] == 0) {
                                        // MODIFICACIÓN DE DISEÑO: Solo pinta el botón limpio
                                        echo '<a href="#" onclick="confirmarCancelacion(' . $reserva['idReserva'] . ')" class="btn-cancelar">❌ Cancelar Reserva</a>';
                                    }
                                    elseif ($reserva['statusVerificado'] == 2 || $reserva['statusVerificado'] == 3) {
                                        echo '<span style="color: var(--danger); font-weight: bold;">Reserva inactiva</span>';
                                    }
                                    else {
                                        // Caso 2: Verificado (statusVerificado == 1), evalúa pagos
                                        if (is_null($reserva['idPago'])) {
                                            echo '<a href="procesar_pago.php?idReserva=' . $reserva['idReserva'] . '" class="btn-pagar">💳 Pagar Reserva</a>';
                                        } else {
                                            if ($reserva['statusPago'] == 'Pendiente') {
                                                echo '<span class="badge badge-info">📩 Pago en revisión</span>';
                                            } elseif ($reserva['statusPago'] == 'Aprobado') {
                                                echo '<a href="consultar_ticket.php?idReserva=' . $reserva['idReserva'] . '" class="btn-ticket">🎫 Ver Ticket</a>';
                                            } elseif ($reserva['statusPago'] == 'Rechazado') {
                                                echo '<span class="badge badge-danger">❌ Pago <br>Rechazado</span><br>';
                                                echo '<a href="procesar_pago.php?idReserva=' . $reserva['idReserva'] . '" style="font-size:12px; font-weight:bold; color:var(--color-principal); display:inline-block; margin-top:5px;">Intentar pagar de nuevo</a>';
                                            }
                                        }
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <span style="font-size: 2rem; display:block; margin-bottom:10px;">💼</span>
                                <h3>Aún no has realizado ninguna reservación</h3>
                                <p>Explora nuestras ofertas destacadas en la página de inicio.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
