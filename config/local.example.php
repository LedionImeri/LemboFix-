<?php
/**
 * Copy this file to config/local.php and adjust the values for your machine.
 * config/local.php is git-ignored — never commit real credentials or API keys.
 *
 * You can alternatively use a .env file in the project root (see .env.example).
 */

return [
    'app' => [
        // Leave empty to auto-detect (works for http://localhost/lembofix/).
        // Set it if you use an Apache alias or virtual host, e.g. '' or '/lembofix'.
        'url' => '',
        'env' => 'development',
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'lembofix',
        'user' => 'root',
        'pass' => '',
    ],

    'groq' => [
        // Create a key at https://console.groq.com/keys
        'api_key' => 'YOUR_API_KEY_HERE',
        'model'   => 'openai/gpt-oss-120b',
        // 'ca_bundle' => 'C:/xampp/php/extras/ssl/cacert.pem',
    ],
];
