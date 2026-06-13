<?php
session_start();

// 1. CONTROL DE ACCESO
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login2.php");
    exit();
}

include '../config/db.php';
$idUsuario = $_SESSION['user_id'];

// 2. VALIDAR ENTRADA
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $idReserva = intval($_GET['id']);

    // OBTENER EL DESTINO ANTES DE ACTUALIZAR (Para el texto del correo)
    $paisDestino = "tu destino solicitado"; // Valor por defecto por si acaso
    $sqlViaje = "SELECT V.pais FROM ReservaBoleto R 
                 INNER JOIN ViajeDetalles V ON R.idViaje = V.idViaje 
                 WHERE R.idReserva = ? AND R.idUsuario = ?";
    $stmtViaje = $conn->prepare($sqlViaje);
    $stmtViaje->bind_param("ii", $idReserva, $idUsuario);
    $stmtViaje->execute();
    $resViaje = $stmtViaje->get_result();
    if ($filaViaje = $resViaje->fetch_assoc()) {
        $paisDestino = $filaViaje['pais'];
    }
    $stmtViaje->close();

    // 3. SEGURIDAD REFORZADA: Borrado lógico cambiando estado a 3
    $sql = "UPDATE ReservaBoleto 
            SET statusVerificado = 3 
            WHERE idReserva = ? AND idUsuario = ? AND statusVerificado = 0";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $idReserva, $idUsuario);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        
        // 4. GENERAR NOTIFICACIÓN DE CORREO INTERNO
        $asunto = "🚫 Confirmación de Cancelación: Reserva #$idReserva";
        $mensaje = "Hola. Te confirmamos que has cancelado de manera exitosa tu solicitud de reservación para el viaje a **$paisDestino** (Reserva #$idReserva).\n\n"
                 . "Lamentamos que no puedas viajar con nosotros en esta ocasión. Tus datos y expediente han sido resguardados de forma segura en nuestro histórico.\n\n"
                 . "Esperamos verte a bordo en una próxima aventura. 🚢";

        $sqlEmail = "INSERT INTO NotificacionCorreo (idUsuario, idReserva, asunto, mensaje, leido) VALUES (?, ?, ?, ?, 0)";
        $stmtEmail = $conn->prepare($sqlEmail);
        $stmtEmail->bind_param("iiss", $idUsuario, $idReserva, $asunto, $mensaje);
        $stmtEmail->execute();
        $stmtEmail->close();

        // Redirige de vuelta con éxito (activará el banner verde en mis_reservas.php)
        header("Location: mis_reservas.php?cancelado=1");
        exit();
    }
    $stmt->close();
}

// Si falla, regresa sin cambios
header("Location: mis_reservas.php?cancelado=0");
exit();
?>
