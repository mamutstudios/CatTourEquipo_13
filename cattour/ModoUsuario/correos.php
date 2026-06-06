<?php
session_start();

// 1. CONTROL DE ACCESO: SI NO HAY SESIÓN ACTIVA, EXPULSAR AL LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login2.php");
    exit();
}

include '../config/db.php';

$idUsuario = $_SESSION['user_id'];
$user_nombre = isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : "Usuario";

// 2. OBTENER LOS CORREOS DEL USUARIO LOGUEADO (DEL MÁS RECIENTE AL MÁS VIEJO)
$sql = "SELECT idCorreo, asunto, mensaje, fechaEnvio, leido FROM NotificacionCorreo WHERE idUsuario = ? ORDER BY idCorreo DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Correos - CatTour</title>
    <style>
        :root {
            --color-principal: #6f42c1;
            --color-oscuro: #4b2c85;
            --color-suave: #f3effb;
            --texto-oscuro: #333;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #fcfcfd;
            color: var(--texto-oscuro);
        }

        /* Barra de navegación minimalista para el apartado de correos */
        nav {
            background: white;
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--color-principal);
            text-decoration: none;
        }
        .menu a {
            text-decoration: none;
            color: var(--texto-oscuro);
            font-weight: 500;
            margin-left: 20px;
            transition: color 0.3s;
        }
        .menu a:hover { color: var(--color-principal); }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        h2 {
            color: var(--color-oscuro);
            border-left: 6px solid var(--color-principal);
            padding-left: 15px;
            margin-bottom: 30px;
        }

        /* Contenedor de la Bandeja de Entrada */
        .mailbox {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid #efebf7;
            overflow: hidden;
        }

        /* Estilo de cada fila de Correo */
        .email-item {
            padding: 20px;
            border-bottom: 1px solid #efebf7;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: background 0.2s;
        }
        .email-item:last-child {
            border-bottom: none;
        }
        .email-item:hover {
            background-color: #faf8ff;
        }

        /* Indicador de correo no leído */
        .no-leido {
            background-color: #f7f3ff;
            border-left: 4px solid var(--color-principal);
        }

        .email-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .email-subject {
            font-size: 16px;
            font-weight: bold;
            color: #222;
        }

        .no-leido .email-subject {
            color: var(--color-oscuro);
        }

        .email-date {
            font-size: 12px;
            color: #888;
        }

        .email-body {
            font-size: 14px;
            color: #555;
            line-height: 1.5;
            white-space: pre-line; /* Mantiene saltos de línea del texto */
        }

        /* Estado vacío */
        .empty-mailbox {
            text-align: center;
            padding: 50px 20px;
            color: #777;
        }
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
    </style>
</head>
<body>

    <nav>
        <a href="../index.php" class="logo">CAT TOUR 🚢</a>
        <div class="menu">
            <span style="font-weight: 600; color: var(--color-oscuro);">👤 <?php echo htmlspecialchars($user_nombre); ?></span>
            <a href="../index.php">🏠 Inicio</a>
            <a href="../ModoUsuario/mis_reservas.php">💼 Mis Reservas</a>
            <a href="../auth/logout.php" style="color: #dc3545; font-size: 14px; font-weight: bold;">Cerrar Sesión</a>
        </div>
    </nav>

    <div class="container">
        <h2>Bandeja de Notificaciones Internas</h2>

        <div class="mailbox">
            <?php if ($resultado->num_rows > 0): ?>
                <?php while($correo = $resultado->fetch_assoc()): ?>
                    <div class="email-item <?php echo ($correo['leido'] == 0) ? 'no-leido' : ''; ?>">
                        <div class="email-header">
                            <span class="email-subject">
                                <?php echo ($correo['leido'] == 0) ? '✉️ ' : '📩 '; ?>
                                <?php echo htmlspecialchars($correo['asunto']); ?>
                            </span>
                            <span class="email-date">
                                📅 <?php echo date("d/m/Y H:i", strtotime($correo['fechaEnvio'])); ?>
                            </span>
                        </div>
                        <div class="email-body">
                            <?php echo htmlspecialchars($correo['mensaje']); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-mailbox">
                    <span class="empty-icon">📥</span>
                    <h3>Tu bandeja de entrada está vacía</h3>
                    <p>No tienes notificaciones o alertas en este momento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
