<?php
/**
 * Database & Schema Manager - SinNotario.cl
 * Compatible con PHP 7.4 y 8.x
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dbPath = __DIR__ . '/sinnotario.sqlite';
        $isNew = !file_exists($dbPath);

        try {
            $this->pdo = new PDO("sqlite:" . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec("PRAGMA foreign_keys = ON;");
            $this->pdo->exec("PRAGMA journal_mode = WAL;");

            if ($isNew || filesize($dbPath) === 0) {
                $this->initSchema();
            } else {
                $this->migrateSchema();
            }
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    public function migrateSchema() {
        try {
            // Verificar columnas necesarias en tabla users
            $userColsStmt = $this->pdo->query("PRAGMA table_info(users);");
            $userCols = $userColsStmt->fetchAll(PDO::FETCH_COLUMN, 1);

            $missingCols = [
                'auth_provider' => "ALTER TABLE users ADD COLUMN auth_provider VARCHAR(20) DEFAULT 'LOCAL';",
                'google_id' => "ALTER TABLE users ADD COLUMN google_id VARCHAR(100);",
                'avatar_url' => "ALTER TABLE users ADD COLUMN avatar_url TEXT;",
                'empresa_razon_social' => "ALTER TABLE users ADD COLUMN empresa_razon_social VARCHAR(200);",
                'empresa_rut' => "ALTER TABLE users ADD COLUMN empresa_rut VARCHAR(20);",
                'email_verificado' => "ALTER TABLE users ADD COLUMN email_verificado BOOLEAN DEFAULT 0;",
                'ultimo_login' => "ALTER TABLE users ADD COLUMN ultimo_login DATETIME;"
            ];

            foreach ($missingCols as $colName => $alterSql) {
                if (!in_array($colName, $userCols)) {
                    $this->pdo->exec($alterSql);
                }
            }

            // Crear tabla de sesiones seguras si no existe
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token VARCHAR(128) NOT NULL UNIQUE,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );");

            // Crear tabla de tokens de recuperación / verificación si no existe
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                tipo VARCHAR(30) NOT NULL, -- PASSWORD_RESET, EMAIL_VERIFY, OTP_LOGIN
                token VARCHAR(128) NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                utilizado BOOLEAN DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );");

            // Migrar columnas B2B en tabla companies si ya existía previamente
            $compColsStmt = $this->pdo->query("PRAGMA table_info(companies);");
            $compCols = $compColsStmt ? $compColsStmt->fetchAll(PDO::FETCH_COLUMN, 1) : [];
            $missingCompCols = [
                'plan_nombre' => "ALTER TABLE companies ADD COLUMN plan_nombre VARCHAR(50) DEFAULT 'PLAN_INICIAL';",
                'plan_estado' => "ALTER TABLE companies ADD COLUMN plan_estado VARCHAR(20) DEFAULT 'ACTIVO';",
                'firmas_totales_plan' => "ALTER TABLE companies ADD COLUMN firmas_totales_plan INTEGER DEFAULT 50;",
                'firmas_utilizadas' => "ALTER TABLE companies ADD COLUMN firmas_utilizadas INTEGER DEFAULT 0;",
                'costo_mensual_uf' => "ALTER TABLE companies ADD COLUMN costo_mensual_uf DECIMAL(6,2) DEFAULT 2.50;",
                'costo_firma_extra_clp' => "ALTER TABLE companies ADD COLUMN costo_firma_extra_clp INTEGER DEFAULT 2490;",
                // Atributos de Control Financiero B2B (Prepago / Postpago y Umbrales)
                'modalidad_cobro' => "ALTER TABLE companies ADD COLUMN modalidad_cobro VARCHAR(20) DEFAULT 'PREPAGO';",
                'saldo_firmas_prepago' => "ALTER TABLE companies ADD COLUMN saldo_firmas_prepago INTEGER DEFAULT 0;",
                'saldo_firmas_fea' => "ALTER TABLE companies ADD COLUMN saldo_firmas_fea INTEGER DEFAULT 0;",
                'umbral_max_postpago' => "ALTER TABLE companies ADD COLUMN umbral_max_postpago INTEGER DEFAULT 0;",
                'gasto_acumulado_periodo' => "ALTER TABLE companies ADD COLUMN gasto_acumulado_periodo INTEGER DEFAULT 0;",
                'tarifa_tramo_clp' => "ALTER TABLE companies ADD COLUMN tarifa_tramo_clp INTEGER DEFAULT 2490;"
            ];
            foreach ($missingCompCols as $cCol => $cSql) {
                if (!in_array($cCol, $compCols)) {
                    $this->pdo->exec($cSql);
                }
            }

            // Migrar columnas B2B en tabla b2b_batches si ya existía previamente
            $batchColsStmt = $this->pdo->query("PRAGMA table_info(b2b_batches);");
            $batchCols = $batchColsStmt ? $batchColsStmt->fetchAll(PDO::FETCH_COLUMN, 1) : [];
            $missingBatchCols = [
                'batch_type' => "ALTER TABLE b2b_batches ADD COLUMN batch_type VARCHAR(30) DEFAULT 'TEMPLATE';", // TEMPLATE o BROADCAST_PDF
                'tipo_firma' => "ALTER TABLE b2b_batches ADD COLUMN tipo_firma VARCHAR(10) DEFAULT 'FES';", // FES o FEA
                'pdf_r2_key' => "ALTER TABLE b2b_batches ADD COLUMN pdf_r2_key VARCHAR(255);",
                'pdf_sha256' => "ALTER TABLE b2b_batches ADD COLUMN pdf_sha256 VARCHAR(64);",
                'is_simulation' => "ALTER TABLE b2b_batches ADD COLUMN is_simulation INTEGER DEFAULT 0;",
                'simulation_email' => "ALTER TABLE b2b_batches ADD COLUMN simulation_email VARCHAR(150);"
            ];
            foreach ($missingBatchCols as $bCol => $bSql) {
                if (!in_array($bCol, $batchCols)) {
                    $this->pdo->exec($bSql);
                }
            }

            // Migrar columnas en documents
            $docColsStmt = $this->pdo->query("PRAGMA table_info(documents);");
            $docCols = $docColsStmt ? $docColsStmt->fetchAll(PDO::FETCH_COLUMN, 1) : [];
            $missingDocCols = [
                'tipo_firma' => "ALTER TABLE documents ADD COLUMN tipo_firma VARCHAR(10) DEFAULT 'FES';",
                'is_simulation' => "ALTER TABLE documents ADD COLUMN is_simulation INTEGER DEFAULT 0;"
            ];
            foreach ($missingDocCols as $dCol => $dSql) {
                if (!in_array($dCol, $docCols)) {
                    $this->pdo->exec($dSql);
                }
            }

            // Migrar columnas 2FA en users (SOL-0123)
            $userColsStmt = $this->pdo->query("PRAGMA table_info(users);");
            $userCols = $userColsStmt ? $userColsStmt->fetchAll(PDO::FETCH_COLUMN, 1) : [];
            $missingUserCols = [
                'otp_code' => "ALTER TABLE users ADD COLUMN otp_code VARCHAR(255);",
                'otp_expires_at' => "ALTER TABLE users ADD COLUMN otp_expires_at DATETIME;"
            ];
            foreach ($missingUserCols as $uCol => $uSql) {
                if (!in_array($uCol, $userCols)) {
                    $this->pdo->exec($uSql);
                }
            }

            // Historial de Recargas de Bolsas y Facturaciones B2B
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS b2b_billing_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                tipo_movimiento VARCHAR(30) NOT NULL, -- RECARGA_PREPAGO, CONSUMO_FIRMA, FACTURACION_MENSUAL
                modalidad VARCHAR(20) NOT NULL, -- PREPAGO, POSTPAGO
                cantidad_firmas INTEGER NOT NULL,
                monto_clp INTEGER DEFAULT 0,
                saldo_anterior INTEGER DEFAULT 0,
                saldo_nuevo INTEGER DEFAULT 0,
                descripcion TEXT,
                documento_tributario_folio VARCHAR(50),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            );");

            // Tabla de Órdenes y Pagos Online (Mercado Pago, Webpay, Khipu)
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS payment_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                external_reference VARCHAR(100) NOT NULL UNIQUE,
                payment_type VARCHAR(30) NOT NULL, -- B2B_PACK, DOCUMENT_RETAIL
                company_id INTEGER,
                user_id INTEGER,
                document_id INTEGER,
                pack_key VARCHAR(50),
                monto_neto INTEGER DEFAULT 0,
                monto_total INTEGER NOT NULL,
                moneda VARCHAR(10) DEFAULT 'CLP',
                estado VARCHAR(30) DEFAULT 'PENDIENTE', -- PENDIENTE, APROBADO, RECHAZADO, CANCELADO
                gateway VARCHAR(30) DEFAULT 'MERCADO_PAGO',
                preference_id VARCHAR(150),
                mp_payment_id VARCHAR(100),
                metadata_json TEXT,
                raw_response TEXT,
                rescue_sent_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
            );");

            // Paso 2: Plantillas Propias de la Empresa con Variables Dinámicas (SOL-0066)
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS company_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                slug VARCHAR(100) NOT NULL,
                titulo VARCHAR(200) NOT NULL,
                categoria VARCHAR(50) DEFAULT 'laboral', -- laboral, comercial, rrhh, arriendo
                cuerpo_texto TEXT NOT NULL, -- Texto legal con variables {{NOMBRE}}, {{RUT}}, {{CARGO}}, etc.
                variables_json TEXT, -- Array JSON con los nombres de variables detectadas
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            );");

            // Paso 2: Expediente Digital del Trabajador (Agrupador histórico de contratos, anexos y finiquitos)
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS worker_folders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                colaborador_rut VARCHAR(20) NOT NULL,
                colaborador_nombre VARCHAR(150) NOT NULL,
                colaborador_email VARCHAR(150),
                colaborador_telefono VARCHAR(30),
                cargo_actual VARCHAR(150),
                fecha_ingreso DATE,
                estado_laboral VARCHAR(30) DEFAULT 'ACTIVO', -- ACTIVO, LICENCIA, FINIQUITADO
                total_documentos INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                UNIQUE(company_id, colaborador_rut)
            );");

            // Paso 2: Documentos asociados al expediente del trabajador
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS worker_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                worker_folder_id INTEGER NOT NULL,
                document_id INTEGER,
                tipo_documento VARCHAR(50) NOT NULL, -- CONTRATO_INICIAL, ANEXO_40_HORAS, ANEXO_SUELDO, FINIQUITO, EPP, VACACIONES
                titulo VARCHAR(200) NOT NULL,
                fecha_emision DATE,
                estado_firma VARCHAR(30) DEFAULT 'PENDIENTE',
                pdf_r2_key VARCHAR(255),
                pdf_sha256 VARCHAR(64),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (worker_folder_id) REFERENCES worker_folders(id) ON DELETE CASCADE,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
            );");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                rut VARCHAR(20) NOT NULL UNIQUE,
                razon_social VARCHAR(200) NOT NULL,
                giro VARCHAR(200),
                direccion VARCHAR(255),
                comuna VARCHAR(100),
                ciudad VARCHAR(100),
                rep_legal_nombre VARCHAR(150),
                rep_legal_rut VARCHAR(20),
                rep_legal_email VARCHAR(150),
                whatsapp_phone VARCHAR(30),
                whatsapp_instance_id VARCHAR(100),
                plan_nombre VARCHAR(50) DEFAULT 'PLAN_INICIAL', -- PLAN_INICIAL, PLAN_PYME, PLAN_PRO, PLAN_ENTERPRISE
                plan_estado VARCHAR(20) DEFAULT 'ACTIVO', -- ACTIVO, SUSPENDIDO, PRUEBA, VENCIDO
                firmas_totales_plan INTEGER DEFAULT 50, -- cupo mensual contratado
                firmas_utilizadas INTEGER DEFAULT 0, -- consumo acumulado
                costo_mensual_uf DECIMAL(6,2) DEFAULT 2.50, -- valor mensual en UF o CLP
                costo_firma_extra_clp INTEGER DEFAULT 4990, -- valor por firma extra
                modalidad_cobro VARCHAR(20) DEFAULT 'PREPAGO', -- PREPAGO, POSTPAGO
                saldo_firmas_prepago INTEGER DEFAULT 0, -- Bolsa de firmas prepagadas
                umbral_max_postpago INTEGER DEFAULT 0, -- Límite máximo de crédito para emisión continua (0 por defecto)
                gasto_acumulado_periodo INTEGER DEFAULT 0, -- Monto CLP acumulado para factura mensual DTE 33
                tarifa_tramo_clp INTEGER DEFAULT 7990, -- Precio unitario base inicial (igual a precio_base_subir_pdf)
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS b2b_batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER,
                user_id INTEGER,
                titulo VARCHAR(255) NOT NULL,
                template_slug VARCHAR(100) NOT NULL DEFAULT 'contrato-individual-trabajo',
                total_records INTEGER DEFAULT 0,
                valid_records INTEGER DEFAULT 0,
                invalid_records INTEGER DEFAULT 0,
                status VARCHAR(30) DEFAULT 'BORRADOR', -- BORRADOR, EN_FIRMA, COMPLETADO
                batch_hash VARCHAR(64),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS b2b_batch_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                batch_id INTEGER NOT NULL,
                document_id INTEGER,
                fila_num INTEGER NOT NULL,
                colaborador_nombre VARCHAR(150) NOT NULL,
                colaborador_rut VARCHAR(20) NOT NULL,
                colaborador_email VARCHAR(150),
                colaborador_telefono VARCHAR(30),
                cargo VARCHAR(150),
                sueldo VARCHAR(50),
                datos_extra TEXT, -- JSON con campos dinámicos
                is_valid BOOLEAN DEFAULT 1,
                error_detalle TEXT,
                status VARCHAR(30) DEFAULT 'PENDIENTE', -- PENDIENTE, FIRMADO, ERROR
                fecha_firma DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (batch_id) REFERENCES b2b_batches(id) ON DELETE CASCADE,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
            );");

        } catch (Exception $e) {
            error_log("Database migration warning: " . $e->getMessage());
        }
    }

    public function initSchema() {
        $queries = [
            // Usuarios / Clientes
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rut VARCHAR(12),
                nombre VARCHAR(100) NOT NULL,
                apellido VARCHAR(100) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                telefono VARCHAR(20),
                password_hash VARCHAR(255),
                tipo_persona VARCHAR(10) DEFAULT 'NATURAL', -- NATURAL / JURIDICA
                auth_provider VARCHAR(20) DEFAULT 'LOCAL',
                google_id VARCHAR(100),
                avatar_url TEXT,
                empresa_razon_social VARCHAR(200),
                empresa_rut VARCHAR(20),
                email_verificado BOOLEAN DEFAULT 0,
                ultimo_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            // Catálogo de Plantillas y Trámites
            "CREATE TABLE IF NOT EXISTS templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug VARCHAR(100) NOT NULL UNIQUE,
                nombre VARCHAR(200) NOT NULL,
                categoria VARCHAR(50) NOT NULL, -- inmobiliario, laboral, comercial, tramites
                descripcion TEXT,
                precio_base INTEGER DEFAULT 4990, -- en CLP
                permite_firma_digital BOOLEAN DEFAULT 1,
                requiere_notaria_exclusiva BOOLEAN DEFAULT 0,
                alerta_legal TEXT,
                tiempo_estimado_min INTEGER DEFAULT 5,
                icono VARCHAR(50) DEFAULT 'fa-file-signature',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            // Documentos / Contratos generados
            "CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo_verificacion VARCHAR(32) NOT NULL UNIQUE,
                template_id INTEGER,
                user_id INTEGER,
                titulo VARCHAR(255) NOT NULL,
                estado VARCHAR(30) DEFAULT 'BORRADOR', -- BORRADOR, PENDIENTE_FIRMAS, COMPLETADO, RECHAZADO, VENCIDO
                datos_formulario TEXT, -- JSON con los valores completados
                contenido_html TEXT,
                pdf_r2_key VARCHAR(255),
                pdf_sha256 VARCHAR(64),
                tipo_firma VARCHAR(10) DEFAULT 'FES', -- FES (Firma Simple) o FEA (Firma Avanzada Spot)
                monto_pagado INTEGER DEFAULT 0,
                estado_pago VARCHAR(20) DEFAULT 'PENDIENTE', -- PENDIENTE, PAGADO, FALLIDO
                token_pago VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );",

            // Firmantes de cada documento
            "CREATE TABLE IF NOT EXISTS document_signers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_id INTEGER NOT NULL,
                rol_firmante VARCHAR(50) NOT NULL, -- ARRENDADOR, ARRENDATARIO, EMPLEADOR, TRABAJADOR, etc.
                rut VARCHAR(50) NOT NULL,
                tipo_documento VARCHAR(20) DEFAULT 'RUT', -- RUT / PASAPORTE
                numero_identificacion VARCHAR(50),
                pais_emisor VARCHAR(100) DEFAULT 'Chile',
                nombre_completo VARCHAR(200) NOT NULL,
                es_representante BOOLEAN DEFAULT 0,
                empresa_razon_social VARCHAR(200),
                empresa_rut VARCHAR(20),
                email VARCHAR(150) NOT NULL,
                telefono VARCHAR(20),
                orden_firma INTEGER DEFAULT 1,
                token_acceso VARCHAR(64) UNIQUE,
                estado_firma VARCHAR(20) DEFAULT 'PENDIENTE', -- PENDIENTE, NOTIFICADO, FIRMADO, RECHAZADO
                metodo_firma VARCHAR(30) DEFAULT 'FES', -- FES (ClaveUnica/SMS), FEA (Avanzada)
                fecha_firma DATETIME,
                ip_firma VARCHAR(45),
                user_agent_firma TEXT,
                evidencia_firma TEXT, -- JSON con detalles de auditoría
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            );",

            // Parámetros y Tarifas Globales del Sistema
            "CREATE TABLE IF NOT EXISTS system_settings (
                key VARCHAR(50) PRIMARY KEY,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            // Registro de Auditoría y Trazabilidad (Cadena de Custodia)
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_id INTEGER NOT NULL,
                evento VARCHAR(50) NOT NULL,
                descripcion TEXT,
                ip VARCHAR(45),
                hash_estado VARCHAR(64),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            );",

            // Bitácora de Mensajería WhatsApp Gateway
            "CREATE TABLE IF NOT EXISTS whatsapp_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_id INTEGER NULL,
                company_id INTEGER NULL,
                instance_name VARCHAR(100) NOT NULL,
                destinatario_telefono VARCHAR(30) NOT NULL,
                destinatario_nombre VARCHAR(150) NULL,
                tipo_mensaje VARCHAR(50) NOT NULL,
                mensaje TEXT NOT NULL,
                estado VARCHAR(30) DEFAULT 'ENVIADO',
                gateway_response TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
            );"
        ];

        foreach ($queries as $sql) {
            $this->pdo->exec($sql);
        }

        // Migración idempotente: columna tipo_firma e is_simulation en documents si no existen
        try {
            $colsDoc = $this->pdo->query("PRAGMA table_info(documents)")->fetchAll(PDO::FETCH_ASSOC);
            $hasTipoFirma = false;
            $hasIsSimulation = false;
            foreach ($colsDoc as $cd) {
                if (($cd['name'] ?? '') === 'tipo_firma') {
                    $hasTipoFirma = true;
                }
                if (($cd['name'] ?? '') === 'is_simulation') {
                    $hasIsSimulation = true;
                }
            }
            if (!$hasTipoFirma) {
                $this->pdo->exec("ALTER TABLE documents ADD COLUMN tipo_firma VARCHAR(10) DEFAULT 'FES';");
            }
            if (!$hasIsSimulation) {
                $this->pdo->exec("ALTER TABLE documents ADD COLUMN is_simulation INTEGER DEFAULT 0;");
            }
        } catch (Exception $eCol) {
            // Ignorar si ya existe
        }

        // Sembrar catálogo de plantillas inicial
        $this->seedTemplates();
    }

    private function seedTemplates() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM templates");
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $seeds = [
            [
                'slug' => 'contrato-arriendo-ley-21461',
                'nombre' => 'Contrato de Arriendo Residencial (Ley Devuélveme Mi Casa)',
                'categoria' => 'inmobiliario',
                'descripcion' => 'Contrato blindado bajo Ley N° 21.461 y N° 18.101. Incluye cláusula de restitución anticipada express de 10 días y renuncia a prórroga tácita.',
                'precio_base' => 9990,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => '100% legal con Firma Electrónica Avanzada (FEA) o Simple certificada.',
                'tiempo_estimado_min' => 7,
                'icono' => 'fa-home'
            ],
            [
                'slug' => 'promesa-compraventa-inmueble',
                'nombre' => 'Promesa de Compraventa de Inmueble',
                'categoria' => 'inmobiliario',
                'descripcion' => 'Contrato preparatorio conforme al Artículo 1554 del Código Civil, con arras y cláusula penal de resguardo.',
                'precio_base' => 14990,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Válido en instrumento privado. La escritura definitiva requerirá notario.',
                'tiempo_estimado_min' => 10,
                'icono' => 'fa-handshake'
            ],
            [
                'slug' => 'acta-entrega-llaves-e-inventario',
                'nombre' => 'Acta de Entrega de Inmueble e Inventario',
                'categoria' => 'inmobiliario',
                'descripcion' => 'Constancia formal de recepción física y estado de conservación de la propiedad arrendada.',
                'precio_base' => 4990,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Instrumento privado con validez probatoria inmediata.',
                'tiempo_estimado_min' => 5,
                'icono' => 'fa-key'
            ],
            [
                'slug' => 'finiquito-laboral-mutuo-acuerdo',
                'nombre' => 'Finiquito Laboral por Mutuo Acuerdo',
                'categoria' => 'laboral',
                'descripcion' => 'Término de la relación de trabajo según Art. 159 N° 1 y Art. 177 del Código del Trabajo, con ratificación digital.',
                'precio_base' => 7990,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Requiere ratificación digital ante ministro de fe o portal Dirección del Trabajo.',
                'tiempo_estimado_min' => 8,
                'icono' => 'fa-file-invoice-dollar'
            ],
            [
                'slug' => 'contrato-individual-trabajo',
                'nombre' => 'Contrato Individual de Trabajo',
                'categoria' => 'laboral',
                'descripcion' => 'Conforme a los Arts. 9 y 10 del Código del Trabajo. Apto para jornada ordinaria o teletrabajo.',
                'precio_base' => 5990,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Plenamente válido entre empleador y trabajador sin trámite notarial.',
                'tiempo_estimado_min' => 6,
                'icono' => 'fa-user-tie'
            ],
            [
                'slug' => 'reconocimiento-deuda-pagare-privado',
                'nombre' => 'Reconocimiento de Deuda y Compromiso de Pago',
                'categoria' => 'comercial',
                'descripcion' => 'Documento privado con valor probatorio civil mercantil, calendario de cuotas e intereses moratorios.',
                'precio_base' => 8990,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Válido como instrumento privado. Puede homologarse a título ejecutivo mediante gestión judicial preparatoria.',
                'tiempo_estimado_min' => 6,
                'icono' => 'fa-money-bill-wave'
            ],
            [
                'slug' => 'acuerdo-confidencialidad-nda',
                'nombre' => 'Acuerdo de Confidencialidad y No Divulgación (NDA)',
                'categoria' => 'comercial',
                'descripcion' => 'Protección integral de secretos industriales, know-how y datos sensibles entre partes y proveedores.',
                'precio_base' => 6990,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => '100% vinculante en sede civil por simple acuerdo entre partes.',
                'tiempo_estimado_min' => 5,
                'icono' => 'fa-user-shield'
            ],
            [
                'slug' => 'prestacion-servicios-honorarios',
                'nombre' => 'Contrato de Prestación de Servicios Profesionales',
                'categoria' => 'comercial',
                'descripcion' => 'Regulado por los Arts. 2006 y ss. del Código Civil. Desacopla la relación de la subordinación laboral.',
                'precio_base' => 5990,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Instrumento civil privado de plena validez jurídica.',
                'tiempo_estimado_min' => 5,
                'icono' => 'fa-briefcase'
            ],
            [
                'slug' => 'declaracion-jurada-residencia',
                'nombre' => 'Declaración Jurada Simple de Residencia',
                'categoria' => 'tramites',
                'descripcion' => 'Acreditación de domicilio particular para bancos, casas comerciales, colegios o trámites varios.',
                'precio_base' => 3490,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Válida según Ley 19.799. No requiere notario salvo que la institución exija explícitamente escritura pública.',
                'tiempo_estimado_min' => 3,
                'icono' => 'fa-map-marker-alt'
            ],
            [
                'slug' => 'declaracion-jurada-ingresos-informales',
                'nombre' => 'Declaración Jurada de Ingresos (Actividades Informales / Independientes)',
                'categoria' => 'tramites',
                'descripcion' => 'Acreditación de ingresos percibidos sin contrato laboral formal para postulaciones y trámites.',
                'precio_base' => 3490,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Declaración jurada simple bajo responsabilidad penal del declarante.',
                'tiempo_estimado_min' => 4,
                'icono' => 'fa-coins'
            ],
            [
                'slug' => 'salvoconducto-mudanza',
                'nombre' => 'Declaración Jurada de Traslado de Enseres (Mudanza)',
                'categoria' => 'tramites',
                'descripcion' => 'Conforme a la Ley N° 20.227 para el traslado legítimo de muebles y enseres domésticos.',
                'precio_base' => 4490,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Válida ante Carabineros de Chile según la ley vigente sobre salvoconductos.',
                'tiempo_estimado_min' => 5,
                'icono' => 'fa-truck-moving'
            ],
            [
                'slug' => 'poder-especial-privado',
                'nombre' => 'Poder Especial y Delegación de Facultades Simples',
                'categoria' => 'tramites',
                'descripcion' => 'Mandato para gestiones administrativas, retiro de encomiendas, trámites ante colegios o municipalidades.',
                'precio_base' => 4490,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Apto para trámites administrativos simples. No aplica para juicios ni venta de bienes raíces.',
                'tiempo_estimado_min' => 4,
                'icono' => 'fa-file-signature'
            ],
            [
                'slug' => 'autorizacion-domicilio-tributario-sii',
                'nombre' => 'Autorización de Uso de Inmueble para Domicilio Tributario (SII)',
                'categoria' => 'tramites',
                'descripcion' => 'Autorización del propietario o arrendatario para que una empresa acredite domicilio fiscal ante el SII.',
                'precio_base' => 5490,
                'permite_firma_digital' => 1,
                'requiere_notaria_exclusiva' => 0,
                'alerta_legal' => 'Admitido por el Servicio de Impuestos Internos con verificación de firma y rol de avalúo.',
                'tiempo_estimado_min' => 5,
                'icono' => 'fa-building'
            ]
        ];

        $stmt = $this->pdo->prepare("INSERT INTO templates (slug, nombre, categoria, descripcion, precio_base, permite_firma_digital, requiere_notaria_exclusiva, alerta_legal, tiempo_estimado_min, icono) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($seeds as $item) {
            $stmt->execute([
                $item['slug'],
                $item['nombre'],
                $item['categoria'],
                $item['descripcion'],
                $item['precio_base'],
                $item['permite_firma_digital'],
                $item['requiere_notaria_exclusiva'],
                $item['alerta_legal'],
                $item['tiempo_estimado_min'],
                $item['icono']
            ]);
        }
    }
}
