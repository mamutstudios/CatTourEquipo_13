<?php
session_start();
include '../config/db.php'; // Salimos de auth/ para entrar a config/

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Limpiamos las entradas para evitar inyecciones básicas
    $usuario = $conn->real_escape_string($_POST['usuario']);
    $password = $_POST['password'];

    // Buscamos al usuario por su nombre de usuario
    $sql = "SELECT idUsuario, nombre, password FROM UsuarioCliente WHERE usuario = '$usuario'";
    $resultado = $conn->query($sql);

    if ($resultado->num_rows > 0) {
        $user = $resultado->fetch_assoc();

        // Verificamos si la contraseña coincide con el hash guardado
        if (password_verify($password, $user['password'])) {
            // ¡Éxito! Guardamos datos en la sesión
            $_SESSION['user_id'] = $user['idUsuario'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['ultimo_acceso'] = time(); // Iniciamos el cronómetro de 15 min

            // Redirigimos al index principal
            header("Location: ../index.php");
            exit();
        } else {
            $error = "La contraseña es incorrecta.";
        }
    } else {
        $error = "El nombre de usuario no existe.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CatTour</title>
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
        .registro-link { margin-top: 20px; font-size: 14px; color: #666; }
        .registro-link a { color: var(--morado); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Iniciar Sesión</h2>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <input type="text" name="usuario" placeholder="Nombre de usuario" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">ENTRAR</button>
    </form>

    <div class="registro-link">
        ¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a>
    </div>
</div>

</body>
</html>
