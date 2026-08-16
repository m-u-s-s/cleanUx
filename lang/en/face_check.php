<?php

/**
 * Provider face verification — strings the PROVIDER reads.
 *
 * See `lang/fr/face_check.php` for the full rationale. In short: only what a provider sees lives
 * here; the admin screens stay in French, like the rest of the console.
 *
 * ANY CHANGE TO `consent.text` REQUIRES BUMPING `FACE_CHECK_CONSENT_VERSION`:
 * consent given against an older version does not cover a newer one.
 */
return [

    'consent' => [
        'text' => 'I agree that my face is recorded and compared with my identity document, in order to '
            .'verify that I am indeed the person attending clients. My reference face is kept encrypted '
            .'for as long as my account exists; the photos taken during checks are deleted after :days '
            .'days. No client ever sees these images. I can withdraw my consent at any time: my face will '
            .'then be deleted and I will no longer be able to work in the trades that require this check.',
        'legal_note' => 'Biometric data — special category under Article 9 GDPR.',
        'version_label' => 'Consent version: :version',
        'withdraw_done' => 'Your reference face has been deleted. You will no longer be able to work in '
            .'trades requiring an identity check until you register it again.',
    ],

    'gate' => [
        'enrolment_required' => 'Register your face so you can attend clients. It takes thirty seconds.',
        'check_required' => 'An identity check is required before you can continue.',
        'check_pending' => 'Your check is being verified. A few more seconds.',
        'blocked' => 'Your account is suspended following an identity check. An administrator must lift '
            .'the suspension: report it from the verification screen.',
    ],

    'screen' => [
        'eyebrow_enrolment' => 'First step',
        'eyebrow_check' => 'Identity check',
        'title_enrolment' => 'Register your face',
        'title_check' => 'Confirm it is you',
        'help_enrolment' => 'This photo will serve as the reference. It stays private: neither your '
            .'clients nor your company see it.',
        'help_check' => 'Look at the lens, without sunglasses or a mask. No client will see this photo.',
        'liveness_hint' => 'Take the photo live: a photo of a screen does not pass the check.',
        'capture_enrolment' => 'Register my face',
        'capture_check' => 'Take the photo',
        'later' => 'Later',
        'all_good_title' => 'All clear',
        'all_good_body' => 'Your identity is verified. You can work normally.',
        'enrolled_since' => 'Face registered :when',
        'pending_title' => 'Verification in progress',
        'pending_body' => 'A few more seconds. Do not close the app.',
        'blocked_title' => 'Account suspended',
        'blocked_note' => 'Reporting a problem does not lift the suspension: it opens a case an '
            .'administrator will handle.',
        'not_concerned' => 'None of your trades requires face verification in your zone. Nothing to do here.',
        'refresh' => 'Refresh',
        'attempt_recap' => 'Attempt :number · previous reason: :reason',
    ],

    'camera' => [
        'permission_title' => 'Camera access',
        'permission_body' => 'The identity check needs the front camera. No image is shared with your clients.',
        'permission_action' => 'Allow the camera',
        'unavailable' => 'The camera could not be opened. Allow it in your browser, or report the problem below.',
        'empty_capture' => 'The camera returned nothing. Try again.',
    ],

    'result' => [
        'passed' => 'Identity confirmed. Have a good day.',
        'enrolled' => 'Your face has been registered.',
        'failed_final' => 'We could not recognise you. An administrator will review your case.',
        'failed_retry' => 'We did not recognise you. Face the light. You have :left attempt(s) left.',
        'liveness_retry' => 'Screen photo detected. Take the photo live. Attempt :number of :total.',
        'network' => 'Connection lost. Check your network and try again.',
        'upload_failed' => 'The upload failed. Check your connection and try again.',
    ],

    'errors' => [
        'consent_required' => 'Registering your face requires your explicit consent.',
        'empty_image' => 'Empty image.',
        'check_closed' => 'This check is already closed.',
        'check_expired' => 'This check has expired. Start again.',
        'orphan_check' => 'Orphan check.',
        'no_open_check' => 'No check in progress. Reload the page.',
        'default' => 'An identity check is required before you can continue.',
    ],

    'incident' => [
        'title' => 'The check is not working?',
        'subtitle' => 'Describe what happens. An administrator will look at your case.',
        'cta' => 'It is not working',
        'cta_blocked' => 'Report a problem',
        'field_label' => 'Describe the problem',
        'placeholder' => 'E.g.: the camera stays black when I open the page.',
        'no_unblock_warning' => 'This report does not unblock your account. It opens a timestamped case '
            .'with your device’s technical details.',
        'sent_title' => 'Case opened',
        'sent_body' => 'An administrator has been notified. Your account remains pending verification: '
            .'this report does not unblock it.',
        'send' => 'Send',
        'cancel' => 'Cancel',
        'close' => 'Close',
    ],

    'notifications' => [
        'blocked' => [
            'subject' => 'Your account is suspended — identity check',
            'greeting' => 'Hello :name,',
            'line_reason' => 'Your access to missions is suspended: :reason',
            'line_action' => 'An administrator must lift this suspension. You cannot lift it yourself, and '
                .'waiting will not lift it either.',
            'line_report' => 'If the check does not work on your side, report it from the verification '
                .'screen: that opens a case an administrator will handle.',
            'action' => 'Open verification',
            'reason' => [
                'failed_checks' => 'several face checks did not succeed.',
                'id_mismatch' => 'the registered face does not match the portrait on your identity document.',
                'consent_withdrawn' => 'you withdrew your consent to the face check.',
                'admin_decision' => 'decision of an administrator.',
                'unknown' => 'an identity check did not succeed.',
            ],
        ],
        'unblocked' => [
            'subject' => 'Your account is active again',
            'greeting' => 'Hello :name,',
            'line_lifted' => 'An administrator has lifted the suspension on your account.',
            'line_next' => 'A new check will be requested the next time you go online: lifting the '
                .'suspension gives you the chance to prove your identity, it does not exempt you from it.',
            'action' => 'Get back to work',
        ],
    ],

];
