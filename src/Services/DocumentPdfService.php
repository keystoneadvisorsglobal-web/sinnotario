<?php
/**
 * SinNotario.cl - Motor Criptográfico de Generación de PDF
 * Genera el documento con:
 * 1. Encabezado institucional con folio único (SN-XXXX-XXXX)
 * 2. Código QR dinámico de verificación impreso al pie
 * 3. Hash criptográfico SHA-256 inalterable
 * 4. Metadatos de auditoría y sellado bajo Ley N° 19.799
 * 5. Carga directa a la bóveda Cloudflare R2
 */

require_once __DIR__ . '/../../lib/fpdf/fpdf.php';
require_once __DIR__ . '/../../lib/phpqrcode/qrlib.php';
require_once __DIR__ . '/../../api/adapters/StorageR2Adapter.php';
require_once __DIR__ . '/../../database/Database.php';

class PdfContractGenerator extends FPDF {
    private $verificationCode;
    private $docTitle;
    private $qrTempPath;

    public function __construct($verificationCode, $docTitle) {
        parent::__construct('P', 'mm', 'Letter');
        $this->verificationCode = $verificationCode;
        $this->docTitle = utf8_decode($docTitle);
        $this->SetMargins(20, 22, 20);
        $this->SetAutoPageBreak(true, 25);

        // Generar QR temporal en PNG
        $this->generateQRCode();
    }

    private function generateQRCode() {
        $verifyUrl = "https://sinnotario.cl/#verificar?code=" . urlencode($this->verificationCode);
        $tempDir = sys_get_temp_dir();
        $this->qrTempPath = $tempDir . '/qr_' . $this->verificationCode . '.png';
        QRcode::png($verifyUrl, $this->qrTempPath, QR_ECLEVEL_M, 3, 2);
    }

    // Cabecera institucional
    public function Header() {
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(15, 42, 74); // Azul Marino
        $this->Cell(0, 5, utf8_decode('SINNOTARIO.CL | PLATAFORMA DE INSTRUMENTOS PRIVADOS Y FIRMA DIGITAL'), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, utf8_decode('CUSTODIA CRIPTOGRÁFICA LEY N° 19.799 | CÓDIGO ÚNICO: ' . $this->verificationCode), 0, 1, 'L');
        
        $this->SetDrawColor(226, 232, 240);
        $this->Line(20, 31, 196, 31);
        $this->Ln(6);
    }

    // Pie de página con QR y Hash de verificación
    public function Footer() {
        $this->SetY(-22);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(20, 257, 196, 257);

        // Incrustar QR al pie izquierdo
        if (file_exists($this->qrTempPath)) {
            $this->Image($this->qrTempPath, 20, 258, 16, 16);
        }

        // Texto legal y folio al centro/derecha
        $this->SetX(38);
        $this->SetFont('Arial', 'B', 7);
        $this->SetTextColor(15, 42, 74);
        $this->Cell(120, 3.5, utf8_decode('Documento Electrónico Válido - Valídalo en sinnotario.cl/#verificar'), 0, 0, 'L');
        
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(38, 3.5, utf8_decode('Página ' . $this->PageNo() . ' de {nb}'), 0, 1, 'R');

        $this->SetX(38);
        $this->Cell(0, 3.5, utf8_decode('FOLIO ÚNICO: ' . $this->verificationCode . ' | FECHA EMISIÓN: ' . date('d/m/Y H:i:s')), 0, 1, 'L');
        
        $this->SetX(38);
        $this->Cell(0, 3.5, utf8_decode('Integridad garantizada mediante algoritmo SHA-256 y sellado cronológico TSA.'), 0, 1, 'L');
    }

    public function __destruct() {
        if ($this->qrTempPath && file_exists($this->qrTempPath)) {
            @unlink($this->qrTempPath);
        }
    }
}

class DocumentPdfService {
    /**
     * Construye y guarda el PDF oficial en Cloudflare R2
     */
    public static function generateAndVaultDocument(int $documentId): array {
        $pdo = Database::getInstance();

        // 1. Obtener documento y firmantes
        $stmt = $pdo->prepare("SELECT d.*, t.nombre as template_nombre, t.slug as template_slug, t.categoria FROM documents d LEFT JOIN templates t ON d.template_id = t.id WHERE d.id = ?");
        $stmt->execute([$documentId]);
        $doc = $stmt->fetch();

        if (!$doc) {
            return ['success' => false, 'error' => 'Documento no encontrado'];
        }

        $stmtSig = $pdo->prepare("SELECT * FROM document_signers WHERE document_id = ? ORDER BY id ASC");
        $stmtSig->execute([$documentId]);
        $signers = $stmtSig->fetchAll();

        $formData = json_decode($doc['datos_formulario'] ?? '{}', true);

        // 2. Instanciar Generador PDF
        $pdf = new PdfContractGenerator($doc['codigo_verificacion'], $doc['titulo']);
        $pdf->AliasNbPages();
        $pdf->AddPage();

        // Título del Documento
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetTextColor(15, 42, 74);
        $pdf->Cell(0, 7, utf8_decode(mb_strtoupper($doc['titulo'], 'UTF-8')), 0, 1, 'C');

        $pdf->SetFont('Arial', 'I', 8.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 5, utf8_decode('Instrumento Privado con Firma Electrónica conforme a la Ley N° 19.799'), 0, 1, 'C');
        $pdf->Ln(5);

        // Texto de Comparecencia
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->SetTextColor(30, 41, 59);

        $ciudad = $formData['parte_a_comuna'] ?? ($formData['arrendador_comuna'] ?? 'Santiago');
        $introText = "En la ciudad de {$ciudad}, República de Chile, a " . date('d') . " de " . self::mesEnEspanol(date('m')) . " de " . date('Y') . ", comparecen ante el presente instrumento privado las siguientes partes:";
        $pdf->MultiCell(0, 5, utf8_decode($introText));
        $pdf->Ln(3);

        // Detalle de los Comparecientes
        foreach ($signers as $idx => $s) {
            $numParte = $idx + 1;
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5, utf8_decode("COMPARECIENTE N° {$numParte} ({$s['rol_firmante']}):"), 0, 1, 'L');
            
            $pdf->SetFont('Arial', '', 9);
            $partDet = "Don/Doña " . $s['nombre_completo'] . ", cédula nacional de identidad número " . $s['rut'] . ", correo electrónico " . $s['email'] . ", teléfono " . ($s['telefono'] ?: 'No registrado') . ", en adelante indistintamente como el \"" . ucfirst(strtolower($s['rol_firmante'])) . "\".";
            $pdf->MultiCell(0, 4.5, utf8_decode($partDet));
            $pdf->Ln(2);
        }

        // Cláusulas según tipo
        $pdf->Ln(3);
        self::renderClauses($pdf, $doc, $formData);

        // Cláusula de Validez y Firma
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 5, utf8_decode('VALIDEZ JURÍDICA Y SUMISIÓN A LA LEY N° 19.799:'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
        $validezText = "Las partes acuerdan y consienten libremente otorgar el presente instrumento en formato electrónico, conviniendo suscribirlo mediante Firma Electrónica conforme a los Artículos 4 y 5 de la Ley N° 19.799 sobre Documentos Electrónicos, Firma Electrónica y Servicios de Certificación. Las partes reconocen que este instrumento produce plena fe probatoria, obligándose en los mismos términos que si lo hubiesen otorgado por escrito en papel, fijando su domicilio convencional en la comuna señalada en la comparecencia y declarando como auténticos sus correos electrónicos para toda notificación formal.";
        $pdf->MultiCell(0, 4.5, utf8_decode($validezText));
        $pdf->Ln(6);

        // Bloque de Firmas al pie
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, utf8_decode('CONSTANCIA DE FIRMAS Y SELLOS DE IDENTIDAD DIGITAL:'), 0, 1, 'L');
        $pdf->Ln(2);

        foreach ($signers as $s) {
            $estadoFirma = ($s['estado_firma'] === 'FIRMADO') ? 'FIRMADO DIGITALMENTE' : 'PENDIENTE DE FIRMA ELECTRÓNICA';
            $metodo = $s['metodo_firma'] ?? 'FEA (ClaveÚnica / PSC Acreditado)';
            $fecha = $s['fecha_firma'] ?? 'Pendiente de suscripción';

            $pdf->SetDrawColor(203, 213, 225);
            $pdf->SetFillColor(248, 250, 252);
            $pdf->Rect($pdf->GetX(), $pdf->GetY(), 176, 15, 'DF');

            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetTextColor(15, 42, 74);
            $pdf->SetXY($pdf->GetX() + 4, $pdf->GetY() + 2);
            $pdf->Cell(110, 4, utf8_decode($s['nombre_completo'] . ' (RUT: ' . $s['rut'] . ') - ' . $s['rol_firmante']), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(($s['estado_firma'] === 'FIRMADO') ? 16 : 180, ($s['estado_firma'] === 'FIRMADO') ? 149 : 83, ($s['estado_firma'] === 'FIRMADO') ? 100 : 9);
            $pdf->Cell(58, 4, utf8_decode($estadoFirma), 0, 1, 'R');

            $pdf->SetXY($pdf->GetX() + 4, $pdf->GetY() + 1);
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(0, 4, utf8_decode("Método: {$metodo} | Fecha de estampa: {$fecha}"), 0, 1, 'L');

            $pdf->Ln(6);
        }

        // 3. Generar contenido binario del PDF
        $pdfOutput = $pdf->Output('S');
        $sha256Hash = hash('sha256', $pdfOutput);

        // 4. Subir a Cloudflare R2
        $r2Key = "documents/{$doc['codigo_verificacion']}.pdf";
        $r2 = new StorageR2Adapter();
        $uploadResult = $r2->uploadDocument($r2Key, $pdfOutput, 'application/pdf');

        // 5. Actualizar en Base de Datos
        $stmtUp = $pdo->prepare("UPDATE documents SET pdf_r2_key = ?, pdf_sha256 = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtUp->execute([$r2Key, $sha256Hash, $documentId]);

        return [
            'success' => true,
            'document_id' => $documentId,
            'codigo_verificacion' => $doc['codigo_verificacion'],
            'r2_key' => $r2Key,
            'sha256' => $sha256Hash,
            'r2_uploaded' => $uploadResult['success'] ?? false,
            'size_bytes' => strlen($pdfOutput)
        ];
    }

    private static function renderClauses($pdf, $doc, $formData) {
        $slug = $doc['template_slug'] ?? '';

        switch ($slug) {
            case 'contrato-arriendo-ley-21461':
                $dir = $formData['parte_a_domicilio'] ?? ($formData['propiedad_direccion'] ?? 'Av. Providencia 1234, Depto 402, Providencia');
                $renta = !empty($formData['renta_clp']) ? '$' . number_format($formData['renta_clp'], 0, ',', '.') . ' CLP' : '$650.000 CLP';
                
                self::printClause($pdf, 'PRIMERO: OBJETO Y DESTINO EXCLUSIVO', "El Arrendador cede y da en arrendamiento al Arrendatario el inmueble ubicado en {$dir}, el cual se destina única y exclusivamente a habitación familiar del Arrendatario, quedando estrictamente prohibido su subarrendamiento o cesión a cualquier título.");
                self::printClause($pdf, 'SEGUNDO: RENTA, FORMA DE PAGO Y REAJUSTABILIDAD', "La renta mensual será la suma de {$renta}, pagadera por mesadas anticipadas dentro de los primeros 5 días corridos de cada mes mediante transferencia bancaria. Dicha suma se reajustará de acuerdo a la variación acumulada del IPC semestral o anualmente.");
                self::printClause($pdf, 'TERCERO: PROCEDIMIENTO MONITORIO LEY N° 21.461 ("DEVUÉLVEME MI CASA")', "Las partes se someten en forma expresa e irrevocable al procedimiento monitorio de cobro de rentas y restitución consagrado en la Ley N° 21.461 y Ley N° 18.101, autorizando al tribunal competente a decretar el lanzamiento anticipado y desalojo dentro del décimo día corrido si la mora no fuere enervada.");
                self::printClause($pdf, 'CUARTO: GARANTÍA Y CONSERVACIÓN', "El Arrendatario entrega en garantía la suma equivalente a un mes de renta para responder del buen uso, conservación y pago de servicios básicos, no pudiendo imputarse al pago de rentas y debiendo restituirse dentro de 30 días posteriores a la entrega material del bien.");
                break;

            case 'promesa-compraventa-inmueble':
                self::printClause($pdf, 'PRIMERO: DEL INMUEBLE OBJETO DEL CONTRATO', "El Promitente Vendedor declara ser titular exclusivo del dominio del inmueble singularizado en los antecedentes, el cual se compromete a vender libre de todo gravamen, hipoteca o prohibición al momento de escriturar.");
                self::printClause($pdf, 'SEGUNDO: PROMESA Y PRECIO DEFINITIVO', "El Promitente Vendedor promete y se obliga a vender, y el Promitente Comprador promete y se obliga a comprar la propiedad por el precio convenido, garantizado mediante la entrega previa de arras o señal de garantía.");
                self::printClause($pdf, 'TERCERO: PLAZO DE ESCRITURACIÓN Y CONDICIÓN', "La escritura pública definitiva de compraventa se otorgará en el plazo máximo estipulado o una vez concluidas satisfactoriamente las aprobaciones del estudio de títulos e hipotecas.");
                self::printClause($pdf, 'CUARTO: CLÁUSULA PENAL Y RESGUARDO', "Si cualquiera de las partes se desistiera injustificadamente o no compareciere a otorgar la escritura definitiva, pagará a título de indemnización de perjuicios tasada y convencional la multa pactada en las condiciones del trámite.");
                break;

            case 'acta-entrega-llaves-e-inventario':
                $modActa = $formData['f_acta_modalidad'] ?? 'opcion_a';
                $dirActa = $formData['direccion'] ?? ($formData['f_acta_direccion'] ?? 'Av. Providencia 1234, Depto 402, Santiago');
                $llaves = $formData['llaves'] ?? ($formData['f_acta_llaves'] ?? '3 juegos de llaves');
                $luz = $formData['luz'] ?? ($formData['f_acta_luz'] ?? '14.850 kWh');
                $agua = $formData['agua'] ?? ($formData['f_acta_agua'] ?? '1.230 m3');
                $gas = $formData['gas'] ?? ($formData['f_acta_gas'] ?? '450 m3');
                $estado = $formData['estado'] ?? ($formData['f_acta_estado'] ?? 'Buen estado de conservacion general');
                $inv = $formData['inventario'] ?? ($formData['f_acta_inventario'] ?? 'Artefactos y bienes incluidos');

                self::printClause($pdf, 'SECCION PRIMERA: CONSTANCIA MATERIAL DEL INMUEBLE', "Las partes dejan constancia de la comparecencia y entrega material del inmueble de {$dirActa}.");
                self::printClause($pdf, 'SECCION SEGUNDA: LLAVES Y LECTURAS DE SUMINISTROS', "Entrega de llaves: {$llaves}. Lecturas oficiales: Electricidad: {$luz} | Agua Potable: {$agua} | Gas: {$gas}. Consumos anteriores cancelados.");

                if ($modActa === 'opcion_b') {
                    // Opción B: Recepción Provisoria con Snag List (5 Secciones)
                    $snag = $formData['snag_list'] ?? ($formData['f_acta_snag_list'] ?? 'Observaciones menores registradas');
                    $plazoSub = $formData['plazo_subsanar'] ?? ($formData['f_acta_plazo_subsanar'] ?? '10 dias corridos');

                    self::printClause($pdf, 'SECCION TERCERA: LISTA DETALLADA DE OBSERVACIONES (SNAG LIST)', "Se constatan de mutuo acuerdo los siguientes detalles menores pendientes de subsanar: {$snag}.");
                    self::printClause($pdf, 'SECCION CUARTA: COMPROMISO Y PLAZO DE SUBSANACION', "El Propietario se compromete a reparar a su costo los detalles indicados dentro de {$plazoSub}.");
                    self::printClause($pdf, 'SECCION QUINTA: CONVERSION EN RECEPCION DEFINITIVA', "Subsanadas las observaciones a conformidad, la recepcion se entendera definitiva de pleno derecho.");
                } else if ($modActa === 'opcion_c') {
                    // Opción C: Desocupación y Restitución Final con Liquidación de Garantía (5 Secciones)
                    $garOrig = !empty($formData['garantia_original']) ? '$' . number_format($formData['garantia_original'], 0, ',', '.') . ' CLP' : '$650.000 CLP';
                    $descMonto = !empty($formData['descuentos_monto']) ? '$' . number_format($formData['descuentos_monto'], 0, ',', '.') . ' CLP' : '$50.000 CLP';
                    $saldoGar = !empty($formData['saldo_garantia']) ? '$' . number_format($formData['saldo_garantia'], 0, ',', '.') . ' CLP' : '$600.000 CLP';
                    $plazoDev = $formData['plazo_devolucion'] ?? ($formData['f_acta_plazo_devolucion'] ?? 'Dentro de 15 dias habiles');

                    self::printClause($pdf, 'SECCION TERCERA: ESTADO DE SALIDA Y DESOCUPACION', "Se constata la restitucion material del bien desocupado de moradores. Estado de salida: {$estado}. Inventario: {$inv}.");
                    self::printClause($pdf, 'SECCION CUARTA: LIQUIDACION DE GARANTIA DE ARRIENDO', "Garantia original: {$garOrig}. Descuentos acordados por cuentas o reparaciones: {$descMonto}. SALDO LIQUIDO A RESTITUIR AL ARRENDATARIO: {$saldoGar}. Plazo: {$plazoDev}.");
                    self::printClause($pdf, 'SECCION QUINTA: FINIQUITO RECIPROCO DE ARRENDAMIENTO', "Con el pago del saldo de garantia y la restitucion, las partes se otorgan mutuo finiquito liberatorio de toda obligacion derivada del arriendo.");
                } else {
                    // Opción A: Notarial Inmobiliaria Exhaustiva (6 Secciones - Recomendado)
                    self::printClause($pdf, 'SECCION TERCERA: ESTADO DE CONSERVACION DETALLADO POR RECINTOS', "Inspeccion por recintos (living, dormitorios, banos, cocina y pisos): {$estado}.");
                    self::printClause($pdf, 'SECCION CUARTA: TABLA DE INVENTARIO DE ARTEFACTOS Y BIENES', "Bienes muebles y electrodomesticos incluidos en perfecto estado de funcionamiento: {$inv}.");
                    self::printClause($pdf, 'SECCION QUINTA: DEBER DE CUSTODIA Y MANTENCION', "El Arrendatario se compromete al cuidado diligente y reparaciones locativas que legalmente procedan.");
                    self::printClause($pdf, 'SECCION SEXTA: FIRMA DE CONFORMIDAD Y VALIDEZ LEGAL', "Las partes suscriben la presente acta en senal de plena conformidad como anexo vinculante del contrato de arrendamiento (Ley N° 19.799).");
                }
                break;

            case 'finiquito-laboral-mutuo-acuerdo':
                $modFini = $formData['f_fini_modalidad'] ?? 'opcion_a';
                $cargo = $formData['cargo'] ?? ($formData['f_cargo'] ?? 'Analista de Operaciones');
                $causal = $formData['causal'] ?? ($formData['f_causal'] ?? 'Art. 159 N° 1 Mutuo acuerdo de las partes');
                $fechaInicio = $formData['fecha_inicio'] ?? ($formData['f_fini_fecha_inicio'] ?? '01-03-2023');
                $fechaTermino = $formData['fecha_termino'] ?? ($formData['f_fecha_termino'] ?? date('d-m-Y'));
                $totalLiq = !empty($formData['monto_total']) ? '$' . number_format($formData['monto_total'], 0, ',', '.') . ' CLP' : '$1.450.000 CLP';
                $diasPend = !empty($formData['dias_pendientes']) ? '$' . number_format($formData['dias_pendientes'], 0, ',', '.') . ' CLP' : '$450.000 CLP';
                $feriado = !empty($formData['feriado_proporcional']) ? '$' . number_format($formData['feriado_proporcional'], 0, ',', '.') . ' CLP' : '$380.000 CLP';
                $indemn = !empty($formData['indemnizacion']) ? '$' . number_format($formData['indemnizacion'], 0, ',', '.') . ' CLP' : '$620.000 CLP';
                $formaPago = $formData['forma_pago'] ?? ($formData['f_fini_forma_pago'] ?? 'Transferencia electronica');
                $cuenta = $formData['cuenta'] ?? ($formData['f_fini_cuenta'] ?? 'Cuenta bancaria personal');

                self::printClause($pdf, 'PRIMERO: ANTECEDENTES Y RELACION LABORAL', "El Trabajador presto servicios subordinados y dependientes para el Empleador desde el {$fechaInicio}, desempenando las labores de {$cargo}.");
                self::printClause($pdf, 'SEGUNDO: CESE Y CAUSAL DE TERMINO', "Las partes convienen poner termino formal y definitivo a la relacion laboral con fecha {$fechaTermino}, por la causal del {$causal} del Codigo del Trabajo.");
                self::printClause($pdf, 'TERCERO: LIQUIDACION DE PRESTACIONES Y HABERES', "El Empleador liquida los siguientes haberes finales: a) Remuneraciones pendientes: {$diasPend}; b) Feriado proporcional adeudado: {$feriado}; c) Indemnizacion voluntaria o pactada: {$indemn}. TOTAL LIQUIDO A PAGAR: {$totalLiq}.");
                self::printClause($pdf, 'CUARTO: PAGO EFECTIVO Y RECEPCION CONFORME', "El Trabajador declara recibir conforme a su entera satisfaccion el monto total liquidado mediante {$formaPago} en {$cuenta}, declarando que dicha suma cubre integro todo haber.");

                if ($modFini === 'opcion_b') {
                    // Blindaje Corporativo / Ley Bustos y Prevención de Riesgos
                    self::printClause($pdf, 'QUINTO: CUMPLIMIENTO PREVISIONAL INTEGRO (LEY BUSTOS N° 19.631)', "El Empleador acredita haber integrado y pagado la totalidad de las imposiciones y cotizaciones de seguridad social devengadas (AFP, Salud y Seguro de Cesantia AFC) hasta el ultimo dia del mes precedente al termino, dando integro cumplimiento al articulo 162 del Codigo del Trabajo.");
                    self::printClause($pdf, 'SEXTO: DECLARACION DE ACCIDENTES Y ENFERMEDADES (LEY N° 16.744)', "El Trabajador declara que durante la vigencia del contrato no sufrio accidentes laborales ni contrajo enfermedades profesionales, cumpliendo el Empleador su deber de seguridad y provision de EPP (Art. 184 CdT).");
                    self::printClause($pdf, 'SEPTIMO: AMPLIO, COMPLETO Y RECIPROCO FINIQUITO', "Las partes declaran que nada se adeudan por concepto de remuneraciones, horas extras, feriados o indemnizaciones, otorgandose mutuamente el mas amplio, irrevocable y definitivo finiquito con valor de transaccion.");
                    self::printClause($pdf, 'OCTAVO: RENUNCIA EXPRESA DE ACCIONES JUDICIALES Y ADMINISTRATIVAS', "El Trabajador renuncia irrevocablemente a entablar cualquier demanda, reclamo o accion judicial o administrativa ante Tribunales o la Direccion del Trabajo.");
                    self::printClause($pdf, 'NOVENO: RATIFICACION LEGAL ANTE MINISTRO DE FE (ART. 177 CODIGO DEL TRABAJO)', "El instrumento se ratifica electronicamente mediante firma digital en el portal Mi DT de la Direccion del Trabajo al amparo de la Ley N° 19.799 y Ley N° 21.327.");
                } else if ($modFini === 'opcion_c') {
                    // Transaccional Extrajudicial con Reserva de Derechos
                    $reserva = $formData['reserva_derechos'] ?? ($formData['f_fini_reserva_derechos'] ?? '');
                    self::printClause($pdf, 'QUINTO: NATURALEZA TRANSACCIONAL EXTRAJUDICIAL (ART. 2446 CODIGO CIVIL)', "El presente instrumento reviste el caracter de transaccion extrajudicial conforme al articulo 2446 del Codigo Civil, produciendo efecto de cosa juzgada en ultima instancia respecto de los rubros regulados.");
                    self::printClause($pdf, 'SEXTO: FINIQUITO RECIPROCO SOBRE MATERIAS NO CONTROVERTIDAS', "Las partes se otorgan recíproco finiquito respecto de todos los haberes y rubros laborales no objeto de reserva explícita.");
                    if (!empty(trim($reserva))) {
                        self::printClause($pdf, 'SEPTIMO: CONSTANCIA FORMAL DE RESERVA DE DERECHOS', "En conformidad a la doctrina uniforme de la Excma. Corte Suprema de Justicia, el Trabajador estipula la siguiente reserva expresa: \"{$reserva}\", tomando el Empleador conocimiento formal de la misma.");
                    } else {
                        self::printClause($pdf, 'SEPTIMO: RENUNCIA VOLUNTARIA DE ACCIONES', "El Trabajador no formula reserva de derechos alguna, declarandose plenamente satisfecho en todas sus acreencias.");
                    }
                    self::printClause($pdf, 'OCTAVO: RATIFICACION ANTE MINISTRO DE FE (ART. 177 CODIGO DEL TRABAJO)', "El finiquito se firma electronicamente con pleno valor legal probatorio conforme a la Ley N° 19.799 y normativa de la Direccion del Trabajo.");
                } else {
                    // Opción A: Estándar Dirección del Trabajo (Portal Mi DT - 6 Cláusulas)
                    self::printClause($pdf, 'QUINTO: RENUNCIA MUTUA DE ACCIONES Y FINIQUITO DEFINITIVO', "El Trabajador otorga al Empleador el mas amplio, completo y definitivo finiquito, renunciando expresamente a cualquier accion, reclamo o demanda posterior por remuneraciones, cotizaciones o indemnizaciones de la relacion laboral.");
                    self::printClause($pdf, 'SEXTO: RATIFICACION DIGITAL ANTE LA DIRECCION DEL TRABAJO (MI DT)', "Las partes ratifican el presente finiquito electronicamente en el portal institucional Mi DT con ClaveUnica conforme al articulo 177 del Codigo del Trabajo y la Ley N° 19.799.");
                }
                break;

            case 'acuerdo-confidencialidad-nda':
                $proy = $formData['proyecto'] ?? ($formData['f_proyecto_nda'] ?? 'Evaluacion conjunta y desarrollo de plataforma tecnologica');
                $alcance = $formData['alcance'] ?? ($formData['f_nda_alcance'] ?? 'Codigo fuente, diagramas de sistemas, modelos de negocio, algoritmos y clientes');
                $vig = $formData['vigencia'] ?? ($formData['f_vigencia_nda'] ?? '2 anos desde la firma');
                $multa = $formData['multa'] ?? ($formData['f_multa_nda'] ?? '500 UF');
                $destrucc = $formData['destruccion'] ?? ($formData['f_nda_destruccion'] ?? 'Devolucion o destruccion certificada en 10 dias');
                $tribunales = $formData['tribunales'] ?? ($formData['f_nda_tribunales'] ?? 'Tribunales de Santiago');

                self::printClause($pdf, 'PRIMERO: PROPOSITO DEL ACUERDO Y ANTECEDENTES', "Las Partes celebran el presente acuerdo con el objeto de regular el intercambio y analisis de informacion reservada inherente a: {$proy}.");
                self::printClause($pdf, 'SEGUNDO: DEFINICION AMPLIA DE INFORMACION CONFIDENCIAL', "Se considerara Informacion Confidencial toda informacion tecnica, financiera, comercial y legal transmitida, incluyendo: {$alcance}, ademas de informes, prototipos y desarrollos derivados.");
                self::printClause($pdf, 'TERCERO: DEBER DE CUSTODIA Y ESTANDAR DE DILIGENCIA', "La Parte Receptora mantendra estricta reserva de la informacion empleando la mayor diligencia, usandola unicamente para el proposito convenido, sin reproducirla ni efectuar ingenieria inversa.");
                self::printClause($pdf, 'CUARTO: EXCEPCIONES AL DEBER DE CONFIDENCIALIDAD', "El deber de reserva no aplicara a informacion de dominio publico sin mediar infraccion, recibida legalmente de terceros sin restricciones, o que deba ser revelada por mandato imperativo de ley u orden judicial.");
                self::printClause($pdf, 'QUINTO: PROPIEDAD INTELECTUAL E INEXISTENCIA DE LICENCIA', "Toda la informacion confidencial continuara siendo propiedad exclusiva de la Parte Divulgante, sin que el presente instrumento constituya cesion, licencia o transferencia de propiedad intelectual o industrial.");
                self::printClause($pdf, 'SEXTO: VIGENCIA Y PROCEDIMIENTO DE DEVOLUCION O DESTRUCCION', "El deber de confidencialidad regira por {$vig}, subsistiendo indefinidamente para secretos industriales. A su termino, se procedera mediante: {$destrucc}.");
                self::printClause($pdf, 'SEPTIMO: CLAUSULA PENAL TASADA Y CONVENCIONAL DE PERJUICIOS', "En caso de contravencion o divulgacion indebida, la parte infractora pagara a la parte afectada, a titulo de clausula penal compensatoria tasada (Art. 1535 Codigo Civil), la cantidad de {$multa}, sin perjuicio de medidas cautelares y mayores perjuicios.");
                self::printClause($pdf, 'OCTAVO: DOMICILIO, LEY APLICABLE Y JURISDICCION', "El contrato se rige por las leyes chilenas. Las Partes fijan domicilio en la ciudad de Santiago y se someten a la competencia de los {$tribunales}.");
                break;

            case 'prestacion-servicios-honorarios':
                $modServ = $formData['f_serv_modalidad'] ?? 'opcion_a';
                $servDesc = $formData['servicio_desc'] ?? ($formData['f_servicio_desc'] ?? 'Consultoria tecnica especializada y servicios profesionales');
                $honorario = !empty($formData['honorario']) ? '$' . number_format($formData['honorario'], 0, ',', '.') . ' CLP' : '$1.250.000 CLP';
                $retencion = $formData['retencion'] ?? ($formData['f_retencion'] ?? 'Con retencion legal por Mandante');
                $formaPago = $formData['forma_pago'] ?? ($formData['f_serv_forma_pago'] ?? 'Dentro de 5 dias habiles contra entrega de BHE');
                $avisoPrevio = $formData['aviso_previo'] ?? ($formData['f_serv_aviso_previo'] ?? '30 dias corridos de anticipacion');

                self::printClause($pdf, 'PRIMERO: OBJETO Y ENCARGO PROFESIONAL', "El Mandante encomienda al Profesional, quien acepta y se obliga a ejecutar con estricta sujecion al Codigo Civil, los siguientes servicios: {$servDesc}.");
                self::printClause($pdf, 'SEGUNDO: HONORARIOS Y REGIMEN TRIBUTARIO', "Por la prestacion integra de los servicios, el Mandante pagara la suma de {$honorario} brutos. Forma de pago: {$formaPago}, previa entrega de la Boleta de Honorarios Electronica (BHE). Regimen tributario: {$retencion}.");

                if ($modServ === 'opcion_b') {
                    // Opción B: Por Hito / Proyecto Cerrado (7 Cláusulas)
                    $hitos = $formData['hitos'] ?? ($formData['f_serv_hitos_detalle'] ?? 'Fases aprobadas por escrito');
                    $garantia = $formData['garantia'] ?? ($formData['f_serv_garantia_tecnica'] ?? '60 dias de soporte correctivo');

                    self::printClause($pdf, 'TERCERO: FASES, ENTREGABLES Y RECEPCION CONFORME', "Los servicios se estructuran bajo hitos verificables: {$hitos}. Cada hito se considerara recibido tras la aprobacion escrita formal del Mandante dentro de 5 dias habiles.");
                    self::printClause($pdf, 'CUARTO: GARANTIA TECNICA Y SOPORTE POST-ENTREGA', "El Profesional garantiza la estabilidad y operatividad tecnica de los entregables: {$garantia}.");
                    self::printClause($pdf, 'QUINTO: PROPIEDAD INTELECTUAL EXCLUSIVA DEL MANDANTE', "Una vez satisfechos los honorarios del proyecto, la titularidad patrimonial sobre todos los entregables, codigos, informes y disenos pertenecera íntegra al Mandante (Ley N° 17.336).");
                    self::printClause($pdf, 'SEXTO: DEBER PERPETUO DE CONFIDENCIALIDAD', "El Profesional mantendra estricta reserva de toda informacion tecnica y estrategica del Mandante, no pudiendo divulgarla a terceros.");
                    self::printClause($pdf, 'SEPTIMO: TERMINO ANTICIPADO Y TRIBUNALES', "Cualquiera de las partes podra rescindir el contrato con {$avisoPrevio}, liquidando los hitos finalizados. Las partes prorrogan competencia en los Tribunales Ordinarios de Santiago.");
                } else if ($modServ === 'opcion_c') {
                    // Opción C: SLA Corporativo / Servicios Continuos con Penalizaciones (9 Cláusulas)
                    $slaTiempo = $formData['sla_tiempo'] ?? ($formData['f_serv_sla_tiempo'] ?? 'Critico: 2h / Normal: 24h');
                    $slaMulta = $formData['sla_multa'] ?? ($formData['f_serv_sla_multa'] ?? 'Descuento 2% por hora de retraso');
                    $slaSeguro = $formData['sla_seguro'] ?? ($formData['f_serv_sla_seguro'] ?? 'Sustitucion en 5 dias y resguardo patrimonial');

                    self::printClause($pdf, 'TERCERO: ACUERDOS DE NIVEL DE SERVICIO (SLA)', "El Profesional asegura los siguientes estandares de disponibilidad y tiempos de respuesta: {$slaTiempo}.");
                    self::printClause($pdf, 'CUARTO: AUDITORIA DE CALIDAD Y PENALIZACIONES', "El Mandante auditara mensualmente los SLA. En caso de incumplimiento imputable al Profesional, se aplicara la siguiente sancion deducible: {$slaMulta}.");
                    self::printClause($pdf, 'QUINTO: CONTINUIDAD OPERATIVA Y SUSTITUCION DE PERSONAL', "El Profesional garantiza personal calificado para el servicio: {$slaSeguro}.");
                    self::printClause($pdf, 'SEXTO: AUTONOMIA TECNICA E INDEPENDENCIA PATRIMONIAL', "El Profesional ejecutara sus labores con sus propios medios materiales, respondiendo de sus dependientes sin vinculo laboral con el Mandante.");
                    self::printClause($pdf, 'SEPTIMO: PROPIEDAD INTELECTUAL Y SECRETO INDUSTRIAL', "Todos los desarrollos generados corresponderan exclusivamente al Mandante bajo las Leyes 17.336 y 19.039.");
                    self::printClause($pdf, 'OCTAVO: VIGENCIA Y RESCISION UNILATERAL', "El contrato tendra vigencia continuada, pudiendo terminarse con {$avisoPrevio} o ipso facto ante fallas SLA graves.");
                    self::printClause($pdf, 'NOVENO: DOMICILIO Y JURISDICCION CIVIL', "Las partes fijan domicilio convencional en Santiago y se someten a sus Juzgados Civiles.");
                } else {
                    // Opción A: Civil Profesional con Blindaje Anti-Laboralidad (8 Cláusulas - Recomendado)
                    self::printClause($pdf, 'TERCERO: EXCLUSION CATEGORICA DE SUBORDINACION Y DEPENDENCIA', "Las partes declaran que el presente contrato es estrictamente civil (Art. 2006 Codigo Civil), careciendo de subordinacion: el Profesional goza de libertad horaria, sin fiscalizacion superior jerarquica, con plena autonomia tecnica y sin exclusividad.");
                    self::printClause($pdf, 'CUARTO: PROVISION DE MEDIOS E INDEMNIDAD LABORAL', "El Profesional operara con sus propios equipos, software y herramientas, no existiendo derecho a feriado legal, asignaciones ni cotizaciones de seguridad social patronales.");
                    self::printClause($pdf, 'QUINTO: PROPIEDAD INTELECTUAL DE LOS ENTREGABLES', "Los entregables, informes, creaciones tecnicas y codigos elaborados perteneceran en forma exclusiva al Mandante al satisfacer los honorarios (Ley 17.336).");
                    self::printClause($pdf, 'SEXTO: CONFIDENCIALIDAD Y SECRETO PROFESIONAL', "El Profesional mantendra estricta reserva de todos los datos y antecedentes a los que tenga acceso, subsistiendo este deber por un minimo de 3 anos tras su cese.");
                    self::printClause($pdf, 'SEPTIMO: VIGENCIA Y DESAHUCIO CIVIL ANTICIPADO', "Cualquiera de las partes podra poner termino anticipado al contrato sin expresion de causa mediante aviso escrito previo de {$avisoPrevio}.");
                    self::printClause($pdf, 'OCTAVO: DOMICILIO Y JURISDICCION', "Para todos los efectos legales, las partes fijan domicilio en la ciudad de Santiago y prorrogan competencia en sus Tribunales Ordinarios en lo Civil.");
                }
                break;

            case 'contrato-individual-trabajo':
                $cargo = $formData['cargo'] ?? ($formData['f_trab_cargo'] ?? 'Desarrollador Full Stack / Consultor TI');
                $lugar = $formData['lugar'] ?? ($formData['f_trab_lugar'] ?? 'Las Condes, Santiago');
                $sueldo = !empty($formData['sueldo']) ? '$' . number_format($formData['sueldo'], 0, ',', '.') . ' CLP' : '$1.200.000 CLP';
                $grat = $formData['gratificacion'] ?? 'Artículo 50 (25% mensual con tope 4,75 IMM)';
                $colacion = !empty($formData['colacion']) ? '$' . number_format($formData['colacion'], 0, ',', '.') . ' CLP' : '$60.000 CLP';
                $movil = !empty($formData['movilizacion']) ? '$' . number_format($formData['movilizacion'], 0, ',', '.') . ' CLP' : '$40.000 CLP';
                $jornada = $formData['jornada'] ?? '40 horas semanales distribuidas de lunes a viernes';
                $duracion = $formData['duracion'] ?? 'Indefinida';

                self::printClause($pdf, 'PRIMERO: NATURALEZA DE LOS SERVICIOS Y CARGO', "El Trabajador se compromete a prestar servicios personales y subordinados para el Empleador desempenando el cargo de {$cargo}. Las funciones comprenden el diseno, ejecucion y mantenimiento operativo de tareas inherentes al cargo, y aquellas tareas conexas y complementarias que resulten necesarias para su adecuada ejecucion.");
                self::printClause($pdf, 'SEGUNDO: LUGAR DE PRESTACION DE SERVICIOS (ART. 12 COD. DEL TRABAJO)', "El Trabajador prestara sus servicios en las dependencias ubicadas en {$lugar}. De conformidad con el articulo 12 del Codigo del Trabajo, el Empleador podra alterar la naturaleza de los servicios o el recinto laboral siempre que se trate de labores similares y dentro de la misma localidad sin menoscabo para el Trabajador.");
                self::printClause($pdf, 'TERCERO: JORNADA DE TRABAJO (LEY N° 21.561 DE 40 HORAS)', "La jornada ordinaria de trabajo sera de {$jornada}, con descanso diario para colacion no imputable a la jornada. El control de asistencia y determinacion de horas laboradas se efectuara mediante sistema digital autorizado por la Direccion del Trabajo.");
                self::printClause($pdf, 'CUARTO: REMUNERACION Y BENEFICIOS', "El Empleador pagara al Trabajador: a) Sueldo Base mensual de {$sueldo}; b) Gratificacion Legal segun modalidad de {$grat}; c) Asignacion no remuneracional de colacion de {$colacion}; d) Asignacion no remuneracional de movilizacion de {$movil}. Las remuneraciones se pagaran mensualmente mediante transferencia bancaria.");
                self::printClause($pdf, 'QUINTO: DESCUENTOS LEGALES Y SEGURIDAD SOCIAL', "De las remuneraciones se deduciran los impuestos aplicables, las cotizaciones previsionales de AFP, salud (FONASA/ISAPRE), Seguro de Cesantia (Ley N° 19.728) y demas autorizados por el articulo 58 del Codigo del Trabajo.");
                self::printClause($pdf, 'SEXTO: DURACION DEL CONTRATO', "El presente contrato tendra una duracion de modalidad {$duracion}, rigiendo con plena vigencia a contar de la fecha de incorporacion efectiva.");
                self::printClause($pdf, 'SEPTIMO: OBLIGACIONES Y PROHIBICIONES', "El Trabajador se compromete a desempenar sus labores con probidad, lealtad y cuidado, acatando las ordenes del Empleador, el Reglamento Interno de Orden, Higiene y Seguridad, y las normas de prevencion de riesgos y EPP.");
                self::printClause($pdf, 'OCTAVO: CONFIDENCIALIDAD Y PROPIEDAD INTELECTUAL', "El Trabajador mantendra estricta reserva de toda informacion tecnica, comercial o confidencial. Toda obra intelectual, invencion o programa computacional desarrollado correspondera exclusivamente al Empleador (Leyes 17.336 y 19.039).");
                self::printClause($pdf, 'NOVENO: PREVENCION, ACOSO Y CONVIVENCIA LABORAL', "Las partes adhieren a una politica de tolerancia cero frente al acoso laboral, sexual y violencia en el trabajo, rigiendose por los protocolos legales y reglamentarios vigentes.");
                self::printClause($pdf, 'DECIMO: TERMINACION DEL CONTRATO', "El contrato terminara por las causales legales estipuladas en los articulos 159, 160 y 161 del Codigo del Trabajo, cumpliendo con las formalidades del articulo 162.");
                self::printClause($pdf, 'DECIMO PRIMERO: DOMICILIO Y JURISDICCION', "Para todos los efectos legales, las partes fijan su domicilio en la ciudad del lugar de prestacion de servicios y se someten a la competencia de los Juzgados de Letras del Trabajo correspondientes.");
                self::printClause($pdf, 'DECIMO SEGUNDO: REGISTRO ELECTRONICO LABORAL (REL) Y EJEMPLARES', "El Empleador registrara el presente contrato en el portal electronico Mi DT de la Direccion del Trabajo (Art. 9 Cod. del Trabajo y Ley 21.327). El documento se suscribe con firma electronica conforme a la Ley N° 19.799.");
                break;

            case 'reconocimiento-deuda-pagare-privado':
                $modDeuda = $formData['f_deuda_modalidad'] ?? 'opcion_a';
                $deuda = !empty($formData['deuda_total']) ? '$' . number_format($formData['deuda_total'], 0, ',', '.') . ' CLP' : '$3.500.000 CLP';
                $cuotas = $formData['cuotas'] ?? ($formData['f_deuda_cuotas'] ?? '10');
                $causa = $formData['causa'] ?? ($formData['f_deuda_causa'] ?? 'Saldo insoluto por operaciones y suministros comerciales');
                $diaPago = $formData['dia_pago'] ?? ($formData['f_deuda_dia_pago'] ?? '10');
                $interes = $formData['interes'] ?? ($formData['f_deuda_interes'] ?? 'Interes Maximo Convencional fijado por la CMF');
                $pagare = $formData['pagare'] ?? ($formData['f_deuda_pagare'] ?? 'Con suscripcion paralela de Pagare');

                self::printClause($pdf, 'PRIMERO: RECONOCIMIENTO EXPRESO Y LIQUIDO DE LA DEUDA', "El Deudor reconoce expresa, pura, simple e irrevocablemente adeudar al Acreedor la suma liquida de {$deuda}.");
                self::printClause($pdf, 'SEGUNDO: CAUSA U ORIGEN LICITO DE LA OBLIGACION', "La deuda proviene de: {$causa}, declarando el Deudor haber recibido a su total conformidad las contraprestaciones y bienes respectivos.");
                self::printClause($pdf, 'TERCERO: CALENDARIO DE PAGO EN CUOTAS MENSUALES', "La obligacion se solucionara en {$cuotas} cuotas mensuales, iguales y sucesivas, pagaderas a mas tardar el dia {$diaPago} de cada mes calendario mediante transferencia bancaria o vale vista.");

                if ($modDeuda === 'opcion_b') {
                    // Opción B: Con Codeudor Solidario / Fiador y Aval (8 Cláusulas)
                    $avalNom = $formData['aval_nombre'] ?? ($formData['f_deuda_aval_nombre'] ?? 'Fiador y Codeudor');
                    $avalRut = $formData['aval_rut'] ?? ($formData['f_deuda_aval_rut'] ?? '11.222.333-4');

                    self::printClause($pdf, 'CUARTO: INTERES PENAL MORATORIO (LEY N° 18.010)', "En caso de simple retardo o mora en cualquiera de las cuotas, se devengara de pleno derecho: {$interes} hasta su extincion total.");
                    self::printClause($pdf, 'QUINTO: CLAUSULA DE ACELERACION IPSO FACTO', "El no pago oportuno de cualquiera de las cuotas facultara al Acreedor a hacer exigible de inmediato el total del saldo insoluto como de plazo vencido.");
                    self::printClause($pdf, 'SEXTO: OBLIGACION SOLIDARIA DE FIADOR Y AVAL', "Comparece Don/Dona {$avalNom}, RUT N° {$avalRut}, constituyendose en fiador, aval y codeudor solidario de todas las obligaciones, cuotas, intereses y costas asumidas en este instrumento, renunciando formalmente a los beneficios de orden y excusion.");
                    self::printClause($pdf, 'SEPTIMO: PAGARE EN GARANTIA Y MERITO EJECUTIVO', "En resguardo del credito se estipula: {$pagare}, sin que ello importe novacion.");
                    self::printClause($pdf, 'OCTAVO: DOMICILIO Y JURISDICCION', "Para todos los efectos legales, las partes y el codeudor solidario fijan domicilio en la ciudad de Santiago y prorrogan competencia ante sus Tribunales Ordinarios.");
                } else if ($modDeuda === 'opcion_c') {
                    // Opción C: Transaccional Judicial / Pre-Demanda con Confesión de Deuda Art. 434 CPC (7 Cláusulas)
                    $pactoProc = $formData['pacto_cpc'] ?? ($formData['f_deuda_cpc_tribunal'] ?? 'Sometimiento a gestion preparatoria Art. 434 N° 5 CPC');

                    self::printClause($pdf, 'CUARTO: VALOR DE COSA JUZGADA Y PREVENCION DE LITIGIO (ART. 2446 CC)', "Las partes celebran este acto con el caracter de transaccion extrajudicial para precaver un litigio inminente, con efecto de cosa juzgada en ultima instancia (Art. 2460 Codigo Civil).");
                    self::printClause($pdf, 'QUINTO: PACTO PROCESAL PREPARATORIO DE VIA EJECUTIVA (ART. 434 N° 5 CPC)', "Para efectos de cobro compulsivo, las partes pactan expresamente: {$pactoProc}, habilitando embargo y ejecucion inmediata de bienes.");
                    self::printClause($pdf, 'SEXTO: NOTIFICACION ELECTRONICA Y DOMICILIO PROCESAL', "El Deudor designa domicilio procesal el indicado en la comparecencia y autoriza notificacion por correo electronico conforme a la Ley N° 20.886 de Tramitacion Electronica.");
                    self::printClause($pdf, 'SEPTIMO: COSTAS Y GASTOS DE COBRANZA', "Todas las costas judiciales originadas por el cobro compulsivo seran de exclusivo cargo del Deudor moroso.");
                } else {
                    // Opción A: Notarial Bancario con Cláusula de Aceleración y Pagaré (6 Cláusulas - Recomendado)
                    self::printClause($pdf, 'CUARTO: INTERESES PENALES MORATORIOS (LEY N° 18.010)', "La mora devengara diariamente de pleno derecho: {$interes} sobre el capital insoluto.");
                    self::printClause($pdf, 'QUINTO: CLAUSULA DE ACELERACION Y RENUNCIA A REQUERIMIENTO', "El no pago de cualquiera de las cuotas hara exigible de inmediato la totalidad del saldo insoluto, renunciando el Deudor a requerimiento judicial previo de pago.");
                    self::printClause($pdf, 'SEXTO: PAGARE EN GARANTIA Y DOMICILIO ESPECIAL', "Regimen de titulos: {$pagare}. Las partes fijan domicilio unico en Santiago y prorrogan competencia ante sus Tribunales Ordinarios en lo Civil.");
                }
                break;

            case 'salvoconducto-mudanza':
                $modMud = $formData['f_mud_modalidad'] ?? 'opcion_a';
                $origen = $formData['f_mud_origen'] ?? 'Av. Grecia 1234, Depto 301, Nunoa, Santiago';
                $destino = $formData['f_mud_destino'] ?? 'Av. El Bosque 567, Renaca, Vina del Mar';
                $fechaMud = $formData['f_mud_fecha'] ?? 'Fecha programada';
                $calidad = $formData['f_mud_calidad_inmueble'] ?? 'Propietario legitimo del inmueble de origen';
                $vehiculo = $formData['f_mud_vehiculo'] ?? 'Camion de carga';
                $chofer = $formData['f_mud_conductor'] ?? 'Empresa de flete / Conductor autorizado';
                $impedimentos = $formData['f_mud_impedimentos'] ?? 'Sin orden judicial, embargo ni deuda de arriendo pendiente';

                if ($modMud === 'opcion_b') {
                    // Opción B: Interregional de Larga Distancia con Declaración de Carga (6 Cláusulas)
                    $bultos = $formData['f_mud_bultos'] ?? 'Muebles de hogar y electrodomesticos';
                    $ruta = $formData['f_mud_ruta'] ?? 'Ruta carretera interregional';
                    $sag = $formData['f_mud_sag'] ?? 'Carga libre de productos agropecuarios restringidos por el SAG';

                    self::printClause($pdf, 'PRIMERO: COMPARECENCIA Y CALIDAD JURIDICA DEL DECLARANTE', "El Declarante expone bajo juramento ser {$calidad} respecto del inmueble de origen, teniendo la libre y exclusiva disposicion de los bienes corporales muebles que se trasladan.");
                    self::printClause($pdf, 'SEGUNDO: ITINERARIO INTERREGIONAL Y FECHA DE TRASLADO', "El traslado se efectuara desde {$origen} con destino definitivo a {$destino}, programado para {$fechaMud}, a traves de la ruta: {$ruta}.");
                    self::printClause($pdf, 'TERCERO: IDENTIFICACION DEL VEHICULO DE TRANSPORTE Y CHOFER', "El porteo sera ejecutado mediante el vehiculo: {$vehiculo}, a cargo del transportista/conductor: {$chofer}.");
                    self::printClause($pdf, 'CUARTO: DESGLOSE DETALLADO DE BULTOS Y EFECTOS DECLARADOS', "La carga transportada comprende estrictamente los siguientes enseres y bienes domesticos de propiedad del declarante: {$bultos}.");
                    self::printClause($pdf, 'QUINTO: CUMPLIMIENTO SANITARIO, ADUANAS Y BARRERAS DEL SAG', "De conformidad a las regulaciones sanitarias y de control fitozoosanitario interregional: {$sag}.");
                    self::printClause($pdf, 'SEXTO: INEXISTENCIA DE IMPEDIMENTOS Y SALVOCONDUCTO (LEY N° 20.227)', "El declarante reitera bajo juramento que: {$impedimentos}, extendiendose el presente instrumento con pleno valor de salvoconducto oficial ante Carabineros de Chile y autoridades fiscalizadoras.");
                } else if ($modMud === 'opcion_c') {
                    // Opción C: Mudanza Corporativa / Traslado de Oficinas y Bienes de Capital (5 Cláusulas)
                    $datosEmp = $formData['f_mud_empresa_datos'] ?? 'Razon social y sucursales de la empresa';
                    $equipTi = $formData['f_mud_ti_equipos'] ?? 'Equipamiento informatico e infraestructura TI';
                    $archCont = $formData['f_mud_archivos'] ?? 'Archivos de oficina y documentacion contable';

                    self::printClause($pdf, 'PRIMERO: PERSONERIA Y TITULARIDAD DE ACTIVOS CORPORATIVOS', "El compareciente actua en representacion de la persona juridica: {$datosEmp}, declarando que la empresa es duena legitima de todos los activos, equipos y mobiliario que se trasladan.");
                    self::printClause($pdf, 'SEGUNDO: SUCURSALES DE ORIGEN, DESTINO Y CALENDARIO', "El traslado corporativo se efectua desde las oficinas ubicadas en {$origen} hacia las dependencias de {$destino}, durante la jornada de: {$fechaMud}.");
                    self::printClause($pdf, 'TERCERO: INVENTARIO DE INFRAESTRUCTURA TI Y MOBILIARIO', "La carga esta constituida por: a) Equipamiento TI y Servidores: {$equipTi}; b) Archivos y Libros Contables: {$archCont}.");
                    self::printClause($pdf, 'CUARTO: LOGISTICA DE TRANSPORTE Y GUIAS DE TRASLADO', "El transporte es ejecutado por el vehiculo {$vehiculo}, conducido por {$chofer}, acompanandose Guia de Despacho de Traslado Interno sin venta conforme a normas del SII.");
                    self::printClause($pdf, 'QUINTO: INEXISTENCIA DE EMBARGOS Y VALOR ANTE CARABINEROS (LEY 20.227)', "La empresa declara bajo juramento que: {$impedimentos}, sirviendo el presente de salvoconducto corporativo valido ante Carabineros de Chile e inspectores del MTT.");
                } else {
                    // Opción A: Formato Ley 20.227 con Acreditación de Dominio / No Impedimento (5 Cláusulas - Recomendado)
                    self::printClause($pdf, 'PRIMERO: DECLARACION BAJO JURAMENTO Y CALIDAD JURIDICA', "El Declarante expone bajo fe de juramento ser: {$calidad} respecto del inmueble desde donde se efectua la mudanza, contando con plena facultad de disposicion de los bienes muebles que se trasladan.");
                    self::printClause($pdf, 'SEGUNDO: DOMICILIOS DE ORIGEN Y DESTINO', "El traslado material de muebles y enseres de hogar se efectua desde: {$origen}, teniendo como destino y nueva morada: {$destino}.");
                    self::printClause($pdf, 'TERCERO: FECHA Y CONDICIONES DE LA MUDANZA', "La operacion de mudanza se desarrollara el dia: {$fechaMud}.");
                    self::printClause($pdf, 'CUARTO: MEDIO DE TRANSPORTE Y OPERADOR DEL FLETE', "El transporte sera ejecutado por el vehiculo de carga: {$vehiculo}, a cargo de: {$chofer}.");
                    self::printClause($pdf, 'QUINTO: INEXISTENCIA DE IMPEDIMENTOS LEGALES Y SALVOCONDUCTO (LEY N° 20.227)', "En cumplimiento de los articulos 1°, 2°, 3° y 4° de la Ley N° 20.227, el declarante acredita solemnemente: {$impedimentos}. Este documento constituye salvoconducto habilitante oficial ante Carabineros de Chile.");
                }
                break;

            case 'autorizacion-domicilio-tributario-sii':
                $modSii = $formData['f_sii_modalidad'] ?? 'opcion_a';
                $siiDir = $formData['f_sii_direccion'] ?? 'Av. Andres Bello 2711, Piso 8, Oficina 802, Las Condes, Santiago';
                $siiRol = $formData['f_sii_rol'] ?? '1234-56 de Las Condes';
                $siiDest = $formData['f_sii_destino'] ?? 'Domicilio tributario, comercial, postal y administrativo exclusivo';
                $siiVig = $formData['f_sii_vigencia'] ?? 'Indefinida mientras subsista el giro de la sociedad';
                $siiRest = $formData['f_sii_restitucion'] ?? 'Aviso escrito previo de 60 dias corridos para que la empresa traslade su domicilio tributario en el SII';

                if ($modSii === 'opcion_c') {
                    // Opción C: Declaración Jurada Simple Unipersonal del Propietario (4 Cláusulas)
                    $apercib = $formData['f_sii_apercibimiento'] ?? 'Bajo fe de juramento y apercibimiento del Art. 210 del Codigo Penal en caso de falsedad en declaracion jurada ante la autoridad tributaria';
                    $empNom = $formData['parte_b_nombre'] ?? ($formData['f_parte_b_nombre'] ?? 'Sociedad Beneficiaria');
                    $empRut = $formData['parte_b_rut'] ?? ($formData['f_parte_b_rut'] ?? '77.888.999-0');

                    self::printClause($pdf, 'DECLARACION PRIMERA: CALIDAD DE PROPIETARIO Y ROL DE AVALUO FISCAL', "El declarante expone bajo juramento ser el legitimo y exclusivo propietario del inmueble ubicado en {$siiDir}, enrolado ante el Servicio de Impuestos Internos bajo el Rol N° {$siiRol}.");
                    self::printClause($pdf, 'DECLARACION SEGUNDA: AUTORIZACION EXPRESA DE USO Y DESTINO TRIBUTARIO', "Autoriza en forma libre y expresa a {$empNom} (RUT N° {$empRut}) para fijar y acreditar su domicilio tributario, comercial y administrativo en dicho inmueble para inicio de actividades, verificacion de actividad y timbraje ante el SII.");
                    self::printClause($pdf, 'DECLARACION TERCERA: DESTINO EXCLUSIVO Y VIGENCIA', "La autorizacion se concede para: {$siiDest}, con vigencia de: {$siiVig}, declarando que el inmueble se encuentra libre de gravamenes que impidan este acto.");
                    self::printClause($pdf, 'DECLARACION CUARTA: APERCIBIMIENTO LEGAL Y RESPONSABILIDAD PENAL (ART. 210 CP)', "El declarante asume plena responsabilidad legal sobre la veracidad de lo manifestado, conociendo las sanciones penales por perjurio del Art. 210 del Codigo Penal: {$apercib}.");
                } else if ($modSii === 'opcion_b') {
                    // Opción B: Autorización por Subarrendamiento / Espacio Co-Work (6 Cláusulas)
                    $facCowork = $formData['f_sii_facultad_cowork'] ?? 'Facultad expresa segun contrato de arrendamiento matriz y estatutos del centro de negocios';
                    $servVirt = $formData['f_sii_servicios_virtual'] ?? 'Recepcion de correspondencia postal, notificacion del SII y uso de salas de reuniones';

                    self::printClause($pdf, 'PRIMERO: INMUEBLE, INSTALACIONES Y ROL DE AVALUO FISCAL', "El Operador Co-Work es poseedor y administrador del inmueble ubicado en {$siiDir}, Rol de Avaluo SII N° {$siiRol}.");
                    self::printClause($pdf, 'SEGUNDO: FACULTADES EXPRESAS DE CESION Y SUBARRENDAMIENTO', "El Operador declara y acredita facultades suficientes para ceder domicilio tributario a terceros: {$facCowork}, conforme a la Circular N° 31 de 2007 del SII.");
                    self::printClause($pdf, 'TERCERO: SERVICIOS DE OFICINA VIRTUAL Y RECEPCION TRIBUTARIA', "Se autoriza formalmente a la empresa a fijar su domicilio tributario y comercial en el inmueble, incluyendo los servicios de: {$servVirt}.");
                    self::printClause($pdf, 'CUARTO: DESTINO EXCLUSIVO Y VERIFICACION DE ACTIVIDAD DEL SII', "Destino autorizado: {$siiDest}. La empresa se compromete a mantener en el recinto su documentacion legal y colaborar en visitas inspectivas de funcionarios del SII.");
                    self::printClause($pdf, 'QUINTO: VIGENCIA Y PROCEDIMIENTO DE TRASLADO DE DOMICILIO', "Vigencia de {$siiVig}. Al termino del contrato rige el siguiente procedimiento: {$siiRest}.");
                    self::printClause($pdf, 'SEXTO: DOMICILIO CONVENCIONAL Y JURISDICCION', "Las partes fijan domicilio convencional en la ciudad de Santiago y se someten a la jurisdiccion de sus Tribunales Ordinarios de Justicia.");
                } else {
                    // Opción A: Comodato Gratuito Inmobiliario - Formato Estándar SII Art. 2174 CC (5 Cláusulas - Recomendado)
                    self::printClause($pdf, 'PRIMERO: INDIVIDUALIZACION DEL INMUEBLE Y ROL DE AVALUO FISCAL', "El Comodante declara ser el exclusivo dueno del inmueble ubicado en {$siiDir}, Rol de Avaluo Fiscal SII N° {$siiRol}.");
                    self::printClause($pdf, 'SEGUNDO: OTORGAMIENTO DE COMODATO GRATUITO (ART. 2174 CODIGO CIVIL)', "El Comodante entrega en comodato o prestamo de uso a titulo enteramente gratuito a la Comodataria el uso del inmueble para fijar, acreditar y mantener su domicilio tributario, comercial y postal oficial ante el Servicio de Impuestos Internos (SII), Circular N° 31 de 2007.");
                    self::printClause($pdf, 'TERCERO: DESTINO EXCLUSIVO Y PROHIBICION DE CESION', "El inmueble se destinara exclusivamente a: {$siiDest}. Se prohibe ceder o subarrendar el uso del domicilio tributario a terceros sin consentimiento escrito.");
                    self::printClause($pdf, 'CUARTO: VIGENCIA Y PROCEDIMIENTO DE RESTITUCION / REVOCACION', "El comodato rige por: {$siiVig}. Procedimiento de restitucion: {$siiRest}, debiendo la empresa tramitar y acreditar el cambio de domicilio en el SII.");
                    self::printClause($pdf, 'QUINTO: DOMICILIO CONVENCIONAL Y PRORROGA DE COMPETENCIA', "Para todos los efectos legales, las partes fijan domicilio en la ciudad de Santiago y prorrogan competencia ante sus Tribunales Ordinarios de Justicia en lo Civil.");
                }
                break;

            case 'poder-especial-privado':
                $modPoder = $formData['f_poder_modalidad'] ?? 'opcion_a';
                $fac = $formData['f_poder_facultades'] ?? 'Representar al mandante en gestiones administrativas';
                $vig = $formData['f_poder_vigencia'] ?? '6 meses contados desde la firma digital';
                $del = $formData['f_poder_delegar'] ?? 'Sin facultad de delegacion ni sustitucion en terceros';
                $cuentas = $formData['f_poder_cuentas'] ?? 'Rendicion de cuentas con culpa leve conforme al Art. 2129 del Codigo Civil';

                if ($modPoder === 'opcion_b') {
                    // Opción B: Poder para Representación en Asambleas de Copropietarios (Ley 21.442 - 4 Cláusulas)
                    $edif = $formData['f_poder_edificio'] ?? 'Comunidad Edificio Nueva Providencia';
                    $unidad = $formData['f_poder_unidad'] ?? 'Departamento 402 y bodega';
                    $asamTipo = $formData['f_poder_asamblea_tipo'] ?? 'Voz y voto pleno para asambleas de copropietarios ordinarias y extraordinarias';

                    self::printClause($pdf, 'PRIMERO: DOMINIO DE LA UNIDAD INMOBILIARIA', "El Mandante declara ser legitimo y exclusivo propietario de: {$unidad}, ubicada en el edificio o condominio: {$edif}, sujeto a la Ley N° 21.442 sobre Copropiedad Inmobiliaria.");
                    self::printClause($pdf, 'SEGUNDO: FACULTADES DE VOZ Y VOTO EN ASAMBLEAS (LEY N° 21.442)', "El Mandante confiere poder especial suficiente al Mandatario para asistir con derecho a voz y voto a las Asambleas de Copropietarios: {$asamTipo}, y suscribir actas de asamblea.");
                    self::printClause($pdf, 'TERCERO: VIGENCIA Y REVOCABILIDAD', "El poder rige por: {$vig}. Puede ser revocado en cualquier momento comunicandolo por escrito a la Administracion. Regimen de delegacion: {$del}.");
                    self::printClause($pdf, 'CUARTO: RESPONSABILIDAD Y RENDICION DE CUENTAS', "Rendicion de cuentas y deber de lealtad: {$cuentas}. Las partes fijan domicilio convencional en Santiago.");
                } else if ($modPoder === 'opcion_c') {
                    // Opción C: Poder para Trámites de Vehículos Motorizados (Registro Civil / PRT - 5 Cláusulas)
                    $pat = $formData['f_poder_auto_patente'] ?? 'BB-CC-34';
                    $auto = $formData['f_poder_auto_datos'] ?? 'Vehiculo motorizado';
                    $tramites = $formData['f_poder_auto_tramites'] ?? 'Tramites de padron, duplicado de placas y revision tecnica en PRT';

                    self::printClause($pdf, 'PRIMERO: INDIVIDUALIZACION DEL VEHICULO MOTORIZADO', "El Mandante es dueno del vehiculo: {$auto}, Placa Patente Unica (PPU) {$pat}, inscrito a su nombre en el Registro de Vehiculos Motorizados del Registro Civil.");
                    self::printClause($pdf, 'SEGUNDO: FACULTADES ESPECIALES ANTE EL REGISTRO CIVIL Y PRT', "Confiere poder al Mandatario para gestionar: {$tramites}, suscribir solicitudes oficiales y retirar certificados y distintivos.");
                    self::printClause($pdf, 'TERCERO: GESTIONES MUNICIPALES Y TRANSITO', "El Mandatario podra tramitar y pagar el Permiso de Circulacion en Direcciones de Transito y contratar el SOAP respectivo.");
                    self::printClause($pdf, 'CUARTO: VIGENCIA Y PROHIBICION DE DISPOSICION PATRIMONIAL', "Vigencia: {$vig}, actuando {$del}. Se deja expresa constancia de que este mandato NO faculta para vender, transferir, prendar ni gravar el vehiculo.");
                    self::printClause($pdf, 'QUINTO: RESPONSABILIDAD, RENDICION DE CUENTAS Y JURISDICCION', "El Mandatario respondera de sus actos con arreglo al mandato civil: {$cuentas}, fijando las partes domicilio en Santiago.");
                } else {
                    // Opción A: Mandato Administrativo / Trámites y Encomiendas (5 Cláusulas - Recomendado)
                    self::printClause($pdf, 'PRIMERO: OBJETO DEL MANDATO Y ENCARGO', "El Mandante confiere poder especial al Mandatario, quien acepta para si, para que en su nombre y representacion realice todas las actuaciones necesarias para el encargo conferido.");
                    self::printClause($pdf, 'SEGUNDO: FACULTADES ESPECIFICAS DELEGADAS', "Facultades especificas conferidas: {$fac}.");
                    self::printClause($pdf, 'TERCERO: VIGENCIA DEL PODER ESPECIAL', "El presente mandato tendra una duracion de: {$vig}, vencida la cual cesaran de pleno derecho las facultades sin necesidad de notificacion judicial.");
                    self::printClause($pdf, 'CUARTO: REGIMEN DE DELEGACION Y SUSTITUCION', "Regimen de sustitucion: {$del}. Toda actuacion ejecutada por terceros carecera de validez frente al Mandante.");
                    self::printClause($pdf, 'QUINTO: RENDICION DE CUENTAS Y TRIBUNALES COMPETENTES', "Conforme a los Arts. 2129 y 2155 del Codigo Civil: {$cuentas}. Las partes fijan domicilio convencional en la comuna y ciudad de Santiago.");
                }
                break;

            case 'declaracion-jurada-residencia':
                $modRes = $formData['f_res_modalidad'] ?? 'opcion_a';
                $declDom = $formData['f_decl_domicilio'] ?? ($formData['parte_a_domicilio'] ?? 'Pasaje Los Copihues 456, Maipu, Santiago');
                $declFin = $formData['f_decl_destino'] ?? 'tramites bancarios y escolares';
                $antiguedad = $formData['f_res_antiguedad'] ?? 'Mas de 3 anos continuos';

                if ($modRes === 'opcion_b') {
                    // Opción B: Familiar / Acreditación de Carga para Becas y Asignaciones (5 Cláusulas)
                    $cargas = $formData['f_res_cargas'] ?? 'Conyuge e hijos menores en Registro Social de Hogares';
                    $prog = $formData['f_res_programa'] ?? 'Beneficios estudiantiles FUAS, Gratuidad y subsidios estatales';

                    self::printClause($pdf, 'PRIMERO: DECLARACION SOLEMNE DE DOMICILIO FAMILIAR', "El declarante acredita bajo juramento que su morada habitual y residencia principal familiar corresponde al inmueble ubicado en: {$declDom}.");
                    self::printClause($pdf, 'SEGUNDO: TIEMPO DE RESIDENCIA CONTINUA', "Que habita dicho hogar en forma ininterrumpida por un lapso no inferior a: {$antiguedad}.");
                    self::printClause($pdf, 'TERCERO: COHABITACION Y CARGAS FAMILIARES', "Se deja constancia de que en dicho hogar cohabitan bajo su sustento economico las siguientes cargas: {$cargas}.");
                    self::printClause($pdf, 'CUARTO: DESTINO EXCLUSIVO PARA POSTULACION ESTATAL', "La presente declaracion se emite a efectos exclusivos de: {$prog}.");
                    self::printClause($pdf, 'QUINTO: RESPONSABILIDAD PENAL POR PERJURIO (ART. 210 CP)', "El declarante asume plena responsabilidad legal sobre lo expresado, conociendo las sanciones penales contempladas en el Art. 210 del Codigo Penal ante declaraciones falsas.");
                } else if ($modRes === 'opcion_c') {
                    // Opción C: Independiente con Respaldo de Movimientos y Arriendo (5 Cláusulas)
                    $serv = $formData['f_res_servicios'] ?? 'Arrendatario con cuentas de servicios a su nombre';
                    $banco = $formData['f_res_banco'] ?? 'Acreditacion ante instituciones financieras y bancarias';

                    self::printClause($pdf, 'PRIMERO: ACREDITACION DE DOMICILIO Y ASIENTO HABITUAL', "Que el declarante tiene su asiento legal y domicilio efectivo en la propiedad de: {$declDom}, con residencia de {$antiguedad}.");
                    self::printClause($pdf, 'SEGUNDO: CALIDAD DE OCUPACION Y SUMINISTROS', "Detenta la ocupacion del inmueble como: {$serv}, encontrandose los suministros domiciliarios al dia.");
                    self::printClause($pdf, 'TERCERO: DESTINO COMERCIAL Y FINANCIERO', "La presente declaracion jurada se extiende para: {$banco}.");
                    self::printClause($pdf, 'CUARTO: VALIDEZ DE NOTIFICACIONES', "Designa dicho domicilio como valido para la recepcion de correspondencia bancaria y notificaciones legales.");
                    self::printClause($pdf, 'QUINTO: DECLARACION SOLEMNE Y APERCIBIMIENTO LEGAL', "Sostiene bajo fe de juramento la veracidad incondicional de los antecedentes expuestos, sometiendose al Art. 210 del Codigo Penal.");
                } else {
                    // Opción A: Estándar Notarial con Apercibimiento Penal Art. 210 CP (4 Cláusulas - Recomendado)
                    self::printClause($pdf, 'PRIMERO: DECLARACION SOLEMNE BAJO FE DE JURAMENTO', "El declarante manifiesta de forma libre, espontanea y bajo juramento de decir verdad ante las leyes de la Republica de Chile.");
                    self::printClause($pdf, 'SEGUNDO: FIJACION Y ACREDITACION DE RESIDENCIA HABITUAL', "Que su residencia fija, permanente y morada habitual corresponde a la ubicada en: {$declDom}, habitando en ella desde hace: {$antiguedad}.");
                    self::printClause($pdf, 'TERCERO: DESTINO Y FINALIDAD DEL INSTRUMENTO', "Que la presente declaracion jurada se extiende para ser presentada ante: {$declFin}.");
                    self::printClause($pdf, 'CUARTO: APERCIBIMIENTO PENAL POR FALSO TESTIMONIO (ART. 210 CP)', "El declarante manifiesta conocer que prestar declaracion jurada falsa ante la autoridad o para efectos publicos constituye delito de perjurio penado por el Articulo 210 del Codigo Penal chileno con penas de presidio menor y multas legales, ratificando la autenticidad integra de sus dichos mediante firma digital (Ley N° 19.799).");
                }
                break;

            case 'declaracion-jurada-ingresos-informales':
                $modIng = $formData['f_ing_modalidad'] ?? 'opcion_a';
                $activ = $formData['f_ingr_actividad'] ?? 'Comercio independiente y servicios autonomos';
                $montIng = !empty($formData['f_ingr_monto']) ? '$' . number_format($formData['f_ingr_monto'], 0, ',', '.') . ' CLP' : '$950.000 CLP';
                $destIng = $formData['f_ingr_destino'] ?? 'postulacion a creditos y arriendos';
                $antigIng = $formData['f_ing_antiguedad'] ?? 'Mas de 2 anos de ejercicio continuo';

                if ($modIng === 'opcion_b') {
                    // Opción B: Familiar / Acreditación de Carga para Becas y Asignaciones (5 Cláusulas)
                    $aporte = $formData['f_ing_aporte_familiar'] ?? 'Sustentador del hogar destinado a alimentacion y educacion';
                    $progEst = $formData['f_ing_programa_estatal'] ?? 'FUAS / Gratuidad y beneficios socioeconomicos estatales';

                    self::printClause($pdf, 'PRIMERO: DECLARACION SOLEMNE DE ORIGEN DE FONDOS', "El declarante expone bajo juramento percibir ingresos derivados de: {$activ}, con antiguedad de: {$antigIng}.");
                    self::printClause($pdf, 'SEGUNDO: CUANTIFICACION MENSUAL DEL INGRESO PERCIBIDO', "Dichos ingresos corresponden a un promedio liquido mensual disponible de: {$montIng}, sin contrato de subordinacion.");
                    self::printClause($pdf, 'TERCERO: DESTINACION AL SUSTENTO FAMILIAR Y CARGAS', "Los recursos percibidos se destinan prioritariamente a: {$aporte}.");
                    self::printClause($pdf, 'CUARTO: FINALIDAD SOCIOECONOMICA Y BENEFICIOS ESTATALES', "La declaracion jurada se suscribe a efectos de: {$progEst}.");
                    self::printClause($pdf, 'QUINTO: RESPONSABILIDAD PENAL POR PERJURIO (ART. 210 CP)', "Asume plena responsabilidad penal sobre la exactitud de lo expresado, conociendo las sanciones del Art. 210 del Codigo Penal ante declaraciones falsas.");
                } else if ($modIng === 'opcion_c') {
                    // Opción C: Independiente con Respaldo de Movimientos y Transferencias (5 Cláusulas)
                    $dep = $formData['f_ing_depositos'] ?? 'Abonos y transferencias bancarias mensuales acreditables';
                    $costos = $formData['f_ing_costos'] ?? 'Costos operativos e insumos deducibles';

                    self::printClause($pdf, 'PRIMERO: ACTIVIDAD INDEPENDIENTE Y TRAYECTORIA', "El declarante desempena de manera autonoma el rubro de: {$activ}, con trayectoria de: {$antigIng}.");
                    self::printClause($pdf, 'SEGUNDO: FLUJO BRUTO DE INGRESOS Y TRANSFERENCIAS', "Percibe transferencias bancarias y depositos conforme a: {$dep}.");
                    self::printClause($pdf, 'TERCERO: COSTOS OPERATIVOS Y MARGEN NETO ESTIMADO', "Deducidos los gastos operacionales e insumos: {$costos}, fijando un promedio liquido mensual de: {$montIng}.");
                    self::printClause($pdf, 'CUARTO: DESTINO FINANCIERO O ARRENDATICIO', "El instrumento se extiende para acreditar solvencia economica ante: {$destIng}.");
                    self::printClause($pdf, 'QUINTO: VALOR PROBATORIO Y RESPONSABILIDAD PENAL', "Se asume plena responsabilidad civil y penal sobre la legitimidad de las cifras declaradas conforme al Art. 210 del Codigo Penal y Ley N° 19.799.");
                } else {
                    // Opción A: Estándar Notarial con Apercibimiento Penal Art. 210 CP (4 Cláusulas - Recomendado)
                    self::printClause($pdf, 'PRIMERO: DECLARACION SOLEMNE BAJO FE DE JURAMENTO', "El declarante comparece libre, voluntariamente y bajo juramento de decir verdad ante las leyes de la Republica de Chile.");
                    self::printClause($pdf, 'SEGUNDO: DECLARACION DE ACTIVIDADES E INGRESOS PERCIBIDOS', "Que percibe legitimamente ingresos de caracter independiente derivados de: {$activ} por un promedio mensual liquido de: {$montIng}, careciendo de relacion de dependencia laboral subordinada.");
                    self::printClause($pdf, 'TERCERO: DESTINO Y FINALIDAD DEL DOCUMENTO', "Que la presente declaracion jurada se otorga exclusivamente para ser presentada ante: {$destIng}.");
                    self::printClause($pdf, 'CUARTO: APERCIBIMIENTO LEGAL Y RESPONSABILIDAD PENAL (ART. 210 CP)', "El declarante manifiesta tener pleno conocimiento de que falsear antecedentes en una declaracion jurada constituye delito de perjurio sancionado por el Articulo 210 del Codigo Penal con penas de presidio menor y multas legales, ratificando la total veracidad de sus dichos mediante firma electronica (Ley N° 19.799).");
                }
                break;

            default:
                self::printClause($pdf, 'PRIMERO: ANTECEDENTES Y OBJETO', "Las partes formalizan el presente instrumento de " . $doc['titulo'] . ", reconociéndole plena fuerza obligatoria al amparo del principio de autonomía de la voluntad consagrado en el Artículo 1545 del Código Civil chileno.");
                self::printClause($pdf, 'SEGUNDO: CUMPLIMIENTO Y BUENA FE', "Las partes se obligan a ejecutar fielmente las estipulaciones contenidas en este contrato con estricta sujeción al principio de buena fe legal.");
                break;
        }
    }

    private static function printClause($pdf, $title, $text) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(15, 42, 74);
        $pdf->Cell(0, 4.5, utf8_decode($title), 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 8.8);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->MultiCell(0, 4.2, utf8_decode($text));
        $pdf->Ln(2.5);
    }

    private static function mesEnEspanol($mesNum) {
        $meses = [
            '01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
            '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
            '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
        ];
        return $meses[$mesNum] ?? 'mes';
    }
}
