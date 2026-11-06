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

    public function initSchema() {
        $queries = [
            // Usuarios / Clientes
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rut VARCHAR(12) NOT NULL UNIQUE,
                nombre VARCHAR(100) NOT NULL,
                apellido VARCHAR(100) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                telefono VARCHAR(20),
                password_hash VARCHAR(255),
                tipo_persona VARCHAR(10) DEFAULT 'NATURAL', -- NATURAL / JURIDICA
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
                rut VARCHAR(12) NOT NULL,
                nombre_completo VARCHAR(200) NOT NULL,
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
            );"
        ];

        foreach ($queries as $sql) {
            $this->pdo->exec($sql);
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
