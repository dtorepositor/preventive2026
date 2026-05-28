<?php

return [
    'roles' => [
        'superadmin' => 'Super Admin',
        'admin' => 'Admin',
        'encoder' => 'Encoder',
    ],

    'allow_creating_superadmins' => env('ALLOW_CREATING_SUPERADMINS', false),
];
