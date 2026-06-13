<?php
// 1. CARGAR CONTROL DE SESIÓN Y CONEXIÓN A LA BASE DE DATOS
require_once '../config/verificar_sesion_empleado.php';
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Capturar la acción y el ID enviado desde el panel
    $form_accion = isset($_POST['form_accion']) ? trim($_POST['form_accion']) : 'crear';
    $idViaje = isset($_POST['idViaje']) ? (int)$_POST['idViaje'] : 0;

    // ========================================================
    // CASO EXTRACTO: ELIMINACIÓN DE VIAJES (PROCESAMIENTO RÁPIDO)
    // ========================================================
    if ($form_accion === 'eliminar' && $idViaje > 0) {
        // Borrar imagen física primero
        $stmt_img = $conn->prepare("SELECT imagen_url FROM ViajeDetalles WHERE idViaje = ?");
        $stmt_img->bind_param("i", $idViaje);
        $stmt_img->execute();
        $res_img = $stmt_img->get_result();
        if ($v_img = $res_img->fetch_assoc()) {
            $foto_vieja = $v_img['imagen_url'];
            if ($foto_vieja !== 'default.jpg' && !empty($foto_vieja)) {
                $ruta_borrar = '../uploads/imagenesVIAJES/' . $foto_vieja;
                if (file_exists($ruta_borrar)) {
                    unlink($ruta_borrar);
                }
            }
        }
        $stmt_img->close();

        // Limpiar puntos hijos y luego el viaje padre
        $conn->query("DELETE FROM PuntosRecoleccion WHERE idViaje = $idViaje");
        $stmt_del = $conn->prepare("DELETE FROM ViajeDetalles WHERE idViaje = ?");
        $stmt_del->bind_param("i", $idViaje);
        $stmt_del->execute();
        $stmt_del->close();

        header("Location: PanelAdmin.php?status=viaje_eliminado");
        $conn->close();
        exit();
    }

    // ========================================================
    // RECOLECCIÓN Y CONFIGURACIÓN DE DATOS COMUNES (ALTA / EDICIÓN)
    // ========================================================
    $pais = trim($_POST['pais']);
    $ruta = trim($_POST['ruta']);
    $dias = (int)$_POST['dias'];
    $noches = (int)$_POST['noches'];
    $mesSalida = (int)$_POST['mesSalida'];
    $anioSalida = (int)$_POST['anioSalida'];
    $fechaSalida = $_POST['fechaSalida'];
    $estado = (int)$_POST['estado'];
    $precioBoleto = (float)$_POST['precioBoleto'];
    
    $lugaresVisitar = !empty(trim($_POST['lugaresVisitar'])) ? trim($_POST['lugaresVisitar']) : null;
    $queIncluye = !empty(trim($_POST['queIncluye'])) ? trim($_POST['queIncluye']) : null;
    $queNoIncluye = !empty(trim($_POST['queNoIncluye'])) ? trim($_POST['queNoIncluye']) : null;
    $itinerario = !empty(trim($_POST['itinerario'])) ? trim($_POST['itinerario']) : null;
    // ========================================================
    // VALIDACIÓN DE DATOS (NUEVO)
    // ========================================================
    if (empty($pais) || empty($ruta) || $dias < 1 || $precioBoleto < 0) {
        header("Location: PanelAdmin.php?status=error_validacion");
        exit();
    }
    
    // Validar que el año no sea antiguo (opcional pero recomendado)
    if ($anioSalida < date("Y")) {
        header("Location: PanelAdmin.php?status=error_fecha");
        exit();
    }

    // ========================================================
    // VALIDACIÓN DE FORMATO DE IMAGEN ANTES DE REGISTRAR
    // ========================================================
    if (isset($_FILES['imagen_viaje']) && $_FILES['imagen_viaje']['error'] === UPLOAD_ERR_OK) {
        $fileName = $_FILES['imagen_viaje']['name'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($extension, $allowedExtensions)) {
            header("Location: PanelAdmin.php?status=error_imagen");
            exit();
        }
    }

    // ========================================================
    // EVALUACIÓN DE ACCIÓN: CREAR NUEVO VS ACTUALIZAR EXISTENTE
    // ========================================================
    if ($form_accion === 'crear') {
        
        $nombre_imagen_final = "default.jpg";
        $sql = "INSERT INTO ViajeDetalles (pais, ruta, dias, noches, lugaresVisitar, queIncluye, queNoIncluye, itinerario, precioBoleto, mesSalida, anioSalida, estado, fechaSalida, imagen_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssiissssdiiiss", $pais, $ruta, $dias, $noches, $lugaresVisitar, $queIncluye, $queNoIncluye, $itinerario, $precioBoleto, $mesSalida, $anioSalida, $estado, $fechaSalida, $nombre_imagen_final);
            if ($stmt->execute()) {
                $idViaje = $conn->insert_id; // Guardamos el ID recién creado
                procesarImagen($idViaje, $conn);
                procesarPuntos($idViaje, $conn);
                header("Location: PanelAdmin.php?status=viaje_creado");
                exit();
            }
        }

    } elseif ($form_accion === 'editar' && $idViaje > 0) {
        
        // Query de actualización pura sobre el ID correspondiente
        $sql = "UPDATE ViajeDetalles SET pais=?, ruta=?, dias=?, noches=?, lugaresVisitar=?, queIncluye=?, queNoIncluye=?, itinerario=?, precioBoleto=?, mesSalida=?, anioSalida=?, estado=?, fechaSalida=? 
                WHERE idViaje=?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssiissssdiiisi", $pais, $ruta, $dias, $noches, $lugaresVisitar, $queIncluye, $queNoIncluye, $itinerario, $precioBoleto, $mesSalida, $anioSalida, $estado, $fechaSalida, $idViaje);
            if ($stmt->execute()) {
                // Procesar imagen de reemplazo opcional
                if (isset($_FILES['imagen_viaje']) && $_FILES['imagen_viaje']['error'] === UPLOAD_ERR_OK) {
                    procesarImagen($idViaje, $conn);
                }
                procesarPuntos($idViaje, $conn);
                header("Location: PanelAdmin.php?status=viaje_creado");
                exit();
            }
        }
    }
    
    header("Location: PanelAdmin.php?status=error_db");
} else {
    header("Location: PanelAdmin.php");
}

// ========================================================
// REFACTORIZACIÓN EN FUNCIONES LIMPIAS (VUELVE TU CÓDIGO SCANNEABLE)
// ========================================================
function procesarImagen($id, $conn) {
    if (isset($_FILES['imagen_viaje']) && $_FILES['imagen_viaje']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['imagen_viaje']['tmp_name'];
        $fileName = $_FILES['imagen_viaje']['name'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = array('jpg', 'jpeg', 'png', 'webp');
        if (in_array($extension, $allowedExtensions)) {
            $nombre_imagen_final = "imagenViaje_" . $id . "." . $extension;
            $dest_path = '../uploads/imagenesVIAJES/' . $nombre_imagen_final;
            
            // Si ya existía una imagen previa con diferente extensión, la removemos del FileSystem
            $stmt_old = $conn->prepare("SELECT imagen_url FROM ViajeDetalles WHERE idViaje = ?");
            $stmt_old->bind_param("i", $id);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result()->fetch_assoc();
            if ($res_old && $res_old['imagen_url'] !== 'default.jpg' && $res_old['imagen_url'] !== $nombre_imagen_final) {
                @unlink('../uploads/imagenesVIAJES/' . $res_old['imagen_url']);
            }
            $stmt_old->close();

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $stmt_update = $conn->prepare("UPDATE ViajeDetalles SET imagen_url = ? WHERE idViaje = ?");
                $stmt_update->bind_param("si", $nombre_imagen_final, $id);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }
    }
}

function procesarPuntos($id, $conn) {
    if (isset($_POST['nombrePunto']) && is_array($_POST['nombrePunto'])) {
        $sql_puntos = "INSERT INTO PuntosRecoleccion (idViaje, nombrePunto, fechaCita, horaCita) VALUES (?, ?, ?, ?)";
        if ($stmt_puntos = $conn->prepare($sql_puntos)) {
            foreach ($_POST['nombrePunto'] as $index => $nombrePunto) {
                $nombrePuntoLimpio = trim($nombrePunto);
                if (empty($nombrePuntoLimpio)) continue;

                $fechaCita = $_POST['fechaCita'][$index];
                $horaCita = $_POST['horaCita'][$index];

                $stmt_puntos->bind_param("isss", $id, $nombrePuntoLimpio, $fechaCita, $horaCita);
                $stmt_puntos->execute();
            }
            $stmt_puntos->close();
        }
    }
}

$conn->close();
?>
