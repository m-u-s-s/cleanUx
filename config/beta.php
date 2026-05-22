<?php

return [
    'client_mobile_v2_users' => array_filter(explode(',', env('BETA_CLIENT_MOBILE_V2_USERS', ''))),
    'client_mobile_v2_all' => env('BETA_CLIENT_MOBILE_V2_ALL', false),
];
