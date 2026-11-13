<?php
/**
 * Interfaz Unificada de Proveedores DTE (Facturación y Boleta Electrónica SII)
 * SinNotario.cl - Keystone Advisors SpA
 * Solución: SOL-0248 | Tarjeta: SN-104
 * 
 * Permite conmutar en caliente entre LibreDTE, OpenFactura (Haulmer) o Mock/Simulación
 * para la emisión de DTE 39 (Boleta Electrónica) y DTE 33 (Factura Electrónica Afecta).
 */

namespace Services\Dte;

interface DteProviderInterface {
    /**
     * Identificador único del proveedor (ej. 'libredte', 'openfactura', 'mock')
     */
    public function getProviderId(): string;

    /**
     * Nombre amigable para interfaz de administración
     */
    public function getProviderName(): string;

    /**
     * Verifica si el proveedor cuenta con credenciales activas y habilitadas
     */
    public function isEnabled(): bool;

    /**
     * Indica si opera en ambiente Sandbox / Certificación o Producción
     */
    public function isSandbox(): bool;

    /**
     * Ejecuta una prueba de conectividad y autenticación con la API del proveedor
     * 
     * @return array [success => bool, message => string, latency_ms => int]
     */
    public function testConexion(): array;

    /**
     * Emite una Boleta Electrónica (DTE 39)
     * 
     * @param array $receptor Datos del receptor (nombre, email, rut opcional)
     * @param array $items Detalle de ítems [{nombre, cantidad, precio, afecto}]
     * @param int $montoTotal Monto total bruto en CLP (incluye IVA si es afecto)
     * @param array $extra Parámetros adicionales (external_reference, order_id, etc.)
     * @return array [success => bool, folio => int|string, pdf_url => string, dte_xml_url => string, raw_response => array, error => string|null]
     */
    public function emitirBoleta(array $receptor, array $items, int $montoTotal, array $extra = []): array;

    /**
     * Emite una Factura Electrónica Afecta a IVA (DTE 33)
     * 
     * @param array $receptor Datos tributarios empresa (rut, razon_social, giro, direccion, comuna, ciudad, email)
     * @param array $items Detalle de ítems [{nombre, cantidad, precio_neto, precio_total, afecto}]
     * @param int $montoNeto Monto neto en CLP
     * @param int $montoIva Monto de IVA (19%) en CLP
     * @param int $montoTotal Monto total bruto en CLP
     * @param array $extra Parámetros adicionales (order_id, etc.)
     * @return array [success => bool, folio => int|string, pdf_url => string, dte_xml_url => string, raw_response => array, error => string|null]
     */
    public function emitirFactura(array $receptor, array $items, int $montoNeto, int $montoIva, int $montoTotal, array $extra = []): array;

    /**
     * Consulta el estado de emisión o reintenta la obtención del PDF/XML de un folio
     */
    public function consultarEstado(int $tipoDte, $folio): array;
}
