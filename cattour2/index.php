<?php
// Iniciar sesión para poder verificar si el usuario se ha logueado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config/db.php';

// 1. CAPTURAR PARÁMETROS DE FILTRADO (VÍA GET)
$mes_filtrado = isset($_GET['mes']) ? (int)$_GET['mes'] : 0;
$anio_filtrado = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;

// 2. CONSTRUIR LA CONSULTA DINÁMICA CON SENTENCIAS PREPARADAS
$sql = "SELECT * FROM ViajeDetalles WHERE estado = 1";

if ($mes_filtrado > 0) {
    $sql .= " AND mesSalida = ?";
}
if ($anio_filtrado > 0) {
    $sql .= " AND anioSalida = ?";
}

$sql .= " ORDER BY idViaje DESC";

// Preparar y enlazar los parámetros según los filtros activos
$stmt = $conn->prepare($sql);

if ($mes_filtrado > 0 && $anio_filtrado > 0) {
    $stmt->bind_param("ii", $mes_filtrado, $anio_filtrado);
} elseif ($mes_filtrado > 0) {
    $stmt->bind_param("i", $mes_filtrado);
} elseif ($anio_filtrado > 0) {
    $stmt->bind_param("i", $anio_filtrado);
}

$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatTour - Experiencias Increíbles</title>
    <style>
        :root {
            /* Paleta de Morados */
            --color-principal: #6f42c1; /* Morado base */
            --color-oscuro: #4b2c85;    /* Morado profundo */
            --color-suave: #f3effb;     /* Fondo lila muy claro */
            --texto-oscuro: #333;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            margin: 0;
            background-color: #fcfcfd;
            color: var(--texto-oscuro);
        }

        /* Navegación */
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
        .menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .menu a {
            text-decoration: none;
            color: var(--texto-oscuro);
            font-weight: 500;
            transition: color 0.3s;
        }
        .menu a:hover { color: var(--color-principal); }

        /* Estilo especial para los botones de acción del Nav */
        .btn-nav-action {
            background: var(--color-principal);
            color: white !important;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold !important;
            transition: background 0.2s !important;
        }
        .btn-nav-action:hover {
            background: var(--color-oscuro);
        }
        .user-welcome {
            font-weight: 600;
            color: var(--color-oscuro);
        }

        /* Hero Section (Banner Morado) */
        .hero {
            background: linear-gradient(135deg, var(--color-oscuro) 0%, var(--color-principal) 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
        }
        .hero h1 { margin: 0; font-size: 2.8rem; letter-spacing: -1px; }
        .hero p { opacity: 0.9; margin-top: 10px; font-size: 1.1rem; }

        /* Contenedor */
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }

        .section-title {
            font-size: 28px;
            margin-bottom: 30px;
            border-left: 6px solid var(--color-principal);
            padding-left: 15px;
            color: var(--color-oscuro);
        }

        /* --- NUEVOS ESTILOS DEL SISTEMA DE FILTROS --- */
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            margin-bottom: 35px;
            border: 1px solid #efebf7;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: bold;
            color: var(--color-oscuro);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            color: var(--texto-oscuro);
            background-color: white;
            min-width: 160px;
            outline: none;
            transition: border-color 0.2s;
        }

        .filter-select:focus {
            border-color: var(--color-principal);
        }

        .btn-filter {
            background: var(--color-principal);
            color: white;
            border: none;
            padding: 11px 25px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            align-self: flex-end;
            transition: background 0.2s;
        }

        .btn-filter:hover {
            background: var(--color-oscuro);
        }

        .btn-clear {
            background: #f0f2f5;
            color: #555;
            text-decoration: none;
            padding: 11px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            align-self: flex-end;
            transition: background 0.2s;
            text-align: center;
        }

        .btn-clear:hover {
            background: #e4e6e9;
        }

        .no-results {
            grid-column: span 3;
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 12px;
            color: #666;
            border: 1px dashed #ccc;
        }

        /* Grid */
        .viajes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        /* Tarjeta */
        .card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(111, 66, 193, 0.15);
        }

        /* Contenedor de Imagen de Cabecera */
        .card-img-header {
            height: 320px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 20px;
            box-sizing: border-box;
            background: linear-gradient(135deg, var(--color-principal) 0%, #a29bfe 100%);
        }

        .card-img-header::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.25);
            z-index: 1;
        }

        .card-img-header * {
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }

        .card-content { padding: 25px; flex-grow: 1; }
        .card-pais { font-size: 22px; font-weight: bold; margin: 0; color: var(--color-oscuro); }
        .card-ruta { font-size: 14px; color: #666; margin: 8px 0 18px 0; line-height: 1.4; }

        .card-info {
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            color: var(--color-principal);
            font-weight: 600;
        }

        /* Sección de Precio */
        .card-precio {
            background: var(--color-suave);
            color: var(--color-oscuro);
            padding: 20px;
            text-align: center;
            border-top: 1px solid #efebf7;
        }
        .precio-monto { font-size: 1.6rem; font-weight: 800; display: block; color: var(--color-principal); }
        .precio-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #777; }

        .btn-ver {
            display: block;
            text-align: center;
            padding: 15px;
            background: var(--color-principal);
            color: white;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-ver:hover { background: var(--color-oscuro); }
    </style>
</head>
<body>

    <nav>
        <a href="index.php" class="logo">CAT TOUR 🚢</a>
        <div class="menu">
            <?php
            // Buscamos exactamente la variable que crea tu login2.php
            if (isset($_SESSION['user_nombre'])):
            ?>
                <span class="user-welcome">👋 ¡Hola, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>!</span>
                <a href="ModoUsuario/correos.php">📨 Correos</a>
                <a href="ModoUsuario/mis_reservas.php">💼 Mis Reservas</a>
                <a href="auth/logout.php" style="color: #dc3545; font-size: 14px; margin-left: 10px;">Cerrar Sesión</a>
            <?php else: ?>
                <a href="auth/login2.php" class="btn-nav-action">Iniciar Sesión</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="hero">
        <h1>Explora el mundo con CatTour</h1>
        <p>Experiencias exclusivas desde Veracruz para viajeros inolvidables.</p>
    </div>

    <div class="container">

        <div class="filter-bar">
            <form action="index.php" method="GET" class="filter-form">

                <div class="filter-group">
                    <label>Mes de Salida</label>
                    <select name="mes" class="filter-select">
                        <option value="0">Todos los meses</option>
                        <?php
                        $meses_lista = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                        for ($i = 1; $i <= 12; $i++) {
                            $selected = ($mes_filtrado === $i) ? "selected" : "";
                            echo "<option value='$i' $selected>$meses_lista[$i]</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Año de Salida</label>
                    <select name="anio" class="filter-select">
                        <option value="0">Todos los años</option>
                        <?php
                        for ($a = 2026; $a <= 2028; $a++) {
                            $selected = ($anio_filtrado === $a) ? "selected" : "";
                            echo "<option value='$a' $selected>$a</option>";
                        }
                        ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter">🔍 Buscar Rutas</button>

                <?php if ($mes_filtrado > 0 || $anio_filtrado > 0): ?>
                    <a href="index.php" class="btn-clear">❌ Limpiar Filtros</a>
                <?php endif; ?>

            </form>
        </div>

        <h2 class="section-title">OFERTAS DESTACADAS</h2>

        <div class="viajes-grid">
            <?php
            if ($resultado->num_rows > 0):
                while($viaje = $resultado->fetch_assoc()):
                    $ruta_fisica_imagen = 'uploads/imagenesVIAJES/' . $viaje['imagen_url'];
                    $url_imagen_web = 'uploads/imagenesVIAJES/' . $viaje['imagen_url'];

                    $tiene_imagen = (!empty($viaje['imagen_url']) && $viaje['imagen_url'] !== 'default.jpg' && file_exists($ruta_fisica_imagen));

                    $estilo_background = $tiene_imagen
                        ? "style='background-image: url(\"$url_imagen_web\"); background-size: cover; background-position: center;'"
                        : "";
                ?>
                    <div class="card">
                        <div class="card-img-header" <?php echo $estilo_background; ?>>
                            <?php if (!$tiene_imagen): ?>
                                <span style="font-size: 2.5rem; margin-bottom: 10px;">🌍</span>
                            <?php endif; ?>

                            <div style="font-size: 1.5rem; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($viaje['pais']); ?></div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-top: 5px; font-weight: 600;">
                                <?php echo $viaje['dias']; ?> Días / <?php echo $viaje['noches']; ?> Noches
                            </div>
                        </div>

                        <div class="card-content">
                            <p class="card-pais"><?php echo htmlspecialchars($viaje['pais']); ?></p>
                            <p class="card-ruta"><?php echo htmlspecialchars($viaje['ruta']); ?></p>

                            <div class="card-info">
                                <span>📅 <?php
                                    $meses = ["", "Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
                                    echo $meses[$viaje['mesSalida']] . " " . $viaje['anioSalida'];
                                ?></span>
                                <span>✨ Tour Activo</span>
                            </div>
                        </div>

                        <div class="card-precio">
                            <span class="precio-label">Desde por persona</span>
                            <span class="precio-monto">$<?php echo number_format($viaje['precioBoleto'], 2); ?> <small style="font-size: 1rem;">MXN</small></span>
                        </div>

                        <a href="reservaciones/reserva1.php?id=<?php echo $viaje['idViaje']; ?>" class="btn-ver">VER DETALLES</a>
                    </div>
                <?php
                endwhile;
            else:
            ?>
                <div class="no-results">
                    <span style="font-size: 2.5rem; display: block; margin-bottom: 10px;">🗺️</span>
                    <h3>No encontramos tours activos para esa fecha</h3>
                    <p>Prueba seleccionando otro mes o año en la barra superior.</p>
                </div>
            <?php
            endif;
            $stmt->close();
            ?>
        </div>
    </div>

</body>
</html>
