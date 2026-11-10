<?php
/**
 * SinNotario.cl - Portal Oficial de Firma y Gestión de Comparecientes
 * Despacho, firma electrónica con ClaveÚnica / OTP y distribución de enlaces.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/database/Database.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : (isset($_GET['doc']) ? trim($_GET['doc']) : '');
$externalRef = isset($_GET['external_ref']) ? trim($_GET['external_ref']) : '';
$orderIdParam = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$collectionIdParam = isset($_GET['collection_id']) ? trim($_GET['collection_id']) : (isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '');
$paymentStatus = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : (isset($_GET['collection_status']) ? trim($_GET['collection_status']) : '');
if ($paymentStatus === 'approved') {
    $paymentStatus = 'success';
}

// 1. Sincronización y Conciliación Inmediata de Pago
if ($externalRef || $orderIdParam || $collectionIdParam) {
    try {
        require_once __DIR__ . '/src/Services/MercadoPagoService.php';
        $mpSync = new MercadoPagoService();
        $payIdToProcess = $collectionIdParam ?: ($orderIdParam ?: null);

        if ($orderIdParam) {
            $orderData = $mpSync->getOrder($orderIdParam);
            if (!empty($orderData['success']) && in_array($orderData['data']['status'] ?? '', ['processed', 'approved'])) {
                $extRefOrder = $orderData['data']['external_reference'] ?? $externalRef;
                if ($extRefOrder) {
                    MercadoPagoService::processApprovedPayment($extRefOrder, $payIdToProcess, $orderData['data']);
                    $paymentStatus = 'success';
                }
            }
        } elseif ($collectionIdParam) {
            $payInfo = $mpSync->getPayment((int)$collectionIdParam);
            if (!empty($payInfo['status']) && $payInfo['status'] === 'approved') {
                $extRefPay = $payInfo['external_reference'] ?? $externalRef;
                if ($extRefPay) {
                    MercadoPagoService::processApprovedPayment($extRefPay, (string)$collectionIdParam, $payInfo);
                    $paymentStatus = 'success';
                }
            }
        } elseif ($externalRef && $paymentStatus === 'success') {
            MercadoPagoService::processApprovedPayment($externalRef, $payIdToProcess);
        }
    } catch (\Throwable $e) {
        error_log("Error en sincronización backup de firmar.php: " . $e->getMessage());
    }
}

// 2. Cargar Documento y Comparecientes
$pdo = Database::getInstance();
$document = null;
$signers = [];

if (!empty($code)) {
    $stmtDoc = $pdo->prepare("
        SELECT d.*, t.nombre as template_nombre, t.categoria
        FROM documents d
        LEFT JOIN templates t ON d.template_id = t.id
        WHERE d.codigo_verificacion = ?
    ");
    $stmtDoc->execute([$code]);
    $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);

    if ($document) {
        $stmtSig = $pdo->prepare("
            SELECT * FROM document_signers 
            WHERE document_id = ? 
            ORDER BY id ASC
        ");
        $stmtSig->execute([$document['id']]);
        $signers = $stmtSig->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif (!empty($externalRef)) {
    $stmtOrder = $pdo->prepare("SELECT document_id FROM payment_orders WHERE external_reference = ?");
    $stmtOrder->execute([$externalRef]);
    $docId = $stmtOrder->fetchColumn();
    if ($docId) {
        $stmtDoc = $pdo->prepare("
            SELECT d.*, t.nombre as template_nombre, t.categoria
            FROM documents d
            LEFT JOIN templates t ON d.template_id = t.id
            WHERE d.id = ?
        ");
        $stmtDoc->execute([$docId]);
        $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if ($document) {
            $code = $document['codigo_verificacion'];
            $stmtSig = $pdo->prepare("SELECT * FROM document_signers WHERE document_id = ? ORDER BY id ASC");
            $stmtSig->execute([$document['id']]);
            $signers = $stmtSig->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($document['titulo']) ? htmlspecialchars($document['titulo']) . ' — Firma Digital | SinNotario.cl' : 'Firma Electrónica | SinNotario.cl' ?></title>
    
    <!-- Metadatos OpenGraph para Previsualización en WhatsApp / Redes Sociales -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SinNotario.cl">
    <meta property="og:title" content="<?= !empty($document['titulo']) ? '✍️ Firma Digital: ' . htmlspecialchars($document['titulo']) : '✍️ Firma Electrónica Segura | SinNotario.cl' ?>">
    <meta property="og:description" content="Trámite oficial bajo la Ley N° 19.799. Ingresa de forma segura para estampar tu firma electrónica o revisar el documento con validez legal.">
    <meta property="og:image" content="https://sinnotario.cl/assets/logos/sinnotario-og-banner.png">
    <meta property="og:image:secure_url" content="https://sinnotario.cl/assets/logos/sinnotario-og-banner.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="SinNotario.cl — Tu Firma Electrónica con Respaldo Criptográfico">
    <meta property="og:url" content="https://sinnotario.cl/firmar.php?code=<?= htmlspecialchars($code) ?>">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= !empty($document['titulo']) ? '✍️ Firma Digital: ' . htmlspecialchars($document['titulo']) : '✍️ Firma Electrónica | SinNotario.cl' ?>">
    <meta name="twitter:description" content="Trámite oficial bajo la Ley N° 19.799. Firma electrónica con plena validez legal.">
    <meta name="twitter:image" content="https://sinnotario.cl/assets/logos/sinnotario-og-banner.png">

    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20261110_2">
    <style>
        :root {
            --firm-navy: #0F2A4A;
            --firm-gold: #D4AF37;
            --firm-green: #059669;
            --firm-light: #F8FAFC;
        }
        body {
            background-color: #F1F5F9;
            color: #1E293B;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .firm-header {
            background: #FFFFFF;
            border-bottom: 1px solid #E2E8F0;
            padding: 0.85rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .firm-header img {
            height: 72px;
            width: auto;
        }
        .firm-container {
            max-width: 920px;
            margin: 2.5rem auto 4rem;
            padding: 0 1.5rem;
        }
        .firm-card {
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            padding: 2.25rem;
            margin-bottom: 2rem;
        }
        .badge-success-banner {
            background: #ECFDF5;
            border: 2px solid #10B981;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .badge-icon {
            background: #10B981;
            color: #FFFFFF;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .signer-row {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            transition: all 0.2s ease;
        }
        .signer-row:hover {
            border-color: #CBD5E1;
            background: #FFFFFF;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .signer-info h4 {
            margin: 0 0 0.25rem;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--firm-navy);
        }
        .signer-info p {
            margin: 0;
            font-size: 0.85rem;
            color: #64748B;
        }
        .btn-sign-now {
            background: linear-gradient(135deg, #0F2A4A 0%, #1E3A8A 100%);
            color: #FFFFFF !important;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(15,42,74,0.2);
            transition: all 0.2s;
        }
        .btn-sign-now:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(15,42,74,0.3);
        }
        .btn-whatsapp-share {
            background: #25D366;
            color: #FFFFFF !important;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #EFF6FF;
            color: #1D4ED8;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>

    <!-- Header Corporativo -->
    <header class="firm-header">
        <a href="index.php">
            <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl">
        </a>
        <div style="font-size: 0.85rem; color: #64748B; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-shield-alt" style="color: var(--firm-green);"></i>
            Firma Digital Ley N° 19.799 &bull; Infraestructura Segura
        </div>
    </header>

    <div class="firm-container">
        <?php if (!$document): ?>
            <div class="firm-card" style="text-align: center; padding: 3rem 1.5rem;">
                <i class="fas fa-file-excel fa-3x" style="color: #EF4444; margin-bottom: 1rem;"></i>
                <h2 style="color: var(--firm-navy); margin-bottom: 0.5rem;">Documento no Encontrado</h2>
                <p style="color: #64748B; max-width: 500px; margin: 0 auto 1.5rem;">
                    No pudimos localizar un documento asociado al código o referencia ingresada. Revisa el enlace recibido por WhatsApp o correo.
                </p>
                <a href="index.php" class="btn-sign-now">
                    <i class="fas fa-home"></i> Ir a la Portada
                </a>
            </div>
        <?php else: ?>

            <!-- Banner de Pago Confirmado -->
            <?php if ($paymentStatus === 'success' || $document['estado_pago'] === 'PAGADO'): ?>
                <div class="badge-success-banner">
                    <div class="badge-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 4px; color: #065F46; font-size: 1.15rem; font-weight: 800;">
                            ¡Pago Acreditado Exitosamente!
                        </h3>
                        <p style="margin: 0; color: #047857; font-size: 0.88rem; line-height: 1.4;">
                            Tu trámite está confirmado. Cada compareciente debe proceder con la firma electrónica conforme a la Ley N° 19.799.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tarjeta Principal del Documento -->
            <div class="firm-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid #E2E8F0; padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <div class="meta-pill">
                                <i class="fas fa-file-contract"></i> Folio Oficial: <?= htmlspecialchars($document['codigo_verificacion']) ?>
                            </div>
                            <?php if (($document['tipo_firma'] ?? 'FES') === 'FEA'): ?>
                                <span style="background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; font-size:0.75rem; font-weight:800; padding:4px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                                    <i class="fas fa-award" style="color:#D97706;"></i> Firma Electrónica Avanzada (FEA Ley 19.799)
                                </span>
                            <?php else: ?>
                                <span style="background:#DCFCE7; color:#166534; border:1px solid #BBF7D0; font-size:0.75rem; font-weight:800; padding:4px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                                    <i class="fas fa-check-circle" style="color:#16A34A;"></i> Firma Electrónica Simple (FES)
                                </span>
                            <?php endif; ?>
                        </div>
                        <h2 style="margin: 0; color: var(--firm-navy); font-size: 1.4rem; font-weight: 900;">
                            <?= htmlspecialchars($document['titulo'] ?: $document['template_nombre']) ?>
                        </h2>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; background: #FEF3C7; color: #92400E; padding: 4px 10px; border-radius: 9999px;">
                            <i class="fas fa-clock"></i> <?= htmlspecialchars($document['estado']) ?>
                        </span>
                        <div style="font-size: 0.78rem; color: #64748B; margin-top: 4px;">
                            Emitido: <?= date('d/m/Y H:i', strtotime($document['created_at'])) ?>
                        </div>
                    </div>
                </div>

                <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--firm-navy); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-users" style="color: var(--firm-gold);"></i> Comparecientes y Firmas Requeridas
                </h3>

                <!-- Lista de Firmantes -->
                <div class="signers-list">
                    <?php foreach ($signers as $idx => $s): ?>
                        <?php
                        $isSigned = ($s['estado_firma'] === 'FIRMADO');
                        $signerToken = $s['token_acceso'] ?? '';
                        $signDirectUrl = "firmar.php?code={$document['codigo_verificacion']}&token={$signerToken}";
                        $wsShareUrl = "https://wa.me/" . preg_replace('/\D/', '', $s['telefono']) . "?text=" . urlencode("Hola {$s['nombre_completo']}, en SinNotario.cl el pago de tu trámite '{$document['titulo']}' está confirmado. Puedes firmar electrónicamente aquí: https://sinnotario.cl/{$signDirectUrl}");
                        ?>
                        <div class="signer-row">
                            <div class="signer-info">
                                <h4><?= htmlspecialchars($s['nombre_completo']) ?></h4>
                                <p>
                                    <strong><?= htmlspecialchars($s['tipo_documento'] ?? 'RUT') ?>:</strong> <?= htmlspecialchars($s['rut'] ?: $s['numero_identificacion']) ?> &bull; 
                                    <strong>Rol:</strong> <?= htmlspecialchars($s['rol_firmante']) ?>
                                    <?php if (!empty($s['email'])): ?> &bull; <?= htmlspecialchars($s['email']) ?><?php endif; ?>
                                    <?php if (!empty($s['telefono'])): ?> &bull; <?= htmlspecialchars($s['telefono']) ?><?php endif; ?>
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <?php if ($isSigned): ?>
                                    <span style="background: #DEF7EC; color: #03543F; font-weight: 800; font-size: 0.8rem; padding: 6px 12px; border-radius: 6px;">
                                        <i class="fas fa-check-circle"></i> FIRMADO
                                    </span>
                                <?php else: ?>
                                    <!-- Botón Firmar para el compareciente actual -->
                                    <button type="button" class="btn-sign-now" onclick="openSignatureModal('<?= htmlspecialchars($s['id']) ?>', '<?= htmlspecialchars(addslashes($s['nombre_completo'])) ?>', '<?= htmlspecialchars($s['rut']) ?>')">
                                        <i class="fas fa-pen-fancy"></i> Firmar Ahora
                                    </button>

                                    <!-- Botón Enviar WhatsApp a este firmante si tiene teléfono -->
                                    <?php if (!empty($s['telefono'])): ?>
                                        <a href="<?= $wsShareUrl ?>" target="_blank" class="btn-whatsapp-share" title="Enviar enlace directo por WhatsApp">
                                            <i class="fab fa-whatsapp"></i> Compartir Link
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Aviso de Resguardo y Continuidad -->
                <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 12px; padding: 1.25rem 1.5rem; margin-top: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                    <i class="fas fa-info-circle fa-2x" style="color: #64748B; flex-shrink: 0;"></i>
                    <p style="margin: 0; font-size: 0.85rem; color: #475569; line-height: 1.5;">
                        <strong>¿Se te apagó el teléfono o deseas firmar más tarde?</strong><br>
                        No te preocupes. Hemos enviado automáticamente el enlace seguro y tu código de seguimiento a tu correo y WhatsApp para que tú o los demás comparecientes retomen el proceso en cualquier momento.
                    </p>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- Modal de Confirmación y Estampa de Firma -->
    <div id="modalSign" style="display: none; position: fixed; inset: 0; background: rgba(15,42,74,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px);">
        <div style="background: #FFFFFF; border-radius: 16px; max-width: 520px; width: 100%; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="margin: 0; color: var(--firm-navy); font-size: 1.2rem; font-weight: 800;">
                    <i class="fas fa-fingerprint" style="color: var(--firm-gold);"></i> Estampar Firma Digital
                </h3>
                <button type="button" onclick="closeSignatureModal()" style="background: none; border: none; font-size: 1.3rem; color: #64748B; cursor: pointer;">&times;</button>
            </div>
            
            <p style="font-size: 0.9rem; color: #475569; margin-bottom: 1.25rem;">
                Estás a punto de firmar electrónicamente el documento como <strong id="modalSignerName">-</strong> (RUT: <span id="modalSignerRut">-</span>) bajo la <strong>Ley N° 19.799</strong> de Firma Electrónica en Chile.
            </p>

            <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #1E40AF;">
                <i class="fas fa-lock"></i> Tu firma incluirá sello de tiempo oficial y quedará resguardada inmutablemente en Bóveda Cloudflare R2 con código de verificación público.
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="closeSignatureModal()" class="btn-cta" style="background: #E2E8F0; color: #475569 !important; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer;">
                    Cancelar
                </button>
                <button type="button" id="btnConfirmSign" onclick="executeSign()" class="btn-sign-now" style="background: #059669;">
                    <i class="fas fa-check"></i> Confirmar y Firmar
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentSignerId = null;

        function openSignatureModal(signerId, name, rut) {
            currentSignerId = signerId;
            document.getElementById('modalSignerName').innerText = name;
            document.getElementById('modalSignerRut').innerText = rut;
            document.getElementById('modalSign').style.display = 'flex';
        }

        function closeSignatureModal() {
            document.getElementById('modalSign').style.display = 'none';
            currentSignerId = null;
        }

        async function executeSign() {
            if (!currentSignerId) return;
            const btn = document.getElementById('btnConfirmSign');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Estampando Firma...';

            try {
                const res = await fetch('api/index.php?action=sign_document_signer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ signer_id: currentSignerId })
                });
                const data = await res.json();
                if (data.success) {
                    alert('¡Firma electrónica estampada exitosamente!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'No se pudo estampar la firma.'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Confirmar y Firmar';
                }
            } catch (err) {
                alert('Error de conexión al firmar el documento.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Confirmar y Firmar';
            }
        }
    </script>
</body>
</html>
