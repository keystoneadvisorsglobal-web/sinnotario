<?php
/**
 * Servicio de Control de Stock y Alerta de Abastecimiento de Firmas Electrónicas (FirmaYa / Idok)
 * SinNotario.cl - Keystone Advisors
 */
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/MailService.php';

class StockAlertService {

    /**
     * Obtiene el estado actual de las bolsas de firmas FES y FEA
     */
    public static function getStockStatus(): array {
        $fesTotal = (int)AppSettings::get('firmaya_stock_fes_total', 100);
        $fesConsumed = (int)AppSettings::get('firmaya_stock_fes_consumed', 0);
        $fesRemaining = max(0, $fesTotal - $fesConsumed);
        $fesPct = $fesTotal > 0 ? round(($fesRemaining / $fesTotal) * 100, 1) : 0;

        $feaTotal = (int)AppSettings::get('firmaya_stock_fea_total', 100);
        $feaConsumed = (int)AppSettings::get('firmaya_stock_fea_consumed', 0);
        $feaRemaining = max(0, $feaTotal - $feaConsumed);
        $feaPct = $feaTotal > 0 ? round(($feaRemaining / $feaTotal) * 100, 1) : 0;

        $threshold = (int)AppSettings::get('firmaya_alert_threshold', 30);

        return [
            'threshold' => $threshold,
            'fes' => [
                'name' => 'Firma Electrónica Simple (FES)',
                'total' => $fesTotal,
                'consumed' => $fesConsumed,
                'remaining' => $fesRemaining,
                'percent' => $fesPct,
                'is_alert' => ($fesPct <= $threshold) && ($fesTotal > 0)
            ],
            'fea' => [
                'name' => 'Firma Electrónica Avanzada Spot (FEA)',
                'total' => $feaTotal,
                'consumed' => $feaConsumed,
                'remaining' => $feaRemaining,
                'percent' => $feaPct,
                'is_alert' => ($feaPct <= $threshold) && ($feaTotal > 0)
            ]
        ];
    }

    /**
     * Consume 1 o más firmas del inventario y dispara alertas si llega al umbral
     */
    public static function consumeSignature(string $tipoFirma, int $quantity = 1, ?int $documentId = null): array {
        $tipo = strtoupper(trim($tipoFirma)) === 'FEA' ? 'fea' : 'fes';
        $keyConsumed = "firmaya_stock_{$tipo}_consumed";

        $currentConsumed = (int)AppSettings::get($keyConsumed, 0);
        $newConsumed = $currentConsumed + $quantity;
        AppSettings::set($keyConsumed, (string)$newConsumed);

        // Comprobar estado tras el consumo
        $status = self::getStockStatus();
        $stockItem = $status[$tipo];

        // Verificar si cruzó el umbral y disparar notificación si corresponde
        if ($stockItem['is_alert']) {
            self::triggerStockAlert($tipo, $stockItem, $status['threshold'], $documentId);
        }

        return [
            'success' => true,
            'tipo' => $tipo,
            'consumed' => $newConsumed,
            'remaining' => $stockItem['remaining'],
            'percent' => $stockItem['percent'],
            'alert_triggered' => $stockItem['is_alert']
        ];
    }

    /**
     * Dispara la alerta por WhatsApp y Correo al teléfono/contacto administrativo
     */
    public static function triggerStockAlert(string $tipo, array $stockItem, int $threshold, ?int $documentId = null): void {
        $lastAlertKey = "firmaya_last_alert_{$tipo}_at";
        $lastAlert = AppSettings::get($lastAlertKey, '');

        // Evitar inundar: notificar máximo una vez cada 6 horas para el mismo tipo salvo que queden 0
        if (!empty($lastAlert) && $stockItem['remaining'] > 0) {
            $lastTime = strtotime($lastAlert);
            if ($lastTime && (time() - $lastTime) < (6 * 3600)) {
                return;
            }
        }

        AppSettings::set($lastAlertKey, date('Y-m-d H:i:s'));

        $destPhone = AppSettings::getWhatsAppPhone(true);
        $destEmail = AppSettings::get('admin_alert_email', 'contacto@keystoneadvisors.cl');
        $nombreFirma = $stockItem['name'];
        $rem = $stockItem['remaining'];
        $pct = $stockItem['percent'];
        $tot = $stockItem['total'];

        $mensajeWa = "🚨 *ALERTA DE STOCK DE FIRMAS - SINNOTARIO.CL*\n\n"
                   . "Se ha alcanzado el umbral crítico de abastecimiento:\n\n"
                   . "• *Tipo de Firma:* {$nombreFirma}\n"
                   . "• *Firmas Restantes:* {$rem} de {$tot}\n"
                   . "• *Stock Remanente:* {$pct}% (Umbral: {$threshold}%)\n\n"
                   . "⚠️ *Recomendación:* Se sugiere gestionar la recarga del siguiente paquete con el ejecutivo de FirmaYa / Idok para asegurar continuidad operativa.\n\n"
                   . "Panel Administrativo: https://sinnotario.cl/admin.php#integraciones";

        // Despacho por WhatsApp Gateway
        try {
            $waService = new WhatsAppService();
            $waService->sendTextMessage(
                $destPhone,
                $mensajeWa,
                'keystone-general',
                $documentId,
                'Administrador SinNotario',
                'ALERTA_STOCK_FIRMAS'
            );
        } catch (\Throwable $eWa) {
            error_log("Error enviando alerta stock WhatsApp: " . $eWa->getMessage());
        }

        // Despacho por Correo Electrónico
        try {
            $subject = "🚨 Alerta Crítica: Stock de {$nombreFirma} al {$pct}% en SinNotario.cl";
            $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333333; line-height: 1.5;'>
                    <div style='background: #0F2A4A; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;'>
                        <h2 style='color: #FFFFFF; margin: 0;'>SinNotario.cl — Alerta de Abastecimiento</h2>
                    </div>
                    <div style='background: #FFFFFF; padding: 24px; border: 1px solid #E2E8F0; border-top: none; border-radius: 0 0 8px 8px;'>
                        <p style='font-size: 16px; color: #DC2626; font-weight: bold;'>
                            ⚠️ El stock de firmas ha descendido por debajo del {$threshold}% del paquete activo.
                        </p>
                        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                            <tr style='background: #F8FAFC;'>
                                <td style='padding: 10px; border: 1px solid #CBD5E1; font-weight: bold;'>Paquete:</td>
                                <td style='padding: 10px; border: 1px solid #CBD5E1;'>{$nombreFirma}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #CBD5E1; font-weight: bold;'>Firmas Restantes:</td>
                                <td style='padding: 10px; border: 1px solid #CBD5E1; color: #DC2626; font-weight: bold;'>{$rem} firmas</td>
                            </tr>
                            <tr style='background: #F8FAFC;'>
                                <td style='padding: 10px; border: 1px solid #CBD5E1; font-weight: bold;'>Total Contratado:</td>
                                <td style='padding: 10px; border: 1px solid #CBD5E1;'>{$tot} firmas</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #CBD5E1; font-weight: bold;'>Porcentaje Disponible:</td>
                                <td style='padding: 10px; border: 1px solid #CBD5E1; font-weight: bold;'>{$pct}%</td>
                            </tr>
                        </table>
                        <p style='color: #475569;'>
                            Para evitar rechazos en el proceso de checkout y firma de clientes, por favor gestiona la compra del siguiente tramo con Idok.
                        </p>
                        <div style='text-align: center; margin-top: 24px;'>
                            <a href='https://sinnotario.cl/admin.php#integraciones' style='background: #0F2A4A; color: #FFFFFF; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;'>
                                Ver Telemetría en Admin Console
                            </a>
                        </div>
                    </div>
                </div>
            ";
            MailService::sendEmail($destEmail, $subject, $htmlBody);
        } catch (\Throwable $eMail) {
            error_log("Error enviando alerta stock Correo: " . $eMail->getMessage());
        }
    }
}
