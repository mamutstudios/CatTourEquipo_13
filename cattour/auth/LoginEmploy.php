<?php
session_start();
// Ajusta esta ruta según la ubicación real de LoginEmploy.php y config/db.php
include '../config/db.php';

$error = "";

// Capturar el mensaje si fue expulsado por la expiración de sesión que pusimos en config/
if (isset($_GET['error']) && $_GET['error'] === 'sesion_expirada') {
    $error = "Tu sesión ha expirado por inactividad. Por seguridad, inicia sesión de nuevo.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];

    // Sentencia Preparada para evitar Inyección SQL
    $sql = "SELECT idEmpleado, nombre, usuario, password, rol, tiempoSesion FROM Empleado WHERE usuario = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $user = $resultado->fetch_assoc();
            
            // --- LÍNEAS DE DEPURACIÓN TEMPORAL CORREGIDAS ---
            error_log("Hash en DB: " . $user['password']);
            error_log("Password enviada: " . $password);
            // -------------------------------------

            // Verificación del Hash de la contraseña
            if (password_verify($password, $user['password'])) {

                // Regenerar ID de sesión para prevenir Session Fixation
                session_regenerate_id(true);

                $_SESSION['empleado_id'] = $user['idEmpleado'];
                $_SESSION['empleado_nombre'] = $user['nombre'];
                $_SESSION['empleado_rol'] = $user['rol'];
                $_SESSION['ultimo_acceso'] = time();
                $_SESSION['duracion_maxima'] = $user['tiempoSesion'] * 60;

                // Enrutamiento (RBAC) ajustado a tu entorno de servidores Linux
                if ($user['rol'] === 'Owner') {
                    // Ruta apuntando al directorio OWNER
                    header("Location: ../OWNER/PanelOwner.php");
                } else if ($user['rol'] === 'Administrador') {
                    // CORRECCIÓN: Ruta apuntando a la carpeta ModoAdmin y su PanelAdmin.php
                    header("Location: ../ModoAdmin/PanelAdmin.php");
                } else {
                    // Ruta de respaldo por si tienes un rol Operador/Staff básico en el futuro
                    header("Location: ../ModoOperador/panelOperador.php");
                }
                exit();
            } else {
                $error = "Credenciales incorrectas.";
            }
        } else {
            $error = "Credenciales incorrectas.";
        }
        $stmt->close();
    } else {
        error_log("Error de DB en LoginEmploy: " . $conn->error);
        $error = "Ocurrió un error interno. Intente más tarde.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Personal - CatTour</title>
    <style>
        :root {
            --morado: #6f42c1;
            --morado-oscuro: #4b2c85;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--morado);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 350px;
            text-align: center;
        }
        .login-card h2 {
            color: var(--morado);
            margin-bottom: 25px;
            font-size: 22px;
        }
        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 16px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: var(--morado);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            font-size: 16px;
        }
        button:hover { background: var(--morado-oscuro); }
        .error-msg {
            color: #dc3545;
            background: #f8d7da;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .back-link { margin-top: 20px; font-size: 14px; color: #666; }
        .back-link a { color: var(--morado); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="login-card">
    <div style="font-size: 40px; margin-bottom: 10px;">🐾</div>
    <h2>Inicio Sesión Personal</h2>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <input type="text" name="usuario" placeholder="Usuario Personal" required autocomplete="username">
        <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
        <button type="submit">ENTRAR AL PANEL</button>
    </form>

    <div class="back-link">
        <a href="login2.php">← Volver a Login Clientes</a>
    </div>
</div>

</body>
</html>
