<?php
/**
 * Orquestador y Factory de Facturación Electrónica (DTE Manager)
 * SinNotario.cl - Keystone Advisors SpA
 * Solución: SOL-0248 | Tarjeta: SN-104
 * 
 * Gestiona el conmutador de proveedores (LibreDTE, OpenFactura o Mock),
 * cálculo tributario, persistencia atómica en payment_orders y fallback de resiliencia.
 */

namespace Services\Dte;

use Database;
use Exception;
use PDO;

require_once __DIR__ . '/DteProviderInterface.php';
require_once __DIR__ . '/MockDteProvider.php';
require_once __DIR__ . '/LibreDteProvider.php';
require_once __DIR__ . '/OpenFacturaProvider.php';
require_once __DIR__ . '/../../../database/Database.php';

class DteManager {
    /** @var DteProviderInterface[] */
    private array $providers = [];
    private string $activeProviderKey;

    public function __construct() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT value FROM system_settings WHERE key = 'dte_active_provider'");
        $val = $stmt ? $stmt->fetchColumn() : null;
        $this->activeProviderKey = $val ?: 'mock'; // Por defecto mock/seguro hasta configurar llaves

        // Registrar proveedores de la suite
        $this->registerProvider(new MockDteProvider());
        $this->registerProvider(new LibreDteProvider());
        $this->registerProvider(new OpenFacturaProvider());
    }

    public function registerProvider(DteProviderInterface $provider): void {
        $this->providers[$provider->getProviderId()] = $provider;
    }

    public function getActiveProvider(): DteProviderInterface {
        if (isset($this->providers[$this->activeProviderKey])) {
            $p = $this->providers[$this->activeProviderKey];
            if ($p->isEnabled()) {
                return $p;
            }
        }
        // Fallback seguro a Mock si el proveedor seleccionado no está configurado
        return $this->providers['mock'];
    }

    public function getProvider(string $key): ?DteProviderInterface {
        return $this->providers[$key] ?? null;
    }

    /**
     * Retorna el estado consolidado de los proveedores para el panel de administración
     */
    public function getProvidersStatus(): array {
        $list = [];
        foreach ($this->providers as $key => $p) {
            $list[$key] = [
                'id' => $p->getProviderId(),
                'name' => $p->getProviderName(),
                'enabled' => $p->isEnabled(),
                'is_sandbox' => $p->isSandbox(),
                'is_active' => ($key === $this->activeProviderKey)
            ];
        }
        return $list;
    }

    /**
     * Valida matemáticamente un RUT chileno usando Módulo 11
     */
    public static function validarRut(string $rut): bool {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        if (strlen($rut) < 2) return false;

        $dv = strtoupper(substr($rut, -1));
        $numero = substr($rut, 0, -1);

        $i = 2;
        $suma = 0;
        foreach (array_reverse(str_split($numero)) as $v) {
            if ($i == 8) $i = 2;
            $suma += (int)$v * $i;
            $i++;
        }

        $dvr = 11 - ($suma % 11);
        if ($dvr == 11) $dvr = '0';
        elseif ($dvr == 10) $dvr = 'K';
        else $dvr = (string)$dvr;

        return ($dvr === $dv);
    }

    /**
     * Procesa y emite el documento tributario correspondiente a una orden de pago confirmada
     * 
     * @param int $orderId ID de la orden en tabla payment_orders
     * @return array [success => bool, folio => string, pdf_url => string, provider => string]
     */
    public function emitirComprobanteOrden(int $orderId): array {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM payment_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['success' => false, 'error' => "Orden {$orderId} no encontrada"];
        }

        // Si ya fue emitido con anterioridad, retornar los datos existentes
        if ($order['dte_status'] === 'EMITTED' && !empty($order['dte_folio'])) {
            return [
                'success' => true,
                'already_emitted' => true,
                'folio' => $order['dte_folio'],
                'pdf_url' => $order['dte_pdf_url'],
                'provider' => $order['dte_provider']
            ];
        }

        $docType = $order['billing_doc_type'] ?: 'boleta';
        $montoTotal = (int)$order['monto_total'];
        $montoNeto = (int)($order['monto_neto'] ?: round($montoTotal / 1.19));
        $montoIva = $montoTotal - $montoNeto;

        $items = [
            [
                'nombre' => !empty($order['pack_key']) ? "Pack Firmas {$order['pack_key']}" : "Trámite Notarial Digital (Doc #{$order['document_id']})",
                'cantidad' => 1,
                'precio_neto' => $montoNeto,
                'precio' => $montoTotal,
                'afecto' => true
            ]
        ];

        $provider = $this->getActiveProvider();
        $result = [];

        try {
            if ($docType === 'factura') {
                $receptor = [
                    'rut' => $order['billing_rut'],
                    'razon_social' => $order['billing_razon_social'],
                    'giro' => $order['billing_giro'] ?: 'Servicios',
                    'direccion' => $order['billing_direccion'] ?: 'Santiago',
                    'comuna' => $order['billing_comuna'] ?: 'Santiago',
                    'ciudad' => $order['billing_ciudad'] ?: 'Santiago',
                    'email' => $order['billing_email']
                ];
                $result = $provider->emitirFactura($receptor, $items, $montoNeto, $montoIva, $montoTotal, ['order_id' => $orderId]);
            } else {
                $receptor = [
                    'nombre' => $order['billing_razon_social'] ?: 'Cliente Final',
                    'email' => $order['billing_email'],
                    'rut' => $order['billing_rut']
                ];
                $result = $provider->emitirBoleta($receptor, $items, $montoTotal, ['order_id' => $orderId]);
            }

            if (!empty($result['success'])) {
                $upd = $pdo->prepare("UPDATE payment_orders SET 
                    dte_provider = ?,
                    dte_tipo = ?,
                    dte_folio = ?,
                    dte_pdf_url = ?,
                    dte_status = 'EMITTED',
                    dte_emitted_at = datetime('now'),
                    updated_at = datetime('now')
                    WHERE id = ?");
                $upd->execute([
                    $result['provider'] ?? $provider->getProviderId(),
                    $result['tipo_dte'] ?? ($docType === 'factura' ? 33 : 39),
                    $result['folio'],
                    $result['pdf_url'],
                    $orderId
                ]);

                return $result;
            } else {
                // Registrar intento fallido como PENDING_RETRY para no quebrar el flujo de negocio
                $upd = $pdo->prepare("UPDATE payment_orders SET 
                    dte_provider = ?,
                    dte_status = 'PENDING_RETRY',
                    updated_at = datetime('now')
                    WHERE id = ?");
                $upd->execute([$provider->getProviderId(), $orderId]);

                error_log("DteManager Error Orden {$orderId}: " . ($result['error'] ?? 'Error desconocido'));
                return $result;
            }
        } catch (Exception $e) {
            error_log("DteManager Excepción Orden {$orderId}: " . $e->getMessage());
            $upd = $pdo->prepare("UPDATE payment_orders SET dte_status = 'PENDING_RETRY' WHERE id = ?");
            $upd->execute([$orderId]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider->getProviderId()
            ];
        }
    }
}
