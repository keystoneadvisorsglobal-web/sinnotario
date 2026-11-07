<?php
/**
 * Configuración General SinNotario.cl
 */

// Cargar variables de entorno si existe .env.production o .env
if (!function_exists('loadEnv')) {
    function loadEnv($file) {
        if (!file_exists($file)) return false;
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
        return true;
    }
}

$envFile = __DIR__ . '/../.env.production';
if (!file_exists($envFile)) {
    $envFile = __DIR__ . '/../.env';
}
loadEnv($envFile);

require_once __DIR__ . '/gate.php';

return [
    'app_name' => 'SinNotario.cl',
    'app_url' => getenv('APP_URL') ?: 'https://sinnotario.cl',
    'support_whatsapp' => '+56984180479',
    'support_email' => 'soporte@sinnotario.cl',
    'r2' => [
        'account_id' => getenv('CLOUDFLARE_ACCOUNT_ID') ?: 'cf9641882a84e35bbf80ae7681d7ee71',
        'bucket' => getenv('CLOUDFLARE_R2_BUCKET') ?: 'sinnotario-vault-prod',
        'token' => getenv('CLOUDFLARE_API_TOKEN') ?: 'cfat_i4KtjnZZ8GjFRttA23uEQC5hQvS6eI5Czcthy1g571fbf3a3',
    ],
    'evolution_api' => [
        'url' => getenv('EVOLUTION_API_URL') ?: 'http://5.183.9.148:8080',
        'global_key' => getenv('EVOLUTION_API_KEY') ?: '',
    ],
    'google_oauth' => [
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    ]
];
