<?php
/**
 * Adaptador de Facturación Electrónica LibreDTE (API REST)
 * SinNotario.cl - Keystone Advisors SpA
 * Solución: SOL-0248 | Tarjeta: SN-104
 * 
 * Documentación oficial: https://www.libredte.cl/docs/api
 */

namespace Services\Dte;

use Database;
use PDO;

class LibreDteProvider implements DteProviderInterface {
    private string $apiUrl;
    private string $apiKey;
    private string $rutEmisor;
    private bool $isSandbox;

    public function __construct() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT key, value FROM system_settings WHERE key LIKE 'dte_libredte_%' OR key = 'dte_emisor_rut'");
        $settings = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $this->isSandbox = ($settings['dte_libredte_sandbox'] ?? '1') === '1';
        $this->apiUrl = $this->isSandbox 
            ? 'https://sandbox.libredte.cl/api' 
            : 'https://libredte.cl/api';
        
        $this->apiKey = $settings['dte_libredte_apikey'] ?? '';
        $this->rutEmisor = $settings['dte_emisor_rut'] ?? '77892450-K'; // Keystone Advisors SpA
    }

    public function getProviderId(): string {
        return 'libredte';
    }

    public function getProviderName(): string {
        return 'LibreDTE (API Open Source / Nube)';
    }

    public function isEnabled(): bool {
        return !empty($this->apiKey);
    }

    public function isSandbox(): bool {
        return $this->isSandbox;
    }

    public function testConexion(): array {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'LibreDTE no configurado: falta API Key en panel de administración',
                'latency_ms' => 0
            ];
        }

        $start = microtime(true);
        $res = $this->request('GET', '/usuarios/datos');
        $latency = (int)round((microtime(true) - $start) * 1000);

        if ($res['status'] === 200 && isset($res['data']['rut'])) {
            return [
                'success' => true,
                'message' => "Conexión exitosa con LibreDTE ({$res['data']['nombre']})",
                'latency_ms' => $latency
            ];
        }

        return [
            'success' => false,
            'message' => 'Error de autenticación o conexión: ' . ($res['data']['error'] ?? $res['raw']),
            'latency_ms' => $latency
        ];
    }

    public function emitirBoleta(array $receptor, array $items, int $montoTotal, array $extra = []): array {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'LibreDTE API Key no configurada'];
        }

        // Formato DTE 39 Boleta Electrónica LibreDTE
        $payload = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 39,
                    'Folio' => 0, // 0 = Asignación automática por CAF cargado en LibreDTE
                    'FchEmis' => date('Y-m-d'),
                    'IndServicio' => 3 // Servicios periódicos o trámite único
                ],
                'Emisor' => [
                    'RUTEmisor' => $this->rutEmisor
                ],
                'Receptor' => [
                    'RUTRecep' => !empty($receptor['rut']) ? preg_replace('/[^0-9kK]/', '', $receptor['rut']) : '66666666-6',
                    'RznSocRecep' => mb_substr($receptor['nombre'] ?? 'Cliente Final', 0, 40),
                    'Contacto' => mb_substr($receptor['email'] ?? '', 0, 80)
                ],
                'Totales' => [
                    'MntTotal' => $montoTotal
                ]
            ],
            'Detalle' => []
        ];

        $nro = 1;
        foreach ($items as $item) {
            $payload['Detalle'][] = [
                'NroLinDet' => $nro++,
                'NmbItem' => mb_substr($item['nombre'] ?? 'Trámite Notarial SinNotario', 0, 80),
                'QtyItem' => $item['cantidad'] ?? 1,
                'PrcItem' => $item['precio'] ?? $montoTotal,
                'MontoItem' => ($item['cantidad'] ?? 1) * ($item['precio'] ?? $montoTotal)
            ];
        }

        $res = $this->request('POST', '/dte/documentos/emitir', $payload);

        if ($res['status'] === 200 && !empty($res['data']['folio'])) {
            $folio = $res['data']['folio'];
            return [
                'success' => true,
                'provider' => 'libredte',
                'tipo_dte' => 39,
                'tipo_dte_glosa' => 'Boleta Electrónica',
                'folio' => $folio,
                'pdf_url' => $this->apiUrl . "/dte/documentos/generar_pdf/39/{$folio}?rut_emisor={$this->rutEmisor}",
                'dte_xml_url' => $this->apiUrl . "/dte/documentos/generar_xml/39/{$folio}?rut_emisor={$this->rutEmisor}",
                'track_id' => $res['data']['track_id'] ?? null,
                'raw_response' => $res['data'],
                'error' => null
            ];
        }

        return [
            'success' => false,
            'provider' => 'libredte',
            'error' => $res['data']['error'] ?? ('Fallo en emisión LibreDTE HTTP ' . $res['status']),
            'raw_response' => $res['data'] ?? $res['raw']
        ];
    }

    public function emitirFactura(array $receptor, array $items, int $montoNeto, int $montoIva, int $montoTotal, array $extra = []): array {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'LibreDTE API Key no configurada'];
        }

        $cleanRut = preg_replace('/[^0-9kK]/', '', $receptor['rut'] ?? '');
        if (strlen($cleanRut) > 1) {
            $cleanRut = substr($cleanRut, 0, -1) . '-' . substr($cleanRut, -1);
        }

        $payload = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 33, // Factura Electrónica Afecta
                    'Folio' => 0,
                    'FchEmis' => date('Y-m-d'),
                    'FmaPago' => 1 // Contado
                ],
                'Emisor' => [
                    'RUTEmisor' => $this->rutEmisor
                ],
                'Receptor' => [
                    'RUTRecep' => $cleanRut,
                    'RznSocRecep' => mb_substr($receptor['razon_social'] ?? '', 0, 100),
                    'GiroRecep' => mb_substr($receptor['giro'] ?? 'Servicios', 0, 40),
                    'DirRecep' => mb_substr($receptor['direccion'] ?? 'Santiago', 0, 70),
                    'CmnaRecep' => mb_substr($receptor['comuna'] ?? 'Santiago', 0, 20),
                    'CiudadRecep' => mb_substr($receptor['ciudad'] ?? 'Santiago', 0, 20),
                    'CorreoRecep' => mb_substr($receptor['email'] ?? '', 0, 80)
                ],
                'Totales' => [
                    'MntNeto' => $montoNeto,
                    'TasaIVA' => 19,
                    'IVA' => $montoIva,
                    'MntTotal' => $montoTotal
                ]
            ],
            'Detalle' => []
        ];

        $nro = 1;
        foreach ($items as $item) {
            $itemNeto = $item['precio_neto'] ?? (int)round(($item['precio'] ?? $montoTotal) / 1.19);
            $qty = $item['cantidad'] ?? 1;
            $payload['Detalle'][] = [
                'NroLinDet' => $nro++,
                'NmbItem' => mb_substr($item['nombre'] ?? 'Servicio Notarial Digital', 0, 80),
                'QtyItem' => $qty,
                'PrcItem' => $itemNeto,
                'MontoItem' => $qty * $itemNeto
            ];
        }

        $res = $this->request('POST', '/dte/documentos/emitir', $payload);

        if ($res['status'] === 200 && !empty($res['data']['folio'])) {
            $folio = $res['data']['folio'];
            return [
                'success' => true,
                'provider' => 'libredte',
                'tipo_dte' => 33,
                'tipo_dte_glosa' => 'Factura Electrónica Afecta',
                'folio' => $folio,
                'pdf_url' => $this->apiUrl . "/dte/documentos/generar_pdf/33/{$folio}?rut_emisor={$this->rutEmisor}",
                'dte_xml_url' => $this->apiUrl . "/dte/documentos/generar_xml/33/{$folio}?rut_emisor={$this->rutEmisor}",
                'track_id' => $res['data']['track_id'] ?? null,
                'raw_response' => $res['data'],
                'error' => null
            ];
        }

        return [
            'success' => false,
            'provider' => 'libredte',
            'error' => $res['data']['error'] ?? ('Fallo en emisión LibreDTE HTTP ' . $res['status']),
            'raw_response' => $res['data'] ?? $res['raw']
        ];
    }

    public function consultarEstado(int $tipoDte, $folio): array {
        $res = $this->request('GET', "/dte/documentos/consultar/{$tipoDte}/{$folio}?rut_emisor={$this->rutEmisor}");
        return [
            'success' => ($res['status'] === 200),
            'folio' => $folio,
            'raw' => $res['data'] ?? $res['raw']
        ];
    }

    private function request(string $method, string $endpoint, ?array $body = null): array {
        $url = $this->apiUrl . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                $payloadJson = json_encode($body);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
                $headers[] = 'Content-Type: application/json';
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($raw, true);

        return [
            'status' => $status,
            'data' => $data,
            'raw' => $raw
        ];
    }
}
