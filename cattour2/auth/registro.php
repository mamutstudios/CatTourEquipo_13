<?php
session_start();
include '../config/db.php'; // Salimos de auth/ para entrar a config/

$error = "";
$exito = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Limpiar entradas para evitar inyecciones básicas y espacios vacíos
    $usuario  = trim($conn->real_escape_string($_POST['usuario']));
    $nombre   = trim($conn->real_escape_string($_POST['nombre']));
    $correo   = trim($conn->real_escape_string($_POST['correo']));
    $password = $_POST['password'];
    $numero   = trim($conn->real_escape_string($_POST['numero']));

    // 1. VALIDACIÓN: Verificar si el usuario o el correo ya existen en la base de datos
    $sql_verificar = "SELECT usuario, correo FROM UsuarioCliente WHERE usuario = ? OR correo = ?";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bind_param("ss", $usuario, $correo);
    $stmt_verificar->execute();
    $resultado_verificar = $stmt_verificar->get_result();

    if ($resultado_verificar->num_rows > 0) {
        // Recorremos el resultado para saber exactamente qué se duplicó
        $existe = $resultado_verificar->fetch_assoc();
        if ($existe['usuario'] === $usuario) {
            $error = "El nombre de usuario '$usuario' ya fue registrado antes. Elige otro.";
        } else if ($existe['correo'] === $correo) {
            $error = "El correo electrónico '$correo' ya se encuentra registrado.";
        }
        $stmt_verificar->close();
    } else {
        $stmt_verificar->close();

        // 2. ENCRIPTACIÓN: Crear el hash seguro de la contraseña
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // 3. INSERCIÓN: Registrar al nuevo cliente usando sentencias preparadas
        $sql_insertar = "INSERT INTO UsuarioCliente (usuario, nombre, correo, password, numero) VALUES (?, ?, ?, ?, ?)";
        
        if ($stmt_insertar = $conn->prepare($sql_insertar)) {
            $stmt_insertar->bind_param("sssss", $usuario, $nombre, $correo, $password_hash, $numero);
            
            if ($stmt_insertar->execute()) {
                $exito = "¡Registro completado con éxito! Ya puedes iniciar sesión.";
            } else {
                $error = "Ocurrió un error inesperado al guardar los datos.";
            }
            $stmt_insertar->close();
        } else {
            $error = "Error interno del servidor. Inténtalo más tarde.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - CatTour</title>
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
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .register-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .register-card h2 { color: var(--morado); margin-bottom: 25px; }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input:focus {
            outline: none;
            border-color: var(--morado);
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
            margin-top: 10px;
        }
        button:hover { background: var(--morado-oscuro); }

        .error-msg {
            color: #dc3545;
            background: #f8d7da;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #f5c6cb;
            text-align: left;
        }
        .exito-msg {
            color: #155724;
            background: #d4edda;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #c3e6cb;
        }
        .login-link { margin-top: 20px; font-size: 14px; color: #666; }
        .login-link a { color: var(--morado); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="register-card">
    <div style="font-size: 40px; margin-bottom: 10px;">🗺️</div>
    <h2>Crear Cuenta</h2>

    <?php if (!empty($error)): ?>
        <div class="error-msg">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($exito)): ?>
        <div class="exito-msg">✅ <?php echo $exito; ?></div>
    <?php endif; ?>

    <form action="registro.php" method="POST">
        <input type="text" name="nombre" placeholder="Nombre completo" required>
        <input type="text" name="usuario" placeholder="Nombre de usuario" required>
        <input type="email" name="correo" placeholder="Correo electrónico" required>
        <input type="tel" name="numero" placeholder="Teléfono celular" required>
        <input type="password" name="password" placeholder="Contraseña de acceso" required>
        <button type="submit">REGISTRARME</button>
    </form>

    <div class="login-link">
        ¿Ya tienes cuenta? <a href="login2.php">Inicia sesión aquí</a>
    </div>
</div>

</body>
</html>
