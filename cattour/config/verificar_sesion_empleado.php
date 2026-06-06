<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================================
// CONTROL ANTI-CACHÉ: OBLIGA AL NAVEGADOR A VALIDAR EN VIVO
// ========================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
// ========================================================

// Forzar la zona horaria de México para sincronizar con tu hora local
date_default_timezone_set('America/Mexico_City');

// 1. Si es un cliente, congelar este script y dejarlo pasar
if (isset($_SESSION['user_id'])) {
    return; 
}

// 2. Si no hay sesión de empleado activa, expulsar de inmediato al login
if (!isset($_SESSION['empleado_id'])) {
    header("Location: ../auth/LoginEmploy.php");
    exit();
}

// CONFIGURACIÓN DE TIEMPO MANUAL (1 minuto para probar rápido)
$minutos_fijos = 15; 
$max_tiempo_permitido = $minutos_fijos * 60; 

// 3. Control de Inactividad Fija
if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_inactivo = time() - $_SESSION['ultimo_acceso'];

    // Si el tiempo ocioso superó los minutos configurados...
    if ($tiempo_inactivo > $max_tiempo_permitido) {
        
        // Limpiamos de forma segura las variables del empleado
        unset($_SESSION['empleado_id']);
        unset($_SESSION['empleado_nombre']);
        unset($_SESSION['empleado_rol']);
        unset($_SESSION['duracion_maxima']);
        unset($_SESSION['ultimo_acceso']);
        
        // Destruimos la sesión por completo
        session_destroy();
        
        // Redireccionamos a la carpeta de autenticación
        header("Location: ../auth/LoginEmploy.php?error=sesion_expirada");
        exit(); 
    }
}

// Si pasó el filtro y sigue activo, renovamos la estampa de tiempo
$_SESSION['ultimo_acceso'] = time();
?>
