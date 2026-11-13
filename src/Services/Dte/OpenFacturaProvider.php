<?php
/**
 * Adaptador de Facturación Electrónica OpenFactura (Haulmer API REST v2)
 * SinNotario.cl - Keystone Advisors SpA
 * Solución: SOL-0248 | Tarjeta: SN-104
 * 
 * Documentación oficial: https://www.openfactura.cl/factura-electronica/api/
 */

namespace Services\Dte;

use Database;
use PDO;

class OpenFacturaProvider implements DteProviderInterface {
    private string $apiUrl;
    private string $apiKey;
    private bool $isSandbox;

    public function __construct() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT key, value FROM system_settings WHERE key LIKE 'dte_openfactura_%'");
        $settings = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $this->isSandbox = ($settings['dte_openfactura_sandbox'] ?? '1') === '1';
        $this->apiUrl = $this->isSandbox 
            ? 'https://dev-api.haulmer.com/v2' 
            : 'https://api.haulmer.com/v2';
        
        $this->apiKey = $settings['dte_openfactura_apikey'] ?? '';
    }

    public function getProviderId(): string {
        return 'openfactura';
    }

    public function getProviderName(): string {
        return 'OpenFactura (Haulmer)';
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
                'message' => 'OpenFactura no configurado: falta API Key en panel de administración',
                'latency_ms' => 0
            ];
        }

        $start = microtime(true);
        $res = $this->request('GET', '/dte/organization');
        $latency = (int)round((microtime(true) - $start) * 1000);

        if ($res['status'] === 200 && (isset($res['data']['RUTEmisor']) || isset($res['data']['RznSoc']))) {
            return [
                'success' => true,
                'message' => "Conexión exitosa con OpenFactura ({$res['data']['RznSoc']})",
                'latency_ms' => $latency
            ];
        }

        return [
            'success' => false,
            'message' => 'Error de autenticación o conexión: ' . ($res['data']['message'] ?? $res['raw']),
            'latency_ms' => $latency
        ];
    }

    public function emitirBoleta(array $receptor, array $items, int $montoTotal, array $extra = []): array {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'OpenFactura API Key no configurada'];
        }

        // Formato DTE 39 Boleta OpenFactura
        $payload = [
            'response' => ['PDF', 'XML'],
            'dte' => [
                'Encabezado' => [
                    'IdDoc' => [
                        'TipoDTE' => 39,
                        'Folio' => 0,
                        'FchEmis' => date('Y-m-d'),
                        'IndServicio' => 3
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
            ]
        ];

        $nro = 1;
        foreach ($items as $item) {
            $payload['dte']['Detalle'][] = [
                'NroLinDet' => $nro++,
                'NmbItem' => mb_substr($item['nombre'] ?? 'Trámite Notarial SinNotario', 0, 80),
                'QtyItem' => $item['cantidad'] ?? 1,
                'PrcItem' => $item['precio'] ?? $montoTotal,
                'MontoItem' => ($item['cantidad'] ?? 1) * ($item['precio'] ?? $montoTotal)
            ];
        }

        $res = $this->request('POST', '/dte/document', $payload);

        if ($res['status'] === 200 && !empty($res['data']['FOLIO'])) {
            $folio = $res['data']['FOLIO'];
            return [
                'success' => true,
                'provider' => 'openfactura',
                'tipo_dte' => 39,
                'tipo_dte_glosa' => 'Boleta Electrónica',
                'folio' => $folio,
                'pdf_url' => $res['data']['PDF'] ?? null,
                'dte_xml_url' => $res['data']['XML'] ?? null,
                'track_id' => $res['data']['TOKEN'] ?? null,
                'raw_response' => $res['data'],
                'error' => null
            ];
        }

        return [
            'success' => false,
            'provider' => 'openfactura',
            'error' => $res['data']['message'] ?? ('Fallo en emisión OpenFactura HTTP ' . $res['status']),
            'raw_response' => $res['data'] ?? $res['raw']
        ];
    }

    public function emitirFactura(array $receptor, array $items, int $montoNeto, int $montoIva, int $montoTotal, array $extra = []): array {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'OpenFactura API Key no configurada'];
        }

        $cleanRut = preg_replace('/[^0-9kK]/', '', $receptor['rut'] ?? '');
        if (strlen($cleanRut) > 1) {
            $cleanRut = substr($cleanRut, 0, -1) . '-' . substr($cleanRut, -1);
        }

        $payload = [
            'response' => ['PDF', 'XML'],
            'dte' => [
                'Encabezado' => [
                    'IdDoc' => [
                        'TipoDTE' => 33, // Factura Electrónica
                        'Folio' => 0,
                        'FchEmis' => date('Y-m-d'),
                        'FmaPago' => 1
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
            ]
        ];

        $nro = 1;
        foreach ($items as $item) {
            $itemNeto = $item['precio_neto'] ?? (int)round(($item['precio'] ?? $montoTotal) / 1.19);
            $qty = $item['cantidad'] ?? 1;
            $payload['dte']['Detalle'][] = [
                'NroLinDet' => $nro++,
                'NmbItem' => mb_substr($item['nombre'] ?? 'Servicio Notarial Digital', 0, 80),
                'QtyItem' => $qty,
                'PrcItem' => $itemNeto,
                'MontoItem' => $qty * $itemNeto
            ];
        }

        $res = $this->request('POST', '/dte/document', $payload);

        if ($res['status'] === 200 && !empty($res['data']['FOLIO'])) {
            $folio = $res['data']['FOLIO'];
            return [
                'success' => true,
                'provider' => 'openfactura',
                'tipo_dte' => 33,
                'tipo_dte_glosa' => 'Factura Electrónica Afecta',
                'folio' => $folio,
                'pdf_url' => $res['data']['PDF'] ?? null,
                'dte_xml_url' => $res['data']['XML'] ?? null,
                'track_id' => $res['data']['TOKEN'] ?? null,
                'raw_response' => $res['data'],
                'error' => null
            ];
        }

        return [
            'success' => false,
            'provider' => 'openfactura',
            'error' => $res['data']['message'] ?? ('Fallo en emisión OpenFactura HTTP ' . $res['status']),
            'raw_response' => $res['data'] ?? $res['raw']
        ];
    }

    public function consultarEstado(int $tipoDte, $folio): array {
        $res = $this->request('GET', "/dte/document/{$tipoDte}/{$folio}");
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
            'apikey: ' . $this->apiKey
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
