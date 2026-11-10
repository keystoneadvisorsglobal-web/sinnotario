<?php
/**
 * SinNotario.cl - Marco Legal Oficial y Biblioteca del Congreso Nacional (BCN)
 * Portal interactivo de validez jurídica, normativa chilena y jurisprudencia.
 */

$leyes = [
    'ley-19799' => [
        'titulo' => 'Ley N° 19.799',
        'subtitulo' => 'Sobre Documentos Electrónicos, Firma Electrónica y Servicios de Certificación',
        'badge' => 'Estatuto de Equivalencia Legal del Soporte Electrónico',
        'fecha' => 'Promulgada el 12 de abril de 2002',
        'icono' => 'fa-file-signature',
        'bcn_url' => 'https://www.bcn.cl/leychile/navegar?idNorma=196640',
        'id_norma' => '196640',
        'resumen' => 'Establece el principio de equivalencia funcional en el derecho chileno: los actos y contratos celebrados por medios electrónicos y firmados digitalmente son plenamente válidos, se reputan por escrito y producen idénticos efectos que los otorgados en papel.',
        'pilares' => [
            [
                'titulo' => 'Principio de Equivalencia Funcional (Art. 3°)',
                'desc' => 'Los actos y contratos otorgados o celebrados por personas naturales o jurídicas, suscritos por medio de firma electrónica, serán válidos de la misma manera y producirán los mismos efectos que los celebrados por escrito y en soporte de papel.'
            ],
            [
                'titulo' => 'Firma Electrónica Simple vs. Avanzada (Art. 2°)',
                'desc' => 'Distingue la Firma Electrónica Simple (FES) para todo tipo de actos y convenciones privadas, y la Firma Electrónica Avanzada (FEA), emitida por prestador acreditado bajo exclusivo control del titular, con presunción legal de autoría e integridad.'
            ],
            [
                'titulo' => 'Valor Probatorio en Juicio (Art. 4° y 5°)',
                'desc' => 'Los documentos electrónicos suscritos con firma electrónica simple tienen mérito probatorio de instrumento privado. Los suscritos con Firma Electrónica Avanzada hacen plena prueba respecto a su autoría y fecha cierta.'
            ],
            [
                'titulo' => 'Excepciones Exclusivas de Notaría (Art. 3° inc. 2)',
                'desc' => 'Solo quedan excluidos aquellos actos donde la ley exige solemnidad pública indelegable (ej. compraventa de inmuebles por escritura pública Art. 1801 CC, testamentos o derecho de familia), permitiendo que el 90% restante de la vida civil y mercantil se celebre digitalmente.'
            ]
        ],
        'aplicacion_sinnotario' => 'En SinNotario.cl utilizamos firma electrónica certificada vinculada al RUT y trazabilidad criptográfica SHA-256 con código QR de verificación, amparada 100% bajo el Art. 3° y 4° de la Ley 19.799.'
    ],
    'ley-21461' => [
        'titulo' => 'Ley N° 21.461',
        'subtitulo' => 'Ley "Devuélveme Mi Casa" (Reforma a la Ley N° 18.101)',
        'badge' => 'Procedimiento Monitorio y Restitución Anticipada',
        'fecha' => 'Publicada el 30 de junio de 2022',
        'icono' => 'fa-home',
        'bcn_url' => 'https://www.bcn.cl/leychile/navegar?idNorma=1178044',
        'id_norma' => '1178044',
        'resumen' => 'Modernizó el juicio de arrendamiento en Chile, facultando a los arrendadores a recuperar su propiedad en plazos récord mediante el procedimiento monitorio de cobro de rentas y la medida cautelar de desalojo express con auxilio de la fuerza pública.',
        'pilares' => [
            [
                'titulo' => 'Procedimiento Monitorio de Cobro de Rentas (Art. 18-A)',
                'desc' => 'Permite demandar el cobro de rentas adeudadas y cuentas de servicios básicos sin necesidad de un juicio ordinario extenso. Si el arrendatario no paga ni opone excepciones en 10 días corridos, el juez decreta el lanzamiento inmediato.'
            ],
            [
                'titulo' => 'Plazo Perentorio de 10 Días',
                'desc' => 'El deudor cuenta con exactamente 10 días corridos tras ser notificado judicialmente para pagar el total adeudado o comparecer con defensa justificada. Ante su silencio, la resolución tiene mérito de sentencia condenatoria ejecutoriada.'
            ],
            [
                'titulo' => 'Restitución Anticipada del Inmueble (Art. 8° bis)',
                'desc' => 'Faculta al juez a ordenar la desocupación preventiva y el desalojo provisional durante el juicio si el inmueble sufre destrucción parcial o menoscabo imputable al arrendatario.'
            ],
            [
                'titulo' => 'Cláusulas Críticas del Contrato de Arriendo',
                'desc' => 'Exige individualización rigurosa de las partes, renuncia a prórroga tácita, designación de correo electrónico procesal y acreditación del dominio y cuentas al día.'
            ]
        ],
        'aplicacion_sinnotario' => 'La plantilla oficial de Contrato de Arriendo Residencial de SinNotario.cl está blindada con las cláusulas exactas exigidas por la Ley N° 21.461 para litigar directamente en sede monitoria civil.'
    ],
    'ley-18101' => [
        'titulo' => 'Ley N° 18.101',
        'subtitulo' => 'Estatuto Especial sobre Arrendamiento de Predios Urbanos',
        'badge' => 'Régimen General de Arrendamiento Urbano',
        'fecha' => 'Publicada el 29 de enero de 1982 (Texto Refundido y Modificado)',
        'icono' => 'fa-building',
        'bcn_url' => 'https://www.bcn.cl/leychile/navegar?idNorma=29526',
        'id_norma' => '29526',
        'resumen' => 'Regula las relaciones contractuales entre arrendadores y arrendatarios en inmuebles ubicados dentro del radio urbano en todo el territorio de Chile, consagrando los derechos y obligaciones de ambas partes.',
        'pilares' => [
            [
                'titulo' => 'Ámbito de Aplicación (Art. 1° y 2°)',
                'desc' => 'Rige predios urbanos y viviendas situadas fuera del radio urbano de superficie no superior a una hectárea, aplicándose supletoriamente el Código Civil.'
            ],
            [
                'titulo' => 'Irrenunciabilidad de Derechos (Art. 19)',
                'desc' => 'Los derechos que la ley confiere a los arrendatarios son irrenunciables, por lo que toda cláusula contractual que vulnere o reduzca las garantías mínimas de la ley se tendrá por no escrita.'
            ],
            [
                'titulo' => 'Desahucio y Plazos de Restitución (Art. 3° y 4°)',
                'desc' => 'Regula los plazos mínimos de preaviso para contratos a plazo indefinido o mes a mes: 2 meses fijos, más un mes adicional por cada año completo de arriendo hasta un máximo de 6 meses.'
            ],
            [
                'titulo' => 'Presunción Legal en Ausencia de Contrato Escrito (Art. 20)',
                'desc' => 'Si el arriendo no consta por escrito, la ley presume que la renta pactada es la que declare el arrendatario, salvo que el arrendador presente recibos de pago consecutivos de los últimos 3 meses.'
            ]
        ],
        'aplicacion_sinnotario' => 'SinNotario.cl formaliza el arriendo en un instrumento escrito robusto, erradicando la indefensión probatoria del Art. 20 y fijando garantías, inventarios y reglas de terminación claras.'
    ],
    'codigo-trabajo-177' => [
        'titulo' => 'Código del Trabajo - Art. 177',
        'subtitulo' => 'Estatuto de Formalización y Ratificación del Finiquito Laboral',
        'badge' => 'Validez del Término de la Relación Laboral',
        'fecha' => 'Código del Trabajo de la República de Chile (DFL N° 1)',
        'icono' => 'fa-user-tie',
        'bcn_url' => 'https://www.bcn.cl/leychile/navegar?idNorma=207436',
        'id_norma' => '207436',
        'resumen' => 'Regula las formalidades para que el finiquito laboral pueda ser válidamente invocado por el empleador, los plazos de otorgamiento y pago, la acreditación de cotizaciones previsionales y la reserva de derechos del trabajador.',
        'pilares' => [
            [
                'titulo' => 'Obligación de Escrituración y Ministros de Fe',
                'desc' => 'Para tener mérito liberatorio, el finiquito debe constar por escrito y ser ratificado por el trabajador ante un ministro de fe (Inspector del Trabajo, Notario, Oficial del Registro Civil, Secretario Municipal o dirigente sindical).'
            ],
            [
                'titulo' => 'Ratificación Electrónica con ClaveÚnica (Portal Mi DT)',
                'desc' => 'Conforme a la ley de modernización de la Dirección del Trabajo, el finiquito suscrito en el portal electrónico de la DT mediante ClaveÚnica se considera legalmente ratificado ante el Inspector del Trabajo.'
            ],
            [
                'titulo' => 'Plazo Estricto de 10 Días Hábiles',
                'desc' => 'El empleador tiene un plazo perentorio de diez días hábiles desde la separación del trabajador para poner a su disposición el finiquito y el pago íntegro de las indemnizaciones adeudadas.'
            ],
            [
                'titulo' => 'Exigencia de Cotizaciones Previsionales al Día (Ley Bustos)',
                'desc' => 'Es requisito legal inexcusable adjuntar las planillas de pago de cotizaciones previsionales, salud y cesantía hasta el último día del mes anterior al despido para que el finiquito tenga eficacia extintiva.'
            ]
        ],
        'aplicacion_sinnotario' => 'El borrador de finiquito emitido por SinNotario.cl desglosa legalmente cada haber, indemnización por años de servicio, feriado proporcional y habilita la firma para su ratificación presencial o vía Mi DT.'
    ],
    'cloudflare-r2' => [
        'titulo' => 'Bóveda Cifrada Cloudflare R2',
        'subtitulo' => 'Arquitectura de Custodia Criptográfica y Preservación Forense',
        'badge' => 'Seguridad Informática y Cadena de Custodia Digital',
        'fecha' => 'Estándar ISO/IEC 27001 & Cloudflare Enterprise',
        'icono' => 'fa-shield-alt',
        'bcn_url' => 'https://www.bcn.cl/leychile/navegar?idNorma=196640',
        'id_norma' => '196640',
        'resumen' => 'La custodia de todos los contratos y documentos firmados en SinNotario.cl se realiza en la bóveda distribuida sin egress fees de Cloudflare R2 (bucket sinnotario-vault-prod), garantizando inalterabilidad, sellado de tiempo y disponibilidad 99.99%.',
        'pilares' => [
            [
                'titulo' => 'Sellado Hash SHA-256 Inmutable',
                'desc' => 'Cada documento generado se somete a una función criptográfica unidireccional SHA-256 que calcula su huella digital única. Si un solo byte del archivo llegara a modificarse, el validador detectaría de inmediato la adulteración.'
            ],
            [
                'titulo' => 'Código QR Dinámico de Verificación',
                'desc' => 'Cada página del contrato impreso o digital incluye un código QR que enlaza al endpoint público https://sinnotario.cl/verificar?code=SN-XXXX, permitiendo a cualquier tercero contrastar el documento físico contra el original en la bóveda.'
            ],
            [
                'titulo' => 'Cifrado en Reposo y en Tránsito (AES-256 / TLS 1.3)',
                'desc' => 'Los archivos son cifrados con AES-256 al ingresar a la bóveda y se transfieren bajo certificados SSL/TLS 1.3 de extremo a extremo, cumpliendo con los más estrictos estándares de privacidad.'
            ],
            [
                'titulo' => 'Cadena de Custodia Legal Digital',
                'desc' => 'El sistema registra la fecha exacta de emisión, dirección IP, dispositivo de firma y firma electrónica, asegurando plena admisibilidad probatoria en tribunales de justicia chilenos.'
            ]
        ],
        'aplicacion_sinnotario' => 'Tu documento nunca se extravía, no se deteriora con el tiempo y cualquier persona o institución puede validar su autenticidad instantáneamente desde cualquier celular.'
    ]
];

// Obtener ley solicitada
$slug = isset($_GET['ley']) ? trim($_GET['ley']) : 'ley-19799';
if (!array_key_exists($slug, $leyes)) {
    $slug = 'ley-19799';
}

$item = $leyes[$slug];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['titulo'] . ' | Marco Legal SinNotario.cl'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($item['subtitulo']); ?>. Consulta la legislación chilena oficial y accede al texto íntegro en la Biblioteca del Congreso Nacional (BCN).">
    
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20261110_2">

    <style>
        .legal-page-header {
            background: linear-gradient(135deg, #0A1C33 0%, #0F2A4A 100%);
            color: #FFFFFF;
            padding: 4.5rem 1.5rem 3.5rem;
            position: relative;
            overflow: hidden;
        }
        .legal-page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent) 0%, #F59E0B 100%);
        }
        .legal-page-container {
            max-width: 1140px;
            margin: 0 auto;
        }
        .nav-legal-tabs {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            margin-top: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        .nav-legal-tabs a {
            color: #CBD5E1;
            text-decoration: none;
            padding: 0.65rem 1.1rem;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.05);
        }
        .nav-legal-tabs a:hover {
            color: #FFFFFF;
            background: rgba(255, 255, 255, 0.15);
        }
        .nav-legal-tabs a.active {
            color: #0A1C33;
            background: var(--accent);
            font-weight: 700;
        }

        .legal-content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2.5rem;
            margin: 3.5rem auto;
            max-width: 1140px;
            padding: 0 1.5rem;
        }
        @media (max-width: 860px) {
            .legal-content-grid {
                grid-template-columns: 1fr;
            }
        }

        .pillar-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-left: 4px solid var(--accent);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        .pillar-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .pillar-card p {
            color: #475569;
            font-size: 0.94rem;
            line-height: 1.6;
            margin: 0;
        }

        .bcn-action-card {
            background: linear-gradient(135deg, #F8FAFC 0%, #EFF6FF 100%);
            border: 2px solid #BFDBFE;
            border-radius: 12px;
            padding: 1.85rem;
            text-align: center;
            position: sticky;
            top: 2rem;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.08);
        }
        .bcn-badge {
            display: inline-block;
            background: #1E3A8A;
            color: #FFFFFF;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            margin-bottom: 1rem;
        }
        .btn-bcn-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            background: #003366;
            color: #FFFFFF !important;
            text-decoration: none;
            padding: 0.85rem 1.4rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            width: 100%;
            margin-top: 1.25rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 51, 102, 0.25);
        }
        .btn-bcn-link:hover {
            background: #002244;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0, 51, 102, 0.35);
        }
    </style>
</head>
<body>

    <!-- Header / Navbar -->
    <header class="site-header">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl Logo">
            </a>

            <ul class="nav-links">
                <li><a href="index.php">Trámites</a></li>
                <li><a href="index.php#verificar">Verificar Documento</a></li>
                <li><a href="empresas.php">Solución Empresas</a></li>
                <li><a href="asesoria-legal.php">Asesoría Legal IA</a></li>
                <li><a href="https://wa.me/56984180479?text=Hola,%20necesito%20asistencia%20legal%20en%20SinNotario.cl" target="_blank" class="nav-whatsapp"><i class="fab fa-whatsapp"></i> +56 9 8418 0479</a></li>
            </ul>

            <div>
                <a href="index.php#tramites" class="btn-cta">
                    <i class="fas fa-file-signature"></i> Iniciar Trámite
                </a>
            </div>
        </div>
    </header>

    <!-- Header del Marco Legal -->
    <section class="legal-page-header">
        <div class="legal-page-container">
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(212, 175, 55, 0.18); color: var(--accent); padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; margin-bottom: 1rem;">
                <i class="fas fa-balance-scale"></i> <?php echo htmlspecialchars($item['badge']); ?>
            </div>
            <h1 style="font-size: 2.6rem; font-weight: 800; margin-bottom: 0.6rem; letter-spacing: -0.02em;">
                <?php echo htmlspecialchars($item['titulo']); ?>
            </h1>
            <p style="font-size: 1.15rem; color: #94A3B8; max-width: 800px; line-height: 1.5; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($item['subtitulo']); ?> &bull; <span style="color: #CBD5E1;"><?php echo htmlspecialchars($item['fecha']); ?></span>
            </p>

            <!-- Menú de Navegación entre Leyes -->
            <div class="nav-legal-tabs">
                <a href="marco-legal.php?ley=ley-19799" class="<?php echo $slug === 'ley-19799' ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature"></i> Ley 19.799 (Firma Electrónica)
                </a>
                <a href="marco-legal.php?ley=ley-21461" class="<?php echo $slug === 'ley-21461' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Ley 21.461 (Devuélveme Mi Casa)
                </a>
                <a href="marco-legal.php?ley=ley-18101" class="<?php echo $slug === 'ley-18101' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> Ley 18.101 (Arrendamiento)
                </a>
                <a href="marco-legal.php?ley=codigo-trabajo-177" class="<?php echo $slug === 'codigo-trabajo-177' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i> Cód. Trabajo Art. 177 (Finiquitos)
                </a>
                <a href="marco-legal.php?ley=cloudflare-r2" class="<?php echo $slug === 'cloudflare-r2' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i> Bóveda Cifrada Cloudflare R2
                </a>
            </div>
        </div>
    </section>

    <!-- Cuerpo Principal -->
    <main class="legal-content-grid">
        <!-- Columna Izquierda: Explicación y Pilares -->
        <div>
            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--primary); margin-bottom: 1rem;">
                    Resumen Doctrinal y Alcance Jurídico
                </h2>
                <p style="font-size: 1.02rem; color: #334155; line-height: 1.7; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($item['resumen']); ?>
                </p>

                <div style="background: #F8FAFC; border-left: 4px solid var(--primary); padding: 1rem 1.25rem; border-radius: 6px;">
                    <strong style="color: var(--primary); font-size: 0.92rem;"><i class="fas fa-check-circle" style="color:#10B981;"></i> Implementación en SinNotario.cl:</strong>
                    <p style="color: #475569; font-size: 0.9rem; margin-top: 0.4rem; margin-bottom: 0;">
                        <?php echo htmlspecialchars($item['aplicacion_sinnotario']); ?>
                    </p>
                </div>
            </div>

            <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--primary); margin-bottom: 1.25rem;">
                Articulado y Aspectos Clave de la Normativa
            </h2>

            <?php foreach ($item['pilares'] as $pilar): ?>
                <div class="pillar-card">
                    <h3><i class="fas fa-gavel" style="color: var(--accent); margin-right: 0.4rem;"></i> <?php echo htmlspecialchars($pilar['titulo']); ?></h3>
                    <p><?php echo htmlspecialchars($pilar['desc']); ?></p>
                </div>
            <?php endforeach; ?>

            <div style="text-align: center; margin-top: 2.5rem;">
                <a href="index.php#tramites" class="btn-cta btn-gold" style="padding: 0.85rem 2rem; font-size: 1rem;">
                    <i class="fas fa-file-signature"></i> Ver Trámites Disponibles Bajo Esta Ley
                </a>
            </div>
        </div>

        <!-- Columna Derecha: Tarjeta Oficial BCN y Enlaces de Consulta -->
        <div>
            <div class="bcn-action-card">
                <span class="bcn-badge"><i class="fas fa-landmark"></i> Biblioteca del Congreso Nacional</span>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0A1C33; margin-bottom: 0.75rem;">
                    Texto Oficial en BCN (Ley Chile)
                </h3>
                <p style="color: #475569; font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.25rem;">
                    Accede a la publicación oficial, historia fidedigna de la ley, textos actualizados y modificaciones en la plataforma pública del Estado de Chile.
                </p>

                <div style="background: #FFFFFF; border: 1px solid #DBEAFE; border-radius: 8px; padding: 0.85rem; font-size: 0.82rem; color: #1E40AF; margin-bottom: 1.25rem; text-align: left;">
                    <i class="fas fa-info-circle"></i> <strong>Norma N°:</strong> <?php echo htmlspecialchars($item['id_norma']); ?><br>
                    <i class="fas fa-globe"></i> <strong>Fuente:</strong> bcn.cl / leychile.cl
                </div>

                <a href="<?php echo htmlspecialchars($item['bcn_url']); ?>" target="_blank" rel="noopener noreferrer" class="btn-bcn-link">
                    <i class="fas fa-external-link-alt"></i> Ver Ley en BCN.cl
                </a>

                <div style="margin-top: 1.5rem; border-top: 1px solid #DBEAFE; padding-top: 1.25rem; text-align: left;">
                    <h4 style="font-size: 0.88rem; font-weight: 700; color: #1E3A8A; margin-bottom: 0.5rem;">
                        ¿Necesitas Asesoría Legal?
                    </h4>
                    <p style="font-size: 0.82rem; color: #64748B; margin-bottom: 0.85rem;">
                        Nuestra IA Legal y equipo de abogados titulados están disponibles para resolver tus dudas.
                    </p>
                    <a href="asesoria-legal.php" style="color: var(--primary); font-weight: 700; font-size: 0.85rem; text-decoration: underline;">
                        Ir a Asesoría Legal Inteligente &rarr;
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="background: #0A1C33; color: white; padding: 3rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 4rem;">
        <div style="max-width: 1140px; margin: 0 auto; text-align: center; color: #94A3B8; font-size: 0.88rem;">
            <p>&copy; <?php echo date('Y'); ?> SinNotario.cl &bull; Keystone Advisors. Todos los derechos reservados.</p>
            <p style="margin-top: 0.5rem; font-size: 0.8rem;">Documentos suscritos con validez jurídica y fe pública digital bajo la Ley N° 19.799.</p>
        </div>
    </footer>

</body>
</html>

