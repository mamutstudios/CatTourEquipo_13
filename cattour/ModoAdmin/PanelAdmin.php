<?php
// 1. CARGAR CONTROL DE SESIÓN E INACTIVIDAD DINÁMICA
require_once '../config/verificar_sesion_empleado.php';
include '../config/db.php';

// 2. CONTROL DE ACCESO ESTRICTO (RBAC)
if (!isset($_SESSION['empleado_id']) || ($_SESSION['empleado_rol'] !== 'Administrador' && $_SESSION['empleado_rol'] !== 'Owner')) {
    header("Location: ../auth/LoginEmploy.php");
    exit();
}

// 3. CAPTURAR ACCIONES DENTRO DEL MISMO PANEL
$accion = isset($_GET['accion']) ? $_GET['accion'] : 'crear';
$id_editar = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Variables base para el formulario
$valores = [
    'pais' => '', 'ruta' => '', 'dias' => '', 'noches' => '', 
    'precioBoleto' => '', 'mesSalida' => 6, 'anioSalida' => 2026, 
    'fechaSalida' => '', 'estado' => 1, 'lugaresVisitar' => '', 
    'queIncluye' => '', 'queNoIncluye' => '', 'itinerario' => '', 'imagen_url' => 'default.jpg'
];

$puntos_existentes = [];

// Si la acción es editar, jalamos los datos actuales de MariaDB para precargar el formulario
if ($accion === 'editar' && $id_editar > 0) {
    $stmt = $conn->prepare("SELECT * FROM ViajeDetalles WHERE idViaje = ?");
    $stmt->bind_param("i", $id_editar);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $valores = $row;
    }
    $stmt->close();

    // Jalamos también sus puntos de recolección actuales
    $stmt_p = $conn->prepare("SELECT * FROM PuntosRecoleccion WHERE idViaje = ?");
    $stmt_p->bind_param("i", $id_editar);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    while ($p = $res_p->fetch_assoc()) {
        $puntos_existentes[] = $p;
    }
    $stmt_p->close();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - CatTour</title>
    <style>
        :root {
            --morado: #6f42c1;
            --morado-oscuro: #4b2c85;
            --fondo: #f4f6f9;
            --texto: #333;
            --azul-info: #0d6efd;
            --amarillo-warn: #ffc107;
            --rojo-danger: #dc3545;
        }

        html { scroll-behavior: smooth; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--fondo); margin: 0; display: flex; }

        /* Barra Lateral */
        .sidebar { width: 250px; background: var(--morado-oscuro); color: white; height: 100vh; padding: 20px; box-sizing: border-box; position: fixed; }
        .sidebar h2 { margin-top: 0; font-size: 1.2rem; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 10px; }
        .sidebar p { font-size: 0.9rem; color: #ccc; margin-bottom: 30px; }
        .sidebar a { display: block; color: white; text-decoration: none; padding: 12px; margin-top: 15px; background: rgba(255,255,255,0.1); border-radius: 5px; text-align: center; font-weight: 500; transition: 0.3s; }
        .sidebar a:hover { background: rgba(255,255,255,0.2); }

        /* Contenido */
        .main-content { margin-left: 250px; flex: 1; padding: 40px; text-align: center; }
        .welcome-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 900px; margin: 0 auto 40px auto; }
        .welcome-card h1 { color: var(--morado); margin-top: 0; }

        .grid-acciones { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; max-width: 900px; margin: 0 auto; }
        .card-action { background: white; padding: 30px 20px; border-radius: 15px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); text-decoration: none; color: var(--texto); font-weight: bold; font-size: 18px; transition: transform 0.3s, box-shadow 0.3s; display: flex; flex-direction: column; align-items: center; gap: 15px; border-top: 5px solid var(--morado); }
        .card-action.crear { border-top-color: var(--azul-info); }
        .card-action.editar { border-top-color: var(--amarillo-warn); }
        .card-action.eliminar { border-top-color: var(--rojo-danger); }
        .card-action:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.12); }
        .icon-badge { font-size: 35px; width: 70px; height: 70px; background: #f0f2f5; display: flex; justify-content: center; align-items: center; border-radius: 50%; }

        /* Formulario e Inline general */
        .seccion-formulario { padding: 20px 0 40px 0; margin: 50px auto 0 auto; max-width: 900px; text-align: left; }
        .formulario-card { background: white; padding: 35px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.06); border-top: 5px solid var(--azul-info); }
        .formulario-card.card-edicion { border-top-color: var(--amarillo-warn); }
        .formulario-card.card-baja { border-top-color: var(--rojo-danger); }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 25px; }
        .form-group-full { grid-column: span 2; }

        .image-uploader-box { grid-column: span 2; border: 2px dashed var(--morado); background: #fdfbfe; border-radius: 12px; padding: 25px; text-align: center; cursor: pointer; margin-bottom: 10px; }
        .image-uploader-box .upload-icon { font-size: 40px; display: block; margin-bottom: 5px; }

        .formulario-card label { display: block; margin-bottom: 7px; font-weight: bold; color: #444; font-size: 14px; }
        .formulario-card input, .formulario-card select, .formulario-card textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-size: 15px; }
        .formulario-card textarea { resize: vertical; height: 100px; }

        .btn-publicar { background: var(--morado); color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 25px; transition: 0.3s; }
        .btn-publicar:hover { background: var(--morado-oscuro); }

        /* Estilos de tablas internas para gestionar rutas */
        .tabla-gestion { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 8px; overflow: hidden; }
        .tabla-gestion th, .tabla-gestion td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .tabla-gestion th { background: #f4f6f9; color: var(--morado-oscuro); font-weight: bold; }

        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>

<body>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] === 'viaje_creado'): ?>
        <div id="alerta-exito" style="position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 15px 25px; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 999999; display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s ease;">
            <span>🚢 ¡Operación completada con éxito en CatTour!</span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
    <?php elseif ($_GET['status'] === 'viaje_eliminado'): ?>
        <div id="alerta-exito" style="position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 15px 25px; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 999999; display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s ease;">
            <span>🗑️ El viaje y su imagen del servidor fueron removidos.</span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="sidebar">
    <h2>Panel Admin</h2>
    <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['empleado_nombre']); ?><br>
       <small style="color: #a8ffb2;">Rol: <?php echo htmlspecialchars($_SESSION['empleado_rol']); ?></small>
    </p>
    <a href="PanelAdmin.php" style="background: rgba(255,255,255,0.25);">Inicio</a>
    <a href="../index.php" target="_blank">Ver Sitio Cliente</a>
    <a href="../auth/LoginEmploy.php" style="background: var(--rojo-danger); margin-top: 50px;">Cerrar Sesión</a>
</div>

<div class="main-content">
    <div class="welcome-card">
        <div style="font-size: 45px; margin-bottom: 10px;">🌍</div>
        <h1>Gestión de Operaciones de Viajes</h1>
        <p style="color: #666;">Selecciona una herramienta para dar de alta, modificar parámetros o purgar rutas del catálogo.</p>
    </div>

    <div class="grid-acciones">
        <a href="PanelAdmin.php?accion=crear#seccion-anadir" class="card-action crear">
            <div class="icon-badge">➕</div>
            <span>Crear Viaje</span>
        </a>
        <a href="PanelAdmin.php?accion=listar_editar#seccion-gestion" class="card-action editar">
            <div class="icon-badge">✏️</div>
            <span>Editar Viaje</span>
        </a>
        <a href="PanelAdmin.php?accion=listar_eliminar#seccion-gestion" class="card-action eliminar">
            <div class="icon-badge">🗑️</div>
            <span>Eliminar Viaje</span>
        </a>
    </div>

    <?php if ($accion === 'listar_editar' || $accion === 'listar_eliminar'): ?>
        <div id="seccion-gestion" class="seccion-formulario">
            <div class="formulario-card">
                <h2><?php echo $accion === 'listar_editar' ? '✏️ Seleccionar Viaje para Modificar' : '🗑️ Seleccionar Viaje para Eliminar'; ?></h2>
                <table class="tabla-gestion">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>País</th>
                            <th>Ruta</th>
                            <th>Precio</th>
                            <th>Fecha Vuelo</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res_lista = $conn->query("SELECT * FROM ViajeDetalles ORDER BY idViaje DESC");
                        while ($v = $res_lista->fetch_assoc()):
                        ?>
                            <tr>
                                <td><strong>#<?php echo $v['idViaje']; ?></strong></td>
                                <td><?php echo htmlspecialchars($v['pais']); ?></td>
                                <td><?php echo htmlspecialchars($v['ruta']); ?></td>
                                <td>$<?php echo number_format($v['precioBoleto'], 2); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($v['fechaSalida'])); ?></td>
                                <td>
                                    <?php if ($accion === 'listar_editar'): ?>
                                        <a href="PanelAdmin.php?accion=editar&id=<?php echo $v['idViaje']; ?>#seccion-anadir" style="background: var(--amarillo-warn); color:#333; padding: 6px 12px; border-radius:5px; text-decoration:none; font-weight:bold; font-size:13px;">Editar Parámetros</a>
                                    <?php else: ?>
                                        <a href="PanelAdmin.php?accion=confirmar_baja&id=<?php echo $v['idViaje']; ?>#seccion-anadir" style="background: var(--rojo-danger); color:white; padding: 6px 12px; border-radius:5px; text-decoration:none; font-weight:bold; font-size:13px;">Eliminar Ruta</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($accion === 'crear' || $accion === 'editar'): ?>
        <div id="seccion-anadir" class="seccion-formulario">
            <div class="formulario-card <?php echo $accion === 'editar' ? 'card-edicion' : ''; ?>">
                <h2><?php echo $accion === 'editar' ? '✏️ Modificar Parámetros del Viaje #' . $id_editar : '🚢 Publicar Nuevo Viaje Internacional'; ?></h2>
                <p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">Los cambios se verán reflejados inmediatamente en la base de datos de CatTour.</p>
                
                <form action="procesar_viaje.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="form_accion" value="<?php echo $accion; ?>">
                    <input type="hidden" name="idViaje" value="<?php echo $id_editar; ?>">

                    <div class="form-grid">
                        
                        <div class="image-uploader-box">
                            <i class="upload-icon">🖼️</i>
                            <span>Imagen Promocional del Destino</span>
                            <?php if ($accion === 'editar'): ?>
                                <p style="color:var(--morado); font-weight:bold;">Imagen actual: <?php echo $valores['imagen_url']; ?> (Sube una nueva solo si deseas reemplazarla)</p>
                                <input type="file" name="imagen_viaje" accept="image/jpeg, image/png, image/webp">
                            <?php else: ?>
                                <p>Selecciona una fotografía de alta calidad (.jpg, .jpeg, .png, .webp)</p>
                                <input type="file" name="imagen_viaje" accept="image/jpeg, image/png, image/webp" required>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label>País Destino:</label>
                            <input type="text" name="pais" value="<?php echo htmlspecialchars($valores['pais']); ?>" required>
                        </div>
                        <div>
                            <label>Ruta Completa:</label>
                            <input type="text" name="ruta" value="<?php echo htmlspecialchars($valores['ruta']); ?>" required>
                        </div>
                        <div>
                            <label>Días de Duración:</label>
                            <input type="number" name="dias" min="1" value="<?php echo $valores['dias']; ?>" required>
                        </div>
                        <div>
                            <label>Noches de Hospedaje:</label>
                            <input type="number" name="noches" min="0" value="<?php echo $valores['noches']; ?>" required>
                        </div>
                        <div>
                            <label>Mes de Salida:</label>
                            <select name="mesSalida" required>
                                <?php
                                $meses_nombres = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                                for($m=1; $m<=12; $m++) {
                                    $sel = ($valores['mesSalida'] == $m) ? 'selected' : '';
                                    echo "<option value='$m' $sel>$meses_nombres[$m]</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label>Año de Salida:</label>
                            <input type="number" name="anioSalida" value="<?php echo $valores['anioSalida']; ?>" min="2026" required>
                        </div>
                        <div>
                            <label>Fecha Exacta de Salida (Vuelo):</label>
                            <input type="date" name="fechaSalida" value="<?php echo $valores['fechaSalida']; ?>" required>
                        </div>
                        <div>
                            <label>Estado del Viaje:</label>
                            <select name="estado" required>
                                <option value="1" <?php echo $valores['estado'] == 1 ? 'selected' : ''; ?>>Activo (Visible al Público)</option>
                                <option value="0" <?php echo $valores['estado'] == 0 ? 'selected' : ''; ?>>Inactivo (Ocultar / Cerrado)</option>
                            </select>
                        </div>
                        <div class="form-group-full">
                            <label>Precio del Boleto (MXN):</label>
                            <input type="number" step="0.01" name="precioBoleto" value="<?php echo $valores['precioBoleto']; ?>" required>
                        </div>
                        <div class="form-group-full">
                            <label>Lugares a Visitar:</label>
                            <textarea name="lugaresVisitar" required><?php echo htmlspecialchars($valores['lugaresVisitar']); ?></textarea>
                        </div>
                        <div class="form-group-full">
                            <label>¿Qué Incluye el Viaje?:</label>
                            <textarea name="queIncluye"><?php echo htmlspecialchars($valores['queIncluye']); ?></textarea>
                        </div>
                        <div class="form-group-full">
                            <label>¿Qué NO Incluye?:</label>
                            <textarea name="queNoIncluye"><?php echo htmlspecialchars($valores['queNoIncluye']); ?></textarea>
                        </div>
                        <div class="form-group-full">
                            <label>Itinerario Detallado:</label>
                            <textarea name="itinerario"><?php echo htmlspecialchars($valores['itinerario']); ?></textarea>
                        </div>

                        <div class="form-group-full" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e3e6f0; margin-top: 15px;">
                            <h4 style="margin-top: 0; color: var(--morado); font-size: 16px;">📍 Puntos de Recolección del Tour</h4>
                            <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Añade los lugares, fechas y horas donde el transporte pasará por los pasajeros.</p>
                            
                            <div id="contenedor-puntos">
                                <?php if ($accion === 'editar' && !empty($puntos_existentes)): ?>
                                    <p style="color: var(--morado); font-weight:bold; font-size:13px; margin-bottom:10px;">⚠️ Nota: Los nuevos puntos añadidos se sumarán a los existentes de esta ruta en la base de datos.</p>
                                <?php endif; ?>
                                <div class="fila-punto" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                    <input type="text" name="nombrePunto[]" placeholder="Ej. Terminal ADO Veracruz" <?php echo $accion === 'crear' ? 'required' : ''; ?>>
                                    <input type="date" name="fechaCita[]" <?php echo $accion === 'crear' ? 'required' : ''; ?>>
                                    <input type="time" name="horaCita[]" <?php echo $accion === 'crear' ? 'required' : ''; ?>>
                                </div>
                            </div>
                            
                            <button type="button" id="btn-agregar-punto" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; cursor: pointer; margin-top: 5px;">
                                ➕ Añadir otro punto
                            </button>
                        </div>

                    </div>
                    
                    <button type="submit" class="btn-publicar"><?php echo $accion === 'editar' ? 'GUARDAR CAMBIOS EN LA RUTA' : 'PUBLICAR VIAJE EN CATTOUR'; ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($accion === 'confirmar_baja' && $id_editar > 0): ?>
        <div id="seccion-anadir" class="seccion-formulario">
            <div class="formulario-card card-baja" style="text-align: center;">
                <h2 style="color: var(--rojo-danger);">⚠️ ¿Confirmas la eliminación definitiva?</h2>
                <p style="font-size:15px; color:#555; margin-top:15px;">Estás a punto de borrar permanentemente el **Viaje #<?php echo $id_editar; ?>**. Esta acción purgará el registro de MariaDB y destruirá la imagen asociada del disco duro de AWS Linux de forma irreversible.</p>
                
                <form action="procesar_viaje.php" method="POST" style="margin-top: 30px; display:flex; justify-content:center; gap:20px;">
                    <input type="hidden" name="form_accion" value="eliminar">
                    <input type="hidden" name="idViaje" value="<?php echo $id_editar; ?>">
                    
                    <a href="PanelAdmin.php" style="background:#6c757d; color:white; padding:12px 30px; border-radius:8px; text-decoration:none; font-weight:bold;">CANCELAR</a>
                    <button type="submit" style="background:var(--rojo-danger); color:white; border:none; padding:12px 30px; border-radius:8px; font-weight:bold; cursor:pointer;">SÍ, ELIMINAR VIAJE</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

</body>
</html>

<script>
// El Despertador JS: Desactivado para evitar falsos positivos en AWS
/*
['click', 'keydown', 'mousemove'].forEach(function(evento) {
    document.addEventListener(evento, function() {
        fetch('../config/verificar_sesion_empleado.php', { cache: 'no-store' })
        .then(response => {
            if (response.url.includes('LoginEmploy.php')) {
                window.location.href = "../auth/LoginEmploy.php?error=sesion_expirada";
            }
        });
    });
});
*/

// SCRIPT PARA DUPLICAR FILAS DE PUNTOS DE RECOLECCIÓN
const btnAgregar = document.getElementById('btn-agregar-punto');
if(btnAgregar) {
    btnAgregar.addEventListener('click', function() {
        const contenedor = document.getElementById('contenedor-puntos');
        const nuevaFila = document.createElement('div');
        nuevaFila.className = 'fila-punto';
        nuevaFila.style = 'display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; margin-bottom: 10px;';
        
        nuevaFila.innerHTML = `
            <input type="text" name="nombrePunto[]" placeholder="Ej. Plaza Américas" required>
            <input type="date" name="fechaCita[]" required>
            <input type="time" name="horaCita[]" required>
        `;
        contenedor.appendChild(nuevaFila);
    });
}

// TIMER PARA AUTO-OCULTAR LA ALERTA DE ÉXITO TRAS 4 SEGUNDOS
document.addEventListener('DOMContentLoaded', () => {
    const alerta = document.getElementById('alerta-exito');
    if (alerta) {
        setTimeout(() => {
            alerta.style.transition = "opacity 0.5s ease";
            alerta.style.opacity = "0";
            setTimeout(() => alerta.remove(), 500);
        }, 4000);
    }
});
</script>
