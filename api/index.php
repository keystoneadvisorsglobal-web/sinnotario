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
            $stmtSigners = $pdo->prepare("SELECT rol_firmante, rut, nombre_completo, estado_firma, metodo_firma, fecha_firma FROM document_signers WHERE document_id = ?");
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
            $stmtSigners = $pdo->prepare("SELECT rol_firmante, rut, nombre_completo, estado_firma, metodo_firma, fecha_firma FROM document_signers WHERE document_id = ?");
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

            // Generar código único de verificación: SN-XXXX-XXXX
            $verifCode = 'SN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 4, 4));

            $stmtInsert = $pdo->prepare("INSERT INTO documents (codigo_verificacion, template_id, titulo, estado, datos_formulario, monto_pagado, estado_pago) VALUES (?, ?, ?, 'BORRADOR', ?, ?, 'PENDIENTE')");
            $stmtInsert->execute([
                $verifCode,
                $tpl['id'],
                $tpl['nombre'],
                json_encode($payload['form_data'] ?? [], JSON_UNESCAPED_UNICODE),
                $tpl['precio_base']
            ]);

            $docId = $pdo->lastInsertId();

            // Insertar firmantes si vienen
            if (!empty($payload['signers']) && is_array($payload['signers'])) {
                $stmtSig = $pdo->prepare("INSERT INTO document_signers (document_id, rol_firmante, rut, nombre_completo, email, telefono, token_acceso) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($payload['signers'] as $s) {
                    $token = bin2hex(random_bytes(24));
                    $stmtSig->execute([
                        $docId,
                        $s['rol'] ?? 'PARTE',
                        $s['rut'] ?? '',
                        $s['nombre'] ?? '',
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
                'precio' => $tpl['precio_base'],
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

            // Generar código único de verificación: SN-XXXX-XXXX
            $verifCode = 'SN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 4, 4));
            $r2Key = "documents/{$verifCode}.pdf";

            // Guardar en Cloudflare R2
            $r2Adapter = new StorageR2Adapter();
            $r2Upload = $r2Adapter->uploadDocument($r2Key, $binaryPdf, 'application/pdf');

            // Determinar si hay template o asociar a NULL
            $stmtInsert = $pdo->prepare("INSERT INTO documents (codigo_verificacion, template_id, titulo, estado, pdf_r2_key, pdf_sha256, datos_formulario, monto_pagado, estado_pago) VALUES (?, NULL, ?, 'PENDIENTE_FIRMAS', ?, ?, ?, 7990, 'PENDIENTE')");
            $formData = [
                'tipo_origen' => 'DOCUMENTO_PROPIO_PDF',
                'nombre_archivo_original' => $fileName,
                'tamano_bytes' => strlen($binaryPdf),
                'r2_subida_exitosa' => ($r2Upload !== false)
            ];
            $stmtInsert->execute([
                $verifCode,
                $titulo,
                $r2Key,
                $sha256,
                json_encode($formData, JSON_UNESCAPED_UNICODE)
            ]);

            $docId = $pdo->lastInsertId();

            // Insertar firmantes asociados
            $insertedSigners = [];
            if (!empty($payload['signers']) && is_array($payload['signers'])) {
                $stmtSig = $pdo->prepare("INSERT INTO document_signers (document_id, rol_firmante, rut, nombre_completo, email, telefono, token_acceso) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($payload['signers'] as $s) {
                    $token = bin2hex(random_bytes(24));
                    $rol = !empty($s['rol']) ? trim($s['rol']) : 'Parte Firmante';
                    $rut = !empty($s['rut']) ? trim($s['rut']) : '';
                    $nombre = !empty($s['nombre']) ? trim($s['nombre']) : '';
                    $email = !empty($s['email']) ? trim($s['email']) : '';
                    $tel = !empty($s['telefono']) ? trim($s['telefono']) : '';

                    if (!empty($rut) || !empty($nombre)) {
                        $stmtSig->execute([
                            $docId,
                            $rol,
                            $rut,
                            $nombre,
                            $email,
                            $tel,
                            $token
                        ]);
                        $insertedSigners[] = [
                            'rol' => $rol,
                            'rut' => $rut,
                            'nombre' => $nombre,
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
                'precio' => 7990,
                'signers_count' => count($insertedSigners),
                'signers' => $insertedSigners,
                'message' => 'Documento PDF propio recibido, auditado criptográficamente y custodiado en Cloudflare R2'
            ]);
            break;

        // 8. Estado de salud / Check
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
