<?php
// FORZAR VISUALIZACIÓN DE ERRORES PARA DIAGNÓSTICO
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/verificar_sesion_empleado.php';
include '../config/db.php';

if (!isset($_SESSION['empleado_id']) || $_SESSION['empleado_rol'] !== 'Owner') {
    header("Location: ../auth/LoginEmploy.php");
    exit();
}

$mensaje = "";
$tipo_mensaje = "";

// Lógica de Procesamiento (Actualizar, Eliminar, Crear)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_time') {
            $id = (int)$_POST['idEmpleado'];
            $nuevo_tiempo = (int)$_POST['tiempoSesion'];
            if ($nuevo_tiempo < 5) { $mensaje = "El mínimo es 5 minutos."; $tipo_mensaje = "error"; }
            else {
                $stmt = $conn->prepare("UPDATE Empleado SET tiempoSesion = ? WHERE idEmpleado = ?");
                $stmt->bind_param("ii", $nuevo_tiempo, $id);
                if ($stmt->execute()) {
                    $mensaje = "Tiempo de sesión actualizado correctamente."; $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar: " . $stmt->error; $tipo_mensaje = "error";
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id_del = (int)$_POST['idEmpleado'];
            if ($id_del !== (int)$_SESSION['empleado_id']) {
                $stmt = $conn->prepare("DELETE FROM Empleado WHERE idEmpleado = ?");
                $stmt->bind_param("i", $id_del);
                if ($stmt->execute()) {
                    $mensaje = "Empleado eliminado con éxito."; $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al eliminar: " . $stmt->error; $tipo_mensaje = "error";
                }
            }
        }
    } else { // Crear nuevo usuario
        $tiempoSesion = (int)$_POST['tiempoSesion'];
        $usuario_nuevo = $_POST['usuario'];
        $correo_nuevo = $_POST['correo'];

        if ($tiempoSesion < 5) { 
            $mensaje = "Error: El mínimo es 5 minutos."; 
            $tipo_mensaje = "error"; 
        } else {
            // VERIFICACIÓN PREVIA DE DUPLICADOS
            $stmt_check = $conn->prepare("SELECT idEmpleado FROM Empleado WHERE usuario = ? OR correo = ?");
            $stmt_check->bind_param("ss", $usuario_nuevo, $correo_nuevo);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();

            if ($res_check->num_rows > 0) {
                $mensaje = "Error: El usuario o correo electrónico ya ha sido registrado anteriormente.";
                $tipo_mensaje = "error";
            } else {
                $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $sql = "INSERT INTO Empleado (nombre, usuario, correo, numero, rol, password, tiempoSesion) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param("ssssssi", $_POST['nombre'], $_POST['usuario'], $_POST['correo'], $_POST['numero'], $_POST['rol'], $hash, $tiempoSesion);
                    if ($stmt->execute()) { 
                        $mensaje = "¡Cuenta creada exitosamente!"; 
                        $tipo_mensaje = "success"; 
                    } else { 
                        $mensaje = "Error al registrar: " . $stmt->error; 
                        $tipo_mensaje = "error"; 
                    }
                    $stmt->close();
                }
            }
            $stmt_check->close();
        }
    }
}

$empleados = [];
$res = $conn->query("SELECT idEmpleado, nombre, usuario, rol, tiempoSesion FROM Empleado ORDER BY idEmpleado ASC");
while($row = $res->fetch_assoc()) $empleados[] = $row;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Cuentas - Owner Panel</title>
    <style>
        :root { --morado: #6f42c1; --morado-oscuro: #4b2c85; --fondo: #f4f6f9; --rojo: #dc3545; --verde: #28a745; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--fondo); margin: 0; display: flex; }
        .sidebar { width: 250px; background: var(--morado-oscuro); color: white; height: 100vh; padding: 20px; position: fixed; }
        .user-info { margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 15px; }
        .main-content { margin-left: 250px; flex: 1; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto 30px auto; }
        .counter-box { display: flex; align-items: center; justify-content: center; gap: 15px; }
        .btn-qty { background: var(--morado); color: white; border: none; width: 40px; height: 40px; border-radius: 5px; cursor: pointer; font-size: 20px; }
        .display-val { font-weight: bold; font-size: 1.2rem; min-width: 80px; text-align: center; }
        input, select { width: 100%; padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        button { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; color: white; font-weight: bold; font-size: 1rem; }
        .btn-principal { background: var(--morado); width: 100%; }
        .btn-ok { background: var(--verde); }
        .btn-delete { background: var(--rojo); }
        .btn-logout { background: var(--rojo); display: block; text-align: center; text-decoration: none; padding: 12px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: var(--morado); color: white; padding: 15px; }
        td { padding: 15px; border-bottom: 1px solid #ddd; text-align: center; font-size: 1.1rem; }
        .alert { padding: 20px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
    <script>
        function updateVal(btn, delta) {
            let container = btn.parentElement;
            let display = container.querySelector('.display-val');
            let input = container.querySelector('.hidden-val');
            let val = parseInt(input.value) + delta;
            if (val >= 5) {
                display.innerText = val + " min";
                input.value = val;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                let numeroInput = document.querySelector('input[name="numero"]').value;
                if (numeroInput.length > 15) {
                    e.preventDefault();
                    alert("El número de teléfono no debe exceder los 15 caracteres. Longitud actual: " + numeroInput.length);
                }
            });
        });
    </script>
</head>
<body>
<div class="sidebar">
    <h2>Panel Owner</h2>
    <div class="user-info">
        <p>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['empleado_nombre']); ?></strong></p>
        <p>Rol: <strong><?php echo htmlspecialchars($_SESSION['empleado_rol']); ?></strong></p>
    </div>
    <a href="../auth/logoutEmploy.php" class="btn-logout">Cerrar Sesión</a>
</div>

<div class="main-content">
    <div class="card">
        <h1>Crear Nuevo Personal</h1>
        <?php if ($mensaje): ?><div class="alert <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre Completo" required>
            <input type="text" name="usuario" placeholder="Usuario" required>
            <input type="email" name="correo" placeholder="Correo" required>
            <input type="text" name="numero" placeholder="Teléfono" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <select name="rol"><option>Administrador</option><option>Operador</option></select>
            <label style="display:block; margin-bottom:10px;">Tiempo de Sesión:</label>
            <div class="counter-box" style="margin-bottom:20px;">
                <button type="button" class="btn-qty" onclick="updateVal(this, -1)">-</button>
                <span class="display-val">15 min</span>
                <input type="hidden" name="tiempoSesion" value="15" class="hidden-val">
                <button type="button" class="btn-qty" onclick="updateVal(this, 1)">+</button>
            </div>
            <button type="submit" class="btn-principal">Registrar Empleado</button>
        </form>
    </div>

    <div class="card">
        <h1>Cuentas Actuales</h1>
        <table>
            <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Tiempo</th><th>Acción</th></tr></thead>
            <tbody>
                <?php foreach ($empleados as $e): ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($e['usuario']); ?></td>
                    <td><?php echo htmlspecialchars($e['rol']); ?></td>
                    <td>
                        <form method="POST" class="counter-box">
                            <input type="hidden" name="action" value="update_time">
                            <input type="hidden" name="idEmpleado" value="<?php echo $e['idEmpleado']; ?>">
                            <button type="button" class="btn-qty" onclick="updateVal(this, -1)">-</button>
                            <span class="display-val"><?php echo $e['tiempoSesion']; ?> min</span>
                            <input type="hidden" name="tiempoSesion" value="<?php echo $e['tiempoSesion']; ?>" class="hidden-val">
                            <button type="button" class="btn-qty" onclick="updateVal(this, 1)">+</button>
                            <button type="submit" class="btn-ok" style="margin-left:10px;">OK</button>
                        </form>
                    </td>
                    <td>
                        <?php if ($e['idEmpleado'] != $_SESSION['empleado_id']): ?>
                            <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="idEmpleado" value="<?php echo $e['idEmpleado']; ?>"><button type="submit" class="btn-delete">Eliminar</button></form>
                        <?php else: ?>
                            <span style="color:var(--verde); font-weight:bold;">Activo</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
