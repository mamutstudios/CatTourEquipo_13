<?php
session_start();
include '../config/db.php';

// 1. CONTROL DE ACCESO
if (!isset($_SESSION['empleado_id'])) {
    header("Location: ../auth/LoginEmploy.php");
    exit();
}

if (!isset($_GET['idReserva'])) {
    header("Location: panelOperador.php");
    exit();
}

$idReserva = (int)$_GET['idReserva'];
$empleado_nombre = $_SESSION['empleado_nombre'];

// =========================================================================
// ACCIÓN A: CAMBIAR ESTADO INDIVIDUAL DE UN ACOMPAÑANTE
// =========================================================================
if (isset($_GET['accion_pasajero']) && isset($_GET['idAcompanante'])) {
    $idAcomp = (int)$_GET['idAcompanante'];
    $nuevo_estado = ($_GET['accion_pasajero'] === 'verificar') ? 1 : 2; // 1 = Verificado, 2 = No Apto

    $stmt = $conn->prepare("UPDATE AcompanantesPorReserva SET verificado = ? WHERE idAcompanante = ? AND idReserva = ?");
    $stmt->bind_param("iii", $nuevo_estado, $idAcomp, $idReserva);
    $stmt->execute();
    $stmt->close();

    header("Location: ver_pasajeros.php?idReserva=$idReserva");
    exit();
}

// =========================================================================
// ACCIÓN B: DECISIÓN FINAL DE LA RESERVA COMPLETA
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decision_reserva'])) {
    $decision = $_POST['decision_reserva']; // 'aprobar' o 'rechazar'

    // Obtenemos los datos esenciales del usuario y destino para armar el correo
    $sqlData = "SELECT R.idUsuario, V.pais
                FROM ReservaBoleto R
                INNER JOIN ViajeDetalles V ON R.idViaje = V.idViaje
                WHERE R.idReserva = $idReserva";
    $resData = $conn->query($sqlData)->fetch_assoc();

    if ($resData) {
        $idUsuarioCliente = $resData['idUsuario'];
        $paisDestino = $resData['pais'];

        if ($decision === 'aprobar') {
            // Actualizamos la reserva a 1 (Verificado / Listo para pagar)
            $conn->query("UPDATE ReservaBoleto SET statusVerificado = 1 WHERE idReserva = $idReserva");

            $asunto = "🎉 ¡Documentación Verificada! Reserva #$idReserva Lista para Pagar";
            $mensaje = "¡Excelentes noticias! Nuestro equipo de operaciones ha revisado tus documentos para el viaje a **$paisDestino** (Reserva #$idReserva) y todo se encuentra en orden.\n\n"
                     . "Ya puedes ingresar al apartado de **'Mis Reservas'** en tu perfil, donde se ha habilitado el botón de pago para que liquides tu boleto.\n\n"
                     . "¡Prepara las maletas! 🚢";
        } else {
            // CAMBIO CLAVE APLICADO: Ahora cambia a 2 para reflejarse como RECHAZADA inmediatamente con el cliente
            $conn->query("UPDATE ReservaBoleto SET statusVerificado = 2 WHERE idReserva = $idReserva");

            $asunto = "⚠️ Tu Reserva #$idReserva requiere atención en los documentos";
            $mensaje = "Hola. Te informamos que tras revisar los expedientes adjuntos para tu viaje a **$paisDestino**, nuestro operador ha determinado que la documentación **No es Apta** o está incompleta.\n\n"
                     . "Por favor, ponte en contacto directo con las oficinas de CatTour para aclarar qué archivo presentó fallas y puedas reestablecer tu solicitud.\n\n"
                     . "Estamos listos para ayudarte.";
        }

        // Insertamos la notificación en la tabla del cliente
        $stmtEmail = $conn->prepare("INSERT INTO NotificacionCorreo (idUsuario, idReserva, asunto, mensaje, leido) VALUES (?, ?, ?, ?, 0)");
        $stmtEmail->bind_param("iiss", $idUsuarioCliente, $idReserva, $asunto, $mensaje);
        $stmtEmail->execute();
        $stmtEmail->close();
    }

    header("Location: panelOperador.php?mensaje=procesado");
    exit();
}

// 2. CONSULTAR DATOS DE LA RESERVA Y PASAJEROS
$sqlReserva = "SELECT R.*, U.nombre as cliente, V.pais, V.ruta
               FROM ReservaBoleto R
               INNER JOIN UsuarioCliente U ON R.idUsuario = U.idUsuario
               INNER JOIN ViajeDetalles V ON R.idViaje = V.idViaje
               WHERE R.idReserva = $idReserva";
$reservaInfo = $conn->query($sqlReserva)->fetch_assoc();

$sqlAcompanantes = "SELECT * FROM AcompanantesPorReserva WHERE idReserva = $idReserva";
$resultPasajeros = $conn->query($sqlAcompanantes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente Reserva #<?php echo $idReserva; ?></title>
    <style>
        :root {
            --color-principal: #6f42c1;
            --color-oscuro: #4b2c85;
            --color-suave: #f3effb;
            --texto-oscuro: #333;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            margin: 0;
            background-color: #fcfcfd;
            color: var(--texto-oscuro);
        }

        .container { max-width: 950px; margin: 40px auto; padding: 0 20px; }

        .btn-back {
            text-decoration: none;
            color: var(--color-principal);
            font-weight: bold;
            display: inline-block;
            margin-bottom: 20px;
        }

        .card-expediente {
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #efebf7;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            margin-bottom: 30px;
        }

        h2, h3 { color: var(--color-oscuro); margin-top: 0; }

        .meta-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            background: var(--color-suave);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #efebf7; }
        th { background: #fafafa; color: #555; }

        .btn-action-psg {
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-right: 5px;
        }

        .btn-pdf { background: #17a2b8; color: white; }
        .btn-check { background: var(--success); color: white; }
        .btn-cross { background: var(--danger); color: white; }

        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }

        .decision-box {
            background: #fff;
            border: 2px solid #efebf7;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }
        .btn-final {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin: 0 10px;
            transition: 0.2s;
        }
        .btn-final-approve { background: var(--success); color: white; }
        .btn-final-reject { background: var(--danger); color: white; }
        .btn-final:hover { opacity: 0.9; transform: scale(1.02); }
    </style>
</head>
<body>

    <div class="container">
        <a href="panelOperador.php" class="btn-back">← Volver al Panel de Operaciones</a>

        <div class="card-expediente">
            <h2>Expediente de Revisión: Reserva #<?php echo $idReserva; ?></h2>

            <div class="meta-info">
                <div>
                    <strong>Cliente Titular:</strong> <?php echo htmlspecialchars($reservaInfo['cliente']); ?><br>
                    <strong>Destino de Viaje:</strong> <?php echo htmlspecialchars($reservaInfo['pais']); ?>
                </div>
                <div>
                    <strong>Ruta:</strong> <?php echo htmlspecialchars($reservaInfo['ruta']); ?><br>
                    <strong>Fecha Registro:</strong> <?php echo date("d/m/Y", strtotime($reservaInfo['fechaReserva'])); ?>
                </div>
            </div>

            <h3>👥 Documentos y Pasajeros Registrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre Pasajero</th>
                        <th>Teléfono</th>
                        <th>Documento PDF</th>
                        <th>Estado</th>
                        <th>Acción Operador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($psg = $resultPasajeros->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($psg['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($psg['telefono']); ?></td>
                            <td>
                                <a href="../<?php echo $psg['pasaporteDigital']; ?>" target="_blank" class="btn-action-psg btn-pdf">📄 Ver Documento</a>
                            </td>
                            <td>
                                <?php
                                    if ($psg['verificado'] == 0) echo '<span class="badge badge-pending">⏳ Sin Revisar</span>';
                                    elseif ($psg['verificado'] == 1) echo '<span class="badge badge-success">✅ Verificado</span>';
                                    else echo '<span class="badge badge-danger">❌ No Apto</span>';
                                ?>
                            </td>
                            <td>
                                <a href="ver_pasajeros.php?idReserva=<?php echo $idReserva; ?>&idAcompanante=<?php echo $psg['idAcompanante']; ?>&accion_pasajero=verificar" class="btn-action-psg btn-check" title="Marcar como Aceptado">✓</a>
                                <a href="ver_pasajeros.php?idReserva=<?php echo $idReserva; ?>&idAcompanante=<?php echo $psg['idAcompanante']; ?>&accion_pasajero=rechazar" class="btn-action-psg btn-cross" title="Marcar como No Apto">✕</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="decision-box">
            <h3>Dictamen Final de la Reservación</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Al seleccionar una opción, se actualizará el estado de la reserva del cliente y el sistema le mandará un correo interno automáticamente notificando los detalles.</p>

            <form action="ver_pasajeros.php?idReserva=<?php echo $idReserva; ?>" method="POST">
                <button type="submit" name="decision_reserva" value="aprobar" class="btn-final btn-final-approve">👍 APROBAR RESERVA Y REQUERIR PAGO</button>
                <button type="submit" name="decision_reserva" value="rechazar" class="btn-final btn-final-reject">👎 RECHAZAR SOLICITUD (DOCUMENTOS INVÁLIDOS)</button>
            </form>
        </div>
    </div>

</body>
</html>
