<?php
/**
 * Instructivo Operativo y Manual de Usuario para Empresas B2B
 * SinNotario.cl - Keystone Advisors SpA
 * Generación imprimible y descargable a PDF vía browser nativo / descarga
 */
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructivo Operativo Suite B2B Empresas | SinNotario.cl</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/logos/sinnotario-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        body {
            font-family: Arial, sans-serif;
            color: #333333;
            line-height: 1.45;
            background: #F8FAFC;
            margin: 0;
            padding: 2rem 1rem;
        }
        .doc-container {
            max-width: 820px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 3rem 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #E2E8F0;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0A1C33;
            padding-bottom: 1.25rem;
            margin-bottom: 2rem;
        }
        .brand {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0A1C33;
        }
        .brand span {
            color: #D4AF37;
        }
        h1 {
            color: #0A1C33;
            font-size: 1.6rem;
            margin-top: 0;
            border-bottom: 2px solid #003366;
            padding-bottom: 6px;
        }
        h2 {
            color: #006699;
            font-size: 1.25rem;
            margin-top: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .callout {
            background: #F0F4F8;
            border-left: 4px solid #006699;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 0.92rem;
            margin: 1rem 0;
        }
        .step-box {
            background: #FAFAFA;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .step-num {
            display: inline-block;
            background: #0A1C33;
            color: #D4AF37;
            font-weight: 800;
            width: 24px;
            height: 24px;
            text-align: center;
            line-height: 24px;
            border-radius: 50%;
            margin-right: 8px;
            font-size: 0.85rem;
        }
        .btn-print {
            background: #0A1C33;
            color: #FFFFFF;
            border: none;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover {
            background: #003366;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            margin: 1rem 0;
        }
        th {
            background: #0A1C33;
            color: #FFFFFF;
            padding: 8px 12px;
            text-align: left;
        }
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #E2E8F0;
        }
        @media print {
            body {
                background: #FFF;
                padding: 0;
            }
            .doc-container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="doc-container">
        
        <div class="header-bar">
            <div>
                <div class="brand">Sin<span>Notario</span>.cl</div>
                <div style="font-size: 0.8rem; color: #64748B;">Keystone Advisors SpA • Guía Oficial para Empresas</div>
            </div>
            <div class="no-print">
                <button type="button" class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir o Guardar PDF
                </button>
            </div>
        </div>

        <h1>Guía Rápida & Instructivo Operativo: Suite B2B Empresas</h1>
        <p style="color:#64748B; font-size: 0.95rem;">
            Aprende a emitir cientos de contratos con certificación legal en segundos, autogestionar tus bolsas de firmas prepagadas y cumplir con el Libro Digital de la Dirección del Trabajo (Ley N° 21.327).
        </p>

        <div class="callout">
            <strong>💡 Modelo Prepago 100% Flexible:</strong> Sin contratos forzosos mensuales ni permanencia. Tu empresa compra bolsas de firmas con hasta 45% de descuento y las utiliza cuando las necesite.
        </div>

        <h2>1. Estructura y Funcionamiento de las 4 Pestañas</h2>
        
        <div class="step-box">
            <span class="step-num">1</span> <strong>Smart Bulk & Emisión Masiva:</strong>
            <p style="margin: 6px 0 0; font-size: 0.88rem; color: #475569;">
                Descarga la planilla modelo en CSV, completa los datos de tus colaboradores (Nombre, RUT, Email, Teléfono, Cargo, Sueldo) y arrástrala. El motor validará el algoritmo Módulo 11 de cada RUT automáticamente.
            </p>
        </div>

        <div class="step-box">
            <span class="step-num">2</span> <strong>Firma en 1 Clic del Representante Legal:</strong>
            <p style="margin: 6px 0 0; font-size: 0.88rem; color: #475569;">
                El representante legal no necesita firmar documento por documento: presiona <em>"Firmar Lote con 1 Clic"</em> y el motor estampa la firma del empleador (FEA) sobre todo el lote en paralelo, generando los contratos con hash criptográfico SHA-256 e iniciando la convocatoria para los trabajadores.
            </p>
        </div>

        <div class="step-box">
            <span class="step-num">3</span> <strong>Exportación para Mi DT (Dirección del Trabajo):</strong>
            <p style="margin: 6px 0 0; font-size: 0.88rem; color: #475569;">
                Haz clic en <em>"Exportar Libro DT"</em>. El sistema generará el archivo CSV codificado con las columnas y formato exacto exigido por el portal Mi DT para registrar los contratos dentro de los 15 días legales y evitar multas.
            </p>
        </div>

        <div class="step-box">
            <span class="step-num">4</span> <strong>Mis Plantillas Propias (Variables Dinámicas):</strong>
            <p style="margin: 6px 0 0; font-size: 0.88rem; color: #475569;">
                Si tu empresa tiene su propio contrato redactado por sus abogados, ve a la pestaña <em>"2. Mis Plantillas Propias"</em>, pega tu texto y reemplaza los campos variables con <code>{{NOMBRE}}</code>, <code>{{RUT}}</code>, <code>{{CARGO}}</code>, <code>{{SUELDO}}</code>. El sistema armará tu planilla Excel a la medida exacta de tu formato.
            </p>
        </div>

        <div class="step-box">
            <span class="step-num">5</span> <strong>Expediente Digital del Trabajador:</strong>
            <p style="margin: 6px 0 0; font-size: 0.88rem; color: #475569;">
                En la pestaña <em>"3. Expediente del Trabajador"</em> puedes buscar a cualquier colaborador por RUT o nombre para ver su carpeta digital completa: contrato primitivo, anexos de 40 Horas, comprobantes y finiquitos bajo custodia notarial.
            </p>
        </div>

        <h2>2. Tarifario de Bolsas Prepagadas con Descuento</h2>
        <table>
            <thead>
                <tr>
                    <th>Bolsa / Pack</th>
                    <th>Firmas Incluidas</th>
                    <th>Valor Unitario (+ IVA)</th>
                    <th>Total Prepago</th>
                    <th>Ahorro vs Tarifa Retail</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Pack 50</strong></td>
                    <td>50 firmas</td>
                    <td>$3.490 CLP</td>
                    <td>$174.500 CLP</td>
                    <td>30% de ahorro</td>
                </tr>
                <tr style="background:#FEF3C7;">
                    <td><strong>Pack 200 (Popular)</strong></td>
                    <td>200 firmas</td>
                    <td>$2.790 CLP</td>
                    <td>$558.000 CLP</td>
                    <td>45% de ahorro</td>
                </tr>
                <tr>
                    <td><strong>Pack 500 Enterprise</strong></td>
                    <td>500+ firmas</td>
                    <td>$1.990 CLP</td>
                    <td>$995.000 CLP</td>
                    <td>Tarifa preferencial mayorista</td>
                </tr>
            </tbody>
        </table>

        <h2>3. Soporte y Atención Corporativa</h2>
        <p style="font-size: 0.9rem; color: #475569;">
            Para asistencia en la configuración de la instancia de WhatsApp de tu empresa o consultas operativas:<br>
            • Correo de Operaciones: <strong>contacto@sinnotario.cl</strong><br>
            • WhatsApp de Soporte: <strong>+56 9 8418 0479</strong><br>
            • Portal Web: <strong>https://sinnotario.cl/empresas.php</strong>
        </p>

        <div style="margin-top: 3rem; text-align: center; font-size: 0.8rem; color: #94A3B8; border-top: 1px solid #E2E8F0; padding-top: 1rem;">
            Documento Informativo Oficial SinNotario.cl • Keystone Advisors SpA • Santiago de Chile
        </div>

    </div>

</body>
</html>
