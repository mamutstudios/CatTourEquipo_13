<?php
session_start();
// Asegúrate de que esta ruta sea la correcta para llegar a tu db.php
include '../config/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Usamos trim para eliminar espacios accidentales al inicio o final
    $usuario_ingresado = trim($_POST['usuario']);
    $password_ingresada = $_POST['password'];

    // Sentencia Preparada para proteger la cuenta del Owner
    $sql = "SELECT idEmpleado, nombre, password, rol, tiempoSesion FROM Empleado WHERE usuario = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $usuario_ingresado);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $user = $resultado->fetch_assoc();

            // Verificación del Hash generado previamente
            if (password_verify($password_ingresada, $user['password'])) {
                
                // Seguridad de sesión para tu reporte de la UV
                session_regenerate_id(true);

                $_SESSION['empleado_id'] = $user['idEmpleado'];
                $_SESSION['empleado_nombre'] = $user['nombre'];
                $_SESSION['empleado_rol'] = $user['rol'];
                $_SESSION['ultimo_acceso'] = time();
                // Convertimos minutos de la BD a segundos para PHP
                $_SESSION['duracion_maxima'] = (int)$user['tiempoSesion'] * 60;

                // Enrutamiento según el Rol
                if ($user['rol'] === 'Owner') {
                    header("Location: ../OWNER/PanelOwner.php");
                } else {
                    header("Location: ../admin/dashboard.php");
                }
                exit();
            } else {
                $error = "Credenciales incorrectas (Contraseña).";
            }
        } else {
            $error = "El usuario no existe en los registros de Staff.";
        }
        $stmt->close();
    } else {
        $error = "Error interno del sistema de base de datos.";
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        .login-card .icon-staff {
            font-size: 50px;
            margin-bottom: 15px;
        }
        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 16px;
            outline: none;
        }
        input:focus {
            border-color: var(--morado);
            box-shadow: 0 0 5px rgba(111, 66, 193, 0.3);
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
        button:hover {
            background: var(--morado-oscuro);
        }
        .error-msg {
            color: #dc3545;
            background: #f8d7da;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .back-link a {
            color: var(--morado);
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="icon-staff">🛡️</div>
    <h2>Acceso Personal</h2>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="LoginEmploy.php" method="POST">
        <input type="text" name="usuario" placeholder="Usuario Staff" required autocomplete="username">
        <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
        <button type="submit">ENTRAR AL SISTEMA</button>
    </form>

    <div class="back-link">
        <a href="login2.php">← Volver a Login Clientes</a>
    </div>
</div>

</body>
</html>
