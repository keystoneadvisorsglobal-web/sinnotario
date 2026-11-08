<?php
/**
 * Servicio de Integración con Mercado Pago Chile (Checkout Pro)
 * SinNotario.cl - Keystone Advisors SpA
 */

require_once __DIR__ . '/../../database/Database.php';

class MercadoPagoService {
    private string $accessToken;
    private string $publicKey;
    private bool $isSandbox;

    public function __construct() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT key, value FROM system_settings WHERE key LIKE 'mp_%'");
        $settings = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $this->isSandbox = ($settings['mp_mode'] ?? 'production') === 'sandbox';
        $this->enabled = ($settings['mp_enabled'] ?? '1') === '1';

        // Selección inteligente según ambiente activo
        if ($this->isSandbox) {
            $this->accessToken = trim($settings['mp_access_token_sandbox'] ?? ($settings['mp_access_token'] ?? (getenv('MP_ACCESS_TOKEN_SANDBOX') ?: getenv('MP_ACCESS_TOKEN') ?: '')));
            $this->publicKey = trim($settings['mp_public_key_sandbox'] ?? ($settings['mp_public_key'] ?? (getenv('MP_PUBLIC_KEY_SANDBOX') ?: getenv('MP_PUBLIC_KEY') ?: '')));
        } else {
            $this->accessToken = trim($settings['mp_access_token_production'] ?? ($settings['mp_access_token'] ?? (getenv('MP_ACCESS_TOKEN_PROD') ?: getenv('MP_ACCESS_TOKEN') ?: '')));
            $this->publicKey = trim($settings['mp_public_key_production'] ?? ($settings['mp_public_key'] ?? (getenv('MP_PUBLIC_KEY_PROD') ?: getenv('MP_PUBLIC_KEY') ?: '')));
        }
    }

    private bool $enabled;

    public function isConfigured(): bool {
        return $this->enabled && !empty($this->accessToken);
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function getPublicKey(): string {
        return $this->publicKey;
    }

    /**
     * Petición HTTP a la API v1 de Mercado Pago
     */
    private function request(string $endpoint, string $method = 'GET', array $data = []): array {
        if (!$this->isConfigured()) {
            throw new Exception("Mercado Pago no está configurado. Ingrese el Access Token en la consola administrativa.");
        }

        $url = 'https://api.mercadopago.com' . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . bin2hex(random_bytes(16))
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "Error de conexión con Mercado Pago: {$error}"];
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded];
        }

        $msg = $decoded['message'] ?? 'Error en la API de Mercado Pago';
        return ['success' => false, 'error' => $msg, 'http_code' => $httpCode, 'raw' => $response];
    }

    /**
     * Crea una preferencia de pago para comprar una Bolsa Prepagada B2B
     */
    public function createB2BPackPreference(int $companyId, string $packKey, array $packData, array $companyData, int $userId): array {
        $externalReference = "B2B_PACK_{$companyId}_{$packKey}_" . time();
        $totalConIva = (int)round($packData['total'] * 1.19); // Valores B2B son + IVA

        $siteUrl = 'https://sinnotario.cl';

        $preferenceData = [
            'items' => [
                [
                    'id' => $packKey,
                    'title' => "SinNotario - {$packData['nombre']} ({$packData['firmas']} firmas)",
                    'description' => "Bolsa prepagada de certificación digital Ley N° 19.799 para {$companyData['razon_social']}",
                    'quantity' => 1,
                    'currency_id' => 'CLP',
                    'unit_price' => $totalConIva
                ]
            ],
            'payer' => [
                'name' => $companyData['rep_legal_nombre'] ?: 'Representante Legal',
                'email' => $companyData['rep_legal_email'] ?: 'contacto@empresa.cl',
                'identification' => [
                    'type' => 'RUT',
                    'number' => preg_replace('/[^0-9kK]/', '', $companyData['rut'] ?? '')
                ]
            ],
            'back_urls' => [
                'success' => "{$siteUrl}/empresas.php?payment_status=success&external_ref={$externalReference}",
                'pending' => "{$siteUrl}/empresas.php?payment_status=pending&external_ref={$externalReference}",
                'failure' => "{$siteUrl}/empresas.php?payment_status=failure&external_ref={$externalReference}"
            ],
            'auto_return' => 'approved',
            'external_reference' => $externalReference,
            'statement_descriptor' => 'SINNOTARIO',
            'metadata' => [
                'payment_type' => 'B2B_PACK',
                'company_id' => $companyId,
                'pack_key' => $packKey,
                'firmas' => $packData['firmas'],
                'precio_neto' => $packData['total'],
                'user_id' => $userId
            ]
        ];

        // Registrar orden previa en base de datos
        $pdo = Database::getInstance();
        $stmtOrder = $pdo->prepare("
            INSERT INTO payment_orders (
                external_reference, payment_type, company_id, user_id,
                pack_key, monto_neto, monto_total, moneda, estado, metadata_json
            ) VALUES (?, 'B2B_PACK', ?, ?, ?, ?, ?, 'CLP', 'PENDIENTE', ?)
        ");
        $stmtOrder->execute([
            $externalReference,
            $companyId,
            $userId,
            $packKey,
            $packData['total'],
            $totalConIva,
            json_encode($preferenceData['metadata'], JSON_UNESCAPED_UNICODE)
        ]);
        $orderId = $pdo->lastInsertId();

        // Si Mercado Pago no está configurado (por ejemplo antes de pegar las credenciales), retornar orden en modo simulador/pendiente
        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'configured' => false,
                'order_id' => $orderId,
                'external_reference' => $externalReference,
                'monto_total' => $totalConIva,
                'init_point' => null,
                'message' => 'Orden de pago generada. Ingrese las credenciales de Mercado Pago en admin.php para habilitar la redirección automática.'
            ];
        }

        $res = $this->request('/checkout/preferences', 'POST', $preferenceData);
        if (!$res['success']) {
            return $res;
        }

        $initPoint = $this->isSandbox ? ($res['data']['sandbox_init_point'] ?? $res['data']['init_point']) : $res['data']['init_point'];

        // Guardar preference_id en la orden
        $stmtUpd = $pdo->prepare("UPDATE payment_orders SET preference_id = ? WHERE id = ?");
        $stmtUpd->execute([$res['data']['id'], $orderId]);

        return [
            'success' => true,
            'configured' => true,
            'order_id' => $orderId,
            'preference_id' => $res['data']['id'],
            'init_point' => $initPoint,
            'external_reference' => $externalReference,
            'monto_total' => $totalConIva
        ];
    }

    /**
     * Crea una preferencia de pago para documentos de Personas Naturales (Redactar o Subir PDF)
     */
    public function createRetailDocumentPreference(int $docId, string $verifCode, string $docTitle, int $montoTotal, array $payerData, ?int $userId = null): array {
        $externalReference = "RETAIL_DOC_{$docId}_" . time();
        $siteUrl = 'https://sinnotario.cl';

        $preferenceData = [
            'items' => [
                [
                    'id' => "DOC-{$docId}",
                    'title' => "SinNotario - {$docTitle}",
                    'description' => "Certificación digital de documento privado Ley N° 19.799 (Cód: {$verifCode})",
                    'quantity' => 1,
                    'currency_id' => 'CLP',
                    'unit_price' => $montoTotal
                ]
            ],
            'payer' => [
                'name' => $payerData['nombre'] ?: 'Cliente SinNotario',
                'email' => $payerData['email'] ?: 'contacto@sinnotario.cl',
                'identification' => [
                    'type' => 'RUT',
                    'number' => preg_replace('/[^0-9kK]/', '', $payerData['rut'] ?? '')
                ]
            ],
            'back_urls' => [
                'success' => "{$siteUrl}/verificar.php?code={$verifCode}&payment_status=success&external_ref={$externalReference}",
                'pending' => "{$siteUrl}/verificar.php?code={$verifCode}&payment_status=pending&external_ref={$externalReference}",
                'failure' => "{$siteUrl}/verificar.php?code={$verifCode}&payment_status=failure&external_ref={$externalReference}"
            ],
            'auto_return' => 'approved',
            'external_reference' => $externalReference,
            'statement_descriptor' => 'SINNOTARIO',
            'metadata' => [
                'payment_type' => 'DOCUMENT_RETAIL',
                'document_id' => $docId,
                'codigo_verificacion' => $verifCode,
                'monto_total' => $montoTotal,
                'user_id' => $userId
            ]
        ];

        $pdo = Database::getInstance();
        $stmtOrder = $pdo->prepare("
            INSERT INTO payment_orders (
                external_reference, payment_type, document_id, user_id,
                monto_neto, monto_total, moneda, estado, metadata_json
            ) VALUES (?, 'DOCUMENT_RETAIL', ?, ?, ?, ?, 'CLP', 'PENDIENTE', ?)
        ");
        $stmtOrder->execute([
            $externalReference,
            $docId,
            $userId,
            (int)round($montoTotal / 1.19),
            $montoTotal,
            json_encode($preferenceData['metadata'], JSON_UNESCAPED_UNICODE)
        ]);
        $orderId = $pdo->lastInsertId();

        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'configured' => false,
                'order_id' => $orderId,
                'external_reference' => $externalReference,
                'monto_total' => $montoTotal,
                'init_point' => null,
                'message' => 'Orden de pago registrada. Configura Mercado Pago en la administración para cobro automático.'
            ];
        }

        $res = $this->request('/checkout/preferences', 'POST', $preferenceData);
        if (!$res['success']) {
            return $res;
        }

        $initPoint = $this->isSandbox ? ($res['data']['sandbox_init_point'] ?? $res['data']['init_point']) : $res['data']['init_point'];

        $stmtUpd = $pdo->prepare("UPDATE payment_orders SET preference_id = ? WHERE id = ?");
        $stmtUpd->execute([$res['data']['id'], $orderId]);

        return [
            'success' => true,
            'configured' => true,
            'order_id' => $orderId,
            'preference_id' => $res['data']['id'],
            'init_point' => $initPoint,
            'external_reference' => $externalReference,
            'monto_total' => $montoTotal
        ];
    }

    /**
     * Procesa la confirmación de pago (Webhook de Mercado Pago o retorno verificado)
     */
    public static function processApprovedPayment(string $externalRef, string $paymentId = null, array $rawPayload = []): array {
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare("SELECT * FROM payment_orders WHERE external_reference = ?");
        $stmt->execute([$externalRef]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Orden de pago no encontrada: {$externalRef}");
        }

        if ($order['estado'] === 'APROBADO') {
            return ['success' => true, 'already_processed' => true, 'order' => $order];
        }

        $pdo->beginTransaction();
        try {
            // Actualizar estado de orden
            $stmtUpd = $pdo->prepare("
                UPDATE payment_orders
                SET estado = 'APROBADO', mp_payment_id = ?, raw_response = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmtUpd->execute([$paymentId, json_encode($rawPayload, JSON_UNESCAPED_UNICODE), $order['id']]);

            // Si es recarga B2B, acreditar firmas a la empresa
            if ($order['payment_type'] === 'B2B_PACK' && !empty($order['company_id'])) {
                require_once __DIR__ . '/B2BService.php';
                $packs = B2BService::getPrepaidPacks();
                $packKey = $order['pack_key'];
                $firmasAcreditar = isset($packs[$packKey]) ? $packs[$packKey]['firmas'] : 50;

                $stmtC = $pdo->prepare("SELECT saldo_firmas_prepago FROM companies WHERE id = ?");
                $stmtC->execute([$order['company_id']]);
                $saldoActual = (int)$stmtC->fetchColumn();
                $nuevoSaldo = $saldoActual + $firmasAcreditar;

                $stmtUpdComp = $pdo->prepare("
                    UPDATE companies
                    SET saldo_firmas_prepago = ?, updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmtUpdComp->execute([$nuevoSaldo, $order['company_id']]);

                // Registrar en log de auditoría
                $stmtLog = $pdo->prepare("
                    INSERT INTO b2b_billing_logs (
                        company_id, tipo_movimiento, modalidad, cantidad_firmas,
                        monto_clp, saldo_anterior, saldo_nuevo, descripcion
                    ) VALUES (?, 'RECARGA_PREPAGO', 'PREPAGO', ?, ?, ?, ?, ?)
                ");
                $stmtLog->execute([
                    $order['company_id'],
                    $firmasAcreditar,
                    $order['monto_total'],
                    $saldoActual,
                    $nuevoSaldo,
                    "Pago acreditado vía Mercado Pago (ID: {$paymentId}, Ref: {$externalRef})"
                ]);
            }

            $pdo->commit();
            return ['success' => true, 'order' => $order];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

