<?php
session_start(); // Fundamental para capturar al usuario logueado
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // =========================================================================
    // 1. VALIDAR SESIÓN CON INTERFAZ ELEGANTE (MEJORA UX)
    // =========================================================================
    if (!isset($_SESSION['user_id'])) {
        echo "<html>";
        echo "<head>";
        echo "  <meta charset='UTF-8'>";
        echo "  <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "  <title>Acceso Restringido - CatTour</title>";
        echo "  <style>";
        echo "      body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }";
        echo "      .card-error { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 450px; border-top: 5px solid #dc3545; }";
        echo "      h1 { color: #dc3545; font-size: 24px; margin-top: 0; }";
        echo "      p { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 25px; }";
        echo "      .btn-login { display: inline-block; text-decoration: none; color: white; background: #6f42c1; padding: 12px 25px; border-radius: 8px; font-weight: bold; transition: 0.3s; }";
        echo "      .btn-login:hover { background: #4b2c85; }";
        echo "  </style>";
        echo "</head>";
        echo "<body>";
        echo "  <div class='card-error'>";
        echo "      <h1>🔒 Sesión no Iniciada</h1>";
        echo "      <p>Lo sentimos, debes iniciar sesión en tu cuenta de cliente para poder realizar y procesar una reservación en el sistema.</p>";
        echo "      <a href='../auth/login2.php' class='btn-login'>Iniciar Sesión ahora</a>";
        echo "  </div>";
        echo "</body>";
        echo "</html>";
        exit();
    }

    $idUsuario = $_SESSION['user_id'];
    $idViaje = (int)$_POST['idViaje'];
    // Ya no necesitamos real_escape_string porque la sentencia preparada lo protege
    $puntoRecoleccion = $_POST['punto_recoleccion'];
    $numPersonas = (int)$_POST['cantidad_boletos'];

    // =========================================================================
    // 2. VERIFICAR QUE TODOS LOS DOCUMENTOS SEAN ESTRICTAMENTE PDF
    // =========================================================================
    if (isset($_FILES['documentos']) && isset($_POST['nombres'])) {
        $documentos = $_FILES['documentos'];
        $nombres = $_POST['nombres'];
        $erroresPdf = [];

        for ($i = 0; $i < count($documentos['name']); $i++) {
            if ($documentos['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $documentos['tmp_name'][$i];
                $fileName = $documentos['name'][$i];

                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $mime = mime_content_type($tmpName);

                if ($ext !== 'pdf' || $mime !== 'application/pdf') {
                    $erroresPdf[] = "El documento adjunto para el pasajero <b>" . htmlspecialchars($nombres[$i]) . "</b> no es un PDF válido.";
                }
            } else {
                $codigoError = $documentos['error'][$i];
                $motivo = "";
                switch ($codigoError) {
                    case UPLOAD_ERR_INI_SIZE:
                        $motivo = "El archivo pesa más de lo que permite el servidor (supera upload_max_filesize).";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $motivo = "El archivo se subió a medias. Revisa tu conexión.";
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $motivo = "No se adjuntó ningún archivo.";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $motivo = "Falta la carpeta temporal en el servidor.";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $motivo = "Permisos denegados. El servidor no pudo escribir el archivo en el disco.";
                        break;
                    default:
                        $motivo = "Error interno de PHP código $codigoError.";
                        break;
                }
                $erroresPdf[] = "Hubo un error al subir el documento para <b>" . htmlspecialchars($nombres[$i]) . "</b>: $motivo";
            }
        }

        // Si hay errores, detener la ejecución y mostrar la interfaz
        if (!empty($erroresPdf)) {
            echo "<html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Error de Archivo - CatTour</title>";
            echo "<style>
                body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .card-error { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; border-top: 5px solid #dc3545; }
                h1 { color: #dc3545; font-size: 24px; margin-top: 0; }
                p { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 15px; }
                ul { text-align: left; color: #dc3545; font-size: 14px; background: #ffe6e6; padding: 15px 15px 15px 35px; border-radius: 8px; margin-bottom: 25px; }
                li { margin-bottom: 8px; }
                .btn-volver { display: inline-block; text-decoration: none; color: white; background: #6f42c1; padding: 12px 25px; border-radius: 8px; font-weight: bold; transition: 0.3s; }
                .btn-volver:hover { background: #4b2c85; }
            </style></head><body><div class='card-error'>";
            echo "<h1>📄 Formato de Documento Inválido</h1>";
            echo "<p>Se detectaron los siguientes problemas con tus archivos:</p>";
            echo "<ul>";
            foreach ($erroresPdf as $error) {
                echo "<li>" . $error . "</li>";
            }
            echo "</ul>";
            echo "<a href='javascript:history.back()' class='btn-volver'>Volver y corregir archivos</a>";
            echo "</div></body></html>";
            exit(); 
        }
    }

    // =========================================================================
    // 3. Insertar el registro principal de la Reserva (BLINDADO)
    // =========================================================================
    $stmtReserva = $conn->prepare("INSERT INTO ReservaBoleto (idUsuario, idViaje, numeroPersonas, puntoRecoleccion, fechaReserva, statusVerificado) VALUES (?, ?, ?, ?, NOW(), 0)");
    $stmtReserva->bind_param("iiis", $idUsuario, $idViaje, $numPersonas, $puntoRecoleccion);

    if ($stmtReserva->execute() === TRUE) {
        $idReserva = $stmtReserva->insert_id;
        $stmtReserva->close();

        // 4. Estructura de carpetas dinámica
        $rutaBase = "../uploads/viajes/idViaje_$idViaje/reserva_$idReserva";

        if (!file_exists($rutaBase)) {
            mkdir($rutaBase, 0775, true);
        }

        // 5. Procesar Acompañantes y mover sus Archivos (Ya están validados)
        if (isset($_POST['nombres'])) {
            $nombres = $_POST['nombres'];
            $telefonos = $_POST['telefonos'];
            $documentos = $_FILES['documentos'];

            for ($i = 0; $i < count($nombres); $i++) {
                $nombreP = $nombres[$i];
                $telP = $telefonos[$i];

                // Limpiar nombre para el archivo (Previene inyecciones a nivel de Sistema Operativo)
                $nombreLimpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombreP);
                $nombreArchivo = "Doc_" . ($i + 1) . "_" . $nombreLimpio . ".pdf"; 
                $destinoFinal = $rutaBase . "/" . $nombreArchivo;

                // Subir el archivo físicamente
                if (move_uploaded_file($documentos['tmp_name'][$i], $destinoFinal)) {
                    $rutaDB = "uploads/viajes/idViaje_$idViaje/reserva_$idReserva/$nombreArchivo";

                    // Insertar acompañante en la base de datos (BLINDADO)
                    $stmtAcomp = $conn->prepare("INSERT INTO AcompanantesPorReserva (idReserva, nombre, telefono, pasaporteDigital, verificado) VALUES (?, ?, ?, ?, 0)");
                    $stmtAcomp->bind_param("isss", $idReserva, $nombreP, $telP, $rutaDB);
                    $stmtAcomp->execute();
                    $stmtAcomp->close();
                }
            }
        }

        // =========================================================================
        // 6. SISTEMA DE NOTIFICACIÓN INTERNA (BLINDADO)
        // =========================================================================
        $stmtPais = $conn->prepare("SELECT pais FROM ViajeDetalles WHERE idViaje = ?");
        $stmtPais->bind_param("i", $idViaje);
        $stmtPais->execute();
        $viajeData = $stmtPais->get_result()->fetch_assoc();
        $stmtPais->close();

        $nombrePais = $viajeData ? $viajeData['pais'] : "tu destino seleccionado";

        $asunto = "📍 Solicitud de Reserva #$idReserva Pendiente de Verificación";
        $mensaje = "¡Hola! Te informamos que tu solicitud de reserva para el viaje a **$nombrePais** (Reserva #$idReserva) ha sido registrada exitosamente en nuestra plataforma.\n\n"
                 . "Actualmente, nuestro equipo de operadores está revisando detalladamente la documentación de los pasajeros y los pases adjuntos.\n\n"
                 . "Tu estatus actual es **Pendiente de Verificación**. En cuanto verifiquemos que tus archivos PDF cumplen con los requisitos, recibirás una nueva notificación en esta bandeja de entrada y se habilitará automáticamente tu botón de pago en la sección de 'Mis Reservas'.\n\n"
                 . "¡Gracias por confiar en CatTour! 🚢";

        // Este INSERT ya lo tenías blindado perfectamente desde el principio
        $stmtCorreo = $conn->prepare("INSERT INTO NotificacionCorreo (idUsuario, idReserva, asunto, mensaje, leido) VALUES (?, ?, ?, ?, 0)");
        $stmtCorreo->bind_param("iiss", $idUsuario, $idReserva, $asunto, $mensaje);
        $stmtCorreo->execute();
        $stmtCorreo->close();

        // =========================================================================
        // 7. INTERFAZ DE ÉXITO ACTUALIZADA
        // =========================================================================
        echo "<html>";
        echo "<head>";
        echo "  <meta charset='UTF-8'>";
        echo "  <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "  <title>Reserva Exitosa - CatTour</title>";
        echo "  <style>";
        echo "      body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }";
        echo "      .card-success { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; max-width: 550px; border-top: 5px solid #6f42c1; }";
        echo "      h1 { color: #6f42c1; font-size: 26px; margin-top: 0; }";
        echo "      p { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 20px; }";
        echo "      .highlight { font-weight: bold; color: #4b2c85; }";
        echo "      .btn-container { margin-top: 25px; display: flex; gap: 15px; justify-content: center; }";
        echo "      .btn { display: inline-block; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: bold; font-size: 14px; transition: 0.3s; }";
        echo "      .btn-principal { color: white; background: #6f42c1; }";
        echo "      .btn-principal:hover { background: #4b2c85; }";
        echo "      .btn-secundario { color: #6f42c1; background: #f3effb; border: 1px solid #6f42c1; }";
        echo "      .btn-secundario:hover { background: #e8e1f7; }";
        echo "  </style>";
        echo "</head>";
        echo "<body>";
        echo "  <div class='card-success'>";
        echo "      <h1>🚢 ¡Reserva #$idReserva Registrada!</h1>";
        echo "      <p>Te hemos enviado un correo de confirmación de reserva a tu bandeja interna. Puedes consultar todos los detalles e itinerarios de tu solicitud en cualquier momento desde la sección de <span class='highlight'>Mis Reservas</span>.</p>";
        echo "      <p style='background: #fff9db; padding: 12px; border-radius: 8px; border-left: 4px solid #f59f00; text-align: left; font-size: 14px;'>⚙️ <strong>Nota importante:</strong> Un operador se encargará de verificar minuciosamente la documentación adjunta para que, posteriormente, se habilite tu pasarela y puedas realizar el pago del boleto seguro.</p>";
        echo "      <div class='btn-container'>";
        echo "          <a href='../ModoUsuario/mis_reservas.php' class='btn btn-principal'>Ir a Mis Reservas</a>";
        echo "          <a href='../index.php' class='btn btn-secundario'>Volver al Inicio</a>";
        echo "      </div>";
        echo "  </div>";
        echo "</body>";
        echo "</html>";

    } else {
        echo "Error al crear reserva: " . $conn->error;
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>
