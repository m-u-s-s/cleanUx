<?php

/*
|--------------------------------------------------------------------------
| Mensajes de restablecimiento de contraseña
|--------------------------------------------------------------------------
|
| La misma laguna que en `auth.php`. Son las únicas respuestas que recibe alguien que ha perdido el
| acceso a su cuenta: mostrarlas en el idioma de reserva es hablar el idioma equivocado justo cuando
| las cosas van mal.
|
| `user` se mantiene en condicional («si esta dirección existe»): el formulario no dice si la cuenta
| existe, de lo contrario serviría para enumerar las direcciones registradas.
*/

return [

    'reset' => 'Su contraseña se ha restablecido. Todas sus sesiones se han cerrado.',
    'sent' => 'Si esta dirección corresponde a una cuenta, acaba de salir un enlace de restablecimiento.',
    'throttled' => 'Espere un momento antes de solicitar un nuevo enlace.',
    'token' => 'Este enlace de restablecimiento ya no es válido. Solicite uno nuevo.',
    'user' => 'Si esta dirección corresponde a una cuenta, acaba de salir un enlace de restablecimiento.',

];
