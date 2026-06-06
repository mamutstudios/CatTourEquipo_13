<?php
require_once '../config/verificar_sesion_empleado.php';
include '../config/db.php';

// 1. Control de Acceso Estricto (RBAC)
if (!isset($_SESSION['empleado_id']) || $_SESSION['empleado_rol'] !== 'Owner') {
    header("Location: ../auth/LoginEmploy.php");
    exit();
}

$mensaje = "";
$tipo_mensaje = "";

// ==========================================
// LÓGICA: ELIMINAR EMPLEADO
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id_a_eliminar = (int)$_POST['idEmpleado'];

    if ($id_a_eliminar === (int)$_SESSION['empleado_id']) {
        $mensaje = "Error: No puedes eliminar tu propia cuenta de Owner.";
        $tipo_mensaje = "error";
    } else {
        $sql_delete = "DELETE FROM Empleado WHERE idEmpleado = ?";
        if ($stmt_del = $conn->prepare($sql_delete)) {
            $stmt_del->bind_param("i", $id_a_eliminar);
            if ($stmt_del->execute()) {
                $mensaje = "Empleado eliminado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al eliminar: " . $stmt_del->error;
                $tipo_mensaje = "error";
            }
            $stmt_del->close();
        }
    }
}

// ==========================================
// LÓGICA: CREAR NUEVO EMPLEADO (PROTECCIÓN TRY-CATCH)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    $nombre = trim($_POST['nombre']);
    $usuario = trim($_POST['usuario']);
    $correo = trim($_POST['correo']);
    $numero = trim($_POST['numero']);
    $password_raw = $_POST['password'];
    $rol = $_POST['rol'];
    $tiempoSesion = (int)$_POST['tiempoSesion'];

    if (empty($nombre) || empty($usuario) || empty($correo) || empty($numero) || empty($password_raw) || empty($rol)) {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_mensaje = "error";
    } else {
        $password_hashed = password_hash($password_raw, PASSWORD_BCRYPT);
        $sql = "INSERT INTO Empleado (nombre, usuario, correo, numero, rol, password, tiempoSesion) VALUES (?, ?, ?, ?, ?, ?, ?)";

        try {
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssssssi", $nombre, $usuario, $correo, $numero, $rol, $password_hashed, $tiempoSesion);

                if ($stmt->execute()) {
                    $mensaje = "Cuenta de $rol ($usuario) creada con éxito.";
                    $tipo_mensaje = "success";
                } else {
                    if ($stmt->errno == 1062 || $conn->errno == 1062) {
                        $mensaje = "Atención: El Usuario o el Correo Electrónico ya están registrados.";
                    } else {
                        $mensaje = "Error en la base de datos: " . $stmt->error;
                    }
                    $tipo_mensaje = "error";
                }
                $stmt->close();
            } else {
                $mensaje = "Error preparando la consulta: " . $conn->error;
                $tipo_mensaje = "error";
            }
        } catch (Exception $e) {
            if ($e->getCode() == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $mensaje = "Atención: El Nombre de Usuario o el Correo Electrónico ya se encuentran registrados en el sistema.";
            } else {
                $mensaje = "Ocurrió un problemar al registrar: " . $e->getMessage();
            }
            $tipo_mensaje = "error";
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $mensaje = "Atención: El Nombre de Usuario o el Correo Electrónico ya se encuentran registrados en el sistema.";
            } else {
                $mensaje = "Error de SQL: " . $e->getMessage();
            }
            $tipo_mensaje = "error";
        }
    }
}

// OBTENER EMPLEADOS PARA LA TABLA
$empleados = [];
$res = $conn->query("SELECT idEmpleado, nombre, usuario, correo, rol, tiempoSesion FROM Empleado ORDER BY idEmpleado ASC");
if($res) { while($row = $res->fetch_assoc()){ $empleados[] = $row; } }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cuentas - Owner Panel</title>
    <style>
        :root {
            --morado: #6f42c1;
            --morado-oscuro: #4b2c85;
            --fondo: #f4f6f9;
            --texto: #333;
            --rojo: #dc3545;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--fondo); margin: 0; display: flex; }

        .sidebar {
            width: 250px; background: var(--morado-oscuro); color: white;
            height: 100vh; padding: 20px; box-sizing: border-box; position: fixed;
        }
        .sidebar h2 { margin-top: 0; font-size: 1.2rem; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 10px;}
        .sidebar a {
            display: block; color: white; text-decoration: none; padding: 10px;
            margin-top: 20px; background: rgba(255,255,255,0.1); border-radius: 5px; text-align: center;
        }
        .sidebar a:hover { background: rgba(255,255,255,0.2); }

        .main-content { margin-left: 250px; flex: 1; padding: 40px; }

        .card {
            background: white; padding: 30px; border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto 30px auto;
        }
        .card h1 { color: var(--morado); margin-top: 0; }

        .form-group { margin-bottom: 15px; text-align: left; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: var(--texto); }
        input, select {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 5px; box-sizing: border-box; font-size: 14px;
        }

        button.btn-principal {
            width: 100%; padding: 12px; background: var(--morado); color: white;
            border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 16px;
        }
        button.btn-principal:hover { background: var(--morado-oscuro); }

        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: 500; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th { background: var(--morado); color: white; padding: 12px; text-align: left; }
        table td { padding: 12px; border-bottom: 1px solid #ddd; font-size: 14px; }

        .btn-delete {
            background: var(--rojo); color: white; border: none; padding: 5px 10px;
            border-radius: 4px; cursor: pointer;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Panel Owner</h2>
    <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['empleado_nombre']); ?></p>
    <a href="#tabla-cuentas">Cuentas Actuales</a>
    <a href="../auth/logout.php" style="background: var(--rojo);">Cerrar Sesión</a>
</div>

<div class="main-content">
    <div class="card">
        <h1>Crear Nuevo Personal</h1>

        <?php if ($mensaje): ?>
            <div class="alert <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Usuario (Login)</label>
                    <input type="text" name="usuario" required>
                </div>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" required>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="numero" required>
                </div>
                <div class="form-group">
                    <label>Contraseña Temporal</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol" required>
                        <option value="Administrador">Administrador</option>
                        <option value="Operador">Operador</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Tiempo de Sesión (minutos)</label>
                <input type="number" name="tiempoSesion" value="15" min="5" required>
            </div>
            <button type="submit" class="btn-principal">Registrar Empleado</button>
        </form>
    </div>

    <div class="card" id="tabla-cuentas">
        <h1>Cuentas Actuales</h1>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleados as $e): ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($e['usuario']); ?></td>
                    <td><?php echo htmlspecialchars($e['rol']); ?></td>
                    <td>
                        <?php if ((int)$e['idEmpleado'] === (int)$_SESSION['empleado_id']): ?>
                            <span style="color: #6c757d; font-style: italic; font-weight: bold;">Tu Cuenta (Activa)</span>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('¿Borrar cuenta?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="idEmpleado" value="<?php echo $e['idEmpleado']; ?>">
                                <button type="submit" class="btn-delete">Eliminar</button>
                            </form>
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
