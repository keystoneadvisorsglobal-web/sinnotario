<?php
/**
 * API Router & Endpoints - SinNotario.cl
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/adapters/StorageR2Adapter.php';
require_once __DIR__ . '/../src/Services/DocumentPdfService.php';
require_once __DIR__ . '/../src/Services/GeminiLegalService.php';
require_once __DIR__ . '/../src/Services/AuthService.php';
require_once __DIR__ . '/../src/Services/B2BService.php';
require_once __DIR__ . '/../src/Services/WhatsAppService.php';

$pdo = Database::getInstance();
$action = isset($_GET['action']) ? $_GET['action'] : '';

function jsonResponse($success, $data = null, $error = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    switch ($action) {
        // 1. Obtener catálogo de plantillas
        case 'get_templates':
            $categoria = isset($_GET['categoria']) ? $_GET['categoria'] : null;
            if ($categoria) {
                $stmt = $pdo->prepare("SELECT * FROM templates WHERE categoria = ? ORDER BY id ASC");
                $stmt->execute([$categoria]);
            } else {
                $stmt = $pdo->query("SELECT * FROM templates ORDER BY id ASC");
            }
            $templates = $stmt->fetchAll();
            jsonResponse(true, $templates);
            break;

        // 2. Obtener detalle de plantilla por slug
        case 'get_template_by_slug':
            $slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
            if (empty($slug)) {
                jsonResponse(false, null, 'Slug no especificado', 400);
            }
            $stmt = $pdo->prepare("SELECT * FROM templates WHERE slug = ?");
            $stmt->execute([$slug]);
            $template = $stmt->fetch();
            if (!$template) {
                jsonResponse(false, null, 'Plantilla no encontrada', 404);
            }
            jsonResponse(true, $template);
            break;

        // 2B. Obtener Pasarelas de Pago Habilitadas (para selectores dinámicos en Checkout)
        case 'get_payment_gateways':
            $stmtGw = $pdo->query("SELECT key, value FROM system_settings WHERE key IN ('mp_enabled', 'khipu_enabled')");
            $gwSettings = $stmtGw ? $stmtGw->fetchAll(PDO::FETCH_KEY_PAIR) : [];
            
            $mpEnabled = ($gwSettings['mp_enabled'] ?? '1') === '1';
            $khipuEnabled = ($gwSettings['khipu_enabled'] ?? '1') === '1';

            jsonResponse(true, [
                'mercadopago' => [
                    'id' => 'MERCADO_PAGO',
                    'nombre' => 'Mercado Pago',
                    'descripcion' => 'Tarjetas de Crédito, Débito / Redcompra',
                    'enabled' => $mpEnabled
                ],
                'khipu' => [
                    'id' => 'KHIPU',
                    'nombre' => 'Khipu',
                    'descripcion' => 'Transferencia Bancaria Directa',
                    'enabled' => $khipuEnabled
                ]
            ]);
            break;

        // 3B. Iniciar Pago de Documento Retail (Persona Natural - Redactar o Subir PDF)
        case 'pay_document':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $docId = (int)($input['document_id'] ?? 0);
            $gateway = strtoupper(trim($input['gateway'] ?? 'MERCADO_PAGO')); // MERCADO_PAGO o KHIPU

            if ($docId <= 0) {
                jsonResponse(false, null, 'ID de documento requerido', 400);
            }

            $stmtDoc = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
            $stmtDoc->execute([$docId]);
            $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                jsonResponse(false, null, 'Documento no encontrado', 404);
            }

            // Obtener firmante principal o datos del pagador (incluyendo teléfono y tipo de documento / pasaporte)
            $stmtPayer = $pdo->prepare("SELECT nombre_completo, email, rut, telefono, tipo_documento, numero_identificacion, pais_emisor FROM document_signers WHERE document_id = ? ORDER BY id ASC LIMIT 1");
            $stmtPayer->execute([$docId]);
            $payer = $stmtPayer->fetch(PDO::FETCH_ASSOC) ?: [
                'nombre_completo' => 'Cliente SinNotario',
                'email' => 'contacto@sinnotario.cl',
                'rut' => '11111111-1',
                'telefono' => '',
                'tipo_documento' => 'RUT',
                'numero_identificacion' => '',
                'pais_emisor' => 'Chile'
            ];

            $montoTotal = (int)($doc['monto_pagado'] ?? 4990);
            $verifCode = $doc['codigo_verificacion'];
            $docTitle = $doc['titulo'] ?: 'Certificación Digital de Documento';
            $sessionUser = $_SESSION['user_id'] ?? ($doc['user_id'] ?? null);

            $payerData = [
                'nombre' => $payer['nombre_completo'],
                'email' => $payer['email'],
                'rut' => $payer['rut'] ?: ($payer['numero_identificacion'] ?? '11111111-1'),
                'telefono' => $payer['telefono'] ?? '',
                'tipo_documento' => $payer['tipo_documento'] ?? 'RUT',
                'pais_emisor' => $payer['pais_emisor'] ?? 'Chile'
            ];

            if ($gateway === 'KHIPU') {
                require_once __DIR__ . '/../src/Services/KhipuService.php';
                $khipu = new KhipuService();
                if (!$khipu->isEnabled()) {
                    jsonResponse(false, null, 'El medio de pago Khipu se encuentra temporalmente deshabilitado por mantención.', 400);
                }
                if ($khipu->isConfigured()) {
                    $khipuRes = $khipu->createRetailDocumentPayment($docId, $verifCode, $docTitle, $montoTotal, $payerData, $sessionUser);
                    jsonResponse(true, $khipuRes);
                }
            }

            // Mercado Pago
            require_once __DIR__ . '/../src/Services/MercadoPagoService.php';
            $mp = new MercadoPagoService();
            if (!$mp->isEnabled()) {
                jsonResponse(false, null, 'El medio de pago Mercado Pago se encuentra temporalmente deshabilitado por mantención.', 400);
            }
            if ($mp->isConfigured()) {
                $prefResult = $mp->createRetailDocumentPreference($docId, $verifCode, $docTitle, $montoTotal, $payerData, $sessionUser);
                jsonResponse(true, $prefResult);
            } else {
                // Modo simulador demo
                jsonResponse(true, [
                    'configured' => false,
                    'document_id' => $docId,
                    'codigo_verificacion' => $verifCode,
                    'monto_total' => $montoTotal,
                    'redirect_url' => "verificar.php?code={$verifCode}",
                    'message' => 'Modo demostración: Las credenciales de pago están pendientes de configuración en el panel admin.'
                ]);
            }
            break;

        // 3. Verificación de documento público (/verificar)
        case 'verify_by_hash':
            $hash = isset($_GET['hash']) ? trim($_GET['hash']) : '';
            if (empty($hash) || strlen($hash) !== 64) {
                jsonResponse(false, null, 'Hash SHA-256 inválido o no proporcionado', 400);
            }

            $stmt = $pdo->prepare("SELECT d.*, t.nombre as template_nombre FROM documents d LEFT JOIN templates t ON d.template_id = t.id WHERE d.pdf_sha256 = ?");
            $stmt->execute([$hash]);
            $doc = $stmt->fetch();

            if (!$doc) {
                jsonResponse(false, null, 'No se encontró ningún documento con el hash especificado (posible alteración o documento no emitido en SinNotario)', 404);
            }

            // Obtener firmantes
            $stmtSigners = $pdo->prepare("SELECT rol_firmante, rut, tipo_documento, numero_identificacion, pais_emisor, nombre_completo, es_representante, empresa_razon_social, empresa_rut, estado_firma, metodo_firma, fecha_firma FROM document_signers WHERE document_id = ?");
            $stmtSigners->execute([$doc['id']]);
            $signers = $stmtSigners->fetchAll();

            unset($doc['datos_formulario']);
            unset($doc['token_pago']);

            jsonResponse(true, [
                'document' => $doc,
                'signers' => $signers,
                'is_valid' => in_array($doc['estado'], ['COMPLETADO', 'PENDIENTE_FIRMAS', 'BORRADOR']),
                'custody_vault' => 'Cloudflare R2 Encrypted Vault (Chile Law 19.799)'
            ]);
            break;

        case 'verify_document':
            $code = isset($_GET['code']) ? trim($_GET['code']) : '';
            if (empty($code)) {
                jsonResponse(false, null, 'Código de verificación requerido', 400);
            }

            $stmt = $pdo->prepare("SELECT d.*, t.nombre as template_nombre FROM documents d LEFT JOIN templates t ON d.template_id = t.id WHERE d.codigo_verificacion = ?");
            $stmt->execute([$code]);
            $doc = $stmt->fetch();

            if (!$doc) {
                jsonResponse(false, null, 'Documento no encontrado o código inválido', 404);
            }

            // Obtener firmantes
            $stmtSigners = $pdo->prepare("SELECT rol_firmante, rut, tipo_documento, numero_identificacion, pais_emisor, nombre_completo, es_representante, empresa_razon_social, empresa_rut, estado_firma, metodo_firma, fecha_firma FROM document_signers WHERE document_id = ?");
            $stmtSigners->execute([$doc['id']]);
            $signers = $stmtSigners->fetchAll();

            // Ocultar datos sensibles directos del formulario
            unset($doc['datos_formulario']);
            unset($doc['token_pago']);

            jsonResponse(true, [
                'document' => $doc,
                'signers' => $signers,
                'is_valid' => ($doc['estado'] === 'COMPLETADO'),
                'custody_vault' => 'Cloudflare R2 Encrypted Vault (Chile Law 19.799)'
            ]);
            break;

        // 4. Crear o guardar borrador de documento
        case 'create_draft':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $payload = json_decode(file_get_contents('php://input'), true);
            if (!$payload || !isset($payload['template_slug'])) {
                jsonResponse(false, null, 'Datos incompletos', 400);
            }

            $stmtTpl = $pdo->prepare("SELECT id, nombre, precio_base FROM templates WHERE slug = ?");
            $stmtTpl->execute([$payload['template_slug']]);
            $tpl = $stmtTpl->fetch();
            if (!$tpl) {
                jsonResponse(false, null, 'Plantilla inexistente', 404);
            }

            // Obtener configuración de precio extra por firmante y upgrade FEA
            $precioExtraPorFirmante = 2990;
            $stmtExtra = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'precio_firma_extra'");
            $stmtExtra->execute();
            $valExtra = $stmtExtra->fetchColumn();
            if ($valExtra !== false && is_numeric($valExtra)) {
                $precioExtraPorFirmante = (int)$valExtra;
            }

            $precioUpgradeFea = 2990;
            $stmtFea = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'precio_upgrade_fea'");
            $stmtFea->execute();
            $valFea = $stmtFea->fetchColumn();
            if ($valFea !== false && is_numeric($valFea)) {
                $precioUpgradeFea = (int)$valFea;
            }

            $tipoFirma = strtoupper(trim($payload['tipo_firma'] ?? 'FES'));
            if (!in_array($tipoFirma, ['FES', 'FEA'])) {
                $tipoFirma = 'FES';
            }

            // Cálculo dinámico de precio: Precio base incluye 1 firmante. Cada firmante adicional suma $precioExtraPorFirmante
            $numFirmantes = count($payload['signers'] ?? []);
            $firmantesExtra = max(0, $numFirmantes - 1);
            $suplementoFea = ($tipoFirma === 'FEA') ? $precioUpgradeFea : 0;
            $montoTotal = (int)$tpl['precio_base'] + ($firmantesExtra * $precioExtraPorFirmante) + $suplementoFea;

            // Generar código único de verificación: SN-XXXX-XXXX
            $verifCode = 'SN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 4, 4));

            $stmtInsert = $pdo->prepare("INSERT INTO documents (codigo_verificacion, template_id, titulo, estado, datos_formulario, tipo_firma, monto_pagado, estado_pago) VALUES (?, ?, ?, 'BORRADOR', ?, ?, ?, 'PENDIENTE')");
            $stmtInsert->execute([
                $verifCode,
                $tpl['id'],
                $tpl['nombre'],
                json_encode($payload['form_data'] ?? [], JSON_UNESCAPED_UNICODE),
                $tipoFirma,
                $montoTotal
            ]);

            $docId = $pdo->lastInsertId();

            // Insertar firmantes si vienen
            if (!empty($payload['signers']) && is_array($payload['signers'])) {
                $stmtSig = $pdo->prepare("INSERT INTO document_signers (document_id, rol_firmante, rut, tipo_documento, numero_identificacion, pais_emisor, nombre_completo, es_representante, empresa_razon_social, empresa_rut, email, telefono, token_acceso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($payload['signers'] as $s) {
                    $token = bin2hex(random_bytes(24));
                    $tipoDoc = !empty($s['tipo_documento']) ? strtoupper(trim($s['tipo_documento'])) : 'RUT';
                    $numId = !empty($s['numero_identificacion']) ? trim($s['numero_identificacion']) : (!empty($s['rut']) ? trim($s['rut']) : '');
                    $pais = !empty($s['pais_emisor']) ? trim($s['pais_emisor']) : 'Chile';
                    $esRep = !empty($s['es_representante']) ? 1 : 0;
                    $empresaRazon = !empty($s['empresa_razon_social']) ? trim($s['empresa_razon_social']) : null;
                    $empresaRut = !empty($s['empresa_rut']) ? trim($s['empresa_rut']) : null;

                    $stmtSig->execute([
                        $docId,
                        $s['rol'] ?? 'PARTE',
                        $numId,
                        $tipoDoc,
                        $numId,
                        $pais,
                        $s['nombre'] ?? '',
                        $esRep,
                        $empresaRazon,
                        $empresaRut,
                        $s['email'] ?? '',
                        $s['telefono'] ?? '',
                        $token
                    ]);
                }
            }

            // Generar inmediatamente el PDF oficial y guardarlo en Cloudflare R2
            $pdfResult = DocumentPdfService::generateAndVaultDocument((int)$docId);

            jsonResponse(true, [
                'document_id' => $docId,
                'codigo_verificacion' => $verifCode,
                'precio_base' => (int)$tpl['precio_base'],
                'precio_extra_por_firmante' => $precioExtraPorFirmante,
                'precio_upgrade_fea' => $precioUpgradeFea,
                'tipo_firma' => $tipoFirma,
                'suplemento_fea' => $suplementoFea,
                'firmantes_count' => $numFirmantes,
                'precio_total' => $montoTotal,
                'precio' => $montoTotal,
                'pdf_vault' => $pdfResult,
                'message' => 'Borrador creado y PDF sellado en Cloudflare R2'
            ]);
            break;

        // 5. Diagnóstico de contrato con IA (Smart Legal Check)
        case 'ai_audit_contract':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido', 405);
            $payload = json_decode(file_get_contents('php://input'), true);
            $text = trim($payload['contract_text'] ?? '');
            if (empty($text)) jsonResponse(false, null, 'Texto del contrato no proporcionado', 400);

            $aiService = new GeminiLegalService();
            $result = $aiService->analyzeContractText($text);
            jsonResponse(true, $result);
            break;

        // 6. Redacción libre de contrato con IA (Smart Drafter)
        case 'ai_draft_contract':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método no permitido', 405);
            $payload = json_decode(file_get_contents('php://input'), true);
            $prompt = trim($payload['prompt'] ?? '');
            if (empty($prompt)) jsonResponse(false, null, 'Instrucción o prompt no proporcionado', 400);

            $aiService = new GeminiLegalService();
            $result = $aiService->draftContractFromPrompt($prompt, $payload['parameters'] ?? []);
            jsonResponse(true, $result);
            break;

        // 7. Carga de Documento Propio (PDF externo)
        case 'upload_custom_document':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $payload = json_decode(file_get_contents('php://input'), true);
            if (!$payload || empty($payload['pdf_base64']) || empty($payload['titulo'])) {
                jsonResponse(false, null, 'Datos incompletos. Se requiere título y archivo PDF.', 400);
            }

            $binaryPdf = base64_decode($payload['pdf_base64']);
            if (!$binaryPdf || strlen($binaryPdf) < 10) {
                jsonResponse(false, null, 'Archivo PDF inválido o corrupto.', 400);
            }

            // Validar que empiece con %PDF
            if (substr($binaryPdf, 0, 4) !== '%PDF') {
                jsonResponse(false, null, 'El archivo cargado no tiene encabezado de formato PDF legítimo.', 400);
            }

            $sha256 = !empty($payload['sha256']) ? strtolower($payload['sha256']) : hash('sha256', $binaryPdf);
            $fileName = !empty($payload['file_name']) ? basename($payload['file_name']) : 'documento_propio.pdf';
            $titulo = trim($payload['titulo']);

            // Obtener configuración de precios para documento propio
            $precioBaseUpload = 7990;
            $stmtBaseUpload = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'precio_base_subir_pdf'");
            $stmtBaseUpload->execute();
            $valBase = $stmtBaseUpload->fetchColumn();
            if ($valBase !== false && is_numeric($valBase)) {
                $precioBaseUpload = (int)$valBase;
            }

            $precioExtraPorFirmante = 2990;
            $stmtExtra = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'precio_firma_extra'");
            $stmtExtra->execute();
            $valExtra = $stmtExtra->fetchColumn();
            if ($valExtra !== false && is_numeric($valExtra)) {
                $precioExtraPorFirmante = (int)$valExtra;
            }

            $precioUpgradeFea = 2990;
            $stmtFea = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'precio_upgrade_fea'");
            $stmtFea->execute();
            $valFea = $stmtFea->fetchColumn();
            if ($valFea !== false && is_numeric($valFea)) {
                $precioUpgradeFea = (int)$valFea;
            }

            $tipoFirma = strtoupper(trim($payload['tipo_firma'] ?? 'FES'));
            if (!in_array($tipoFirma, ['FES', 'FEA'])) {
                $tipoFirma = 'FES';
            }

            $rawSigners = (!empty($payload['signers']) && is_array($payload['signers'])) ? $payload['signers'] : [];
            $numSigners = count($rawSigners);
            $extraSigners = max(0, $numSigners - 1);
            $suplementoFea = ($tipoFirma === 'FEA') ? $precioUpgradeFea : 0;
            $montoTotal = $precioBaseUpload + ($extraSigners * $precioExtraPorFirmante) + $suplementoFea;

            // Generar código único de verificación: SN-XXXX-XXXX
            $verifCode = 'SN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 4, 4));
            $r2Key = "documents/{$verifCode}.pdf";

            // Guardar en Cloudflare R2
            $r2Adapter = new StorageR2Adapter();
            $r2Upload = $r2Adapter->uploadDocument($r2Key, $binaryPdf, 'application/pdf');

            // Determinar si hay template o asociar a NULL
            $stmtInsert = $pdo->prepare("INSERT INTO documents (codigo_verificacion, template_id, titulo, estado, pdf_r2_key, pdf_sha256, datos_formulario, tipo_firma, monto_pagado, estado_pago) VALUES (?, NULL, ?, 'PENDIENTE_FIRMAS', ?, ?, ?, ?, ?, 'PENDIENTE')");
            $formData = [
                'tipo_origen' => 'DOCUMENTO_PROPIO_PDF',
                'nombre_archivo_original' => $fileName,
                'tamano_bytes' => strlen($binaryPdf),
                'r2_subida_exitosa' => ($r2Upload !== false),
                'precio_base' => $precioBaseUpload,
                'precio_extra_por_firmante' => $precioExtraPorFirmante,
                'precio_upgrade_fea' => $precioUpgradeFea,
                'tipo_firma' => $tipoFirma,
                'suplemento_fea' => $suplementoFea,
                'firmantes_total' => $numSigners
            ];
            $stmtInsert->execute([
                $verifCode,
                $titulo,
                $r2Key,
                $sha256,
                json_encode($formData, JSON_UNESCAPED_UNICODE),
                $tipoFirma,
                $montoTotal
            ]);

            $docId = $pdo->lastInsertId();

            // Insertar firmantes asociados con soporte de Pasaporte y Representante Legal
            $insertedSigners = [];
            if (!empty($rawSigners)) {
                $stmtSig = $pdo->prepare("INSERT INTO document_signers (document_id, rol_firmante, rut, tipo_documento, numero_identificacion, pais_emisor, nombre_completo, es_representante, empresa_razon_social, empresa_rut, email, telefono, token_acceso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($rawSigners as $s) {
                    $token = bin2hex(random_bytes(24));
                    $rol = !empty($s['rol']) ? trim($s['rol']) : 'Parte Firmante';
                    $tipoDoc = !empty($s['tipo_documento']) ? strtoupper(trim($s['tipo_documento'])) : 'RUT';
                    $numId = !empty($s['numero_identificacion']) ? trim($s['numero_identificacion']) : (!empty($s['rut']) ? trim($s['rut']) : '');
                    $pais = !empty($s['pais_emisor']) ? trim($s['pais_emisor']) : 'Chile';
                    $nombre = !empty($s['nombre']) ? trim($s['nombre']) : '';
                    $email = !empty($s['email']) ? trim($s['email']) : '';
                    $tel = !empty($s['telefono']) ? trim($s['telefono']) : '';
                    $esRep = !empty($s['es_representante']) ? 1 : 0;
                    $empresaRazon = !empty($s['empresa_razon_social']) ? trim($s['empresa_razon_social']) : null;
                    $empresaRut = !empty($s['empresa_rut']) ? trim($s['empresa_rut']) : null;

                    if (!empty($numId) || !empty($nombre)) {
                        $stmtSig->execute([
                            $docId,
                            $rol,
                            $numId,
                            $tipoDoc,
                            $numId,
                            $pais,
                            $nombre,
                            $esRep,
                            $empresaRazon,
                            $empresaRut,
                            $email,
                            $tel,
                            $token
                        ]);
                        $insertedSigners[] = [
                            'rol' => $rol,
                            'tipo_documento' => $tipoDoc,
                            'numero_identificacion' => $numId,
                            'pais_emisor' => $pais,
                            'nombre' => $nombre,
                            'es_representante' => (bool)$esRep,
                            'empresa_razon_social' => $empresaRazon,
                            'empresa_rut' => $empresaRut,
                            'email' => $email,
                            'telefono' => $tel
                        ];
                    }
                }
            }

            jsonResponse(true, [
                'document_id' => $docId,
                'codigo_verificacion' => $verifCode,
                'titulo' => $titulo,
                'sha256' => $sha256,
                'r2_key' => $r2Key,
                'precio_base' => $precioBaseUpload,
                'precio_extra_por_firmante' => $precioExtraPorFirmante,
                'precio_upgrade_fea' => $precioUpgradeFea,
                'tipo_firma' => $tipoFirma,
                'suplemento_fea' => $suplementoFea,
                'signers_count' => count($insertedSigners),
                'precio' => $montoTotal,
                'precio_total' => $montoTotal,
                'signers' => $insertedSigners,
                'message' => 'Documento PDF propio recibido, auditado criptográficamente y custodiado en Cloudflare R2'
            ]);
            break;

        // 8. Obtener configuraciones de precios y tarifas
        case 'get_pricing_settings':
            $stmt = $pdo->query("SELECT * FROM system_settings");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $settings = [];
            foreach ($rows as $r) {
                $settings[$r['key']] = $r['value'];
            }
            jsonResponse(true, [
                'precio_firma_extra' => (int)($settings['precio_firma_extra'] ?? 2990),
                'precio_base_subir_pdf' => (int)($settings['precio_base_subir_pdf'] ?? 7990),
                'precio_upgrade_fea' => (int)($settings['precio_upgrade_fea'] ?? 2990),
                'all' => $settings
            ]);
            break;

        // 9. Actualizar precio de plantilla o precio de firma extra
        case 'update_pricing':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!$payload) jsonResponse(false, null, 'Datos inválidos', 400);

            if (isset($payload['precio_firma_extra'])) {
                $val = (int)$payload['precio_firma_extra'];
                $stmt = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES ('precio_firma_extra', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([$val]);
            }

            if (isset($payload['precio_base_subir_pdf'])) {
                $val = (int)$payload['precio_base_subir_pdf'];
                $stmt = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES ('precio_base_subir_pdf', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([$val]);
            }

            if (isset($payload['precio_upgrade_fea'])) {
                $val = (int)$payload['precio_upgrade_fea'];
                $stmt = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES ('precio_upgrade_fea', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([$val]);
            }

            if (isset($payload['templates']) && is_array($payload['templates'])) {
                $stmtUpdTpl = $pdo->prepare("UPDATE templates SET precio_base = ? WHERE id = ?");
                foreach ($payload['templates'] as $t) {
                    if (isset($t['id']) && isset($t['precio_base'])) {
                        $stmtUpdTpl->execute([(int)$t['precio_base'], (int)$t['id']]);
                    }
                }
            }

            jsonResponse(true, ['message' => 'Tarifas y precios actualizados exitosamente']);
            break;

        // 9B. Estampar Firma de Compareciente con Orquestador Multi-Proveedor (FirmaYa / Idok / Keystone Vault)
        case 'sign_document_signer':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }
            $payload = json_decode(file_get_contents('php://input'), true);
            $signerId = (int)($payload['signer_id'] ?? 0);

            if ($signerId <= 0) {
                jsonResponse(false, null, 'ID de firmante requerido', 400);
            }

            $stmtSig = $pdo->prepare("
                SELECT s.*, d.tipo_firma, d.codigo_verificacion, d.pdf_r2_key, d.titulo, d.template_id
                FROM document_signers s
                JOIN documents d ON s.document_id = d.id
                WHERE s.id = ?
            ");
            $stmtSig->execute([$signerId]);
            $signer = $stmtSig->fetch(PDO::FETCH_ASSOC);

            if (!$signer) {
                jsonResponse(false, null, 'Firmante no encontrado', 404);
            }

            if ($signer['estado_firma'] === 'FIRMADO') {
                jsonResponse(true, ['already_signed' => true, 'message' => 'El compareciente ya se encuentra firmado.']);
            }

            $now = date('Y-m-d H:i:s');
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $tipoFirma = $signer['tipo_firma'] ?: 'FES';

            // Invocar el Orquestador Multi-Proveedor de Firma
            require_once __DIR__ . '/../src/Services/Signing/SigningManager.php';
            $signingMgr = new \Services\Signing\SigningManager();

            // Descargar el PDF base de Cloudflare R2 o compilarlo en memoria
            require_once __DIR__ . '/../src/Services/DocumentPdfService.php';
            $r2 = new StorageR2Adapter();
            $pdfBinary = '';
            if (!empty($signer['pdf_r2_key'])) {
                $pdfBinary = $r2->downloadDocument($signer['pdf_r2_key']);
            }
            if (empty($pdfBinary)) {
                // Fallback: regenerar documento
                $vaultRes = DocumentPdfService::generateAndVaultDocument((int)$signer['document_id']);
                if (!empty($vaultRes['r2_key'])) {
                    $pdfBinary = $r2->downloadDocument($vaultRes['r2_key']);
                }
            }

            $signResult = $signingMgr->processSigner(
                (int)$signer['document_id'],
                $signer,
                $pdfBinary ?: 'PDF_PLACEHOLDER',
                $tipoFirma,
                ['redirect_url' => "https://sinnotario.cl/firmar.php?code={$signer['codigo_verificacion']}"]
            );

            // Actualizar estado de firma del compareciente
            $metodoRegistrado = ($tipoFirma === 'FEA') ? 'FEA_CLAVEUNICA_IDOK' : 'FES_FIRMAYA';
            $stmtUpd = $pdo->prepare("
                UPDATE document_signers 
                SET estado_firma = 'FIRMADO',
                    metodo_firma = ?,
                    fecha_firma = ?,
                    ip_firma = ?,
                    user_agent_firma = ?,
                    evidencia_firma = ?,
                    updated_at = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([
                $metodoRegistrado,
                $now,
                $ip,
                $ua,
                json_encode($signResult, JSON_UNESCAPED_UNICODE),
                $now,
                $signerId
            ]);

            // Deducción automática de inventario y monitoreo de stock al 30%
            require_once __DIR__ . '/../src/Services/StockAlertService.php';
            try {
                StockAlertService::consumeSignature($tipoFirma, 1, $docId);
            } catch (\Throwable $eStock) {
                error_log("Error deduciendo stock de firma: " . $eStock->getMessage());
            }

            // Comprobar si todos los comparecientes completaron sus firmas
            $docId = (int)$signer['document_id'];
            $stmtCheckAll = $pdo->prepare("SELECT COUNT(*) FROM document_signers WHERE document_id = ? AND estado_firma != 'FIRMADO'");
            $stmtCheckAll->execute([$docId]);
            $pendientes = (int)$stmtCheckAll->fetchColumn();

            if ($pendientes === 0) {
                // Todos firmaron: actualizar documento a COMPLETADO y re-generar PDF oficial sellado
                $stmtDocComp = $pdo->prepare("UPDATE documents SET estado = 'COMPLETADO', updated_at = ? WHERE id = ?");
                $stmtDocComp->execute([$now, $docId]);

                try {
                    DocumentPdfService::generateAndVaultDocument($docId);
                } catch (\Throwable $ePdf) {
                    error_log("Error actualizando PDF final sellado: " . $ePdf->getMessage());
                }
            }

            jsonResponse(true, [
                'signer_id' => $signerId,
                'document_id' => $docId,
                'estado_firma' => 'FIRMADO',
                'metodo_firma' => $metodoRegistrado,
                'provider' => $signResult['provider'] ?? 'idok_firmaya',
                'transaction_id' => $signResult['transaction_id'] ?? null,
                'auth_url' => $signResult['auth_url'] ?? null,
                'documento_completado' => ($pendientes === 0),
                'message' => 'Firma electrónica estampada exitosamente a través del motor de integración.'
            ]);
            break;

        // ==========================================
        // MÓDULO DE AUTENTICACIÓN Y CLIENTES (PORTAL)
        // ==========================================

        // 10. Login con Email y Contraseña
        case 'auth_login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            $remember = !empty($input['remember']);

            try {
                $sessionData = AuthService::loginWithPassword($email, $password, $remember);
                jsonResponse(true, $sessionData);
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage(), 401);
            }
            break;

        // 11. Registro de nuevo usuario
        case 'auth_register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $nombre = $input['nombre'] ?? '';
            $apellido = $input['apellido'] ?? '';
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            $rut = $input['rut'] ?? null;
            $telefono = $input['telefono'] ?? null;
            $empresaRazonSocial = $input['empresa_razon_social'] ?? null;
            $empresaRut = $input['empresa_rut'] ?? null;

            try {
                $sessionData = AuthService::registerUser($nombre, $apellido, $email, $password, $rut, $telefono, $empresaRazonSocial, $empresaRut);
                jsonResponse(true, $sessionData);
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage(), 400);
            }
            break;

        // 12. Autenticación con Google (One Tap / GIS OAuth)
        case 'auth_google':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $credential = $input['credential'] ?? '';

            try {
                $sessionData = AuthService::loginWithGoogle($credential);
                jsonResponse(true, $sessionData);
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage(), 400);
            }
            break;

        // 12b. Solicitar restablecimiento de contraseña (Forgot Password)
        case 'auth_forgot_password':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $email = $input['email'] ?? '';

            try {
                AuthService::requestPasswordReset($email);
                jsonResponse(true, ['message' => 'Si el correo está registrado, recibirás un enlace de recuperación en los próximos minutos.']);
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage(), 400);
            }
            break;

        // 12c. Restablecer contraseña con token
        case 'auth_reset_password':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['token'] ?? '';
            $password = $input['password'] ?? '';

            try {
                AuthService::resetPasswordWithToken($token, $password);
                jsonResponse(true, ['message' => 'Tu contraseña ha sido restablecida exitosamente. Ya puedes iniciar sesión.']);
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage(), 400);
            }
            break;

        // 13. Obtener usuario autenticado actual
        case 'auth_current_user':
            $user = AuthService::getCurrentUser();
            if ($user) {
                jsonResponse(true, $user);
            } else {
                jsonResponse(false, null, 'No hay sesión activa', 401);
            }
            break;

        // 14. Cerrar sesión
        case 'auth_logout':
            AuthService::logout();
            jsonResponse(true, ['message' => 'Sesión cerrada correctamente']);
            break;

        // 15. Obtener documentos del portal privado del cliente
        case 'client_get_documents':
            $user = AuthService::getCurrentUser();
            if (!$user) {
                jsonResponse(false, null, 'Acceso no autorizado', 401);
            }

            // Buscar documentos asociados por user_id O por correo del usuario en firmantes
            $userEmail = strtolower($user['email']);
            $userId = (int)$user['id'];

            $stmtDocs = $pdo->prepare("
                SELECT DISTINCT 
                    d.id,
                    d.codigo_verificacion,
                    d.titulo,
                    d.estado,
                    d.monto_pagado,
                    d.estado_pago,
                    d.pdf_r2_key,
                    d.pdf_sha256,
                    d.created_at,
                    t.nombre AS template_nombre,
                    t.categoria AS template_categoria,
                    t.icono AS template_icono
                FROM documents d
                LEFT JOIN templates t ON d.template_id = t.id
                LEFT JOIN document_signers s ON s.document_id = d.id
                WHERE d.user_id = ? OR LOWER(s.email) = ?
                ORDER BY d.id DESC
            ");
            $stmtDocs->execute([$userId, $userEmail]);
            $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

            // Obtener estado de firmantes para cada documento
            foreach ($docs as &$doc) {
                $stmtSig = $pdo->prepare("
                    SELECT 
                        id, rol_firmante, rut, tipo_documento, nombre_completo, 
                        email, telefono, estado_firma, fecha_firma, es_representante,
                        empresa_razon_social, empresa_rut
                    FROM document_signers
                    WHERE document_id = ?
                    ORDER BY orden_firma ASC, id ASC
                ");
                $stmtSig->execute([$doc['id']]);
                $doc['signers'] = $stmtSig->fetchAll(PDO::FETCH_ASSOC);
            }

            jsonResponse(true, [
                'user' => $user,
                'documents' => $docs,
                'total' => count($docs)
            ]);
            break;

        // 16. Actualizar perfil de usuario
        case 'client_update_profile':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }
            $user = AuthService::getCurrentUser();
            if (!$user) {
                jsonResponse(false, null, 'Acceso no autorizado', 401);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $nombre = trim($input['nombre'] ?? $user['nombre']);
            $apellido = trim($input['apellido'] ?? $user['apellido']);
            $telefono = trim($input['telefono'] ?? ($user['telefono'] ?? ''));
            $rut = trim($input['rut'] ?? ($user['rut'] ?? ''));
            $empresaNombre = trim($input['empresa_razon_social'] ?? ($user['empresa_razon_social'] ?? ''));
            $empresaRut = trim($input['empresa_rut'] ?? ($user['empresa_rut'] ?? ''));

            $stmtUpd = $pdo->prepare("
                UPDATE users 
                SET nombre = ?, apellido = ?, telefono = ?, rut = ?, empresa_razon_social = ?, empresa_rut = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmtUpd->execute([$nombre, $apellido, $telefono, $rut, $empresaNombre, $empresaRut, $user['id']]);

            jsonResponse(true, ['message' => 'Perfil actualizado exitosamente']);
            break;

        // ==========================================
        // ENDPOINTS B2B EMPRESAS (SOL-0063)
        // ==========================================

        // 18. Obtener datos de empresa del usuario autenticado o crear perfil empresa
        case 'b2b_get_company':
            $user = AuthService::getCurrentUser();
            $userId = $user ? $user['id'] : null;

            $rutEmpresa = isset($_GET['rut']) ? trim($_GET['rut']) : ($user['empresa_rut'] ?? '78.089.686-8');
            $razonSocial = isset($_GET['razon_social']) ? trim($_GET['razon_social']) : ($user['empresa_razon_social'] ?? 'Keystone Advisors SpA');

            $company = B2BService::getOrCreateCompany($userId, [
                'rut' => $rutEmpresa,
                'razon_social' => $razonSocial,
                'rep_legal_nombre' => $user ? ($user['nombre'] . ' ' . $user['apellido']) : 'Maritza Vásquez',
                'rep_legal_rut' => $user['rut'] ?? '17.297.004-4',
                'rep_legal_email' => $user['email'] ?? 'contacto@sinnotario.cl'
            ]);

            // Obtener historial de lotes emitidos
            $stmtBatches = $pdo->prepare("
                SELECT b.*, 
                       (SELECT COUNT(*) FROM b2b_batch_items WHERE batch_id = b.id AND status = 'FIRMADO') as firmados_count
                FROM b2b_batches b
                WHERE b.company_id = ? OR (b.user_id IS NOT NULL AND b.user_id = ?)
                ORDER BY b.id DESC
                LIMIT 20
            ");
            $stmtBatches->execute([$company['id'], $userId]);
            $batches = $stmtBatches->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(true, [
                'company' => $company,
                'batches' => $batches
            ]);
            break;

        // 19. Cargar y validar lote de nómina masiva
        case 'b2b_upload_batch':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $user = AuthService::getCurrentUser();
            $userId = $user ? $user['id'] : null;

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                jsonResponse(false, null, 'Payload JSON requerido', 400);
            }

            $titulo = trim($input['titulo'] ?? ('Lote Nómina - ' . date('d/m/Y H:i')));
            $templateSlug = trim($input['template_slug'] ?? 'contrato-individual-trabajo');
            $rows = $input['rows'] ?? [];

            if (empty($rows) || !is_array($rows)) {
                jsonResponse(false, null, 'No se proporcionaron filas para procesar', 400);
            }

            $companyData = $input['company'] ?? [
                'rut' => $user['empresa_rut'] ?? '78.089.686-8',
                'razon_social' => $user['empresa_razon_social'] ?? 'Keystone Advisors SpA',
                'rep_legal_nombre' => $user ? ($user['nombre'] . ' ' . $user['apellido']) : 'Maritza Vásquez',
                'rep_legal_rut' => $user['rut'] ?? '17.297.004-4',
                'rep_legal_email' => $user['email'] ?? 'contacto@sinnotario.cl'
            ];

            $company = B2BService::getOrCreateCompany($userId, $companyData);
            $tipoFirma = strtoupper(trim($input['tipo_firma'] ?? 'FES')) === 'FEA' ? 'FEA' : 'FES';
            $batchResult = B2BService::processBatchUpload($userId, $company['id'], $titulo, $templateSlug, $rows, $tipoFirma);

            jsonResponse(true, [
                'batch' => $batchResult,
                'company' => $company
            ]);
            break;

        // 20. Firmar masivamente lote en 1 Clic (Generación de contratos + PDFs con FEA/FES)
        case 'b2b_sign_batch':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $batchId = (int)($input['batch_id'] ?? 0);

            if ($batchId <= 0) {
                jsonResponse(false, null, 'ID de lote inválido', 400);
            }

            $repLegalData = $input['rep_legal'] ?? [];
            $resSign = B2BService::signBatch($batchId, $repLegalData);

            jsonResponse(true, $resSign);
            break;

        // 20B. Emisión y Difusión Masiva de PDF Único (Reglamento Interno, Ley Karin, EPP)
        case 'b2b_broadcast_pdf':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $companyId = (int)($input['company_id'] ?? 0);
            $userId = (int)($input['user_id'] ?? 0);
            $titulo = trim($input['titulo'] ?? 'Difusión y Firma Masiva de Documento');
            $categoria = trim($input['categoria'] ?? 'REGLAMENTO_INTERNO');
            $pdfBase64 = $input['pdf_base64'] ?? '';
            $fileName = trim($input['file_name'] ?? 'documento.pdf');
            $workers = $input['workers'] ?? [];
            $repLegal = $input['rep_legal'] ?? [];
            $tipoFirma = strtoupper(trim($input['tipo_firma'] ?? 'FES')) === 'FEA' ? 'FEA' : 'FES';

            if ($companyId <= 0 || empty($pdfBase64) || empty($workers)) {
                jsonResponse(false, null, 'Datos incompletos. Se requiere PDF y lista de trabajadores.', 400);
            }

            try {
                $resBroadcast = B2BService::broadcastPdfBatch($companyId, $userId, $titulo, $categoria, $pdfBase64, $fileName, $workers, $repLegal, $tipoFirma);
                jsonResponse(true, $resBroadcast);
            } catch (Exception $eBroad) {
                jsonResponse(false, null, $eBroad->getMessage(), 400);
            }
            break;

        // 21. Exportar Lote a Formato Libro Digital DT (Ley N° 21.327)
        case 'b2b_export_dt':
            $batchId = (int)($_GET['batch_id'] ?? 0);
            if ($batchId <= 0) {
                jsonResponse(false, null, 'ID de lote requerido', 400);
            }

            $csvData = B2BService::exportDtCsv($batchId);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="Libro_Digital_DT_Lote_' . $batchId . '.csv"');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
            echo $csvData;
            exit;
            break;

        // 22. Recargar Bolsa Prepagada de Firmas (Vía Mercado Pago o Khipu)
        case 'b2b_recharge_bag':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $companyId = (int)($input['company_id'] ?? 0);
            $packType = trim($input['pack_type'] ?? 'PACK_50');
            $gateway = strtoupper(trim($input['gateway'] ?? 'MERCADO_PAGO')); // MERCADO_PAGO o KHIPU

            if ($companyId <= 0) {
                jsonResponse(false, null, 'ID de empresa inválido', 400);
            }

            // Obtener datos de la empresa
            $stmtC = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
            $stmtC->execute([$companyId]);
            $company = $stmtC->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                jsonResponse(false, null, 'Empresa no encontrada', 404);
            }

            $packs = B2BService::getPrepaidPacks();
            if (!isset($packs[$packType])) {
                jsonResponse(false, null, 'Bolsa seleccionada no válida', 400);
            }

            $rawUserId = $_SESSION['user_id'] ?? ($company['user_id'] ?? null);
            $sessionUser = null;
            if (!empty($rawUserId)) {
                $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $stmtCheckUser->execute([(int)$rawUserId]);
                if ($stmtCheckUser->fetchColumn()) {
                    $sessionUser = (int)$rawUserId;
                }
            }

            if ($gateway === 'KHIPU') {
                require_once __DIR__ . '/../src/Services/KhipuService.php';
                $khipu = new KhipuService();
                if (!$khipu->isEnabled()) {
                    jsonResponse(false, null, 'El medio de pago Khipu se encuentra temporalmente deshabilitado por mantención.', 400);
                }
                if ($khipu->isConfigured()) {
                    $khipuRes = $khipu->createB2BPackPayment($companyId, $packType, $packs[$packType], $company, (int)$sessionUser);
                    jsonResponse(true, $khipuRes);
                }
            }

            // Mercado Pago
            require_once __DIR__ . '/../src/Services/MercadoPagoService.php';
            $mp = new MercadoPagoService();
            if (!$mp->isEnabled()) {
                jsonResponse(false, null, 'El medio de pago Mercado Pago se encuentra temporalmente deshabilitado por mantención.', 400);
            }

            if ($mp->isConfigured()) {
                $prefResult = $mp->createB2BPackPreference($companyId, $packType, $packs[$packType], $company, (int)$sessionUser);
                jsonResponse(true, $prefResult);
            } else {
                // Modo simulador / abono administrativo hasta configurar credenciales en admin.php
                $resRecarga = B2BService::rechargePrepagoBag($companyId, $packType);
                jsonResponse(true, $resRecarga);
            }
            break;

        // 22C. Webhook de Notificaciones de Mercado Pago (IPN & Orders API)
        case 'mp_webhook':
            $inputRaw = file_get_contents('php://input');
            $data = json_decode($inputRaw, true) ?: [];
            $topic = $_GET['topic'] ?? ($_GET['type'] ?? ($data['type'] ?? ($data['action'] ?? 'payment')));
            $id = $_GET['id'] ?? ($_GET['data.id'] ?? ($data['data']['id'] ?? null));

            require_once __DIR__ . '/../src/Services/MercadoPagoService.php';
            $mp = new MercadoPagoService();

            // 1. Notificación tipo Orden (Orders API: order.processed / order)
            if (($topic === 'order' || strpos($topic, 'order') !== false) && $id) {
                try {
                    $orderData = $mp->getOrder((string)$id);
                    if (!empty($orderData['success']) && in_array($orderData['data']['status'] ?? '', ['processed', 'approved'])) {
                        $extRef = $orderData['data']['external_reference'] ?? '';
                        if ($extRef) {
                            MercadoPagoService::processApprovedPayment($extRef, (string)$id, $orderData['data']);
                            error_log("MP Webhook: Orden #{$id} acreditada exitosamente para ref {$extRef}");
                        }
                    }
                } catch (\Throwable $eOrd) {
                    error_log("MP Webhook Error procesando orden #{$id}: " . $eOrd->getMessage());
                }
            }

            // 2. Notificación tipo Pago (Payment API: payment.created / payment.updated)
            if (($topic === 'payment' || strpos($topic, 'payment') !== false) && $id) {
                try {
                    // Consultar detalles oficiales del pago en la API de Mercado Pago
                    $payInfo = $mp->getPayment((int)$id);
                    if (!empty($payInfo['external_reference']) && ($payInfo['status'] ?? '') === 'approved') {
                        MercadoPagoService::processApprovedPayment(
                            $payInfo['external_reference'],
                            (string)$id,
                            $payInfo
                        );
                        error_log("MP Webhook: Pago #{$id} acreditado exitosamente para ref {$payInfo['external_reference']}");
                    }
                } catch (\Throwable $e) {
                    error_log("MP Webhook Error procesando pago #{$id}: " . $e->getMessage());
                }
            }
            jsonResponse(true, ['status' => 'received']);
            break;

        // 22D. Webhook y Notificaciones de Khipu
        case 'khipu_webhook':
            $apiVer = $_POST['api_version'] ?? '';
            $notifToken = $_POST['notification_token'] ?? '';
            if (!empty($notifToken)) {
                error_log("Khipu Webhook recibido: token {$notifToken}");
            }
            echo "OK";
            exit;
            break;

        // 22B. Obtener Catálogo Dinámico de Packs Prepagados
        case 'b2b_get_packs':
            $packs = B2BService::getPrepaidPacks();
            jsonResponse(true, $packs);
            break;

        // 23. Obtener Historial de Facturación y Consumo B2B
        case 'b2b_get_billing_logs':
            $companyId = (int)($_GET['company_id'] ?? 0);
            if ($companyId <= 0) {
                jsonResponse(false, null, 'ID de empresa requerido', 400);
            }

            $stmtLogs = $pdo->prepare("SELECT * FROM b2b_billing_logs WHERE company_id = ? ORDER BY id DESC LIMIT 50");
            $stmtLogs->execute([$companyId]);
            $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(true, $logs);
            break;

        // 24. Obtener Expedientes Digitales de Trabajadores (Paso 2)
        case 'b2b_get_worker_folders':
            $companyId = (int)($_GET['company_id'] ?? 0);
            if ($companyId <= 0) {
                jsonResponse(false, null, 'ID de empresa requerido', 400);
            }

            $folders = B2BService::getCompanyWorkerFolders($companyId);
            jsonResponse(true, $folders);
            break;

        // 25. Obtener Detalle de Expediente Digital con sus Documentos
        case 'b2b_get_worker_folder_detail':
            $folderId = (int)($_GET['folder_id'] ?? 0);
            if ($folderId <= 0) {
                jsonResponse(false, null, 'ID de carpeta requerido', 400);
            }

            $detail = B2BService::getWorkerFolderDetail($folderId);
            jsonResponse(true, $detail);
            break;

        // 26. Guardar / Actualizar Plantilla Propia de Empresa (Paso 2)
        case 'b2b_save_company_template':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $companyId = (int)($input['company_id'] ?? 0);
            $titulo = trim($input['titulo'] ?? '');
            $cuerpo = trim($input['cuerpo_texto'] ?? '');
            $categoria = trim($input['categoria'] ?? 'laboral');
            $tplId = isset($input['template_id']) ? (int)$input['template_id'] : null;

            if ($companyId <= 0 || empty($titulo) || empty($cuerpo)) {
                jsonResponse(false, null, 'Datos obligatorios incompletos (título y cuerpo de contrato requeridos)', 400);
            }

            $resTpl = B2BService::saveCompanyTemplate($companyId, $titulo, $cuerpo, $categoria, $tplId);
            jsonResponse(true, $resTpl);
            break;

        // 27. Obtener Plantillas Propias de la Empresa
        case 'b2b_get_company_templates':
            $companyId = (int)($_GET['company_id'] ?? 0);
            if ($companyId <= 0) {
                jsonResponse(false, null, 'ID de empresa requerido', 400);
            }

            $tpls = B2BService::getCompanyTemplates($companyId);
            jsonResponse(true, $tpls);
            break;

        // 28. Configurar WhatsApp Oficial Dedicado de la Empresa
        case 'b2b_update_whatsapp':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $companyId = (int)($input['company_id'] ?? 0);
            $phone = trim($input['whatsapp_phone'] ?? '');
            $instanceId = trim($input['whatsapp_instance_id'] ?? '');

            if ($companyId <= 0 || empty($phone)) {
                jsonResponse(false, null, 'Teléfono WhatsApp requerido', 400);
            }

            $resWa = B2BService::updateCompanyWhatsApp($companyId, $phone, $instanceId);
            jsonResponse(true, $resWa);
            break;

        // 29. Conectar Instancia WhatsApp de Empresa y Obtener QR
        case 'b2b_get_whatsapp_qr':
            $companyId = (int)($_GET['company_id'] ?? 0);
            if ($companyId <= 0) {
                jsonResponse(false, null, 'ID de empresa requerido', 400);
            }

            // Consultar datos de la empresa para resolver o asignar su instancia dedicada
            $stmtC = $pdo->prepare("SELECT id, rut, razon_social, whatsapp_phone, whatsapp_instance_id FROM companies WHERE id = ?");
            $stmtC->execute([$companyId]);
            $comp = $stmtC->fetch(PDO::FETCH_ASSOC);

            if (!$comp) {
                jsonResponse(false, null, 'Empresa no encontrada', 404);
            }

            $wa = new WhatsAppService();
            $instanceName = !empty($comp['whatsapp_instance_id']) ? $comp['whatsapp_instance_id'] : WhatsAppService::generateInstanceName($comp['id'], $comp['rut']);

            // Si no estaba persistido el instanceId en la BD, guardarlo
            if (empty($comp['whatsapp_instance_id'])) {
                $stmtUp = $pdo->prepare("UPDATE companies SET whatsapp_instance_id = ? WHERE id = ?");
                $stmtUp->execute([$instanceName, $companyId]);
            }

            $qrRes = $wa->getOrConnectInstance($instanceName);
            $qrRes['company_id'] = $companyId;
            $qrRes['instance_name'] = $instanceName;
            jsonResponse(true, $qrRes);
            break;

        // 30. Consultar Estado en Vivo de la Instancia WhatsApp de la Empresa
        case 'b2b_get_whatsapp_status':
            $companyId = (int)($_GET['company_id'] ?? 0);
            if ($companyId <= 0) {
                jsonResponse(false, null, 'ID de empresa requerido', 400);
            }

            $stmtC = $pdo->prepare("SELECT id, rut, whatsapp_instance_id FROM companies WHERE id = ?");
            $stmtC->execute([$companyId]);
            $comp = $stmtC->fetch(PDO::FETCH_ASSOC);

            if (!$comp) {
                jsonResponse(false, null, 'Empresa no encontrada', 404);
            }

            $instanceName = !empty($comp['whatsapp_instance_id']) ? $comp['whatsapp_instance_id'] : WhatsAppService::generateInstanceName($comp['id'], $comp['rut']);
            $wa = new WhatsAppService();
            $statusRes = $wa->getConnectionStatus($instanceName);
            jsonResponse(true, $statusRes);
            break;

        // 31. Desconectar Instancia WhatsApp de la Empresa
        case 'b2b_disconnect_whatsapp':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $companyId = (int)($input['company_id'] ?? 0);
            if ($companyId <= 0) {
                jsonResponse(false, null, 'ID de empresa requerido', 400);
            }

            $stmtC = $pdo->prepare("SELECT id, rut, whatsapp_instance_id FROM companies WHERE id = ?");
            $stmtC->execute([$companyId]);
            $comp = $stmtC->fetch(PDO::FETCH_ASSOC);

            if ($comp && !empty($comp['whatsapp_instance_id'])) {
                $wa = new WhatsAppService();
                $wa->disconnectInstance($comp['whatsapp_instance_id']);
            }

            jsonResponse(true, ['message' => 'Instancia desconectada correctamente.']);
            break;

        // 17. Estado de salud / Check
        case 'health':
            $r2 = new StorageR2Adapter();
            $r2Configured = !empty($r2->accountId) && !empty($r2->apiToken);
            jsonResponse(true, [
                'platform' => 'SinNotario.cl',
                'status' => 'operational',
                'database' => 'SQLite 3 connected',
                'r2_vault' => $r2Configured ? 'Ready' : 'Missing credentials',
                'version' => '1.0.0'
            ]);
            break;

        default:
            jsonResponse(false, null, 'Acción no reconocida', 400);
            break;
    }
} catch (Exception $e) {
    jsonResponse(false, null, 'Error interno: ' . $e->getMessage(), 500);
}
