<?php
// Iniciar la sesión si no está iniciada
session_start();

// Destruir todas las variables de sesión
session_unset();

// Destruir la sesión completamente
session_destroy();

// Redirigir al usuario a la página de inicio de sesión con un mensaje opcional
header('Location: login.php?logged_out=true');
exit();
?>
