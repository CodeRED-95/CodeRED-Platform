<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token Request Encryption Keys
    |--------------------------------------------------------------------------
    |
    | These keys are used specifically for encrypting and decrypting token
    | request data, providing an extra layer of security by separating them
    | from the main application key (APP_KEY).
    |
    */

    'token_request_data_key' => env('TOKEN_REQUEST_DATA_ENCRYPTION_KEY'),

    'token_request_blind_index_key' => env('TOKEN_REQUEST_BLIND_INDEX_KEY'),

    // You can add more keys here for other specific encryption purposes.
    // 'token_request_token_encryption_key' => env('TOKEN_REQUEST_TOKEN_ENCRYPTION_KEY'),

];
