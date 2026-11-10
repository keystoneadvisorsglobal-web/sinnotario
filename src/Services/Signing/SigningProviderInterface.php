<?php
/**
 * Interfaz Unificada de Proveedores de Firma Electrónica
 * SinNotario.cl - Keystone Advisors SpA
 * 
 * Permite intercambiar proveedores (FirmaYa / Idok, DocuSign, Autentia, Keystone Vault, etc.)
 * o trabajar con múltiples en paralelo según el tipo de instrumento y cliente.
 */

namespace Services\Signing;

interface SigningProviderInterface {
    /**
     * Identificador único del proveedor (ej. 'idok_firmaya', 'keystone_internal', etc.)
     */
    public function getProviderId(): string;

    /**
     * Nombre amigable para interfaz de usuario
     */
    public function getProviderName(): string;

    /**
     * Indica si el proveedor está habilitado y configurado
     */
    public function isEnabled(): bool;

    /**
     * Indica si está en modo Sandbox o Producción
     */
    public function isSandbox(): bool;

    /**
     * Firma un documento con Firma Electrónica Simple (FES)
     * 
     * @param int $documentId ID del documento en SinNotario
     * @param array $signerData Datos del firmante (nombre, rut, email, telefono, etc.)
     * @param string $pdfBinary Contenido binario del archivo PDF
     * @return array Resultado con [success, signed_pdf, transaction_id, audit_trail]
     */
    public function signFES(int $documentId, array $signerData, string $pdfBinary): array;

    /**
     * Inicia o ejecuta la Firma Electrónica Avanzada Spot (FEA de un solo uso con ClaveÚnica / VIMP)
     * 
     * @param int $documentId ID del documento
     * @param array $signerData Datos del firmante
     * @param string $pdfBinary Contenido binario del archivo PDF
     * @param array $extraParams Parámetros adicionales (redirect_url, pos_x, pos_y, etc.)
     * @return array Resultado con [success, redirect_url, token, transaction_id]
     */
    public function initiateFEASpot(int $documentId, array $signerData, string $pdfBinary, array $extraParams = []): array;

    /**
     * Consulta el estado de una transacción de firma en el proveedor
     */
    public function checkStatus(string $transactionId): array;
}
