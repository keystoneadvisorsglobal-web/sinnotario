<?php
/**
 * Proveedor de Simulación / Mock para Desarrollo, Pruebas y Fallback
 * SinNotario.cl - Keystone Advisors SpA
 * Solución: SOL-0248 | Tarjeta: SN-104
 */

namespace Services\Dte;

class MockDteProvider implements DteProviderInterface {
    public function getProviderId(): string {
        return 'mock';
    }

    public function getProviderName(): string {
        return 'Modo Simulación / Mock DTE (Sandbox Local)';
    }

    public function isEnabled(): bool {
        return true;
    }

    public function isSandbox(): bool {
        return true;
    }

    public function testConexion(): array {
        return [
            'success' => true,
            'message' => 'Simulador Mock DTE conectado y operativo (Respuesta sintética 200 OK)',
            'latency_ms' => 12
        ];
    }

    public function emitirBoleta(array $receptor, array $items, int $montoTotal, array $extra = []): array {
        $simulatedFolio = rand(100000, 999999);
        $orderId = $extra['order_id'] ?? rand(1000, 9999);

        return [
            'success' => true,
            'provider' => 'mock',
            'tipo_dte' => 39,
            'tipo_dte_glosa' => 'Boleta Electrónica',
            'folio' => $simulatedFolio,
            'pdf_url' => "/uploads/dte_simulated/boleta_{$simulatedFolio}_{$orderId}.pdf",
            'dte_xml_url' => "/uploads/dte_simulated/boleta_{$simulatedFolio}.xml",
            'track_id' => 'TRK-MOCK-' . time(),
            'raw_response' => [
                'status' => 'OK',
                'emisor' => 'Keystone Advisors SpA (77.892.450-K)',
                'receptor' => $receptor,
                'items' => $items,
                'total' => $montoTotal,
                'modo' => 'SIMULACION'
            ],
            'error' => null
        ];
    }

    public function emitirFactura(array $receptor, array $items, int $montoNeto, int $montoIva, int $montoTotal, array $extra = []): array {
        $simulatedFolio = rand(20000, 89999);
        $orderId = $extra['order_id'] ?? rand(1000, 9999);

        return [
            'success' => true,
            'provider' => 'mock',
            'tipo_dte' => 33,
            'tipo_dte_glosa' => 'Factura Electrónica Afecta',
            'folio' => $simulatedFolio,
            'pdf_url' => "/uploads/dte_simulated/factura_{$simulatedFolio}_{$orderId}.pdf",
            'dte_xml_url' => "/uploads/dte_simulated/factura_{$simulatedFolio}.xml",
            'track_id' => 'TRK-MOCK-FAC-' . time(),
            'raw_response' => [
                'status' => 'OK',
                'emisor' => 'Keystone Advisors SpA (77.892.450-K)',
                'receptor' => $receptor,
                'neto' => $montoNeto,
                'iva' => $montoIva,
                'total' => $montoTotal,
                'modo' => 'SIMULACION'
            ],
            'error' => null
        ];
    }

    public function consultarEstado(int $tipoDte, $folio): array {
        return [
            'success' => true,
            'folio' => $folio,
            'estado_sii' => 'DTE ACEPTADO (SIMULACIÓN)',
            'glosa' => 'Documento aceptado conforme por SII'
        ];
    }
}
