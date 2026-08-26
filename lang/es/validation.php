<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validación
|--------------------------------------------------------------------------
|
| Este archivo faltaba para el español. Cada entrada rechazada caía en el francés — en un formulario
| que por lo demás estaba enteramente en español. Es el archivo que un usuario lee más a menudo sin
| querer leerlo.
|
| `attributes` traduce el NOMBRE DEL CAMPO que se inserta en la frase. Sin esa tabla, Laravel
| muestra la propia clave: «El campo postal_code es obligatorio.» La lista sigue la de `lang/fr`, la
| más completa del proyecto.
|
| `custom` sustituye la frase estándar en tres casos en los que esta no dice qué hay que hacer.
*/

return [

    'accepted' => 'El campo :attribute debe ser aceptado.',
    'accepted_if' => 'El campo :attribute debe ser aceptado cuando :other sea :value.',
    'active_url' => 'El campo :attribute debe ser una URL válida.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'El campo :attribute solo puede contener letras y números.',
    'any_of' => 'El campo :attribute no es válido.',
    'array' => 'El campo :attribute debe ser una matriz.',
    'ascii' => 'El campo :attribute solo puede contener caracteres alfanuméricos y símbolos de un byte.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha anterior o igual a :date.',

    'between' => [
        'array' => 'El campo :attribute debe contener entre :min y :max elementos.',
        'file' => 'El campo :attribute debe ocupar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],

    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'can' => 'El campo :attribute contiene un valor no autorizado.',
    'confirmed' => 'La confirmación del campo :attribute no coincide.',
    'contains' => 'Al campo :attribute le falta un valor obligatorio.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'date_equals' => 'El campo :attribute debe ser una fecha igual a :date.',
    'date_format' => 'El campo :attribute debe coincidir con el formato :format.',
    'decimal' => 'El campo :attribute debe tener :decimal decimales.',
    'declined' => 'El campo :attribute debe ser rechazado.',
    'declined_if' => 'El campo :attribute debe ser rechazado cuando :other sea :value.',
    'different' => 'El campo :attribute y :other deben ser diferentes.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'El campo :attribute tiene dimensiones de imagen no válidas.',
    'distinct' => 'El campo :attribute contiene un valor duplicado.',
    'doesnt_contain' => 'El campo :attribute no debe contener ninguno de los siguientes: :values.',
    'doesnt_end_with' => 'El campo :attribute no debe terminar por ninguno de los siguientes: :values.',
    'doesnt_start_with' => 'El campo :attribute no debe empezar por ninguno de los siguientes: :values.',
    'email' => 'El campo :attribute debe ser una dirección de correo electrónico válida.',
    'encoding' => 'El campo :attribute debe estar codificado en :encoding.',
    'ends_with' => 'El campo :attribute debe terminar por uno de los siguientes: :values.',
    'enum' => 'El valor seleccionado para :attribute no es válido.',
    'exists' => 'El valor seleccionado para :attribute no es válido.',
    'extensions' => 'El campo :attribute debe tener una de las siguientes extensiones: :values.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute debe tener un valor.',

    'gt' => [
        'array' => 'El campo :attribute debe contener más de :value elementos.',
        'file' => 'El campo :attribute debe ocupar más de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener más de :value caracteres.',
    ],

    'gte' => [
        'array' => 'El campo :attribute debe contener :value elementos o más.',
        'file' => 'El campo :attribute debe ocupar :value kilobytes o más.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o más.',
    ],

    'hex_color' => 'El campo :attribute debe ser un color hexadecimal válido.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor seleccionado para :attribute no es válido.',
    'in_array' => 'El campo :attribute debe existir en :other.',
    'in_array_keys' => 'El campo :attribute debe contener al menos una de las siguientes claves: :values.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'ip' => 'El campo :attribute debe ser una dirección IP válida.',
    'ipv4' => 'El campo :attribute debe ser una dirección IPv4 válida.',
    'ipv6' => 'El campo :attribute debe ser una dirección IPv6 válida.',
    'json' => 'El campo :attribute debe ser una cadena JSON válida.',
    'list' => 'El campo :attribute debe ser una lista.',
    'lowercase' => 'El campo :attribute debe estar en minúsculas.',

    'lt' => [
        'array' => 'El campo :attribute debe contener menos de :value elementos.',
        'file' => 'El campo :attribute debe ocupar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],

    'lte' => [
        'array' => 'El campo :attribute no debe contener más de :value elementos.',
        'file' => 'El campo :attribute debe ocupar :value kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o menos.',
    ],

    'mac_address' => 'El campo :attribute debe ser una dirección MAC válida.',

    'max' => [
        'array' => 'El campo :attribute no debe contener más de :max elementos.',
        'file' => 'El campo :attribute no debe ocupar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
    ],

    'max_digits' => 'El campo :attribute no debe tener más de :max dígitos.',
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El campo :attribute debe ser un archivo de tipo: :values.',

    'min' => [
        'array' => 'El campo :attribute debe contener al menos :min elementos.',
        'file' => 'El campo :attribute debe ocupar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],

    'min_digits' => 'El campo :attribute debe tener al menos :min dígitos.',
    'missing' => 'El campo :attribute no debe estar presente.',
    'missing_if' => 'El campo :attribute no debe estar presente cuando :other sea :value.',
    'missing_unless' => 'El campo :attribute no debe estar presente salvo que :other sea :value.',
    'missing_with' => 'El campo :attribute no debe estar presente cuando :values esté presente.',
    'missing_with_all' => 'El campo :attribute no debe estar presente cuando :values estén presentes.',
    'multiple_of' => 'El campo :attribute debe ser múltiplo de :value.',
    'not_in' => 'El valor seleccionado para :attribute no es válido.',
    'not_regex' => 'El formato del campo :attribute no es válido.',
    'numeric' => 'El campo :attribute debe ser un número.',

    'password' => [
        'letters' => 'El campo :attribute debe contener al menos una letra.',
        'mixed' => 'El campo :attribute debe contener al menos una mayúscula y una minúscula.',
        'numbers' => 'El campo :attribute debe contener al menos un número.',
        'symbols' => 'El campo :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'El :attribute indicado ha aparecido en una filtración de datos. Elija otro :attribute.',
    ],

    'present' => 'El campo :attribute debe estar presente.',
    'present_if' => 'El campo :attribute debe estar presente cuando :other sea :value.',
    'present_unless' => 'El campo :attribute debe estar presente salvo que :other sea :value.',
    'present_with' => 'El campo :attribute debe estar presente cuando :values esté presente.',
    'present_with_all' => 'El campo :attribute debe estar presente cuando :values estén presentes.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'prohibited_if' => 'El campo :attribute no está permitido cuando :other sea :value.',
    'prohibited_if_accepted' => 'El campo :attribute no está permitido cuando :other se haya aceptado.',
    'prohibited_if_declined' => 'El campo :attribute no está permitido cuando :other se haya rechazado.',
    'prohibited_unless' => 'El campo :attribute no está permitido salvo que :other esté en :values.',
    'prohibits' => 'El campo :attribute impide que :other esté presente.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_array_keys' => 'El campo :attribute debe contener entradas para: :values.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other sea :value.',
    'required_if_accepted' => 'El campo :attribute es obligatorio cuando :other se haya aceptado.',
    'required_if_declined' => 'El campo :attribute es obligatorio cuando :other se haya rechazado.',
    'required_unless' => 'El campo :attribute es obligatorio salvo que :other esté en :values.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values esté presente.',
    'required_with_all' => 'El campo :attribute es obligatorio cuando :values estén presentes.',
    'required_without' => 'El campo :attribute es obligatorio cuando :values no esté presente.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando no esté presente ninguno de :values.',
    'same' => 'El campo :attribute debe coincidir con :other.',

    'size' => [
        'array' => 'El campo :attribute debe contener :size elementos.',
        'file' => 'El campo :attribute debe ocupar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],

    'starts_with' => 'El campo :attribute debe empezar por uno de los siguientes: :values.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria válida.',
    'unique' => 'Este :attribute ya está en uso.',
    'uploaded' => 'No se ha podido subir :attribute.',
    'uppercase' => 'El campo :attribute debe estar en mayúsculas.',
    'url' => 'El campo :attribute debe ser una URL válida.',
    'ulid' => 'El campo :attribute debe ser un ULID válido.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',

    /*
     * La frase estándar dice QUÉ está mal, no qué hay que hacer. Para estas tres es la diferencia
     * entre un usuario que abandona y uno que recupera su cuenta.
     */
    'custom' => [
        'email' => [
            'unique' => 'Ya existe una cuenta con esta dirección de correo. Inicie sesión o use «He olvidado mi contraseña».',
        ],
        'accept_terms' => [
            'accepted' => 'Debe aceptar las condiciones generales para crear una cuenta.',
        ],
        'terms' => [
            'accepted' => 'Debe aceptar las condiciones generales para crear una cuenta.',
        ],
    ],

    /*
     * Sin esta tabla, Laravel inserta la CLAVE en la frase: «El campo postal_code es obligatorio.»
     */
    'attributes' => [
        'accept_terms' => 'condiciones generales',
        'address' => 'dirección',
        'city' => 'ciudad',
        'code' => 'código',
        'company_name' => 'nombre de la empresa',
        'current_password' => 'contraseña actual',
        'device_name' => 'dispositivo',
        'email' => 'dirección de correo electrónico',
        'name' => 'nombre',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de la contraseña',
        'phone' => 'número de teléfono',
        'postal_code' => 'código postal',
        'provider_company_name' => 'nombre de la empresa de servicios',
        'terms' => 'condiciones generales',
        'tva_number' => 'número de IVA',
        'two_factor_code' => 'código de autenticación',
        'vat_number' => 'número de IVA',
    ],

];
