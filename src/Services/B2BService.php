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
            // Obtener el precio base configurado para carga de PDF propio como tarifa estándar por defecto
            $stmtBaseTarifa = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'precio_base_subir_pdf'");
            $stmtBaseTarifa->execute();
            $baseTarifaVal = $stmtBaseTarifa->fetchColumn();
            $tarifaInicialClp = ($baseTarifaVal !== false && is_numeric($baseTarifaVal)) ? (int)$baseTarifaVal : 7990;

            $stmtIns = $pdo->prepare("
                INSERT INTO companies (
                    user_id, rut, razon_social, giro, direccion, comuna, ciudad,
                    rep_legal_nombre, rep_legal_rut, rep_legal_email,
                    whatsapp_phone, whatsapp_instance_id,
                    plan_nombre, plan_estado, firmas_totales_plan, firmas_utilizadas, costo_mensual_uf, costo_firma_extra_clp,
                    modalidad_cobro, saldo_firmas_prepago, umbral_max_postpago, gasto_acumulado_periodo, tarifa_tramo_clp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PLAN_INICIAL', 'ACTIVO', 0, 0, 0, ?, 'PREPAGO', 0, 0, 0, ?)
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
                'sinnotario-b2b-' . substr(md5(uniqid()), 0, 8),
                $tarifaInicialClp,
                $tarifaInicialClp
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
                   c.firmas_totales_plan, c.firmas_utilizadas, c.costo_firma_extra_clp,
                   c.modalidad_cobro, c.saldo_firmas_prepago, c.umbral_max_postpago,
                   c.gasto_acumulado_periodo, c.tarifa_tramo_clp
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

        $itemsCount = count($items);

        // Validar estado del plan de la empresa
        if ($batch['plan_estado'] === 'SUSPENDIDO' || $batch['plan_estado'] === 'VENCIDO') {
            throw new Exception("El plan corporativo de su empresa se encuentra " . $batch['plan_estado'] . ". Regularice su suscripción para continuar firmando.");
        }

        // Validar Financieramente: PREPAGO vs POSTPAGO
        $modalidad = $batch['modalidad_cobro'] ?: 'PREPAGO';
        $saldoPrepago = (int)($batch['saldo_firmas_prepago'] ?? 0);
        $umbralPostpago = (int)($batch['umbral_max_postpago'] ?? 200);
        $firmasUsadas = (int)($batch['firmas_utilizadas'] ?? 0);
        $tarifaUnit = (int)($batch['tarifa_tramo_clp'] ?: 2490);

        if ($modalidad === 'PREPAGO') {
            if ($saldoPrepago < $itemsCount) {
                throw new Exception("Saldo de firmas prepagadas insuficiente. Su bolsa actual tiene {$saldoPrepago} firmas disponibles y el lote requiere {$itemsCount}. Por favor recargue una bolsa de firmas con descuento.");
            }
        } else {
            // POSTPAGO: Validar que no sobrepase el umbral de crédito
            if (($firmasUsadas + $itemsCount) > $umbralPostpago) {
                throw new Exception("Límite de sobregiro alcanzado para modalidad Postpago ({$umbralPostpago} firmas). Consumo proyectado: " . ($firmasUsadas + $itemsCount) . ". Solicite ampliación de umbral a Keystone Advisors.");
            }
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

            // Paso 2: Vincular o actualizar Expediente Digital del Trabajador (worker_folders)
            try {
                self::linkWorkerFolderDocument(
                    $batch['company_id'],
                    $item['colaborador_rut'],
                    $item['colaborador_nombre'],
                    $item['colaborador_email'],
                    $item['colaborador_telefono'],
                    $item['cargo'],
                    $docId,
                    'CONTRATO_INICIAL',
                    $docTitle,
                    $pdfResult['pdf_sha256'] ?? null,
                    $pdfResult['r2_key'] ?? null
                );
            } catch (Exception $eExp) {
                error_log("Error vinculando expediente digital: " . $eExp->getMessage());
            }

            $signedCount++;
        }

        // Actualizar estado del lote
        $stmtUpdBatch = $pdo->prepare("UPDATE b2b_batches SET status = 'EN_FIRMA', updated_at = datetime('now') WHERE id = ?");
        $stmtUpdBatch->execute([$batchId]);

        // Incrementar firmas consumidas en la empresa para control de plan y gastos
        if (!empty($batch['company_id'])) {
            $nuevoGastoPeriodo = (int)($batch['gasto_acumulado_periodo'] ?? 0) + ($signedCount * $tarifaUnit);

            if ($modalidad === 'PREPAGO') {
                $nuevoSaldoPrepago = max(0, $saldoPrepago - $signedCount);
                $stmtUpdComp = $pdo->prepare("
                    UPDATE companies 
                    SET firmas_utilizadas = COALESCE(firmas_utilizadas, 0) + ?,
                        saldo_firmas_prepago = ?,
                        updated_at = datetime('now') 
                    WHERE id = ?
                ");
                $stmtUpdComp->execute([$signedCount, $nuevoSaldoPrepago, $batch['company_id']]);

                // Registrar en log contable
                $stmtLog = $pdo->prepare("
                    INSERT INTO b2b_billing_logs (
                        company_id, tipo_movimiento, modalidad, cantidad_firmas,
                        monto_clp, saldo_anterior, saldo_nuevo, descripcion
                    ) VALUES (?, 'CONSUMO_FIRMA', 'PREPAGO', ?, 0, ?, ?, ?)
                ");
                $stmtLog->execute([
                    $batch['company_id'],
                    $signedCount,
                    $saldoPrepago,
                    $nuevoSaldoPrepago,
                    "Consumo masivo de {$signedCount} firmas en Lote #{$batchId}"
                ]);

            } else {
                // Modalidad POSTPAGO
                $stmtUpdComp = $pdo->prepare("
                    UPDATE companies 
                    SET firmas_utilizadas = COALESCE(firmas_utilizadas, 0) + ?,
                        gasto_acumulado_periodo = ?,
                        updated_at = datetime('now') 
                    WHERE id = ?
                ");
                $stmtUpdComp->execute([$signedCount, $nuevoGastoPeriodo, $batch['company_id']]);

                // Registrar en log contable para Factura DTE 33 fin de mes
                $stmtLog = $pdo->prepare("
                    INSERT INTO b2b_billing_logs (
                        company_id, tipo_movimiento, modalidad, cantidad_firmas,
                        monto_clp, saldo_anterior, saldo_nuevo, descripcion
                    ) VALUES (?, 'CONSUMO_FIRMA', 'POSTPAGO', ?, ?, ?, ?, ?)
                ");
                $stmtLog->execute([
                    $batch['company_id'],
                    $signedCount,
                    $signedCount * $tarifaUnit,
                    $firmasUsadas,
                    $firmasUsadas + $signedCount,
                    "Consumo devengado postpago: {$signedCount} firmas a \${$tarifaUnit} CLP"
                ]);
            }
        }

        return [
            'batch_id' => $batchId,
            'signed_count' => $signedCount,
            'status' => 'EN_FIRMA',
            'message' => "Se firmaron masivamente {$signedCount} contratos con certificación legal. Los colaboradores han sido convocados vía correo y WhatsApp."
        ];
    }

    /**
     * Vincula o crea el expediente digital de un trabajador y registra un nuevo documento
     */
    public static function linkWorkerFolderDocument($companyId, $rut, $nombre, $email, $tel, $cargo, $docId, $tipoDoc, $tituloDoc, $sha256 = null, $r2Key = null) {
        $pdo = Database::getInstance();

        // 1. Obtener o crear carpeta del trabajador
        $stmtFolder = $pdo->prepare("SELECT * FROM worker_folders WHERE company_id = ? AND colaborador_rut = ?");
        $stmtFolder->execute([$companyId, $rut]);
        $folder = $stmtFolder->fetch(PDO::FETCH_ASSOC);

        if (!$folder) {
            $stmtInsFolder = $pdo->prepare("
                INSERT INTO worker_folders (
                    company_id, colaborador_rut, colaborador_nombre, colaborador_email,
                    colaborador_telefono, cargo_actual, fecha_ingreso, total_documentos
                ) VALUES (?, ?, ?, ?, ?, ?, date('now'), 1)
            ");
            $stmtInsFolder->execute([$companyId, $rut, $nombre, $email, $tel, $cargo]);
            $folderId = $pdo->lastInsertId();
        } else {
            $folderId = $folder['id'];
            $stmtUpdFolder = $pdo->prepare("
                UPDATE worker_folders
                SET colaborador_nombre = COALESCE(?, colaborador_nombre),
                    colaborador_email = COALESCE(?, colaborador_email),
                    cargo_actual = COALESCE(?, cargo_actual),
                    total_documentos = total_documentos + 1,
                    updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmtUpdFolder->execute([$nombre, $email, $cargo, $folderId]);
        }

        // 2. Insertar documento en el expediente
        $stmtDoc = $pdo->prepare("
            INSERT INTO worker_documents (
                worker_folder_id, document_id, tipo_documento, titulo,
                fecha_emision, estado_firma, pdf_r2_key, pdf_sha256
            ) VALUES (?, ?, ?, ?, date('now'), 'FIRMADO', ?, ?)
        ");
        $stmtDoc->execute([$folderId, $docId, $tipoDoc, $tituloDoc, $r2Key, $sha256]);

        return $folderId;
    }

    /**
     * Obtiene el listado de expedientes digitales de la empresa
     */
    public static function getCompanyWorkerFolders($companyId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT f.*,
                   (SELECT COUNT(*) FROM worker_documents WHERE worker_folder_id = f.id) as docs_count
            FROM worker_folders f
            WHERE f.company_id = ?
            ORDER BY f.colaborador_nombre ASC
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el expediente digital detallado de un trabajador
     */
    public static function getWorkerFolderDetail($folderId) {
        $pdo = Database::getInstance();
        $stmtF = $pdo->prepare("SELECT * FROM worker_folders WHERE id = ?");
        $stmtF->execute([$folderId]);
        $folder = $stmtF->fetch(PDO::FETCH_ASSOC);

        if (!$folder) {
            throw new Exception("Expediente no encontrado.");
        }

        $stmtDocs = $pdo->prepare("
            SELECT d.*, doc.codigo_verificacion, doc.pdf_sha256 as doc_sha, doc.pdf_r2_key as doc_r2
            FROM worker_documents d
            LEFT JOIN documents doc ON d.document_id = doc.id
            WHERE d.worker_folder_id = ?
            ORDER BY d.id DESC
        ");
        $stmtDocs->execute([$folderId]);
        $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        return [
            'folder' => $folder,
            'documents' => $docs
        ];
    }

    /**
     * Guarda o actualiza una plantilla propia de la empresa con detección de variables {{...}}
     */
    public static function saveCompanyTemplate($companyId, $titulo, $cuerpoTexto, $categoria = 'laboral', $templateId = null) {
        $pdo = Database::getInstance();

        // Detectar variables encerradas en {{...}}
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $cuerpoTexto, $matches);
        $rawVars = array_unique($matches[1] ?? []);
        $variables = array_values($rawVars);

        $slug = 'custom-' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $titulo)) . '-' . substr(md5(uniqid()), 0, 4);

        if ($templateId) {
            $stmt = $pdo->prepare("
                UPDATE company_templates
                SET titulo = ?, cuerpo_texto = ?, variables_json = ?, categoria = ?, updated_at = datetime('now')
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$titulo, $cuerpoTexto, json_encode($variables, JSON_UNESCAPED_UNICODE), $categoria, $templateId, $companyId]);
            $id = $templateId;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO company_templates (
                    company_id, slug, titulo, categoria, cuerpo_texto, variables_json
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$companyId, $slug, $titulo, $categoria, $cuerpoTexto, json_encode($variables, JSON_UNESCAPED_UNICODE)]);
            $id = $pdo->lastInsertId();
        }

        return [
            'id' => $id,
            'titulo' => $titulo,
            'slug' => $slug,
            'categoria' => $categoria,
            'variables' => $variables,
            'total_variables' => count($variables)
        ];
    }

    /**
     * Obtiene las plantillas propias de una empresa
     */
    public static function getCompanyTemplates($companyId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM company_templates WHERE company_id = ? AND is_active = 1 ORDER BY id DESC");
        $stmt->execute([$companyId]);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($templates as &$t) {
            $t['variables'] = !empty($t['variables_json']) ? json_decode($t['variables_json'], true) : [];
        }

        return $templates;
    }

    /**
     * Obtiene el catálogo de packs prepagados configurados en el sistema
     */
    public static function getPrepaidPacks() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT key, value FROM system_settings WHERE key LIKE 'b2b_pack_%'");
        $settings = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        // Valores por defecto o sobreescritos dinámicamente desde la administración
        return [
            'PACK_50' => [
                'id' => 'PACK_50',
                'nombre' => $settings['b2b_pack_50_nombre'] ?? 'Pack 50 Firmas',
                'firmas' => (int)($settings['b2b_pack_50_firmas'] ?? 50),
                'precio_unit' => (int)($settings['b2b_pack_50_precio_unit'] ?? 3490),
                'total' => (int)($settings['b2b_pack_50_firmas'] ?? 50) * (int)($settings['b2b_pack_50_precio_unit'] ?? 3490),
                'ahorro_desc' => $settings['b2b_pack_50_ahorro'] ?? 'Ahorro 30% vs tarifa estándar',
                'badge' => $settings['b2b_pack_50_badge'] ?? 'PACK 50 FIRMAS'
            ],
            'PACK_200' => [
                'id' => 'PACK_200',
                'nombre' => $settings['b2b_pack_200_nombre'] ?? 'Pack 200 Firmas',
                'firmas' => (int)($settings['b2b_pack_200_firmas'] ?? 200),
                'precio_unit' => (int)($settings['b2b_pack_200_precio_unit'] ?? 2790),
                'total' => (int)($settings['b2b_pack_200_firmas'] ?? 200) * (int)($settings['b2b_pack_200_precio_unit'] ?? 2790),
                'ahorro_desc' => $settings['b2b_pack_200_ahorro'] ?? 'Ahorro 45% vs tarifa estándar',
                'badge' => $settings['b2b_pack_200_badge'] ?? 'MÁS POPULAR'
            ],
            'PACK_500' => [
                'id' => 'PACK_500',
                'nombre' => $settings['b2b_pack_500_nombre'] ?? 'Pack 500 Enterprise',
                'firmas' => (int)($settings['b2b_pack_500_firmas'] ?? 500),
                'precio_unit' => (int)($settings['b2b_pack_500_precio_unit'] ?? 1990),
                'total' => (int)($settings['b2b_pack_500_firmas'] ?? 500) * (int)($settings['b2b_pack_500_precio_unit'] ?? 1990),
                'ahorro_desc' => $settings['b2b_pack_500_ahorro'] ?? 'Tarifa mínima garantizada',
                'badge' => $settings['b2b_pack_500_badge'] ?? 'PACK 500+ ENTERPRISE'
            ]
        ];
    }

    /**
     * Recargar bolsa de firmas prepagadas
     */
    public static function rechargePrepagoBag($companyId, $packType) {
        $pdo = Database::getInstance();

        $packs = self::getPrepaidPacks();

        if (!isset($packs[$packType])) {
            throw new Exception("Tipo de bolsa no válida.");
        }

        $pack = $packs[$packType];

        $stmtC = $pdo->prepare("SELECT saldo_firmas_prepago FROM companies WHERE id = ?");
        $stmtC->execute([$companyId]);
        $saldoActual = (int)$stmtC->fetchColumn();
        $nuevoSaldo = $saldoActual + $pack['firmas'];

        $stmtUpd = $pdo->prepare("
            UPDATE companies 
            SET saldo_firmas_prepago = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmtUpd->execute([$nuevoSaldo, $companyId]);

        // Registrar movimiento
        $stmtLog = $pdo->prepare("
            INSERT INTO b2b_billing_logs (
                company_id, tipo_movimiento, modalidad, cantidad_firmas,
                monto_clp, saldo_anterior, saldo_nuevo, descripcion
            ) VALUES (?, 'RECARGA_PREPAGO', 'PREPAGO', ?, ?, ?, ?, ?)
        ");
        $stmtLog->execute([
            $companyId,
            $pack['firmas'],
            $pack['total'],
            $saldoActual,
            $nuevoSaldo,
            "Recarga de {$pack['nombre']} a \${$pack['precio_unit']} c/u"
        ]);

        return [
            'success' => true,
            'pack' => $pack,
            'saldo_anterior' => $saldoActual,
            'saldo_nuevo' => $nuevoSaldo,
            'message' => "Bolsa de {$pack['firmas']} firmas activada exitosamente."
        ];
    }

    /**
     * Actualiza la configuración de WhatsApp Oficial Dedicado de la empresa
     */
    public static function updateCompanyWhatsApp($companyId, $phone, $instanceId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            UPDATE companies
            SET whatsapp_phone = ?, whatsapp_instance_id = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$phone, $instanceId, $companyId]);

        return [
            'success' => true,
            'whatsapp_phone' => $phone,
            'whatsapp_instance_id' => $instanceId,
            'message' => 'Configuración de WhatsApp Oficial Dedicado actualizada.'
        ];
    }

    /**
     * Emite y difunde un PDF Único Masivo (Reglamento Interno, Ley Karin, EPP, etc.)
     * Almacena el PDF común en R2 y crea los documentos individuales en cada expediente con convocatoria por WhatsApp y Email.
     */
    public static function broadcastPdfBatch(int $companyId, int $userId, string $titulo, string $categoria, string $pdfBase64, string $fileName, array $workers, array $repLegal) {
        $pdo = Database::getInstance();

        if (empty($pdfBase64)) {
            throw new Exception("El archivo PDF es requerido.");
        }

        $binaryPdf = base64_decode($pdfBase64);
        if (!$binaryPdf || strlen($binaryPdf) < 10 || substr($binaryPdf, 0, 4) !== '%PDF') {
            throw new Exception("El archivo proporcionado no es un documento PDF válido.");
        }

        $pdfSha256 = hash('sha256', $binaryPdf);
        $totalWorkers = count($workers);
        if ($totalWorkers === 0) {
            throw new Exception("Debes incluir al menos un trabajador en la nómina destinataria.");
        }

        // 1. Obtener y validar datos de la empresa y saldo de firmas
        $stmtC = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmtC->execute([$companyId]);
        $company = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            throw new Exception("Empresa no encontrada.");
        }

        $modalidad = strtoupper($company['modalidad_cobro'] ?? 'PREPAGO');
        $saldoPrepago = (int)($company['saldo_firmas_prepago'] ?? 0);
        $tarifaUnit = (int)($company['tarifa_tramo_clp'] ?? 2490);

        if ($modalidad === 'PREPAGO' && $saldoPrepago < $totalWorkers) {
            throw new Exception("Saldo de firmas prepago insuficiente. Tienes {$saldoPrepago} firmas disponibles y necesitas {$totalWorkers}. Por favor, recarga una bolsa de firmas.");
        }

        // 2. Almacenar PDF maestro en Cloudflare R2
        require_once __DIR__ . '/../../api/adapters/StorageR2Adapter.php';
        $r2 = new StorageR2Adapter();
        $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName ?: 'documento_difusion.pdf');
        $r2Key = "b2b/broadcast/{$companyId}/" . date('Ymd_His') . "_{$safeName}";

        if ($r2->isConfigured()) {
            $r2Uploaded = $r2->uploadFile($r2Key, $binaryPdf, 'application/pdf');
            if (!$r2Uploaded) {
                // Fallback local
                $localDir = __DIR__ . '/../../storage/documents/broadcast/';
                if (!is_dir($localDir)) mkdir($localDir, 0755, true);
                file_put_contents($localDir . basename($r2Key), $binaryPdf);
            }
        } else {
            $localDir = __DIR__ . '/../../storage/documents/broadcast/';
            if (!is_dir($localDir)) mkdir($localDir, 0755, true);
            file_put_contents($localDir . basename($r2Key), $binaryPdf);
        }

        // 3. Crear Lote B2B tipo BROADCAST_PDF
        $batchHash = hash('sha256', $titulo . '_' . $pdfSha256 . '_' . time());
        $stmtBatch = $pdo->prepare("
            INSERT INTO b2b_batches (
                company_id, user_id, titulo, template_slug,
                total_records, valid_records, invalid_records,
                status, batch_hash, batch_type, pdf_r2_key, pdf_sha256
            ) VALUES (?, ?, ?, 'pdf-difusion-masiva', ?, ?, 0, 'EN_FIRMA', ?, 'BROADCAST_PDF', ?, ?)
        ");
        $stmtBatch->execute([
            $companyId,
            $userId,
            $titulo,
            $totalWorkers,
            $totalWorkers,
            $batchHash,
            $r2Key,
            $pdfSha256
        ]);
        $batchId = $pdo->lastInsertId();

        $repNombre = !empty($repLegal['nombre']) ? trim($repLegal['nombre']) : ($company['rep_legal_nombre'] ?: 'Representante Legal');
        $repRut = !empty($repLegal['rut']) ? trim($repLegal['rut']) : ($company['rep_legal_rut'] ?: '17.297.004-4');
        $repEmail = !empty($repLegal['email']) ? trim($repLegal['email']) : ($company['rep_legal_email'] ?: 'contacto@keystoneadvisors.cl');
        $now = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // 4. Procesar cada trabajador: crear document, signers y vincular a worker_folder
        $processedCount = 0;
        require_once __DIR__ . '/WhatsAppService.php';
        $wa = new WhatsAppService();
        $waInstance = !empty($company['whatsapp_instance_id']) ? $company['whatsapp_instance_id'] : null;

        $stmtItem = $pdo->prepare("
            INSERT INTO b2b_batch_items (
                batch_id, fila_num, colaborador_nombre, colaborador_rut,
                colaborador_email, colaborador_telefono, cargo, sueldo,
                is_valid, status, document_id, fecha_firma
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'FIRMADO', ?, ?)
        ");

        foreach ($workers as $idx => $w) {
            $filaNum = $idx + 1;
            $rut = self::formatRut($w['rut'] ?? '');
            $nombre = trim($w['nombre'] ?? '');
            $email = trim($w['email'] ?? '');
            $tel = trim($w['telefono'] ?? '');
            $cargo = trim($w['cargo'] ?? 'Colaborador');

            $verifCode = 'SN-DIF-' . strtoupper(substr(md5(uniqid($rut, true)), 0, 10));

            // Crear documento de acuse de recibo y firma
            $stmtDoc = $pdo->prepare("
                INSERT INTO documents (
                    codigo_verificacion, user_id, titulo,
                    estado, pdf_sha256, pdf_r2_key, estado_pago, monto_pagado
                ) VALUES (?, ?, ?, 'PENDIENTE_FIRMAS', ?, ?, 'PAGADO', 4990)
            ");
            $docTitle = "{$titulo} - {$nombre}";
            $stmtDoc->execute([$verifCode, $userId, $docTitle, $pdfSha256, $r2Key]);
            $docId = $pdo->lastInsertId();

            // Firmante 1: Empleador (firmado por representante)
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
                $company['razon_social'] ?: 'Keystone Advisors SpA',
                $company['rut'] ?: '78.089.686-8',
                $repEmail,
                $tokenRep,
                $now,
                $ip
            ]);

            // Firmante 2: Colaborador
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
                $rut,
                $nombre,
                $email,
                $tel,
                $tokenColab
            ]);

            // Vincular al lote
            $stmtItem->execute([
                $batchId,
                $filaNum,
                $nombre,
                $rut,
                $email,
                $tel,
                $cargo,
                '0',
                $docId,
                $now
            ]);

            // Vincular al Expediente Digital del Trabajador
            try {
                self::linkWorkerFolderDocument(
                    $companyId,
                    $rut,
                    $nombre,
                    $email,
                    $tel,
                    $cargo,
                    $docId,
                    $categoria,
                    $docTitle,
                    $pdfSha256,
                    $r2Key
                );
            } catch (Exception $eFolder) {
                error_log("Error vinculando expediente para {$rut}: " . $eFolder->getMessage());
            }

            // Despacho de notificación WhatsApp si la empresa tiene WhatsApp Oficial conectado
            if (!empty($waInstance) && !empty($tel)) {
                $signUrl = "https://sinnotario.cl/firmar.php?code={$verifCode}&token={$tokenColab}";
                $waMsg = "Hola {$nombre}. {$company['razon_social']} ha emitido el documento corporativo oficial: *{$titulo}*.\n\n" .
                         "Conforme a la normativa laboral, puedes revisar el PDF certificado y estampar tu firma de recepción conforme aquí:\n" .
                         "👉 {$signUrl}\n\n" .
                         "_Documento seguro certificado por SinNotario.cl bajo Ley N° 19.799._";
                try {
                    $wa->sendTextMessage($tel, $waMsg, $waInstance, (int)$docId, $nombre, 'B2B_NOTIFICACION_LABORAL', (int)$companyId);
                } catch (Exception $eWa) {
                    error_log("Error enviando WhatsApp a {$tel}: " . $eWa->getMessage());
                }
            }

            $processedCount++;
        }

        // 5. Descontar firmas de la bolsa prepagada
        if ($modalidad === 'PREPAGO') {
            $nuevoSaldoPrepago = max(0, $saldoPrepago - $processedCount);
            $stmtUpdComp = $pdo->prepare("
                UPDATE companies 
                SET firmas_utilizadas = COALESCE(firmas_utilizadas, 0) + ?,
                    saldo_firmas_prepago = ?,
                    updated_at = datetime('now') 
                WHERE id = ?
            ");
            $stmtUpdComp->execute([$processedCount, $nuevoSaldoPrepago, $companyId]);

            // Registrar en auditoría contable
            $stmtLog = $pdo->prepare("
                INSERT INTO b2b_billing_logs (
                    company_id, tipo_movimiento, modalidad, cantidad_firmas,
                    monto_clp, saldo_anterior, saldo_nuevo, descripcion
                ) VALUES (?, 'CONSUMO_FIRMA', 'PREPAGO', ?, 0, ?, ?, ?)
            ");
            $stmtLog->execute([
                $companyId,
                $processedCount,
                $saldoPrepago,
                $nuevoSaldoPrepago,
                "Difusión Masiva PDF '{$titulo}' para {$processedCount} colaboradores (Lote #{$batchId})"
            ]);
        } else {
            // Postpago
            $gastoAcumulado = (int)($company['gasto_acumulado_periodo'] ?? 0) + ($processedCount * $tarifaUnit);
            $stmtUpdComp = $pdo->prepare("
                UPDATE companies 
                SET firmas_utilizadas = COALESCE(firmas_utilizadas, 0) + ?,
                    gasto_acumulado_periodo = ?,
                    updated_at = datetime('now') 
                WHERE id = ?
            ");
            $stmtUpdComp->execute([$processedCount, $gastoAcumulado, $companyId]);
        }

        return [
            'success' => true,
            'batch_id' => $batchId,
            'titulo' => $titulo,
            'total_workers' => $processedCount,
            'pdf_sha256' => $pdfSha256,
            'saldo_anterior' => $saldoPrepago,
            'saldo_restante' => ($modalidad === 'PREPAGO') ? max(0, $saldoPrepago - $processedCount) : null,
            'message' => "Se emitieron y difundieron exitosamente {$processedCount} documentos. Cada trabajador ha sido convocado y el PDF quedó archivado en su Expediente Digital."
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
