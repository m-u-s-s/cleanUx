<?php

/*
|--------------------------------------------------------------------------
| Mensajes de autenticación
|--------------------------------------------------------------------------
|
| Este archivo faltaba mientras `es` figuraba como idioma activo. Una contraseña incorrecta mostraba
| por tanto el idioma de reserva — el francés — en una página por lo demás en español. Tres claves, y
| son exactamente las tres frases que lee quien no consigue entrar.
|
| `failed` sigue siendo DELIBERADAMENTE vago: decir que una dirección no existe revelaría a un
| desconocido qué cuentas hay.
*/

return [

    'failed' => 'Estas credenciales no coinciden con ninguna cuenta.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Inténtelo de nuevo en :seconds segundos.',

];
