<?php
/**
 * Helper de Configuración Global Dinámica de SinNotario.cl
 * Permite obtener valores desde system_settings con fallback a app.php / env
 */
require_once __DIR__ . '/../database/Database.php';

class AppSettings {
    private static array $cache = [];
    private static bool $loaded = false;

    public static function loadAll(): void {
        if (self::$loaded) return;
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query("SELECT key, value FROM system_settings");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    self::$cache[$row['key']] = $row['value'];
                }
            }
        } catch (\Throwable $e) {
            // Silencioso en caso de DB en proceso de inicio
        }
        self::$loaded = true;
    }

    public static function get(string $key, $default = null) {
        if (!self::$loaded) {
            self::loadAll();
        }
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): bool {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (key, value, updated_at) 
                VALUES (?, ?, CURRENT_TIMESTAMP) 
                ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
            ");
            $res = $stmt->execute([$key, $value]);
            self::$cache[$key] = $value;
            return $res;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Retorna el número de teléfono oficial formateado o para enlaces wa.me
     */
    public static function getWhatsAppPhone(bool $onlyDigits = false): string {
        $phone = self::get('support_whatsapp_phone', '+56984180479');
        if ($onlyDigits) {
            return preg_replace('/[^0-9]/', '', $phone);
        }
        return $phone;
    }

    /**
     * Retorna el teléfono formateado visualmente con espacios: +56 9 8418 0479
     */
    public static function getWhatsAppPhoneDisplay(): string {
        $digits = self::getWhatsAppPhone(true);
        if (strlen($digits) === 11 && strpos($digits, '569') === 0) {
            return '+56 9 ' . substr($digits, 3, 4) . ' ' . substr($digits, 7, 4);
        }
        return self::getWhatsAppPhone();
    }
}
