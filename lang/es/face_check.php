<?php

/**
 * Verificación facial del profesional — textos que lee el PROFESIONAL.
 *
 * La justificación completa está en `lang/fr/face_check.php`. En resumen: aquí solo vive lo que ve
 * un profesional; las pantallas de administración siguen en francés, como el resto de la consola.
 *
 * TODA MODIFICACIÓN DE `consent.text` EXIGE INCREMENTAR `FACE_CHECK_CONSENT_VERSION`:
 * un consentimiento otorgado sobre una versión anterior no cubre una posterior.
 *
 * UNA TRADUCCIÓN NO ES UNA MODIFICACIÓN. Este texto dice lo mismo que el original francés en otro
 * idioma — el objeto del consentimiento no cambia, y la versión tampoco.
 */
return [

    'consent' => [
        'text' => 'Acepto que se registre mi rostro y se compare con mi documento de identidad, con el '
            .'fin de verificar que soy realmente la persona que acude a los clientes. Mi rostro de '
            .'referencia se conserva cifrado mientras exista mi cuenta; las fotos tomadas durante las '
            .'comprobaciones se eliminan a los :days días. Ningún cliente ve nunca estas imágenes. '
            .'Puedo retirar mi consentimiento en cualquier momento: mi rostro se eliminará entonces y '
            .'no podré volver a trabajar en los oficios que exigen esta comprobación.',
        'legal_note' => 'Dato biométrico — categoría especial según el artículo 9 del RGPD.',
        'version_label' => 'Versión del consentimiento: :version',
        'withdraw_done' => 'Su rostro de referencia se ha eliminado. No podrá trabajar en oficios que '
            .'exigen una comprobación de identidad hasta que vuelva a registrarlo.',
    ],

    'gate' => [
        'enrolment_required' => 'Registre su rostro para poder acudir a los clientes. Son treinta segundos.',
        'check_required' => 'Se requiere una comprobación de identidad antes de continuar.',
        'check_pending' => 'Su comprobación se está verificando. Solo unos segundos más.',
        'blocked' => 'Su cuenta está suspendida tras una comprobación de identidad. Un administrador '
            .'debe levantar la suspensión: comuníquelo desde la pantalla de verificación.',
    ],

    'screen' => [
        'eyebrow_enrolment' => 'Primer paso',
        'eyebrow_check' => 'Comprobación de identidad',
        'title_enrolment' => 'Registre su rostro',
        'title_check' => 'Confirme que es usted',
        'help_enrolment' => 'Esta foto servirá de referencia. Sigue siendo privada: ni sus clientes ni '
            .'su empresa la ven.',
        'help_check' => 'Mire al objetivo, sin gafas de sol ni mascarilla. Ningún cliente verá esta foto.',
        'liveness_hint' => 'Tome la foto en directo: la foto de una pantalla no supera la comprobación.',
        'capture_enrolment' => 'Registrar mi rostro',
        'capture_check' => 'Tomar la foto',
        'later' => 'Más tarde',
        'all_good_title' => 'Todo correcto',
        'all_good_body' => 'Su identidad está verificada. Puede trabajar con normalidad.',
        'enrolled_since' => 'Rostro registrado :when',
        'pending_title' => 'Verificación en curso',
        'pending_body' => 'Solo unos segundos más. No cierre la aplicación.',
        'blocked_title' => 'Cuenta suspendida',
        'blocked_note' => 'Comunicar un problema no levanta la suspensión: abre un caso que atenderá '
            .'un administrador.',
        'not_concerned' => 'Ninguno de sus oficios exige verificación facial en su zona. Aquí no hay nada que hacer.',
        'refresh' => 'Actualizar',
        'attempt_recap' => 'Intento :number · motivo anterior: :reason',
    ],

    'camera' => [
        'permission_title' => 'Acceso a la cámara',
        'permission_body' => 'La comprobación de identidad necesita la cámara frontal. No se comparte ninguna imagen con sus clientes.',
        'permission_action' => 'Permitir la cámara',
        'unavailable' => 'No se ha podido abrir la cámara. Permítala en su navegador o comunique el problema más abajo.',
        'empty_capture' => 'La cámara no ha devuelto nada. Inténtelo de nuevo.',
    ],

    'result' => [
        'passed' => 'Identidad confirmada. Que tenga un buen día.',
        'enrolled' => 'Su rostro se ha registrado.',
        'failed_final' => 'No hemos podido reconocerle. Un administrador revisará su caso.',
        'failed_retry' => 'No le hemos reconocido. Póngase de cara a la luz. Le queda(n) :left intento(s).',
        'liveness_retry' => 'Se ha detectado la foto de una pantalla. Tome la foto en directo. Intento :number de :total.',
        'network' => 'Conexión perdida. Compruebe su red e inténtelo de nuevo.',
        'upload_failed' => 'El envío ha fallado. Compruebe su conexión e inténtelo de nuevo.',
    ],

    'errors' => [
        'consent_required' => 'Registrar su rostro requiere su consentimiento explícito.',
        'empty_image' => 'Imagen vacía.',
        'check_closed' => 'Esta comprobación ya está cerrada.',
        'check_expired' => 'Esta comprobación ha caducado. Empiece de nuevo.',
        'orphan_check' => 'Comprobación huérfana.',
        'no_open_check' => 'No hay ninguna comprobación en curso. Vuelva a cargar la página.',
        'default' => 'Se requiere una comprobación de identidad antes de continuar.',
    ],

    'incident' => [
        'title' => '¿La comprobación no funciona?',
        'subtitle' => 'Describa lo que ocurre. Un administrador revisará su caso.',
        'cta' => 'No funciona',
        'cta_blocked' => 'Comunicar un problema',
        'field_label' => 'Describa el problema',
        'placeholder' => 'P. ej.: la cámara se queda en negro al abrir la página.',
        'no_unblock_warning' => 'Esta comunicación no desbloquea su cuenta. Abre un caso con marca de '
            .'tiempo y los datos técnicos de su dispositivo.',
        'sent_title' => 'Caso abierto',
        'sent_body' => 'Se ha avisado a un administrador. Su cuenta sigue pendiente de verificación: '
            .'esta comunicación no la desbloquea.',
        'send' => 'Enviar',
        'cancel' => 'Cancelar',
        'close' => 'Cerrar',
    ],

    'notifications' => [
        'blocked' => [
            'subject' => 'Su cuenta está suspendida — comprobación de identidad',
            'greeting' => 'Hola :name:',
            'line_reason' => 'Su acceso a los servicios está suspendido: :reason',
            'line_action' => 'Un administrador debe levantar esta suspensión. No puede levantarla usted '
                .'mismo, y esperar tampoco la levantará.',
            'line_report' => 'Si la comprobación no funciona por su parte, comuníquelo desde la pantalla '
                .'de verificación: eso abre un caso que atenderá un administrador.',
            'action' => 'Abrir la verificación',
            'reason' => [
                'failed_checks' => 'varias comprobaciones faciales no han tenido éxito.',
                'id_mismatch' => 'el rostro registrado no coincide con el retrato de su documento de identidad.',
                'consent_withdrawn' => 'ha retirado su consentimiento a la comprobación facial.',
                'admin_decision' => 'decisión de un administrador.',
                'unknown' => 'una comprobación de identidad no ha tenido éxito.',
            ],
        ],
        'unblocked' => [
            'subject' => 'Su cuenta vuelve a estar activa',
            'greeting' => 'Hola :name:',
            'line_lifted' => 'Un administrador ha levantado la suspensión de su cuenta.',
            'line_next' => 'Se le pedirá una nueva comprobación la próxima vez que se conecte: levantar '
                .'la suspensión le da la ocasión de demostrar su identidad, no le exime de ello.',
            'action' => 'Volver al trabajo',
        ],
    ],

];
