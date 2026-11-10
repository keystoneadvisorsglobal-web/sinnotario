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
     * Consulta el estado y detalle de un pago en la API v1 de Mercado Pago
     */
    public function getPayment(int $paymentId): array {
        $res = $this->request("/v1/payments/{$paymentId}", 'GET');
        if (!$res['success']) {
            throw new Exception("Error consultando pago #{$paymentId} en Mercado Pago: " . ($res['error'] ?? 'Desconocido'));
        }
        return $res['data'] ?? [];
    }

    /**
     * Consulta y sincroniza el estado de una Orden en Orders API (/v1/orders/{id})
     * Implementa la buena práctica de Backup de Notificaciones ante eventual retraso del Webhook
     */
    public function getOrder(string $orderId): array {
        $res = $this->request("/v1/orders/{$orderId}", 'GET');
        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error'] ?? 'No se pudo obtener la orden'];
        }
        return ['success' => true, 'data' => $res['data'] ?? []];
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
    public function createB2BPackPreference(int $companyId, string $packKey, array $packData, array $companyData, ?int $userId = null): array {
        $externalReference = "B2B_PACK_{$companyId}_{$packKey}_" . time();
        $totalConIva = (int)round($packData['total'] * 1.19); // Valores B2B son + IVA

        $siteUrl = 'https://sinnotario.cl';

        // Desglosar nombres del pagador y teléfono
        $fullName = trim($companyData['rep_legal_nombre'] ?? $companyData['razon_social'] ?? 'Representante Legal');
        $nameParts = preg_split('/\s+/', $fullName, 2);
        $firstName = $nameParts[0] ?? 'Representante';
        $lastName = $nameParts[1] ?? 'Legal';
        $cleanRut = preg_replace('/[^0-9kK]/', '', $companyData['rut'] ?? '');

        $phoneRaw = $companyData['telefono'] ?? ($companyData['rep_legal_telefono'] ?? '');
        $phoneDigits = preg_replace('/\D/', '', $phoneRaw);
        $areaCode = '56';
        $phoneNumber = '999999999';
        if (!empty($phoneDigits)) {
            if (strlen($phoneDigits) >= 9) {
                $phoneNumber = substr($phoneDigits, -9);
            } else {
                $phoneNumber = $phoneDigits;
            }
        }

        $pdo = Database::getInstance();

        // Historial del cliente para preferencias de pago
        $stmtHistory = $pdo->prepare("SELECT COUNT(*) as total_orders, MAX(created_at) as last_date FROM payment_orders WHERE company_id = ? AND estado IN ('APROBADO', 'PAGADO')");
        $stmtHistory->execute([$companyId]);
        $history = $stmtHistory->fetch(PDO::FETCH_ASSOC) ?: ['total_orders' => 0, 'last_date' => null];
        $isFirstPurchase = ((int)($history['total_orders'] ?? 0)) === 0;

        $preferenceData = [
            'items' => [
                [
                    'id' => $packKey,
                    'title' => "SinNotario - {$packData['nombre']} ({$packData['firmas']} firmas)",
                    'description' => "Bolsa prepagada de certificación digital Ley N° 19.799 para {$companyData['razon_social']}",
                    'category_id' => 'services',
                    'quantity' => 1,
                    'currency_id' => 'CLP',
                    'unit_price' => $totalConIva
                ]
            ],
            'payer' => [
                'name' => $firstName,
                'surname' => $lastName,
                'email' => $companyData['rep_legal_email'] ?: ($companyData['email'] ?: 'contacto@empresa.cl'),
                'phone' => [
                    'area_code' => $areaCode,
                    'number' => $phoneNumber
                ],
                'identification' => [
                    'type' => 'RUT',
                    'number' => $cleanRut ?: '111111111'
                ]
            ],
            'additional_info' => [
                'payer' => [
                    'is_first_purchase_online' => $isFirstPurchase,
                    'authentication_type' => 'native',
                    'registration_date' => date('Y-m-d\TH:i:s.000P', strtotime($companyData['created_at'] ?? 'now')),
                    'last_purchase' => !empty($history['last_date']) ? date('Y-m-d\TH:i:s.000P', strtotime($history['last_date'])) : null
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
        $validUserId = ($userId && $userId > 0) ? $userId : null;

        $stmtOrder = $pdo->prepare("
            INSERT INTO payment_orders (
                external_reference, payment_type, company_id, user_id,
                pack_key, monto_neto, monto_total, moneda, estado, metadata_json
            ) VALUES (?, 'B2B_PACK', ?, ?, ?, ?, ?, 'CLP', 'PENDIENTE', ?)
        ");
        $stmtOrder->execute([
            $externalReference,
            $companyId,
            $validUserId,
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

        // 1. Intentar creación nativa vía Orders API (/v1/orders) oficial de la aplicación
        // Desglosar nombres del pagador
        $fullName = trim($companyData['rep_legal_nombre'] ?? $companyData['razon_social'] ?? 'Representante Legal');
        $nameParts = preg_split('/\s+/', $fullName, 2);
        $firstName = $nameParts[0] ?? 'Representante';
        $lastName = $nameParts[1] ?? 'Legal';
        $cleanRut = preg_replace('/[^0-9kK]/', '', $companyData['rut'] ?? '');

        // Obtener teléfono de la empresa o usuario
        $phoneRaw = $companyData['telefono'] ?? ($companyData['rep_legal_telefono'] ?? '');
        $phoneDigits = preg_replace('/\D/', '', $phoneRaw);
        $areaCode = '56';
        $phoneNumber = '999999999';
        if (!empty($phoneDigits)) {
            if (strlen($phoneDigits) >= 9) {
                $phoneNumber = substr($phoneDigits, -9);
            } else {
                $phoneNumber = $phoneDigits;
            }
        }

        // Historial del pagador para additional_info (Anti-fraude y Scoring MP)
        $stmtHistory = $pdo->prepare("SELECT COUNT(*) as total_orders, MAX(created_at) as last_date FROM payment_orders WHERE company_id = ? AND estado IN ('APROBADO', 'PAGADO')");
        $stmtHistory->execute([$companyId]);
        $history = $stmtHistory->fetch(PDO::FETCH_ASSOC) ?: ['total_orders' => 0, 'last_date' => null];
        $isFirstPurchase = ((int)($history['total_orders'] ?? 0)) === 0;

        $orderPayload = [
            'type' => 'online',
            'processing_mode' => 'manual',
            'external_reference' => $externalReference,
            'total_amount' => (string)$totalConIva,
            'items' => [
                [
                    'external_code' => "PACK-{$packKey}",
                    'title' => "SinNotario - {$packData['nombre']}",
                    'description' => "Bolsa prepagada de certificación digital Ley 19.799 ({$packData['firmas']} firmas)",
                    'category_id' => 'services',
                    'unit_price' => (string)$totalConIva,
                    'quantity' => 1
                ]
            ],
            'payer' => [
                'email' => $companyData['rep_legal_email'] ?: ($companyData['email'] ?: 'contacto@keystoneadvisors.cl'),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => [
                    'area_code' => $areaCode,
                    'number' => $phoneNumber
                ],
                'identification' => [
                    'type' => 'RUT',
                    'number' => $cleanRut ?: '111111111'
                ]
            ],
            'config' => [
                'statement_descriptor' => 'SINNOTARIO',
                'online' => [
                    'success_url' => "{$siteUrl}/empresas.php?payment_status=success&external_ref={$externalReference}",
                    'pending_url' => "{$siteUrl}/empresas.php?payment_status=pending&external_ref={$externalReference}",
                    'failure_url' => "{$siteUrl}/empresas.php?payment_status=failure&external_ref={$externalReference}",
                    'auto_return' => 'approved'
                ]
            ]
        ];

        $resOrder = $this->request('/v1/orders', 'POST', $orderPayload);
        if ($resOrder['success'] && !empty($resOrder['data']['checkout_url'])) {
            $orderMPId = $resOrder['data']['id'] ?? '';
            $stmtUpd = $pdo->prepare("UPDATE payment_orders SET preference_id = ? WHERE id = ?");
            $stmtUpd->execute([$orderMPId, $orderId]);

            return [
                'success' => true,
                'configured' => true,
                'order_id' => $orderId,
                'preference_id' => $orderMPId,
                'init_point' => $resOrder['data']['checkout_url'],
                'external_reference' => $externalReference,
                'monto_total' => $totalConIva
            ];
        }

        // 2. Fallback clásico vía Preferences API (/checkout/preferences)
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

        // Desglosar nombres del pagador y documento de identidad (RUT o Pasaporte)
        $fullName = trim($payerData['nombre'] ?? 'Cliente SinNotario');
        $nameParts = preg_split('/\s+/', $fullName, 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? 'SinNotario';

        $idType = !empty($payerData['tipo_documento']) ? strtoupper($payerData['tipo_documento']) : 'RUT';
        $idNum = !empty($payerData['numero_documento']) ? $payerData['numero_documento'] : ($payerData['rut'] ?? '');
        $idNumClean = preg_replace('/[^a-zA-Z0-9]/', '', $idNum);
        if (empty($idNumClean)) {
            $idNumClean = '111111111';
        }

        // Obtener teléfono del pagador (WhatsApp / Móvil con código de país)
        $phoneRaw = $payerData['telefono'] ?? ($payerData['phone'] ?? '');
        $phoneDigits = preg_replace('/\D/', '', $phoneRaw);
        $areaCode = '56';
        $phoneNumber = '999999999';
        if (!empty($phoneDigits)) {
            if (strlen($phoneDigits) >= 9) {
                $phoneNumber = substr($phoneDigits, -9);
                if (strlen($phoneDigits) > 9) {
                    $areaCode = substr($phoneDigits, 0, strlen($phoneDigits) - 9);
                }
            } else {
                $phoneNumber = $phoneDigits;
            }
        }

        $payerEmail = $payerData['email'] ?: 'contacto@sinnotario.cl';

        // Determinar historial del cliente para additional_info
        $pdo = Database::getInstance();
        $stmtHistory = $pdo->prepare("
            SELECT COUNT(*) as total_orders, MAX(created_at) as last_date 
            FROM payment_orders 
            WHERE (user_id = ? AND user_id IS NOT NULL) 
               OR metadata_json LIKE ?
        ");
        $stmtHistory->execute([$userId, '%' . $payerEmail . '%']);
        $history = $stmtHistory->fetch(PDO::FETCH_ASSOC) ?: ['total_orders' => 0, 'last_date' => null];
        $isFirstPurchase = ((int)($history['total_orders'] ?? 0)) === 0;

        // Tipo de autenticación (Google OAuth vs formulario nativo)
        $authType = 'native';
        if ($userId) {
            $stmtUser = $pdo->prepare("SELECT google_id FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!empty($uRow['google_id'])) {
                $authType = 'gmail';
            }
        }

        // Determinar si el documento tiene upgrade a FEA (Firma Avanzada Spot)
        $pdo = Database::getInstance();
        $stmtDocType = $pdo->prepare("SELECT tipo_firma FROM documents WHERE id = ?");
        $stmtDocType->execute([$docId]);
        $tipoFirmaDoc = $stmtDocType->fetchColumn() ?: 'FES';
        $esFea = ($tipoFirmaDoc === 'FEA');

        $descFirma = $esFea 
            ? "Firma Electrónica Avanzada (FEA Ley 19.799 con ClaveÚnica Idok) - Cód: {$verifCode}"
            : "Certificación digital de documento privado Ley N° 19.799 (Cód: {$verifCode})";

        $preferenceData = [
            'items' => [
                [
                    'id' => "DOC-{$docId}",
                    'title' => "SinNotario - {$docTitle}" . ($esFea ? ' [Firma Avanzada FEA]' : ''),
                    'description' => $descFirma,
                    'category_id' => 'services',
                    'quantity' => 1,
                    'currency_id' => 'CLP',
                    'unit_price' => $montoTotal
                ]
            ],
            'payer' => [
                'name' => $firstName,
                'surname' => $lastName,
                'email' => $payerEmail,
                'phone' => [
                    'area_code' => $areaCode,
                    'number' => $phoneNumber
                ],
                'identification' => [
                    'type' => ($idType === 'PASAPORTE') ? 'PASAPORTE' : 'RUT',
                    'number' => $idNumClean
                ]
            ],
            'additional_info' => [
                'payer' => [
                    'is_first_purchase_online' => $isFirstPurchase,
                    'authentication_type' => $authType,
                    'registration_date' => date('Y-m-d\TH:i:s.000P'),
                    'last_purchase' => !empty($history['last_date']) ? date('Y-m-d\TH:i:s.000P', strtotime($history['last_date'])) : null
                ]
            ],
            'back_urls' => [
                'success' => "{$siteUrl}/firmar.php?code={$verifCode}&payment_status=success&external_ref={$externalReference}",
                'pending' => "{$siteUrl}/firmar.php?code={$verifCode}&payment_status=pending&external_ref={$externalReference}",
                'failure' => "{$siteUrl}/firmar.php?code={$verifCode}&payment_status=failure&external_ref={$externalReference}"
            ],
            'auto_return' => 'approved',
            'external_reference' => $externalReference,
            'statement_descriptor' => 'SINNOTARIO',
            'metadata' => [
                'payment_type' => 'DOCUMENT_RETAIL',
                'document_id' => $docId,
                'codigo_verificacion' => $verifCode,
                'tipo_firma' => $tipoFirmaDoc,
                'monto_total' => $montoTotal,
                'user_id' => $userId
            ]
        ];

        $pdo = Database::getInstance();
        $validUserId = ($userId && $userId > 0) ? $userId : null;

        $stmtOrder = $pdo->prepare("
            INSERT INTO payment_orders (
                external_reference, payment_type, document_id, user_id,
                monto_neto, monto_total, moneda, estado, metadata_json
            ) VALUES (?, 'DOCUMENT_RETAIL', ?, ?, ?, ?, 'CLP', 'PENDIENTE', ?)
        ");
        $stmtOrder->execute([
            $externalReference,
            $docId,
            $validUserId,
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

        // 1. Intentar creación nativa vía Orders API (/v1/orders) oficial de la aplicación
        // Desglosar nombres del pagador y documento de identidad (RUT o Pasaporte)
        $fullName = trim($payerData['nombre'] ?? 'Cliente SinNotario');
        $nameParts = preg_split('/\s+/', $fullName, 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? 'SinNotario';

        $idType = !empty($payerData['tipo_documento']) ? strtoupper($payerData['tipo_documento']) : 'RUT';
        $idNum = !empty($payerData['numero_documento']) ? $payerData['numero_documento'] : ($payerData['rut'] ?? '');
        $idNumClean = preg_replace('/[^a-zA-Z0-9]/', '', $idNum);
        if (empty($idNumClean)) {
            $idNumClean = '111111111';
        }

        // Obtener teléfono del pagador (WhatsApp / Móvil con código de país)
        $phoneRaw = $payerData['telefono'] ?? ($payerData['phone'] ?? '');
        $phoneDigits = preg_replace('/\D/', '', $phoneRaw);
        $areaCode = '56';
        $phoneNumber = '999999999';
        if (!empty($phoneDigits)) {
            if (strlen($phoneDigits) >= 9) {
                $phoneNumber = substr($phoneDigits, -9);
                if (strlen($phoneDigits) > 9) {
                    $areaCode = substr($phoneDigits, 0, strlen($phoneDigits) - 9);
                }
            } else {
                $phoneNumber = $phoneDigits;
            }
        }

        // Determinar historial del cliente para additional_info (Anti-fraude y scoring MP)
        $payerEmail = $payerData['email'] ?: 'contacto@keystoneadvisors.cl';
        $stmtHistory = $pdo->prepare("
            SELECT COUNT(*) as total_orders, MAX(created_at) as last_date 
            FROM payment_orders 
            WHERE (user_id = ? AND user_id IS NOT NULL) 
               OR metadata_json LIKE ?
        ");
        $stmtHistory->execute([$userId, '%' . $payerEmail . '%']);
        $history = $stmtHistory->fetch(PDO::FETCH_ASSOC) ?: ['total_orders' => 0, 'last_date' => null];
        $isFirstPurchase = ((int)($history['total_orders'] ?? 0)) === 0;

        // Tipo de autenticación (Google OAuth vs formulario nativo)
        $authType = 'native';
        if ($userId) {
            $stmtUser = $pdo->prepare("SELECT google_id FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!empty($uRow['google_id'])) {
                $authType = 'gmail';
            }
        }

        $orderPayload = [
            'type' => 'online',
            'processing_mode' => 'manual',
            'external_reference' => $externalReference,
            'total_amount' => (string)$montoTotal,
            'items' => [
                [
                    'external_code' => "DOC-{$docId}",
                    'title' => "SinNotario - {$docTitle}",
                    'description' => "Certificación digital de documento privado Ley 19.799 (Cód: {$verifCode})",
                    'category_id' => 'services',
                    'unit_price' => (string)$montoTotal,
                    'quantity' => 1
                ]
            ],
            'payer' => [
                'email' => $payerEmail,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => [
                    'area_code' => $areaCode,
                    'number' => $phoneNumber
                ],
                'identification' => [
                    'type' => ($idType === 'PASAPORTE') ? 'PASAPORTE' : 'RUT',
                    'number' => $idNumClean
                ]
            ],
            'config' => [
                'statement_descriptor' => 'SINNOTARIO',
                'online' => [
                    'success_url' => "{$siteUrl}/firmar.php?code={$verifCode}&payment_status=success&external_ref={$externalReference}",
                    'pending_url' => "{$siteUrl}/firmar.php?code={$verifCode}&payment_status=pending&external_ref={$externalReference}",
                    'failure_url' => "{$siteUrl}/firmar.php?code={$verifCode}&payment_status=failure&external_ref={$externalReference}",
                    'auto_return' => 'approved'
                ]
            ]
        ];

        $resOrder = $this->request('/v1/orders', 'POST', $orderPayload);
        if ($resOrder['success'] && !empty($resOrder['data']['checkout_url'])) {
            $orderMPId = $resOrder['data']['id'] ?? '';
            $stmtUpd = $pdo->prepare("UPDATE payment_orders SET preference_id = ? WHERE id = ?");
            $stmtUpd->execute([$orderMPId, $orderId]);

            return [
                'success' => true,
                'configured' => true,
                'order_id' => $orderId,
                'preference_id' => $orderMPId,
                'init_point' => $resOrder['data']['checkout_url'],
                'external_reference' => $externalReference,
                'monto_total' => $montoTotal
            ];
        }

        // 2. Fallback clásico vía Preferences API (/checkout/preferences)
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

        if ($order['estado'] === 'PAGADO' || $order['estado'] === 'CONCILIADO' || $order['estado'] === 'APROBADO') {
            return ['success' => true, 'already_processed' => true, 'order' => $order];
        }

        $pdo->beginTransaction();
        try {
            // Actualizar estado de orden a PAGADO (nomenclatura financiera oficial)
            $stmtUpd = $pdo->prepare("
                UPDATE payment_orders
                SET estado = 'PAGADO', mp_payment_id = ?, raw_response = ?, updated_at = datetime('now')
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

                // Notificación automática por Email y WhatsApp a la Empresa B2B (SOL-0107)
                try {
                    self::sendB2BPaymentConfirmation((int)$order['company_id'], $order, $firmasAcreditar, $nuevoSaldo);
                } catch (\Throwable $eB2BNotif) {
                    error_log("Error enviando notificaciones B2B post-pago: " . $eB2BNotif->getMessage());
                }
            }

            // Si es trámite particular (DOCUMENT_RETAIL), pasar documento a EN_FIRMA y estado_pago a PAGADO
            if ($order['payment_type'] === 'DOCUMENT_RETAIL' && !empty($order['document_id'])) {
                $stmtDoc = $pdo->prepare("
                    UPDATE documents
                    SET estado = 'PENDIENTE_FIRMA',
                        estado_pago = 'PAGADO',
                        monto_pagado = ?,
                        updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmtDoc->execute([$order['monto_total'], $order['document_id']]);

                // Notificación automática de Rescate y Enlace de Firma (Email + WhatsApp)
                try {
                    self::sendPaymentConfirmationAndSigningLinks((int)$order['document_id'], $order);
                } catch (\Throwable $eNotif) {
                    error_log("Error enviando notificaciones post-pago: " . $eNotif->getMessage());
                }
            }

            $pdo->commit();
            return ['success' => true, 'order' => $order];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Envío automático de comprobante de pago y enlace de firma digital a los firmantes (Email + WhatsApp)
     */
    private static function sendPaymentConfirmationAndSigningLinks(int $docId, array $order): void {
        $pdo = Database::getInstance();
        $stmtDoc = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
        $stmtDoc->execute([$docId]);
        $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if (!$doc) return;

        $stmtSigners = $pdo->prepare("SELECT * FROM document_signers WHERE document_id = ? ORDER BY id ASC");
        $stmtSigners->execute([$docId]);
        $signers = $stmtSigners->fetchAll(PDO::FETCH_ASSOC);
        if (empty($signers)) return;

        $vCode = $doc['codigo_verificacion'];
        $docTitle = $doc['titulo'] ?: 'Documento Legal';
        $siteUrl = 'https://sinnotario.cl';
        $signingUrl = "{$siteUrl}/firmar.php?code={$vCode}&payment_status=success";

        require_once __DIR__ . '/MailService.php';
        require_once __DIR__ . '/WhatsAppService.php';

        $ws = new WhatsAppService();

        foreach ($signers as $sig) {
            $email = trim($sig['email'] ?? '');
            $tel = trim($sig['telefono'] ?? '');
            $nombre = trim($sig['nombre_completo'] ?? 'Estimado(a)');

            // 1. Envío por Correo Electrónico
            if (!empty($email)) {
                $asunto = "✅ Pago Confirmado: Enlace para firmar tu {$docTitle} ({$vCode})";
                $htmlBody = "
                    <div style='font-family:Arial,sans-serif; color:#333333; line-height:1.6;'>
                        <h2 style='color:#0F2A4A; margin-bottom:10px;'>¡Pago acreditado exitosamente!</h2>
                        <p>Hola <strong>{$nombre}</strong>,</p>
                        <p>Confirmamos la recepción del pago por <strong>$" . number_format($order['monto_total'], 0, ',', '.') . " CLP</strong> para la certificación y firma digital de tu trámite: <strong>{$docTitle}</strong> (Código: <strong>{$vCode}</strong>).</p>
                        
                        <div style='background:#F8FAFC; border-left:4px solid #10B981; padding:15px; margin:20px 0; border-radius:6px;'>
                            <p style='margin:0; font-size:15px; font-weight:bold; color:#0F2A4A;'>¿Cerraste la ventana o se te apagó el dispositivo?</p>
                            <p style='margin:5px 0 0; font-size:14px; color:#475569;'>No te preocupes. Tu trámite está 100% asegurado en nuestra plataforma. Puedes reanudar y estampar tu firma electrónica en cualquier momento haciendo clic en el siguiente botón:</p>
                        </div>

                        <div style='text-align:center; margin:25px 0;'>
                            <a href='{$signingUrl}' style='background:#0F2A4A; color:#FFFFFF; text-decoration:none; padding:12px 28px; border-radius:6px; font-weight:bold; display:inline-block; font-size:16px;'>
                                ✍️ Continuar y Firmar Documento
                            </a>
                        </div>

                        <p style='font-size:13px; color:#64748B;'>También puedes verificar y dar seguimiento ingresando con tu código de validación <strong>{$vCode}</strong> en <a href='{$siteUrl}/verificar.php' style='color:#0F2A4A;'>sinnotario.cl/verificar.php</a>.</p>
                        <hr style='border:none; border-top:1px solid #E2E8F0; margin:20px 0;'>
                        <p style='font-size:12px; color:#94A3B8;'>SinNotario.cl &bull; Keystone Advisors SpA &bull; Certificación bajo Ley N° 19.799</p>
                    </div>
                ";
                MailService::send($email, $asunto, $htmlBody);
            }

            // 2. Envío por WhatsApp
            if (!empty($tel)) {
                // Formatear teléfono internacional: si no tiene +, limpiar caracteres
                $cleanTel = preg_replace('/[^0-9]/', '', $tel);
                if (strlen($cleanTel) === 9 && substr($cleanTel, 0, 1) === '9') {
                    $cleanTel = '56' . $cleanTel;
                }

                $msgWs = "Hola *{$nombre}*, en SinNotario.cl confirmamos el pago de tu trámite: *{$docTitle}* (Folio: *{$vCode}*).\n\nPuedes continuar con la firma digital de inmediato en este enlace seguro:\n👉 {$signingUrl}\n\n_Tu documento cuenta con respaldo criptográfico bajo Ley N° 19.799._";
                $ws->sendTextMessage($cleanTel, $msgWs, 'keystone-general', (int)$docId, $nombre, 'CONFIRMACION_PAGO_FIRMA');
            }
        }
    }

    /**
     * Envío automático de confirmación de recarga y activación de bolsa de firmas a Empresa B2B (Email + WhatsApp)
     */
    public static function sendB2BPaymentConfirmation(int $companyId, array $order, int $firmasAcreditadas, int $nuevoSaldo): void {
        $pdo = Database::getInstance();
        $stmtC = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmtC->execute([$companyId]);
        $comp = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$comp) return;

        $razonSocial = $comp['razon_social'] ?: 'Empresa Cliente';
        $repNombre = $comp['rep_legal_nombre'] ?: 'Representante Legal';
        $email = trim($comp['rep_legal_email'] ?? '');
        $tel = trim($comp['whatsapp_phone'] ?? '');
        $montoTotalFmt = '$' . number_format($order['monto_total'], 0, ',', '.');
        $siteUrl = 'https://sinnotario.cl';
        $b2bUrl = "{$siteUrl}/empresas.php";

        require_once __DIR__ . '/MailService.php';
        require_once __DIR__ . '/WhatsAppService.php';

        // 1. Envío por Correo Electrónico
        if (!empty($email)) {
            $asunto = "✅ Recarga Acreditada: Se han abonado {$firmasAcreditadas} firmas a {$razonSocial}";
            $htmlBody = "
                <div style='font-family:Arial,sans-serif; color:#333333; line-height:1.6;'>
                    <h2 style='color:#0F2A4A; margin-bottom:10px;'>¡Recarga B2B Acreditada Exitosamente!</h2>
                    <p>Estimado(a) <strong>{$repNombre}</strong>,</p>
                    <p>Confirmamos la recepción del pago por <strong>{$montoTotalFmt} CLP</strong> para la recarga de firmas digitales de <strong>{$razonSocial}</strong> (RUT: <strong>{$comp['rut']}</strong>).</p>
                    
                    <div style='background:#F8FAFC; border-left:4px solid #10B981; padding:15px; margin:20px 0; border-radius:6px;'>
                        <p style='margin:0; font-size:15px; font-weight:bold; color:#0F2A4A;'>Detalle de la Bolsa de Firmas:</p>
                        <p style='margin:5px 0 0; font-size:14px; color:#475569;'>
                            • Firmas Acreditadas: <strong>+{$firmasAcreditadas} firmas</strong><br>
                            • Saldo Total Disponible: <strong>{$nuevoSaldo} firmas</strong><br>
                            • Modalidad: <strong>Prepago Corporativo (Ley N° 19.799)</strong>
                        </p>
                    </div>

                    <div style='text-align:center; margin:25px 0;'>
                        <a href='{$b2bUrl}' style='background:#0F2A4A; color:#FFFFFF; text-decoration:none; padding:12px 28px; border-radius:6px; font-weight:bold; display:inline-block; font-size:16px;'>
                            🚀 Ir al Panel Corporativo de Empresas
                        </a>
                    </div>

                    <p style='font-size:13px; color:#64748B;'>Ya puedes emitir contratos masivos vía nómina Excel/CSV y gestionar las carpetas digitales de tus colaboradores.</p>
                    <hr style='border:none; border-top:1px solid #E2E8F0; margin:20px 0;'>
                    <p style='font-size:12px; color:#94A3B8;'>SinNotario.cl &bull; Keystone Advisors SpA &bull; Soluciones Corporativas B2B</p>
                </div>
            ";
            MailService::send($email, $asunto, $htmlBody);
        }

        // 2. Envío por WhatsApp
        if (!empty($tel)) {
            $cleanTel = preg_replace('/[^0-9]/', '', $tel);
            if (strlen($cleanTel) === 9 && substr($cleanTel, 0, 1) === '9') {
                $cleanTel = '56' . $cleanTel;
            }

            $ws = new WhatsAppService();
            $msgWs = "Hola *{$repNombre}*, en SinNotario.cl confirmamos la recarga de tu empresa *{$razonSocial}*.\n\n" .
                     "✅ *+{$firmasAcreditadas} firmas* acreditadas exitosamente ({$montoTotalFmt} CLP).\n" .
                     "📊 *Saldo actual disponible:* {$nuevoSaldo} firmas.\n\n" .
                     "Ya puedes continuar con la emisión masiva en tu panel:\n👉 {$b2bUrl}\n\n" .
                     "_Certificación digital laboral bajo Ley N° 19.799 y DT._";

            $ws->sendTextMessage($cleanTel, $msgWs, 'keystone-general', null, $repNombre, 'B2B_CONFIRMACION_RECARGA', $companyId);
        }
    }

    /**
     * Rescate de Intentos de Compra y Carritos Pendientes (SOL-0108)
     * Envía recordatorio por Email y WhatsApp para reanudar el pago pendiente.
     */
    public static function sendPendingOrderRescue(int $orderId): array {
        $pdo = Database::getInstance();
        $stmtOrder = $pdo->prepare("SELECT * FROM payment_orders WHERE id = ?");
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['estado'] !== 'PENDIENTE') {
            return ['success' => false, 'error' => 'La orden no existe o ya no está en estado pendiente'];
        }

        require_once __DIR__ . '/MailService.php';
        require_once __DIR__ . '/WhatsAppService.php';

        $ws = new WhatsAppService();
        $siteUrl = 'https://sinnotario.cl';
        $montoFmt = '$' . number_format($order['monto_total'], 0, ',', '.');
        $resumeUrl = '';
        $destinatarioNombre = '';
        $destinatarioEmail = '';
        $destinatarioTel = '';
        $itemDesc = '';
        $companyId = !empty($order['company_id']) ? (int)$order['company_id'] : null;

        // Deserializar raw_response o preference_id para obtener el checkout_url directo de Mercado Pago
        $rawRes = !empty($order['raw_response']) ? json_decode($order['raw_response'], true) : [];
        $checkoutUrl = $rawRes['checkout_url'] ?? ($rawRes['data']['checkout_url'] ?? null);

        if ($order['payment_type'] === 'B2B_PACK') {
            // Caso Empresa B2B
            $stmtC = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
            $stmtC->execute([$companyId]);
            $comp = $stmtC->fetch(PDO::FETCH_ASSOC);

            if ($comp) {
                $destinatarioNombre = $comp['rep_legal_nombre'] ?: $comp['razon_social'];
                $destinatarioEmail = trim($comp['rep_legal_email'] ?? '');
                $destinatarioTel = trim($comp['whatsapp_phone'] ?? '');
            }

            // Fallback al usuario dueño si la empresa no tiene datos
            if (empty($destinatarioEmail) && !empty($order['user_id'])) {
                $stmtU = $pdo->prepare("SELECT email, telefono, nombre, apellido FROM users WHERE id = ?");
                $stmtU->execute([$order['user_id']]);
                $u = $stmtU->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    if (empty($destinatarioNombre)) $destinatarioNombre = trim("{$u['nombre']} {$u['apellido']}");
                    $destinatarioEmail = trim($u['email'] ?? '');
                    if (empty($destinatarioTel)) $destinatarioTel = trim($u['telefono'] ?? '');
                }
            }

            $packs = [
                'PACK_10' => 'Bolsa de Bienvenida (10 Firmas)',
                'PACK_50' => 'Bolsa de 50 Firmas',
                'PACK_200' => 'Bolsa de 200 Firmas',
                'PACK_500' => 'Bolsa Enterprise de 500 Firmas'
            ];
            $packName = $packs[$order['pack_key']] ?? "Bolsa de Firmas ({$order['pack_key']})";
            $itemDesc = "la recarga de {$packName} para " . ($comp['razon_social'] ?? 'tu empresa');
            $resumeUrl = $checkoutUrl ?: "{$siteUrl}/empresas.php";

            // 1. Envío Correo B2B Rescate
            if (!empty($destinatarioEmail)) {
                $asunto = "⏳ Rescata tu compra: Completa la activación de {$packName} en SinNotario.cl";
                $htmlBody = "
                    <div style='font-family:Arial,sans-serif; color:#333333; line-height:1.6;'>
                        <h2 style='color:#0F2A4A; margin-bottom:10px;'>¿Tuviste problemas para completar tu pago?</h2>
                        <p>Hola <strong>{$destinatarioNombre}</strong>,</p>
                        <p>Notamos que iniciaste el proceso de compra de <strong>{$packName}</strong> por un monto de <strong>{$montoFmt} CLP</strong> para tu empresa.</p>
                        
                        <div style='background:#FFFBEB; border-left:4px solid #F59E0B; padding:15px; margin:20px 0; border-radius:6px;'>
                            <p style='margin:0; font-size:15px; font-weight:bold; color:#92400E;'>Tu orden sigue reservada y lista para reanudar</p>
                            <p style='margin:5px 0 0; font-size:14px; color:#78350F;'>Puedes retomar tu pago de forma 100% segura mediante Webpay, Débito, Tarjeta de Crédito en cuotas o Transferencia bancaria.</p>
                        </div>

                        <div style='text-align:center; margin:25px 0;'>
                            <a href='{$resumeUrl}' style='background:#0F2A4A; color:#FFFFFF; text-decoration:none; padding:13px 30px; border-radius:6px; font-weight:bold; display:inline-block; font-size:16px;'>
                                💳 Completar y Activar Firmas Ahora
                            </a>
                        </div>

                        <p style='font-size:13px; color:#64748B;'>Si necesitas asistencia personalizada o prefieres pagar mediante Factura / Transferencia Directa, responde a este correo o contáctanos por WhatsApp al +56 9 8418 0479.</p>
                        <hr style='border:none; border-top:1px solid #E2E8F0; margin:20px 0;'>
                        <p style='font-size:12px; color:#94A3B8;'>SinNotario.cl &bull; Keystone Advisors SpA &bull; Certificación Digital Ley N° 19.799</p>
                    </div>
                ";
                MailService::send($destinatarioEmail, $asunto, $htmlBody);
            }

            // 2. Envío WhatsApp B2B Rescate
            if (!empty($destinatarioTel)) {
                $cleanTel = preg_replace('/[^0-9]/', '', $destinatarioTel);
                if (strlen($cleanTel) === 9 && substr($cleanTel, 0, 1) === '9') {
                    $cleanTel = '56' . $cleanTel;
                }
                $msgWs = "Hola *{$destinatarioNombre}*, vimos que iniciaste la compra de *{$packName}* ({$montoFmt} CLP) en SinNotario.cl.\n\n" .
                         "¿Tuviste algún inconveniente con el banco o la pasarela? Tu orden sigue reservada y puedes completar el pago de forma segura aquí:\n" .
                         "👉 {$resumeUrl}\n\n" .
                         "Si prefieres pagar vía transferencia directa o necesitas factura anticipada, cuéntanos por aquí.";

                $ws->sendTextMessage($cleanTel, $msgWs, 'keystone-general', null, $destinatarioNombre, 'RESCATE_COMPRA_PENDIENTE_B2B', $companyId);
            }

        } else {
            // Caso Persona Natural (DOCUMENT_RETAIL)
            $docId = (int)$order['document_id'];
            $stmtDoc = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
            $stmtDoc->execute([$docId]);
            $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

            if ($doc) {
                $stmtSig = $pdo->prepare("SELECT * FROM document_signers WHERE document_id = ? ORDER BY id ASC LIMIT 1");
                $stmtSig->execute([$docId]);
                $sig = $stmtSig->fetch(PDO::FETCH_ASSOC);

                if ($sig) {
                    $destinatarioNombre = trim($sig['nombre_completo'] ?? 'Cliente');
                    $destinatarioEmail = trim($sig['email'] ?? '');
                    $destinatarioTel = trim($sig['telefono'] ?? '');
                }

                $docTitle = $doc['titulo'] ?: 'Documento Legal';
                $vCode = $doc['codigo_verificacion'];
                $itemDesc = "la certificación de tu trámite: {$docTitle} (Folio: {$vCode})";
                $resumeUrl = $checkoutUrl ?: "{$siteUrl}/redactar.php?slug={$doc['template_id']}";

                // 1. Envío Correo Rescate Persona
                if (!empty($destinatarioEmail)) {
                    $asunto = "⏳ Rescata tu trámite: Completa la certificación de tu {$docTitle}";
                    $htmlBody = "
                        <div style='font-family:Arial,sans-serif; color:#333333; line-height:1.6;'>
                            <h2 style='color:#0F2A4A; margin-bottom:10px;'>¿Se cerró la ventana o no pudiste pagar?</h2>
                            <p>Hola <strong>{$destinatarioNombre}</strong>,</p>
                            <p>Tu borrador de <strong>{$docTitle}</strong> (Folio: <strong>{$vCode}</strong>) ya está redactado y resguardado en SinNotario.cl.</p>
                            
                            <div style='background:#FFFBEB; border-left:4px solid #F59E0B; padding:15px; margin:20px 0; border-radius:6px;'>
                                <p style='margin:0; font-size:15px; font-weight:bold; color:#92400E;'>No necesitas redactar todo de nuevo</p>
                                <p style='margin:5px 0 0; font-size:14px; color:#78350F;'>Puedes retomar tu pago de <strong>{$montoFmt} CLP</strong> en cualquier momento para habilitar de inmediato las firmas digitales con plena validez legal (Ley N° 19.799).</p>
                            </div>

                            <div style='text-align:center; margin:25px 0;'>
                                <a href='{$resumeUrl}' style='background:#0F2A4A; color:#FFFFFF; text-decoration:none; padding:13px 30px; border-radius:6px; font-weight:bold; display:inline-block; font-size:16px;'>
                                    ✍️ Reanudar Pago y Firmar
                                </a>
                            </div>

                            <p style='font-size:13px; color:#64748B;'>También puedes verificar tu documento con el código <strong>{$vCode}</strong> en <a href='{$siteUrl}/verificar.php' style='color:#0F2A4A;'>sinnotario.cl/verificar.php</a>.</p>
                        </div>
                    ";
                    MailService::send($destinatarioEmail, $asunto, $htmlBody);
                }

                // 2. Envío WhatsApp Rescate Persona
                if (!empty($destinatarioTel)) {
                    $cleanTel = preg_replace('/[^0-9]/', '', $destinatarioTel);
                    if (strlen($cleanTel) === 9 && substr($cleanTel, 0, 1) === '9') {
                        $cleanTel = '56' . $cleanTel;
                    }
                    $msgWs = "Hola *{$destinatarioNombre}*, vimos que iniciaste la certificación de tu *{$docTitle}* (Folio: *{$vCode}*) en SinNotario.cl.\n\n" .
                             "Tu borrador está 100% guardado. Puedes reanudar y completar tu pago ({$montoFmt} CLP) aquí para continuar con las firmas electrónicas:\n" .
                             "👉 {$resumeUrl}\n\n" .
                             "_Plena validez legal bajo Ley N° 19.799._";

                    $ws->sendTextMessage($cleanTel, $msgWs, 'keystone-general', $docId, $destinatarioNombre, 'RESCATE_COMPRA_PENDIENTE_RETAIL');
                }
            }
        }

        // Marcar fecha de rescate en la orden
        $stmtUpd = $pdo->prepare("UPDATE payment_orders SET rescue_sent_at = datetime('now') WHERE id = ?");
        $stmtUpd->execute([$orderId]);

        return [
            'success' => true,
            'order_id' => $orderId,
            'destinatario' => $destinatarioNombre,
            'email' => $destinatarioEmail,
            'telefono' => $destinatarioTel,
            'monto' => $order['monto_total'],
            'resume_url' => $resumeUrl
        ];
    }
}

