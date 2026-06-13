<?php
session_start();

// 1. Desarmar todas las variables de sesión
$_SESSION = array();

// 2. Destruir la cookie de sesión en el navegador (borra el rastro en el cliente)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destruir físicamente la sesión en el servidor
session_destroy();

// 4. Redirigir al inicio de sesión limpio
header("Location: ../index.php");
exit();
