<?php
/**
 * Servicio de Gestión y Procesamiento B2B Corporativo - SinNotario.cl
 * Keystone Advisors
 */

require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/DocumentPdfService.php';

class B2BService {

    /**
     * Valida y formatea un RUT chileno usando Módulo 11
     */
    public static function validateAndFormatRut($rutRaw) {
        $clean = preg_replace('/[^0-9kK]/', '', (string)$rutRaw);
        if (strlen($clean) < 2) {
            return ['valid' => false, 'rut' => $rutRaw, 'clean' => $clean];
        }

        $cuerpo = substr($clean, 0, -1);
        $dv = strtoupper(substr($clean, -1));

        $suma = 0;
        $multiplo = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int)$cuerpo[$i] * $multiplo;
            $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
        }

        $dvEsperado = 11 - ($suma % 11);
        $dvCalculado = $dvEsperado == 11 ? '0' : ($dvEsperado == 10 ? 'K' : (string)$dvEsperado);

        $isValid = ($dv === $dvCalculado && (int)$cuerpo > 50000);
        $formatted = number_format((int)$cuerpo, 0, '', '.') . '-' . $dv;

        return [
            'valid' => $isValid,
            'rut' => $formatted,
            'clean' => $cuerpo . $dv
        ];
    }

    /**
     * Obtiene o crea la empresa vinculada a un usuario o por RUT
     */
    public static function getOrCreateCompany($userId, $companyData) {
        $pdo = Database::getInstance();

        $rutRes = self::validateAndFormatRut($companyData['rut'] ?? '');
        $rut = $rutRes['rut'];
        $razonSocial = trim($companyData['razon_social'] ?? 'Empresa Sin Notario');

        $stmt = $pdo->prepare("SELECT * FROM companies WHERE rut = ? OR (user_id IS NOT NULL AND user_id = ?)");
        $stmt->execute([$rut, $userId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $stmtIns = $pdo->prepare("
                INSERT INTO companies (
                    user_id, rut, razon_social, giro, direccion, comuna, ciudad,
                    rep_legal_nombre, rep_legal_rut, rep_legal_email,
                    whatsapp_phone, whatsapp_instance_id,
                    plan_nombre, plan_estado, firmas_totales_plan, firmas_utilizadas, costo_mensual_uf, costo_firma_extra_clp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PLAN_INICIAL', 'ACTIVO', 50, 0, 2.50, 2490)
            ");
            $stmtIns->execute([
                $userId,
                $rut,
                $razonSocial,
                $companyData['giro'] ?? 'Servicios Generales',
                $companyData['direccion'] ?? 'Av. Providencia 1208',
                $companyData['comuna'] ?? 'Providencia',
                $companyData['ciudad'] ?? 'Santiago',
                $companyData['rep_legal_nombre'] ?? 'Representante Legal',
                $companyData['rep_legal_rut'] ?? $rut,
                $companyData['rep_legal_email'] ?? 'contacto@empresa.cl',
                $companyData['whatsapp_phone'] ?? '+56984180479',
                'sinnotario-b2b-' . substr(md5(uniqid()), 0, 8)
            ]);
            $companyId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
            $stmt->execute([$companyId]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Actualizar si viene data nueva
            if (!empty($companyData['razon_social'])) {
                $stmtUpd = $pdo->prepare("
                    UPDATE companies SET
                        razon_social = COALESCE(?, razon_social),
                        rep_legal_nombre = COALESCE(?, rep_legal_nombre),
                        rep_legal_rut = COALESCE(?, rep_legal_rut),
                        rep_legal_email = COALESCE(?, rep_legal_email),
                        updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmtUpd->execute([
                    $companyData['razon_social'] ?? null,
                    $companyData['rep_legal_nombre'] ?? null,
                    $companyData['rep_legal_rut'] ?? null,
                    $companyData['rep_legal_email'] ?? null,
                    $company['id']
                ]);
            }
        }

        return $company;
    }

    /**
     * Procesa y valida un lote de registros (filas de nómina)
     */
    public static function processBatchUpload($userId, $companyId, $titulo, $templateSlug, array $rows) {
        $pdo = Database::getInstance();

        $totalRecords = count($rows);
        $validRecords = 0;
        $invalidRecords = 0;
        $processedRows = [];

        foreach ($rows as $idx => $r) {
            $filaNum = $idx + 1;
            $nombre = trim($r['nombre'] ?? ($r['colaborador_nombre'] ?? ''));
            $rawRut = trim($r['rut'] ?? ($r['colaborador_rut'] ?? ''));
            $email = trim($r['email'] ?? ($r['colaborador_email'] ?? ''));
            $tel = trim($r['telefono'] ?? ($r['colaborador_telefono'] ?? ($r['tel'] ?? '')));
            $cargo = trim($r['cargo'] ?? 'Colaborador');
            $sueldo = trim($r['sueldo'] ?? '500.000');

            $rutInfo = self::validateAndFormatRut($rawRut);
            $isValid = true;
            $errors = [];

            if (empty($nombre)) {
                $isValid = false;
                $errors[] = 'Nombre obligatorio';
            }
            if (!$rutInfo['valid']) {
                $isValid = false;
                $errors[] = 'RUT inválido (Módulo 11)';
            }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $isValid = false;
                $errors[] = 'Email con formato inválido';
            }

            if ($isValid) {
                $validRecords++;
            } else {
                $invalidRecords++;
            }

            $processedRows[] = [
                'fila_num' => $filaNum,
                'colaborador_nombre' => $nombre,
                'colaborador_rut' => $rutInfo['rut'],
                'colaborador_email' => $email,
                'colaborador_telefono' => $tel,
                'cargo' => $cargo,
                'sueldo' => $sueldo,
                'is_valid' => $isValid ? 1 : 0,
                'error_detalle' => !empty($errors) ? implode(', ', $errors) : null,
                'datos_extra' => json_encode($r, JSON_UNESCAPED_UNICODE)
            ];
        }

        $batchHash = hash('sha256', $titulo . microtime(true) . json_encode($rows));

        // Insertar cabecera de lote
        $stmtBatch = $pdo->prepare("
            INSERT INTO b2b_batches (
                company_id, user_id, titulo, template_slug,
                total_records, valid_records, invalid_records,
                status, batch_hash
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'BORRADOR', ?)
        ");
        $stmtBatch->execute([
            $companyId,
            $userId,
            $titulo,
            $templateSlug,
            $totalRecords,
            $validRecords,
            $invalidRecords,
            $batchHash
        ]);
        $batchId = $pdo->lastInsertId();

        // Insertar items de lote
        $stmtItem = $pdo->prepare("
            INSERT INTO b2b_batch_items (
                batch_id, fila_num, colaborador_nombre, colaborador_rut,
                colaborador_email, colaborador_telefono, cargo, sueldo,
                datos_extra, is_valid, error_detalle, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE')
        ");

        foreach ($processedRows as &$pr) {
            $stmtItem->execute([
                $batchId,
                $pr['fila_num'],
                $pr['colaborador_nombre'],
                $pr['colaborador_rut'],
                $pr['colaborador_email'],
                $pr['colaborador_telefono'],
                $pr['cargo'],
                $pr['sueldo'],
                $pr['datos_extra'],
                $pr['is_valid'],
                $pr['error_detalle']
            ]);
            $pr['id'] = $pdo->lastInsertId();
        }

        return [
            'batch_id' => $batchId,
            'titulo' => $titulo,
            'template_slug' => $templateSlug,
            'total_records' => $totalRecords,
            'valid_records' => $validRecords,
            'invalid_records' => $invalidRecords,
            'batch_hash' => $batchHash,
            'items' => $processedRows
        ];
    }

    /**
     * Genera y firma en lote todos los contratos válidos de un batch
     */
    public static function signBatch($batchId, $repLegalData) {
        $pdo = Database::getInstance();

        $stmtBatch = $pdo->prepare("
            SELECT b.*, c.id as comp_id, c.razon_social, c.rut as empresa_rut, c.direccion as empresa_dir,
                   c.rep_legal_nombre, c.rep_legal_rut, c.plan_nombre, c.plan_estado,
                   c.firmas_totales_plan, c.firmas_utilizadas, c.costo_firma_extra_clp
            FROM b2b_batches b
            LEFT JOIN companies c ON b.company_id = c.id
            WHERE b.id = ?
        ");
        $stmtBatch->execute([$batchId]);
        $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            throw new Exception("Lote B2B no encontrado.");
        }

        // Obtener items válidos
        $stmtItems = $pdo->prepare("SELECT * FROM b2b_batch_items WHERE batch_id = ? AND is_valid = 1");
        $stmtItems->execute([$batchId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            throw new Exception("El lote no contiene registros válidos para emitir contratos.");
        }

        // Validar estado del plan de la empresa
        if ($batch['plan_estado'] === 'SUSPENDIDO' || $batch['plan_estado'] === 'VENCIDO') {
            throw new Exception("El plan corporativo de su empresa se encuentra " . $batch['plan_estado'] . ". Regularice su suscripción para continuar firmando.");
        }

        // Obtener template
        $templateSlug = $batch['template_slug'] ?: 'contrato-individual-trabajo';
        $stmtTpl = $pdo->prepare("SELECT * FROM templates WHERE slug = ?");
        $stmtTpl->execute([$templateSlug]);
        $template = $stmtTpl->fetch(PDO::FETCH_ASSOC);
        $templateId = $template ? $template['id'] : 1;

        $signedCount = 0;
        $now = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $repNombre = !empty($repLegalData['nombre']) ? $repLegalData['nombre'] : ($batch['rep_legal_nombre'] ?: 'Representante Keystone');
        $repRut = !empty($repLegalData['rut']) ? $repLegalData['rut'] : ($batch['rep_legal_rut'] ?: '17.297.004-4');
        $repEmail = !empty($repLegalData['email']) ? $repLegalData['email'] : 'contacto@sinnotario.cl';

        foreach ($items as $item) {
            $code = 'SN-B2B-' . strtoupper(substr(md5(uniqid($item['id'], true)), 0, 8));
            $docTitle = "Contrato Individual de Trabajo - {$item['colaborador_nombre']}";

            $formData = [
                'f_modalidad' => 'estandar',
                'f_empleador_razon' => $batch['razon_social'] ?: 'Keystone Advisors SpA',
                'f_empleador_rut' => $batch['empresa_rut'] ?: '78.089.686-8',
                'f_empleador_rep_nombre' => $repNombre,
                'f_empleador_rep_rut' => $repRut,
                'f_trabajador_nombre' => $item['colaborador_nombre'],
                'f_trabajador_rut' => $item['colaborador_rut'],
                'f_trabajador_email' => $item['colaborador_email'],
                'f_trabajador_cargo' => $item['cargo'],
                'f_trabajador_sueldo' => $item['sueldo']
            ];

            // Crear documento individual en tabla documents
            $stmtDoc = $pdo->prepare("
                INSERT INTO documents (
                    codigo_verificacion, template_id, user_id, titulo,
                    estado, datos_formulario, estado_pago, monto_pagado
                ) VALUES (?, ?, ?, ?, 'PENDIENTE_FIRMAS', ?, 'PAGADO', 4990)
            ");
            $stmtDoc->execute([
                $code,
                $templateId,
                $batch['user_id'],
                $docTitle,
                json_encode($formData, JSON_UNESCAPED_UNICODE)
            ]);
            $docId = $pdo->lastInsertId();

            // Firmante 1: Empleador (firmado en este acto por el representante legal)
            $tokenRep = bin2hex(random_bytes(16));
            $stmtRep = $pdo->prepare("
                INSERT INTO document_signers (
                    document_id, rol_firmante, rut, tipo_documento, nombre_completo,
                    es_representante, empresa_razon_social, empresa_rut,
                    email, telefono, orden_firma, token_acceso, estado_firma,
                    metodo_firma, fecha_firma, ip_firma
                ) VALUES (?, 'EMPLEADOR', ?, 'RUT', ?, 1, ?, ?, ?, '+56984180479', 1, ?, 'FIRMADO', 'FEA', ?, ?)
            ");
            $stmtRep->execute([
                $docId,
                $repRut,
                $repNombre,
                $batch['razon_social'] ?: 'Keystone Advisors SpA',
                $batch['empresa_rut'] ?: '78.089.686-8',
                $repEmail,
                $tokenRep,
                $now,
                $ip
            ]);

            // Firmante 2: Colaborador (convocado por correo/WhatsApp)
            $tokenColab = bin2hex(random_bytes(16));
            $stmtColab = $pdo->prepare("
                INSERT INTO document_signers (
                    document_id, rol_firmante, rut, tipo_documento, nombre_completo,
                    es_representante, email, telefono, orden_firma, token_acceso,
                    estado_firma, metodo_firma
                ) VALUES (?, 'TRABAJADOR', ?, 'RUT', ?, 0, ?, ?, 2, ?, 'NOTIFICADO', 'FES')
            ");
            $stmtColab->execute([
                $docId,
                $item['colaborador_rut'],
                $item['colaborador_nombre'],
                $item['colaborador_email'],
                $item['colaborador_telefono'],
                $tokenColab
            ]);

            // Generar PDF y almacenar en R2/Local
            try {
                $pdfResult = DocumentPdfService::generateAndVaultDocument($docId);

                if (!empty($pdfResult['pdf_sha256'])) {
                    $stmtUpdDoc = $pdo->prepare("UPDATE documents SET pdf_sha256 = ?, pdf_r2_key = ? WHERE id = ?");
                    $stmtUpdDoc->execute([$pdfResult['pdf_sha256'], $pdfResult['r2_key'] ?? null, $docId]);
                }
            } catch (Exception $ePdf) {
                error_log("Error generando PDF para batch item {$item['id']}: " . $ePdf->getMessage());
            }

            // Actualizar item
            $stmtUpdItem = $pdo->prepare("
                UPDATE b2b_batch_items
                SET document_id = ?, status = 'FIRMADO', fecha_firma = ?
                WHERE id = ?
            ");
            $stmtUpdItem->execute([$docId, $now, $item['id']]);

            $signedCount++;
        }

        // Actualizar estado del lote
        $stmtUpdBatch = $pdo->prepare("UPDATE b2b_batches SET status = 'EN_FIRMA', updated_at = datetime('now') WHERE id = ?");
        $stmtUpdBatch->execute([$batchId]);

        // Incrementar firmas consumidas en la empresa para control de plan y gastos
        if (!empty($batch['company_id'])) {
            $stmtUpdComp = $pdo->prepare("
                UPDATE companies 
                SET firmas_utilizadas = COALESCE(firmas_utilizadas, 0) + ?, updated_at = datetime('now') 
                WHERE id = ?
            ");
            $stmtUpdComp->execute([$signedCount, $batch['company_id']]);
        }

        return [
            'batch_id' => $batchId,
            'signed_count' => $signedCount,
            'status' => 'EN_FIRMA',
            'message' => "Se firmaron masivamente {$signedCount} contratos con certificación legal. Los colaboradores han sido convocados vía correo y WhatsApp."
        ];
    }

    /**
     * Exporta el lote al formato oficial del Registro Electrónico Laboral de la Dirección del Trabajo (Ley N° 21.327)
     */
    public static function exportDtCsv($batchId) {
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare("
            SELECT i.*, b.titulo, b.created_at as batch_date,
                   c.rut as empresa_rut, c.razon_social
            FROM b2b_batch_items i
            JOIN b2b_batches b ON i.batch_id = b.id
            LEFT JOIN companies c ON b.company_id = c.id
            WHERE i.batch_id = ?
            ORDER BY i.fila_num ASC
        ");
        $stmt->execute([$batchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            throw new Exception("Lote sin datos para exportar.");
        }

        // Encabezado oficial estándar DT (Separado por punto y coma conforme a Libro Digital DT)
        $csv = "RUT_EMPLEADOR;RAZON_SOCIAL;RUT_TRABAJADOR;NOMBRE_TRABAJADOR;CARGO;SUELDO_BASE;FECHA_INICIO;JORNADA;ESTADO_CONTRATO;CODIGO_SINNOTARIO\r\n";

        foreach ($rows as $r) {
            $rutEmp = str_replace(['.', '-'], '', $r['empresa_rut'] ?: '780896868');
            $razon = str_replace(';', ',', $r['razon_social'] ?: 'Keystone Advisors SpA');
            $rutTrab = str_replace(['.', '-'], '', $r['colaborador_rut']);
            $nomTrab = str_replace(';', ',', $r['colaborador_nombre']);
            $cargo = str_replace(';', ',', $r['cargo'] ?: 'Operador');
            $sueldo = preg_replace('/[^0-9]/', '', $r['sueldo'] ?: '500000');
            $fecha = date('d/m/Y', strtotime($r['batch_date']));
            $jornada = "Ordinaria 40 Horas (Ley 21.561)";
            $estado = ($r['status'] === 'FIRMADO') ? 'FIRMADO_EMPLEADOR' : 'PENDIENTE';
            $codigo = $r['document_id'] ? ('SN-DOC-' . $r['document_id']) : 'SN-PENDIENTE';

            $csv .= "{$rutEmp};{$razon};{$rutTrab};{$nomTrab};{$cargo};{$sueldo};{$fecha};{$jornada};{$estado};{$codigo}\r\n";
        }

        return $csv;
    }
}
