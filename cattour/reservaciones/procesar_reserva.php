<?php
session_start(); // Fundamental para capturar al usuario logueado
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Validar que el usuario tenga sesión activa
    if (!isset($_SESSION['user_id'])) {
        die("Error: Debes iniciar sesión para realizar una reserva.");
    }

    $idUsuario = $_SESSION['user_id'];
    $idViaje = (int)$_POST['idViaje'];
    $puntoRecoleccion = $conn->real_escape_string($_POST['punto_recoleccion']);
    $numPersonas = (int)$_POST['cantidad_boletos'];

    // 2. Insertar el registro principal de la Reserva
    // Nota: Usamos NOW() para la fecha automática
    $sqlReserva = "INSERT INTO ReservaBoleto (idUsuario, idViaje, numeroPersonas, puntoRecoleccion, fechaReserva, statusVerificado)
                   VALUES ($idUsuario, $idViaje, $numPersonas, '$puntoRecoleccion', NOW(), 0)";

    if ($conn->query($sqlReserva) === TRUE) {
        $idReserva = $conn->insert_id; // Obtenemos el ID de la reserva que se acaba de crear

        // 3. Estructura de carpetas dinámica
        $rutaBase = "../uploads/viajes/idViaje_$idViaje/reserva_$idReserva";

        if (!file_exists($rutaBase)) {
            mkdir($rutaBase, 0775, true);
        }

        // 4. Procesar Acompañantes y sus Archivos
        if (isset($_POST['nombres'])) {
            $nombres = $_POST['nombres'];
            $telefonos = $_POST['telefonos'];
            $documentos = $_FILES['documentos'];

            for ($i = 0; $i < count($nombres); $i++) {
                $nombreP = $conn->real_escape_string($nombres[$i]);
                $telP = $conn->real_escape_string($telefonos[$i]);

                // Limpiar nombre para el archivo (quitar espacios)
                $nombreLimpio = str_replace(' ', '_', $nombreP);
                $nombreArchivo = "Doc_" . ($i + 1) . "_" . $nombreLimpio . ".pdf";
                $destinoFinal = $rutaBase . "/" . $nombreArchivo;

                // Subir el archivo físicamente
                if (move_uploaded_file($documentos['tmp_name'][$i], $destinoFinal)) {
                    // Guardamos la ruta relativa para que sea fácil leerla luego
                    $rutaDB = "uploads/viajes/idViaje_$idViaje/reserva_$idReserva/$nombreArchivo";

                    // Insertar acompañante en la base de datos
                    $conn->query("INSERT INTO AcompanantesPorReserva (idReserva, nombre, telefono, pasaporteDigital, verificado)
                                  VALUES ($idReserva, '$nombreP', '$telP', '$rutaDB', 0)");
                }
            }
        }

        // =========================================================================
        // 5. SISTEMA DE NOTIFICACIÓN INTERNA (NUEVO APARTADO)
        // =========================================================================
        // Consultamos el país del viaje para personalizar el asunto del correo
        $queryPais = $conn->query("SELECT pais FROM ViajeDetalles WHERE idViaje = $idViaje");
        $viajeData = $queryPais->fetch_assoc();
        $nombrePais = $viajeData ? $viajeData['pais'] : "tu destino seleccionado";

        // Redactamos el Asunto y el Mensaje formal
        $asunto = "📍 Solicitud de Reserva #$idReserva Pendiente de Verificación";
        $mensaje = "¡Hola! Te informamos que tu solicitud de reserva para el viaje a **$nombrePais** (Reserva #$idReserva) ha sido registrada exitosamente en nuestra plataforma.\n\n"
                 . "Actualmente, nuestro equipo de operadores está revisando detalladamente la documentación de los pasajeros y los pases adjuntos.\n\n"
                 . "Tu estatus actual es **Pendiente de Verificación**. En cuanto verifiquemos que tus archivos PDF cumplen con los requisitos, recibirás una nueva notificación en esta bandeja de entrada y se habilitará automáticamente tu botón de pago en la sección de 'Mis Reservas'.\n\n"
                 . "¡Gracias por confiar en CatTour! 🚢";

        // Insertar la notificación usando una sentencia preparada para evitar fallos por comillas o caracteres especiales
        $stmtCorreo = $conn->prepare("INSERT INTO NotificacionCorreo (idUsuario, idReserva, asunto, mensaje, leido) VALUES (?, ?, ?, ?, 0)");
        $stmtCorreo->bind_param("iiss", $idUsuario, $idReserva, $asunto, $mensaje);
        $stmtCorreo->execute();
        $stmtCorreo->close();
        // =========================================================================

        // Mensaje de éxito en la interfaz
        echo "<html><body style='font-family:sans-serif; text-align:center; padding-top:50px;'>";
        echo "<h1 style='color:#6f42c1;'>✅ ¡Reserva #$idReserva Exitosa!</h1>";
        echo "<p>Tus documentos han sido guardados correctamente y se ha generado una notificación en tu bandeja de entrada.</p>";
        echo "<a href='../index.php' style='text-decoration:none; color:white; background:#6f42c1; padding:10px 20px; border-radius:5px;'>Volver al Inicio</a>";
        echo "</body></html>";

    } else {
        echo "Error al crear reserva: " . $conn->error;
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>
