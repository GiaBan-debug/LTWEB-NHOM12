<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/checkout.php';
allowMethods(['GET']);
jsonResponse(true, [
    'mode' => authMode(),
    'require_verified_email' => emailVerificationRequired(),
    'providers' => ['google' => true, 'phone' => appConfig('FIREBASE_PHONE_ENABLED', 'true') === 'true', 'email_password' => appConfig('FIREBASE_EMAIL_PASSWORD_ENABLED', 'false') === 'true'],
    'shipping' => shippingPolicy(),
    'firebase' => [
        'apiKey' => appConfig('FIREBASE_WEB_API_KEY', 'AIzaSyCY2kHt5owLohtsLdIqtt61NwHBFMljHu4'),
        'authDomain' => appConfig('FIREBASE_AUTH_DOMAIN', 'bta-store.firebaseapp.com'),
        'projectId' => appConfig('FIREBASE_PROJECT_ID', 'bta-store'),
        'appId' => appConfig('FIREBASE_APP_ID', '1:845737474408:web:3e1880360b112b7444bedd'),
    ],
]);
