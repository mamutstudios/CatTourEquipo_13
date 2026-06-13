<?php
session_start();
include '../config/verificar_sesion_empleado.php';
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
    $nuevo_estado = ($_GET['accion_pasajero'] === 'verificar') ? 1 : 2; 

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
    $decision = $_POST['decision_reserva'];

    $sqlData = "SELECT R.idUsuario, V.pais
                FROM ReservaBoleto R
                INNER JOIN ViajeDetalles V ON R.idViaje = V.idViaje
                WHERE R.idReserva = $idReserva";
    $resData = $conn->query($sqlData)->fetch_assoc();

    if ($resData) {
        $idUsuarioCliente = $resData['idUsuario'];
        $paisDestino = $resData['pais'];

        if ($decision === 'aprobar') {
            $conn->query("UPDATE ReservaBoleto SET statusVerificado = 1 WHERE idReserva = $idReserva");
            $asunto = "🎉 ¡Documentación Verificada! Reserva #$idReserva";
            $mensaje = "¡Excelentes noticias! Nuestro equipo ha revisado tus documentos para el viaje a **$paisDestino**. Todo está en orden, ya puedes realizar tu pago.";
        } else {
            $conn->query("UPDATE ReservaBoleto SET statusVerificado = 2 WHERE idReserva = $idReserva");
            $asunto = "⚠️ Tu Reserva #$idReserva requiere atención";
            $mensaje = "Informamos que la documentación para el viaje a **$paisDestino** no es apta. Por favor contacta a nuestras oficinas.";
        }

        $stmtEmail = $conn->prepare("INSERT INTO NotificacionCorreo (idUsuario, idReserva, asunto, mensaje, leido) VALUES (?, ?, ?, ?, 0)");
        $stmtEmail->bind_param("iiss", $idUsuarioCliente, $idReserva, $asunto, $mensaje);
        $stmtEmail->execute();
        $stmtEmail->close();
    }
    header("Location: panelOperador.php?mensaje=procesado");
    exit();
}

// 2. CONSULTAR DATOS DE LA RESERVA
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
    <title>Expediente Reserva #<?php echo $idReserva; ?></title>
    <style>
        :root { --color-principal: #6f42c1; --color-oscuro: #4b2c85; --color-suave: #f3effb; --texto-oscuro: #333; --success: #28a745; --danger: #dc3545; --warning: #ffc107; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background-color: #fcfcfd; color: var(--texto-oscuro); }
        .container { max-width: 950px; margin: 40px auto; padding: 0 20px; }
        .btn-back { text-decoration: none; color: var(--color-principal); font-weight: bold; margin-bottom: 20px; display: inline-block; }
        .card-expediente { background: white; padding: 30px; border-radius: 12px; border: 1px solid #efebf7; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 30px; }
        .meta-info { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: var(--color-suave); padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #efebf7; }
        th { background: #fafafa; color: #555; }
        .btn-action-psg { padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-block; margin-right: 5px; }
        .btn-pdf { background: #17a2b8; color: white; }
        .btn-check { background: var(--success); color: white; }
        .btn-cross { background: var(--danger); color: white; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .decision-box { background: #fff; border: 2px solid #efebf7; padding: 25px; border-radius: 12px; text-align: center; }
        .btn-final { padding: 12px 30px; border: none; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; margin: 0 10px; transition: 0.2s; }
        .btn-final-approve { background: var(--success); color: white; }
        .btn-final-reject { background: var(--danger); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <a href="panelOperador.php" class="btn-back">← Volver al Panel de Operaciones</a>
        <div class="card-expediente">
            <h2>Expediente de Revisión: Reserva #<?php echo $idReserva; ?></h2>
            <div class="meta-info">
                <div><strong>Cliente:</strong> <?php echo htmlspecialchars($reservaInfo['cliente']); ?><br><strong>Destino:</strong> <?php echo htmlspecialchars($reservaInfo['pais']); ?></div>
                <div><strong>Ruta:</strong> <?php echo htmlspecialchars($reservaInfo['ruta']); ?><br><strong>Fecha Registro:</strong> <?php echo date("d/m/Y", strtotime($reservaInfo['fechaReserva'])); ?></div>
            </div>

            <h3>👥 Pasajeros Registrados</h3>
            <table>
                <thead><tr><th>Nombre</th><th>Teléfono</th><th>Documento</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php while($psg = $resultPasajeros->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($psg['nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($psg['telefono']); ?></td>
                        <td><a href="../<?php echo $psg['pasaporteDigital']; ?>" target="_blank" class="btn-action-psg btn-pdf">📄 Ver</a></td>
                        <td>
                            <?php if ($psg['verificado'] == 0) echo '<span class="badge badge-pending">⏳ Sin Revisar</span>';
                                  elseif ($psg['verificado'] == 1) echo '<span class="badge badge-success">✅ Verificado</span>';
                                  else echo '<span class="badge badge-danger">❌ No Apto</span>'; ?>
                        </td>
                        <td>
                            <?php if ($reservaInfo['statusVerificado'] == 0): ?>
                                <a href="?idReserva=<?php echo $idReserva; ?>&idAcompanante=<?php echo $psg['idAcompanante']; ?>&accion_pasajero=verificar" class="btn-action-psg btn-check">✓</a>
                                <a href="?idReserva=<?php echo $idReserva; ?>&idAcompanante=<?php echo $psg['idAcompanante']; ?>&accion_pasajero=rechazar" class="btn-action-psg btn-cross">✕</a>
                            <?php else: ?>
                                <span style="color:#999; font-size:12px;">Bloqueado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="decision-box">
            <?php if ($reservaInfo['statusVerificado'] == 0): ?>
                <h3>Dictamen Final de la Reservación</h3>
                <form action="ver_pasajeros.php?idReserva=<?php echo $idReserva; ?>" method="POST">
                    <button type="submit" name="decision_reserva" value="aprobar" class="btn-final btn-final-approve">👍 APROBAR RESERVA</button>
                    <button type="submit" name="decision_reserva" value="rechazar" class="btn-final btn-final-reject">👎 RECHAZAR SOLICITUD</button>
                </form>
            <?php else: ?>
                <h3>Reserva Procesada</h3>
                <p style="color:var(--color-principal); font-weight:bold;">
                    Dictamen actual: <?php echo ($reservaInfo['statusVerificado'] == 1) ? "APROBADA" : (($reservaInfo['statusVerificado'] == 2) ? "RECHAZADA POR OPERADOR" : "CANCELADA POR CLIENTE"); ?>
                </p>
                <p style="font-size:13px; color:#666;">El expediente ya no admite cambios.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
