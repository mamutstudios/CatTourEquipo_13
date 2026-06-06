<?php
session_start();
include '../config/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];

    // Sentencia Preparada (Seguridad Nivel Enterprise)
    $sql = "SELECT idEmpleado, nombre, usuario, password, rol, tiempoSesion FROM Empleado WHERE usuario = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $user = $resultado->fetch_assoc();

            // Verificación del Hash que acabamos de generar en la terminal
            if (password_verify($password, $user['password'])) {

                session_regenerate_id(true);

                // Seteo de Sesiones
                $_SESSION['empleado_id'] = $user['idEmpleado'];
                $_SESSION['empleado_nombre'] = $user['nombre'];
                $_SESSION['empleado_rol'] = $user['rol'];
                $_SESSION['ultimo_acceso'] = time();
                $_SESSION['duracion_maxima'] = $user['tiempoSesion'] * 60;

                // REDIRECCIÓN CORREGIDA:
                // Como LoginEmploy.php está en 'auth/', usamos '../' para salir a la raíz
                // y luego entrar a 'OWNER/'
                if ($user['rol'] === 'Owner') {
                    header("Location: ../OWNER/PanelOwner.php");
                } else {
                    header("Location: ../admin/dashboard.php");
                }
                exit();
            } else {
                $error = "La contraseña es incorrecta.";
            }
        } else {
            $error = "El usuario no existe.";
        }
        $stmt->close();
    } else {
        $error = "Error interno del servidor.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Staff - CatTour</title>
    <style>
        :root { --morado: #6f42c1; --morado-oscuro: #4b2c85; }
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
            box-shadow: 0 15px 35px rgba(0,0,0,0.3); 
            width: 100%; 
            max-width: 350px; 
            text-align: center; 
        }
        .login-card h2 { color: var(--morado); margin-bottom: 25px; }
        input { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 15px; 
            border: 1px solid #ddd; 
            border-radius: 10px; 
            box-sizing: border-box; 
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
        .back-link { margin-top: 20px; font-size: 14px; }
        .back-link a { color: var(--morado); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="login-card">
    <div style="font-size: 40px; margin-bottom: 10px;">🛡️</div>
    <h2>Acceso Staff</h2>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="LoginEmploy.php" method="POST">
        <input type="text" name="usuario" placeholder="Usuario Staff" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">ENTRAR AL SISTEMA</button>
    </form>

    <div class="back-link">
        <a href="login.php">← Volver a Clientes</a>
    </div>
</div>

</body>
</html>
