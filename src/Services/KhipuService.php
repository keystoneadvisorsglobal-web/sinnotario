<?php
/**
 * Servicio de Integración con Khipu (Transferencias Bancarias Simplificadas)
 * SinNotario.cl - Keystone Advisors SpA
 * API v3 de Khipu
 */

require_once __DIR__ . '/../../database/Database.php';

class KhipuService {
    private string $receiverId;
    private string $secretKey;
    private bool $isSandbox;

    public function __construct() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT key, value FROM system_settings WHERE key LIKE 'khipu_%'");
        $settings = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $this->receiverId = trim($settings['khipu_receiver_id'] ?? (getenv('KHIPU_RECEIVER_ID') ?: ''));
        $this->secretKey = trim($settings['khipu_secret_key'] ?? (getenv('KHIPU_SECRET_KEY') ?: ''));
        $this->isSandbox = ($settings['khipu_mode'] ?? 'production') === 'sandbox';
    }

    public function isConfigured(): bool {
        return !empty($this->receiverId) && !empty($this->secretKey);
    }

    public function getReceiverId(): string {
        return $this->receiverId;
    }

    /**
     * Petición HTTP a la API de Khipu
     */
    private function request(string $endpoint, string $method = 'POST', array $params = []): array {
        if (!$this->isConfigured()) {
            throw new Exception("Khipu no está configurado. Ingrese Receiver ID y Secret Key en la administración.");
        }

        $url = 'https://khipu.com/api/2.0' . $endpoint;
        $ch = curl_init();

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ];

        // Autenticación Khipu HMAC SHA256 o Header estándar
        $stringToSign = strtoupper($method) . "&" . rawurlencode($url);
        if (!empty($params) && $method === 'POST') {
            ksort($params);
            $queryParts = [];
            foreach ($params as $k => $v) {
                $queryParts[] = rawurlencode($k) . "=" . rawurlencode($v);
            }
            $stringToSign .= "&" . rawurlencode(implode("&", $queryParts));
        }

        $signature = hash_hmac('sha256', $stringToSign, $this->secretKey);
        $headers[] = 'Authorization: ' . $this->receiverId . ':' . $signature;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "Error de conexión con Khipu: {$error}"];
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded];
        }

        $msg = $decoded['message'] ?? 'Error en la API de Khipu';
        return ['success' => false, 'error' => $msg, 'http_code' => $httpCode, 'raw' => $response];
    }

    /**
     * Crea un cobro en Khipu para comprar una Bolsa Prepagada B2B
     */
    public function createB2BPackPayment(int $companyId, string $packKey, array $packData, array $companyData, int $userId): array {
        $externalReference = "B2B_KHIPU_{$companyId}_{$packKey}_" . time();
        $totalConIva = (int)round($packData['total'] * 1.19);
        $siteUrl = 'https://sinnotario.cl';

        $pdo = Database::getInstance();
        $stmtOrder = $pdo->prepare("
            INSERT INTO payment_orders (
                external_reference, payment_type, company_id, user_id,
                pack_key, monto_neto, monto_total, moneda, estado, gateway, metadata_json
            ) VALUES (?, 'B2B_PACK', ?, ?, ?, ?, ?, 'CLP', 'PENDIENTE', 'KHIPU', ?)
        ");
        $metadata = [
            'payment_type' => 'B2B_PACK',
            'gateway' => 'KHIPU',
            'company_id' => $companyId,
            'pack_key' => $packKey,
            'firmas' => $packData['firmas'],
            'precio_neto' => $packData['total'],
            'user_id' => $userId
        ];
        $stmtOrder->execute([
            $externalReference,
            $companyId,
            $userId,
            $packKey,
            $packData['total'],
            $totalConIva,
            json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ]);
        $orderId = $pdo->lastInsertId();

        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'configured' => false,
                'order_id' => $orderId,
                'external_reference' => $externalReference,
                'monto_total' => $totalConIva,
                'payment_url' => null,
                'message' => 'Orden de Khipu registrada. Configure Receiver ID y Secret Key en admin.php para habilitar el cobro bancario.'
            ];
        }

        $params = [
            'subject' => "SinNotario - {$packData['nombre']} ({$packData['firmas']} firmas)",
            'body' => "Bolsa prepagada de certificación digital Ley N° 19.799 para {$companyData['razon_social']}",
            'amount' => $totalConIva,
            'currency' => 'CLP',
            'transaction_id' => $externalReference,
            'custom' => json_encode(['order_id' => $orderId, 'company_id' => $companyId]),
            'return_url' => "{$siteUrl}/empresas.php?khipu_status=success&external_ref={$externalReference}",
            'cancel_url' => "{$siteUrl}/empresas.php?khipu_status=cancelled&external_ref={$externalReference}",
            'notify_url' => "{$siteUrl}/api/index.php?action=khipu_webhook",
            'notify_api_version' => '1.3',
            'payer_email' => $companyData['rep_legal_email'] ?: 'contacto@empresa.cl'
        ];

        $res = $this->request('/payments', 'POST', $params);
        if (!$res['success']) {
            return $res;
        }

        $paymentUrl = $res['data']['payment_url'] ?? null;
        $paymentId = $res['data']['payment_id'] ?? null;

        $stmtUpd = $pdo->prepare("UPDATE payment_orders SET preference_id = ? WHERE id = ?");
        $stmtUpd->execute([$paymentId, $orderId]);

        return [
            'success' => true,
            'configured' => true,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'payment_url' => $paymentUrl,
            'external_reference' => $externalReference,
            'monto_total' => $totalConIva
        ];
    }
}
