<?php
/**
 * Gestor y Orquestador de Proveedores de Firma Electrónica (Signing Manager)
 * SinNotario.cl - Keystone Advisors SpA
 * 
 * Permite cambiar de proveedor, enrutar por tipo de documento (FEA vs FES),
 * o contar con múltiples integraciones simultáneas según la configuración de panel admin.
 */

namespace Services\Signing;

use Database;
use Exception;
use PDO;

require_once __DIR__ . '/SigningProviderInterface.php';
require_once __DIR__ . '/IdokFirmaYaProvider.php';
require_once __DIR__ . '/KeystoneVaultProvider.php';
require_once __DIR__ . '/../../../database/Database.php';

class SigningManager {
    /** @var SigningProviderInterface[] */
    private array $providers = [];
    private string $activeProviderKey;

    public function __construct() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT value FROM system_settings WHERE key = 'signing_provider_default'");
        $val = $stmt ? $stmt->fetchColumn() : null;
        $this->activeProviderKey = $val ?: 'idok_firmaya';

        // Registrar proveedores disponibles
        $this->registerProvider(new IdokFirmaYaProvider());
        $this->registerProvider(new KeystoneVaultProvider());
    }

    public function registerProvider(SigningProviderInterface $provider): void {
        $this->providers[$provider->getProviderId()] = $provider;
    }

    public function getActiveProvider(): SigningProviderInterface {
        if (isset($this->providers[$this->activeProviderKey])) {
            $p = $this->providers[$this->activeProviderKey];
            if ($p->isEnabled()) {
                return $p;
            }
        }
        // Fallback resiliente a Keystone Vault nativo
        return $this->providers['keystone_vault'];
    }

    public function getProvider(string $key): ?SigningProviderInterface {
        return $this->providers[$key] ?? null;
    }

    /**
     * Retorna todos los proveedores registrados y su estado actual
     */
    public function getAvailableProviders(): array {
        $list = [];
        foreach ($this->providers as $key => $p) {
            $list[$key] = [
                'id' => $p->getProviderId(),
                'name' => $p->getProviderName(),
                'enabled' => $p->isEnabled(),
                'is_sandbox' => $p->isSandbox(),
                'is_active_default' => ($key === $this->activeProviderKey)
            ];
        }
        return $list;
    }

    /**
     * Enruta la firma de un compareciente según la modalidad solicitada (FEA o FES)
     */
    public function processSigner(int $documentId, array $signerData, string $pdfBinary, string $tipoFirma = 'FES', array $options = []): array {
        $provider = $this->getActiveProvider();

        if (strtoupper($tipoFirma) === 'FEA') {
            return $provider->initiateFEASpot($documentId, $signerData, $pdfBinary, $options);
        } else {
            return $provider->signFES($documentId, $signerData, $pdfBinary);
        }
    }
}
