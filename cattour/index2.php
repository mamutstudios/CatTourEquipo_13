<?php
session_start();
include 'config/db.php';

// Verificación de sesión
$sesionActiva = isset($_SESSION['user_id']);
$nombreUsuario = $sesionActiva ? $_SESSION['usuario_nombre'] : null;

// Consulta de viajes (manteniendo tus campos de BD)
$queryViajes = "SELECT * FROM ViajeDetalles";
$resultadoViajes = $conn->query($queryViajes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatTour - Agencia de Viajes</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        /* Estilos para la lógica de sesión en el Header */
        .header-tools {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn-auth {
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }
        .login-link { color: #6f42c1; border: 1px solid #6f42c1; }
        .logout-link { color: #dc3545; }

        /* Estilos para el Buzón al final de la página */
        .section-buzon {
            padding: 50px 20px;
            background-color: #f9f9f9;
            margin-top: 50px;
        }
        .tabla-notificaciones {
            width: 100%;
            max-width: 1000px;
            margin: 20px auto;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .tabla-notificaciones th, .tabla-notificaciones td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
        }
        .status-pendiente { background: #fff3cd; color: #856404; }
        .status-aprobado { background: #d4edda; color: #155724; }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="container-header">
            <div class="logo">
                <img src="img/logo_cattour.png" alt="CatTour">
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="#catalogo">Viajes</a></li>
                    <?php if ($sesionActiva): ?>
                        <li><a href="#buzon">Mi Buzón</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="header-tools">
                <?php if ($sesionActiva): ?>
                    <span class="user-name">👤 <?php echo htmlspecialchars($nombreUsuario); ?></span>
                    <a href="auth/logout.php" class="logout-link">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn-auth login-link">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="hero-banner">
        <div class="hero-content">
            <h1>Descubre el mundo con CatTour</h1>
            <p>Tus sueños de viaje comienzan aquí</p>
        </div>
    </section>

    <section id="catalogo" class="viajes-container">
        <h2 class="section-title">Nuestros Destinos Proximos</h2>
        <div class="grid-viajes">
            <?php while($viaje = $resultadoViajes->fetch_assoc()): ?>
                <div class="viaje-card">
                    <div class="viaje-image">
                        <img src="img/<?php echo $viaje['foto']; ?>" alt="Destino">
                    </div>
                    <div class="viaje-info">
                        <h3><?php echo $viaje['pais']; ?></h3>
                        <p class="descripcion"><?php echo $viaje['lugaresVisitar']; ?></p>
                        <div class="viaje-footer">
                            <span class="precio">$<?php echo number_format($viaje['precioBoleto'], 2); ?></span>
                            
                            <?php if ($sesionActiva): ?>
                                <a href="reservaciones/reserval.php?id=<?php echo $viaje['idViaje']; ?>" class="btn-reservar">Reservar</a>
                            <?php else: ?>
                                <a href="auth/login.php" class="btn-reservar" style="background:#ccc;">Logueate</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>

    <?php if ($sesionActiva): ?>
    <section id="buzon" class="section-buzon">
        <div class="container">
            <h2 style="text-align:center;">📬 Mi Buzón de Notificaciones</h2>
            <p style="text-align:center;">Consulta el estado de tus documentos y procede al pago.</p>
            
            <table class="tabla-notificaciones">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Destino</th>
                        <th>Estatus Documental</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $idU = $_SESSION['user_id'];
                    $sqlBuzon = "SELECT R.idReserva, V.pais, R.statusVerificado 
                                 FROM ReservaBoleto R 
                                 JOIN ViajeDetalles V ON R.idViaje = V.idViaje 
                                 WHERE R.idUsuario = $idU";
                    $resBuzon = $conn->query($sqlBuzon);

                    if ($resBuzon->num_rows > 0):
                        while($row = $resBuzon->fetch_assoc()):
                    ?>
                    <tr>
                        <td>#<?php echo $row['idReserva']; ?></td>
                        <td><?php echo $row['pais']; ?></td>
                        <td>
                            <?php if ($row['statusVerificado'] == 0): ?>
                                <span class="status-badge status-pendiente">⏳ Pendiente de Revisión</span>
                            <?php else: ?>
                                <span class="status-badge status-aprobado">✅ Documentos Aprobados</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['statusVerificado'] == 1): ?>
                                <a href="pagos/checkout.php?reserva=<?php echo $row['idReserva']; ?>" style="color:#6f42c1; font-weight:bold;">Pagar Ahora</a>
                            <?php else: ?>
                                <span style="color:#999;">Esperar validación</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" style="text-align:center;">No tienes solicitudes pendientes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <footer class="footer">
        <p>&copy; 2026 CatTour - Ingeniería en Sistemas UV</p>
    </footer>

</body>
</html>
