<?php
declare(strict_types=1);

return [
    'recipient_email' => 'your-receiving-email@gmail.com',
    'smtp' => [
        'enabled' => true,
        'host' => 'smtp.gmail.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'yourgmail@gmail.com',
        'password' => 'PASTE_GMAIL_APP_PASSWORD_HERE',
        'from_email' => 'yourgmail@gmail.com',
        'from_name' => 'Autoskins Website',
        'timeout' => 20,
    ],
];
