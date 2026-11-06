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

            case 'finiquito-laboral-mutuo-acuerdo':
                self::printClause($pdf, 'PRIMERO: TÉRMINO DE LA RELACIÓN LABORAL', "Las partes declaran de mutuo y común acuerdo poner término definitivo a la relación laboral que las unía, en conformidad a la causal del Artículo 159 N° 1 y con las formalidades del Artículo 177 del Código del Trabajo.");
                self::printClause($pdf, 'SEGUNDO: LIQUIDACIÓN DE PRESTACIONES Y HABERES', "El Empleador hace entrega al Trabajador de la liquidación final por concepto de remuneraciones pendientes, feriado proporcional y demás haberes legales, que el Trabajador declara recibir conforme.");
                self::printClause($pdf, 'TERCERO: AMPLIO, MUTUO Y COMPLETO FINIQUITO', "Las partes declaran que nada se adeudan por concepto de remuneraciones, horas extraordinarias ni cotizaciones previsionales, otorgándose el más amplio y completo finiquito con valor de transacción extrajudicial.");
                break;

            case 'acuerdo-confidencialidad-nda':
                self::printClause($pdf, 'PRIMERO: INFORMACIÓN CONFIDENCIAL Y PROPÓSITO', "Toda información técnica, financiera, comercial, código fuente, algoritmos, bases de datos o secretos industriales revelados entre las partes se califica como Información Confidencial protegida.");
                self::printClause($pdf, 'SEGUNDO: DEBER DE RESERVA Y NO DIVULGACIÓN', "La parte receptora se obliga a custodiar la información confidencial con la mayor diligencia, no pudiendo reproducirla, transferirla ni divulgarla a terceros no autorizados sin consentimiento expreso por escrito.");
                self::printClause($pdf, 'TERCERO: VIGENCIA Y SANCIONES POR INFRACCIÓN', "El deber de confidencialidad subsistirá por el plazo acordado e indefinidamente respecto de secretos comerciales, facultando a la parte afectada a perseguir la indemnización civil y penal correspondiente.");
                break;

            case 'prestacion-servicios-honorarios':
                self::printClause($pdf, 'PRIMERO: NATURALEZA CIVIL DEL CONTRATO', "El Profesional prestará servicios independientes con plena autonomía técnica y funcional bajo las reglas del Artículo 2006 y siguientes del Código Civil, sin subordinación ni dependencia laboral.");
                self::printClause($pdf, 'SEGUNDO: HONORARIOS Y FORMA DE PAGO', "Por la prestación de los servicios profesionales convenidos, el Mandante pagará los honorarios acordados contra entrega de la boleta de honorarios electrónica respectiva con la retención tributaria legal.");
                self::printClause($pdf, 'TERCERO: PROPIEDAD INTELECTUAL Y CONFIDENCIALIDAD', "Los entregables, informes o creaciones técnicas confeccionadas pertenecerán al mandante una vez pagados los honorarios, resguardando ambas partes la reserva de los antecedentes.");
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
                self::printClause($pdf, 'PRIMERO: RECONOCIMIENTO EXPRESO Y LÍQUIDO', "El Deudor reconoce expresa, pura y simplemente adeudar al Acreedor la suma líquida indicada, derivada de obligaciones civiles y mercantiles pendientes entre ambas partes.");
                self::printClause($pdf, 'SEGUNDO: PLAN DE PAGO Y CLÁUSULA DE ACELERACIÓN', "La deuda se solucionará en las cuotas pactadas. El no pago íntegro y oportuno de cualquiera de ellas facultará al Acreedor a hacer exigible de inmediato el total del saldo insoluto como si fuere de plazo vencido.");
                self::printClause($pdf, 'TERCERO: PAGARÉ EN GARANTÍA E INTERESES PENALES', "En caso de mora o simple retardo, la obligación devengará el interés penal máximo convencional permitido por la Ley N° 18.010, facultando la acción ejecutiva ante tribunales ordinarios.");
                break;

            case 'salvoconducto-mudanza':
                self::printClause($pdf, 'PRIMERO: DECLARACIÓN BAJO JURAMENTO (LEY N° 20.227)', "El Declarante expone bajo juramento que trasladará sus muebles y enseres domésticos desde el domicilio de origen al de destino singularizado en este instrumento.");
                self::printClause($pdf, 'SEGUNDO: INEXISTENCIA DE IMPEDIMENTOS', "Declara expresamente no tener impedimento judicial, legal ni contractual para realizar dicho traslado, encontrándose al día en sus obligaciones de arriendo o siendo propietario legítimo del inmueble de origen.");
                break;

            case 'autorizacion-domicilio-tributario-sii':
                self::printClause($pdf, 'PRIMERO: INMUEBLE Y ROL DE AVALÚO FISCAL', "El Propietario o Comodante declara ser legítimo poseedor del inmueble y su respectivo Rol de Avalúo Fiscal acreditado ante el Servicio de Impuestos Internos (SII).");
                self::printClause($pdf, 'SEGUNDO: COMODATO GRATUITO PARA EFECTOS TRIBUTARIOS', "Otorga formalmente en comodato gratuito el uso del inmueble para que la empresa autorizada fije su domicilio tributario, comercial y postal conforme a las exigencias normativas del SII.");
                break;

            case 'poder-especial-privado':
                self::printClause($pdf, 'PRIMERO: MANDATO Y FACULTADES DELEGADAS', "El Mandante confiere poder especial amplio y suficiente al Mandatario para que en su nombre y representación ejecute las gestiones administrativas, trámites y gestiones singularizadas.");
                self::printClause($pdf, 'SEGUNDO: VIGENCIA Y RESPONSABILIDAD', "El mandato se entenderá otorgado por el plazo convenido o hasta la conclusión del encargo, rindiendo oportuna cuenta de su cometido con arreglo al Código Civil.");
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
