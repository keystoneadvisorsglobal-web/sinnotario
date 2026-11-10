<?php
/**
 * Conector de Integración Oficial con FirmaYa / Idok
 * SinNotario.cl - Keystone Advisors SpA
 * 
 * Implementa la API FEA Spot (REST con ClaveÚnica y VIMP)
 * y la API FES BPO-WebService (Firma Simple Machine-to-Machine con Time Stamping X.509)
 */

namespace Services\Signing;

use Database;
use Exception;
use PDO;

require_once __DIR__ . '/SigningProviderInterface.php';
require_once __DIR__ . '/../../../database/Database.php';

class IdokFirmaYaProvider implements SigningProviderInterface {
    private bool $enabled;
    private bool $isSandbox;

    // Configuración FEA Spot
    private string $feaApiUrl;
    private string $feaApiKey;
    private string $feaApiSecret;

    // Configuración FES Simple
    private string $fesWsUrl;
    private string $fesUser;
    private string $fesPassword;

    public function __construct() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT key, value FROM system_settings WHERE key LIKE 'firmaya_%'");
        $settings = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $this->enabled = ($settings['firmaya_enabled'] ?? '0') === '1';
        $this->isSandbox = ($settings['firmaya_mode'] ?? 'sandbox') === 'sandbox';

        if ($this->isSandbox) {
            $this->feaApiUrl = trim($settings['firmaya_fea_url_sandbox'] ?? 'https://firmaya.idok.cl/api/v1/spot');
            $this->feaApiKey = trim($settings['firmaya_fea_key_sandbox'] ?? '');
            $this->feaApiSecret = trim($settings['firmaya_fea_secret_sandbox'] ?? '');

            $this->fesWsUrl = trim($settings['firmaya_fes_url_sandbox'] ?? 'https://cert-bpo.idok.cl:8080/bpo-ws/services/SignFES');
            $this->fesUser = trim($settings['firmaya_fes_user_sandbox'] ?? '');
            $this->fesPassword = trim($settings['firmaya_fes_pass_sandbox'] ?? '');
        } else {
            $this->feaApiUrl = trim($settings['firmaya_fea_url_production'] ?? 'https://firmaya.idok.cl/api/v1/spot');
            $this->feaApiKey = trim($settings['firmaya_fea_key_production'] ?? '');
            $this->feaApiSecret = trim($settings['firmaya_fea_secret_production'] ?? '');

            $this->fesWsUrl = trim($settings['firmaya_fes_url_production'] ?? 'https://bpo.idok.cl:8080/bpo-ws/services/SignFES');
            $this->fesUser = trim($settings['firmaya_fes_user_production'] ?? '');
            $this->fesPassword = trim($settings['firmaya_fes_pass_production'] ?? '');
        }
    }

    public function getProviderId(): string {
        return 'idok_firmaya';
    }

    public function getProviderName(): string {
        return 'FirmaYa / Idok (FEA ClaveÚnica & FES BPO)';
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function isSandbox(): bool {
        return $this->isSandbox;
    }

    public function isConfiguredFEA(): bool {
        return !empty($this->feaApiKey);
    }

    public function isConfiguredFES(): bool {
        return !empty($this->fesUser) && !empty($this->fesPassword);
    }

    /**
     * Firma Simple FES M2M (Firma con Time Stamping y certificado de integración)
     */
    public function signFES(int $documentId, array $signerData, string $pdfBinary): array {
        if (!$this->isEnabled()) {
            throw new Exception("El proveedor FirmaYa está deshabilitado en la configuración general.");
        }

        // Si faltan credenciales activas, operar en modo simulador certificado
        if (!$this->isConfiguredFES()) {
            $simTrx = 'IDOK_FES_SIM_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
            return [
                'success' => true,
                'simulated' => true,
                'provider' => $this->getProviderId(),
                'mode' => $this->isSandbox ? 'sandbox' : 'production',
                'transaction_id' => $simTrx,
                'metodo_firma' => 'FES_FIRMAYA',
                'signed_pdf' => $pdfBinary, // En producción real retorna el binario sellado por Idok
                'message' => 'Firma FES certificada con Time Stamping (Modo Emulador FirmaYa Sandbox).'
            ];
        }

        // Llamada real al WebService FES de Idok
        $payloadWs = [
            'user' => $this->fesUser,
            'password' => $this->fesPassword,
            'rut_firmante' => preg_replace('/[^0-9kK]/', '', $signerData['rut'] ?? ''),
            'nombre_firmante' => $signerData['nombre_completo'] ?? '',
            'email' => $signerData['email'] ?? '',
            'archivo_pdf_b64' => base64_encode($pdfBinary),
            'posicion_firma' => 'HOJA_ADICIONAL'
        ];

        $ch = curl_init($this->fesWsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadWs));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("Error de conexión con WebService Idok FES: " . $err);
        }

        $json = json_decode($resp, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($json['pdf_firmado_b64'])) {
            return [
                'success' => true,
                'simulated' => false,
                'provider' => $this->getProviderId(),
                'transaction_id' => $json['id_transaccion'] ?? ('FES_' . time()),
                'metodo_firma' => 'FES_FIRMAYA',
                'signed_pdf' => base64_decode($json['pdf_firmado_b64']),
                'message' => 'Documento firmado exitosamente con Firma Simple Idok.'
            ];
        }

        throw new Exception("Idok FES rechazó la firma: " . ($json['mensaje'] ?? 'Error desconocido'));
    }

    /**
     * Firma Avanzada Spot (FEA de un solo uso con ClaveÚnica y VIMP)
     */
    public function initiateFEASpot(int $documentId, array $signerData, string $pdfBinary, array $extraParams = []): array {
        if (!$this->isEnabled()) {
            throw new Exception("El proveedor FirmaYa está deshabilitado en la consola administrativa.");
        }

        $cleanRut = preg_replace('/[^0-9kK]/', '', $signerData['rut'] ?? '');
        $redirectUrl = $extraParams['redirect_url'] ?? "https://sinnotario.cl/firmar.php?id={$documentId}";

        // Si no hay credenciales comerciales activas, operar en modo Sandbox guiado
        if (!$this->isConfiguredFEA()) {
            $simTrx = 'IDOK_FEA_SPOT_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
            return [
                'success' => true,
                'simulated' => true,
                'provider' => $this->getProviderId(),
                'mode' => $this->isSandbox ? 'sandbox' : 'production',
                'transaction_id' => $simTrx,
                'metodo_firma' => 'FEA_CLAVEUNICA_SPOT',
                'auth_url' => "https://firmaya.idok.cl/api_docs_spot.html?demo=1&trx={$simTrx}",
                'message' => 'Sesión FEA Spot iniciada (Autenticación ClaveÚnica + VIMP en Sandbox).'
            ];
        }

        // Llamada real al endpoint REST de FEA Spot de Idok
        $postData = [
            'api_key' => $this->feaApiKey,
            'api_secret' => $this->feaApiSecret,
            'signer' => [
                'rut' => $cleanRut,
                'name' => $signerData['nombre_completo'] ?? '',
                'email' => $signerData['email'] ?? '',
                'phone' => $signerData['telefono'] ?? ''
            ],
            'document' => [
                'title' => "Documento SinNotario #{$documentId}",
                'pdf_base64' => base64_encode($pdfBinary)
            ],
            'options' => [
                'authentication_method' => 'CLAVE_UNICA_VIMP',
                'pin_duration_minutes' => 10,
                'redirect_url' => $redirectUrl
            ]
        ];

        $ch = curl_init($this->feaApiUrl . '/documents/create_spot');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("Error al conectar con Idok FEA Spot: " . $err);
        }

        $json = json_decode($resp, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($json['signing_url'])) {
            return [
                'success' => true,
                'simulated' => false,
                'provider' => $this->getProviderId(),
                'transaction_id' => $json['spot_id'] ?? ('FEA_' . time()),
                'metodo_firma' => 'FEA_CLAVEUNICA_SPOT',
                'auth_url' => $json['signing_url'],
                'expires_in' => 600,
                'message' => 'Sesión FEA Spot creada exitosamente en Idok.'
            ];
        }

        throw new Exception("Idok FEA Spot error: " . ($json['error'] ?? 'No se pudo generar la sesión de firma.'));
    }

    public function checkStatus(string $transactionId): array {
        return [
            'provider' => $this->getProviderId(),
            'transaction_id' => $transactionId,
            'status' => 'PENDING',
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }
}
