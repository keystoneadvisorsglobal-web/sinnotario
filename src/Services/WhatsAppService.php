<?php
/**
 * WhatsApp Gateway Service - SinNotario.cl
 * Integración nativa multi-instancia con Evolution API v2 (Baileys) de Keystone Advisors.
 * Permite a cada empresa vincular su propio número corporativo escaneando un código QR en pantalla.
 */

class WhatsAppService {
    private string $gatewayUrl;
    private string $masterApiKey;

    public function __construct() {
        $this->gatewayUrl = rtrim(getenv('WHATSAPP_GATEWAY_URL') ?: 'http://5.183.9.148:8080', '/');
        $this->masterApiKey = getenv('WHATSAPP_MASTER_KEY') ?: '04e9aeffff588f410c0be3bba26d6075de9d47360b134d98635ea3c71b03fdde';
    }

    /**
     * Petición HTTP cURL contra el Gateway Evolution API v2
     */
    public function request(string $endpoint, string $method = 'GET', array $data = []): array {
        $url = $this->gatewayUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);

        $headers = [
            'apikey: ' . $this->masterApiKey,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "Error de conexión con WhatsApp Gateway: $error", 'http_code' => $httpCode];
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded, 'http_code' => $httpCode];
        }

        $errMsg = $decoded['response']['message'] ?? ($decoded['message'] ?? 'Error en Gateway WhatsApp');
        if (is_array($errMsg)) $errMsg = implode(', ', $errMsg);
        return ['success' => false, 'error' => $errMsg, 'http_code' => $httpCode, 'raw' => $response];
    }

    /**
     * Sanitiza el nombre de la instancia
     */
    public static function sanitizeInstanceName(string $name): string {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($name));
        return substr($clean, 0, 40);
    }

    /**
     * Genera un nombre de instancia único y descriptivo para la empresa
     */
    public static function generateInstanceName(int $companyId, string $rut): string {
        $cleanRut = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($rut));
        return "sn-empresa-{$companyId}-{$cleanRut}";
    }

    /**
     * Obtiene el estado o conecta la instancia retornando el código QR
     */
    public function getOrConnectInstance(string $instanceName): array {
        $cleanName = self::sanitizeInstanceName($instanceName);
        if (empty($cleanName)) {
            throw new Exception("Nombre de instancia inválido.");
        }

        // 1. Verificar estado actual de la instancia
        $stateRes = $this->request("/instance/connectionState/{$cleanName}", 'GET');
        if ($stateRes['success']) {
            $state = strtolower($stateRes['data']['instance']['state'] ?? 'unknown');
            if ($state === 'open' || $state === 'connected') {
                return [
                    'success' => true,
                    'connected' => true,
                    'state' => 'open',
                    'instance' => $cleanName,
                    'message' => 'Instancia conectada y lista para operar.'
                ];
            }

            // Está creada pero en estado 'connecting' o 'close': pedir QR de conexión
            $connectRes = $this->request("/instance/connect/{$cleanName}", 'GET');
            if ($connectRes['success']) {
                $base64 = $connectRes['data']['base64'] ?? ($connectRes['data']['qrcode']['base64'] ?? null);
                $code = $connectRes['data']['code'] ?? ($connectRes['data']['qrcode']['code'] ?? null);
                return [
                    'success' => true,
                    'connected' => false,
                    'state' => $state,
                    'instance' => $cleanName,
                    'base64' => $base64,
                    'code' => $code
                ];
            }
        }

        // 2. Si no existe o retornó 404, crearla de inmediato con qrcode = true
        $createRes = $this->request('/instance/create', 'POST', [
            'instanceName' => $cleanName,
            'token' => bin2hex(random_bytes(16)),
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS'
        ]);

        if (!$createRes['success']) {
            return $createRes;
        }

        $base64 = $createRes['data']['qrcode']['base64'] ?? null;
        $code = $createRes['data']['qrcode']['code'] ?? null;

        return [
            'success' => true,
            'connected' => false,
            'state' => 'connecting',
            'instance' => $cleanName,
            'base64' => $base64,
            'code' => $code
        ];
    }

    /**
     * Consulta el estado en tiempo real de una instancia
     */
    public function getConnectionStatus(string $instanceName): array {
        $cleanName = self::sanitizeInstanceName($instanceName);
        $res = $this->request("/instance/connectionState/{$cleanName}", 'GET');
        if ($res['success']) {
            $state = strtolower($res['data']['instance']['state'] ?? 'unknown');
            return [
                'success' => true,
                'instance' => $cleanName,
                'state' => $state,
                'connected' => in_array($state, ['open', 'connected'])
            ];
        }
        return [
            'success' => false,
            'instance' => $cleanName,
            'state' => 'disconnected',
            'connected' => false,
            'error' => $res['error'] ?? 'Desconectada'
        ];
    }

    /**
     * Desconecta (logout) y elimina la instancia en el Gateway
     */
    public function disconnectInstance(string $instanceName): array {
        $cleanName = self::sanitizeInstanceName($instanceName);
        // Intentar logout primero
        $this->request("/instance/logout/{$cleanName}", 'DELETE');
        // Luego borrar
        return $this->request("/instance/delete/{$cleanName}", 'DELETE');
    }

    /**
     * Envía mensaje de texto por WhatsApp a través de una instancia específica
     */
    public function sendTextMessage(string $phoneNumber, string $message, string $instanceName = 'keystone-general'): array {
        return $this->sendTestMessage($instanceName, $phoneNumber, $message);
    }

    /**
     * Envía mensaje de prueba por WhatsApp
     */
    public function sendTestMessage(string $instanceName, string $phoneNumber, string $message): array {
        $cleanName = self::sanitizeInstanceName($instanceName);
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (strlen($cleanPhone) < 8) {
            return ['success' => false, 'error' => 'Número de teléfono inválido'];
        }

        return $this->request("/message/sendText/{$cleanName}", 'POST', [
            'number' => $cleanPhone,
            'text' => $message,
            'delay' => 1200,
            'linkPreview' => true
        ]);
    }
}

