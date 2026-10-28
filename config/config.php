<?php
/**
 * Application configuration.
 *
 * Values are resolved in this order (later wins):
 *   1. Defaults below (safe, no secrets)
 *   2. config/local.php          — private, git-ignored PHP array
 *   3. .env in the project root  — private, git-ignored KEY=VALUE file
 *   4. Real environment variables (e.g. SetEnv in Apache)
 *
 * Secrets such as the Groq API key must only ever live in 2–4.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function config_defaults(): array
{
    return [
        'app' => [
            'name'     => 'LemboFix',
            'url'      => '',               // base path, e.g. "/lembofix". Empty = auto-detect
            'env'      => 'development',    // development | production
            'timezone' => 'Europe/Belgrade',
            'currency' => '€',
        ],
        'db' => [
            'host'    => '127.0.0.1',
            'port'    => 3306,
            'name'    => 'lembofix',
            'user'    => 'root',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],
        'groq' => [
            'api_key'     => '',
            'model'       => 'openai/gpt-oss-120b',
            'base_url'    => 'https://api.groq.com/openai/v1',
            'timeout'     => 20,
            'max_tokens'  => 900,
            'temperature' => 0.4,
            'ca_bundle'   => '',   // optional path to cacert.pem if cURL cannot verify SSL on Windows
        ],
        'chatbot' => [
            'max_message_length' => 500,
            'history_limit'      => 8,
            'guest_per_10min'    => 10,
            'user_per_10min'     => 20,
            'guest_per_day'      => 40,
            'user_per_day'       => 150,
            'min_interval'       => 2,  // seconds between two messages
        ],
        'booking' => [
            'platform_fee_percent'  => 5,
            'slot_interval'         => 30,   // minutes between offered start times
            'min_lead_minutes'      => 60,   // earliest same-day booking from "now"
            'max_days_ahead'        => 60,
            'customer_cancel_hours' => 2,    // confirmed bookings can be cancelled up to N hours before start
            'max_items'             => 5,
        ],
        'uploads' => [
            'max_bytes' => 2 * 1024 * 1024,
            'dir'       => 'assets/uploads',
        ],
        'security' => [
            'session_idle_minutes' => 120,
            'login_max_attempts'   => 5,
            'login_window_minutes' => 15,
        ],
    ];
}

/** Map of environment variable names to config keys. */
function config_env_map(): array
{
    return [
        'APP_URL'          => 'app.url',
        'APP_ENV'          => 'app.env',
        'APP_TIMEZONE'     => 'app.timezone',
        'DB_HOST'          => 'db.host',
        'DB_PORT'          => 'db.port',
        'DB_NAME'          => 'db.name',
        'DB_USER'          => 'db.user',
        'DB_PASS'          => 'db.pass',
        'GROQ_API_KEY'     => 'groq.api_key',
        'GROQ_MODEL'       => 'groq.model',
        'GROQ_TIMEOUT'     => 'groq.timeout',
        'GROQ_CA_BUNDLE'   => 'groq.ca_bundle',
    ];
}

function config_load_dotenv(string $file): array
{
    if (!is_readable($file)) {
        return [];
    }
    $values = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $values[$key] = trim($value, "\"'");
    }
    return $values;
}

function config_set_path(array &$config, string $path, mixed $value): void
{
    $ref = &$config;
    foreach (explode('.', $path) as $segment) {
        if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
            $ref[$segment] = $ref[$segment] ?? [];
        }
        $ref = &$ref[$segment];
    }
    $ref = $value;
}

function config_build(): array
{
    $config = config_defaults();

    $localFile = APP_ROOT . '/config/local.php';
    if (is_readable($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            $config = array_replace_recursive($config, $local);
        }
    }

    $dotenv = config_load_dotenv(APP_ROOT . '/.env');
    foreach (config_env_map() as $env => $path) {
        $value = getenv($env);
        if ($value === false || $value === '') {
            $value = $dotenv[$env] ?? null;
        }
        if ($value !== null && $value !== '' && $value !== 'YOUR_API_KEY_HERE') {
            config_set_path($config, $path, $value);
        }
    }

    return $config;
}

/** Read a config value with dot notation: config('groq.model'). */
function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= config_build();

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}
