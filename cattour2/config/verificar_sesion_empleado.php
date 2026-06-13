<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Seguridad de acceso
if (!isset($_SESSION['empleado_id'])) {
    header("Location: ../auth/LoginEmploy.php?error=no_sesion");
    exit();
}

// Usamos la duración que guardamos en LoginEmploy.php al iniciar sesión
$max_tiempo_seg = $_SESSION['duracion_maxima']; 

// Lógica de expiración
if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_inactivo = time() - $_SESSION['ultimo_acceso'];

    if ($tiempo_inactivo > $max_tiempo_seg) {
        session_unset();
        session_destroy();
        header("Location: ../auth/LoginEmploy.php?error=sesion_expirada");
        exit();
    }
}

// Actualizamos el último acceso
$_SESSION['ultimo_acceso'] = time();
?>
<script type="text/javascript">
    // Usamos la variable de sesión para el tiempo de inactividad
    var limit = <?php echo $max_tiempo_seg * 1000; ?>;
    var timeout;

    function resetTimer() {
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            window.location.href = '../auth/logoutEmploy.php?reason=timeout';
        }, limit);
    }

    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;
</script>
