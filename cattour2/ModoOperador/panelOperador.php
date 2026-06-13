<?php
session_start();
include '../config/verificar_sesion_empleado.php';
include '../config/db.php';

// 1. CONTROL DE ACCESO: Validar que sea un empleado autenticado
if (!isset($_SESSION['empleado_id'])) {
    header("Location: ../auth/LoginEmploy.php");
    exit();
}

$empleado_nombre = $_SESSION['empleado_nombre'];
$empleado_rol = $_SESSION['empleado_rol'];

// 2. CONSULTAR VIAJES QUE TIENEN RESERVACIONES ACTIVAS
$sqlViajes = "SELECT V.idViaje, V.pais, V.ruta, V.fechaSalida,
                     COUNT(R.idReserva) as totalReservas
              FROM ViajeDetalles V
              INNER JOIN ReservaBoleto R ON V.idViaje = R.idViaje
              GROUP BY V.idViaje
              ORDER BY V.fechaSalida ASC";
$resultViajes = $conn->query($sqlViajes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Operador - CatTour</title>
    <style>
        :root {
            --color-principal: #6f42c1;
            --color-oscuro: #4b2c85;
            --color-suave: #f3effb;
            --texto-oscuro: #333;
            --danger: #dc3545;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            margin: 0;
            background-color: #fcfcfd;
            color: var(--texto-oscuro);
            display: flex;
        }

        /* Sidebar Lateral */
        .sidebar {
            width: 260px;
            background-color: var(--color-oscuro);
            color: white;
            min-height: 100vh;
            padding: 30px 20px;
            box-sizing: border-box;
        }
        .sidebar h2 { margin: 0 0 10px 0; font-size: 22px; }
        .sidebar .user-info { font-size: 14px; opacity: 0.8; margin-bottom: 40px; }
        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-weight: 600;
            background: rgba(255,255,255,0.05);
        }
        .sidebar a:hover { background: rgba(255,255,255,0.15); }

        /* Contenido Principal */
        .main-content { flex-grow: 1; padding: 40px; }

        .header-title {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            margin-bottom: 35px;
            border: 1px solid #efebf7;
        }
        .header-title h1 { margin: 0; color: var(--color-oscuro); font-size: 28px; }

        /* Grid de Viajes */
        .grid-viajes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        .card-viaje {
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #efebf7;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-viaje h3 { margin: 0 0 10px 0; color: var(--color-principal); font-size: 20px; }
        .card-viaje p { margin: 5px 0; color: #666; font-size: 14px; }

        .btn-ver-reservas {
            display: block;
            text-align: center;
            background: var(--color-principal);
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 20px;
            transition: 0.2s;
        }
        .btn-ver-reservas:hover { background: var(--color-oscuro); }

        /* Contenedor de tabla debajo si se selecciona un viaje */
        .reservas-section { margin-top: 40px; background: white; padding: 30px; border-radius: 12px; border: 1px solid #efebf7; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #efebf7; }
        th { background: var(--color-suave); color: var(--color-oscuro); }

        .btn-revisar {
            background: #17a2b8;
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
        }
        .btn-revisar:hover { background: #117a8b; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>CatTour 🚢</h2>
        <div class="user-info">
            👤 <?php echo htmlspecialchars($empleado_nombre); ?><br>
            <small style="color: #a29bfe;">Rol: <?php echo htmlspecialchars($empleado_rol); ?></small>
        </div>
        <a href="panelOperador.php">🗺️ Control de Viajes</a>
        <a href="../auth/logoutEmploy.php" style="background: #dc3545; margin-top: 50px;">🚪 Cerrar Sesión</a>
    </div>

    <div class="main-content">
        <div class="header-title">
            <h1>Gestión de Operaciones e Inspección de Documentos</h1>
            <p style="color: #666; margin: 5px 0 0 0;">Selecciona un destino del catálogo para auditar las reservaciones y expedientes de pasajeros.</p>
        </div>

        <h2>🗂️ Catálogo de Viajes con Reservas</h2>
        <div class="grid-viajes">
            <?php if ($resultViajes->num_rows > 0): ?>
                <?php while($viaje = $resultViajes->fetch_assoc()): ?>
                    <div class="card-viaje">
                        <div>
                            <h3><?php echo htmlspecialchars($viaje['pais']); ?></h3>
                            <p><strong>Ruta:</strong> <?php echo htmlspecialchars($viaje['ruta']); ?></p>
                            <p><strong>Fecha Salida:</strong> <?php echo date("d/m/Y", strtotime($viaje['fechaSalida'])); ?></p>
                            <p><strong>Reservas Totales:</strong> <?php echo $viaje['totalReservas']; ?></p>
                        </div>
                        <a href="panelOperador.php?idViaje=<?php echo $viaje['idViaje']; ?>#tabla-reservas" class="btn-ver-reservas">👁️ Ver Reservaciones</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No hay viajes con reservaciones registradas por el momento.</p>
            <?php endif; ?>
        </div>

        <!-- PARTE DINÁMICA: Mostrar reservas si se seleccionó un viaje específico -->
        <?php
        if (isset($_GET['idViaje'])):
            $idViajeSel = (int)$_GET['idViaje'];

            // Consultar datos del viaje seleccionado
            $vSel = $conn->query("SELECT pais FROM ViajeDetalles WHERE idViaje = $idViajeSel")->fetch_assoc();

            // Consultar las reservas de este viaje junto al nombre del cliente titular
            $sqlReservas = "SELECT R.idReserva, R.numeroPersonas, R.fechaReserva, R.statusVerificado, U.nombre as cliente
                            FROM ReservaBoleto R
                            INNER JOIN UsuarioCliente U ON R.idUsuario = U.idUsuario
                            WHERE R.idViaje = $idViajeSel
                            ORDER BY R.idReserva DESC";
            $resultRes = $conn->query($sqlReservas);
        ?>
            <div class="reservas-section" id="tabla-reservas">
                <h3>Reservaciones registradas para: <span style="color: var(--color-principal);"><?php echo htmlspecialchars($vSel['pais']); ?></span></h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID Reserva</th>
                            <th>Cliente Titular</th>
                            <th>Boletos Solicitados</th>
                            <th>Fecha de Registro</th>
                            <th>Estado General</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultRes->num_rows > 0): ?>
                            <?php while($res = $resultRes->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $res['idReserva']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($res['cliente']); ?></td>
                                    <td>👤 <?php echo $res['numeroPersonas']; ?> Pasajeros</td>
                                    <td><?php echo date("d/m/Y", strtotime($res['fechaReserva'])); ?></td>
                                    <td>
                                        <!-- NUEVO BLOQUE DE ESTADOS INTEGRADO CON ÉXITO -->
                                        <?php 
                                            if ($res['statusVerificado'] == 1) {
                                                echo '<span style="color: #28a745; font-weight: bold;">✅ Verificado</span>';
                                            } elseif ($res['statusVerificado'] == 2) {
                                                echo '<span style="color: var(--danger); font-weight: bold;">❌ Rechazado (Operador)</span>';
                                            } elseif ($res['statusVerificado'] == 3) {
                                                echo '<span style="color: #6c757d; font-weight: bold;">🚫 Cancelado (Cliente)</span>';
                                            } else {
                                                echo '<span style="color: #ffc107; font-weight: bold;">⏳ Pendiente</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="ver_pasajeros.php?idReserva=<?php echo $res['idReserva']; ?>" class="btn-revisar">🔍 Revisar Expediente</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No se encontraron reservas para este viaje.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
