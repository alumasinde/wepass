<?php

return [

    'name' => 'GatePass Management System',

    'env' => 'local', // local | staging | production

    'debug' => true,

    'url' => 'http://localhost',

    'timezone' => 'Africa/Nairobi',

    'session_lifetime' => 120, // minutes

    'bcrypt_rounds' => 12,

    'multi_tenant' => true,

    'qr_service_url' => 'http://127.0.0.1:8081',

];
