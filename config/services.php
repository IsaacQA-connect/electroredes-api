<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'culqi' => [
        'public_key' => env('CULQI_PUBLIC_KEY'),
        'secret_key' => env('CULQI_SECRET_KEY'),
    ],

    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    ],

    'apisperu' => [
        'url'             => env('APISPERU_URL', 'https://facturacion.apisperu.com/api/v1'),
        'token'           => env('APISPERU_TOKEN'),
        'ruc'             => env('APISPERU_RUC_EMISOR'),
        'razon_social'    => env('APISPERU_RAZON_SOCIAL', 'ELECTRO REDES S.A.C.'),
        'nombre_comercial' => env('APISPERU_NOMBRE_COMERCIAL', 'ELECTRO REDES'),
        'ubigeo'          => env('APISPERU_UBIGEO', '180101'),
        'direccion'       => env('APISPERU_DIRECCION', 'Av. Principal 123'),
        'departamento'    => env('APISPERU_DEPARTAMENTO', 'MOQUEGUA'),
        'provincia'       => env('APISPERU_PROVINCIA', 'MARISCAL NIETO'),
        'distrito'        => env('APISPERU_DISTRITO', 'MOQUEGUA'),
        'serie_boleta'    => env('APISPERU_SERIE_BOLETA', 'B001'),
        'serie_factura'   => env('APISPERU_SERIE_FACTURA', 'F001'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'https://electroredes-api.test/api/v1/auth/google/callback'),
    ],

];
