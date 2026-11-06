<?php
/**
 * SinNotario.cl - Adaptador Inteligente Gemini AI
 * Soporta análisis de contratos (Smart Legal Check) y redacción a medida (Smart Drafter)
 * Si no hay GEMINI_API_KEY configurada, activa el motor heurístico jurídico chileno de contingencia.
 */

class GeminiLegalService {
    private $apiKey;
    private $model;

    public function __construct() {
        $this->apiKey = getenv('GEMINI_API_KEY') ?: '';
        $this->model  = getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash';
    }

    /**
     * Smart Legal Check: Analiza el texto de un contrato y detecta riesgos bajo ley chilena
     */
    public function analyzeContractText(string $contractText): array {
        if (!empty($this->apiKey)) {
            return $this->callGeminiForAudit($contractText);
        }

        // Motor Heurístico Jurídico Chileno de Respaldo
        return $this->heuristicLegalCheck($contractText);
    }

    /**
     * Smart Legal Drafter: Genera un borrador a partir de lenguaje natural (libre albedrío)
     */
    public function draftContractFromPrompt(string $userPrompt, array $parameters = []): array {
        if (!empty($this->apiKey)) {
            return $this->callGeminiForDrafting($userPrompt, $parameters);
        }

        // Generación guiada por reglas jurídicas
        return $this->heuristicLegalDrafter($userPrompt, $parameters);
    }

    private function callGeminiForAudit(string $contractText): array {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $systemInstruction = "Eres un abogado chileno experto en derecho civil, comercial y laboral (Leyes N° 19.799, 21.461, 18.101, Código Civil y Código del Trabajo).
Tu tarea es auditar el texto del contrato adjunto y entregar un JSON estructurado con:
- is_notary_mandatory: boolean (true si es compraventa de bienes raíces, testamento, mandatos judiciales o requiere notario por ley)
- notary_warning: string o null
- solid_points: array de strings (aspectos legales sólidos)
- warnings: array de strings (alertas medias o mejoras)
- critical_risks: array de strings (riesgos altos, cláusulas nulas o abusivas)
- summary: string resumen ejecutivo en español.";

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemInstruction . "\n\nTexto del documento a auditar:\n" . substr($contractText, 0, 10000)]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $rawJson = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $parsed = json_decode($rawJson, true);
            if ($parsed) return ['success' => true, 'engine' => 'gemini_ai', 'audit' => $parsed];
        }

        return $this->heuristicLegalCheck($contractText);
    }

    private function heuristicLegalCheck(string $text): array {
        $lower = mb_strtolower($text, 'UTF-8');

        $isNotary = false;
        $notaryWarning = null;

        // Detección de actos solemnes
        if (strpos($lower, 'compraventa') !== false && (strpos($lower, 'inmueble') !== false || strpos($lower, 'casa') !== false || strpos($lower, 'departamento') !== false || strpos($lower, 'propiedad') !== false)) {
            $isNotary = true;
            $notaryWarning = "Por mandato del Art. 1801 del Código Civil, la compraventa definitiva de bienes raíces exige Escritura Pública notarial e inscripción en el CBR.";
        } elseif (strpos($lower, 'testamento') !== false || strpos($lower, 'herencia') !== false) {
            $isNotary = true;
            $notaryWarning = "Los testamentos solemnes exigen presencia de Notario Público y testigos según el Art. 999 del Código Civil.";
        }

        $critical = [];
        $warnings = [];
        $solid = [];

        // Análisis para arriendos
        if (strpos($lower, 'arriend') !== false || strpos($lower, 'arrendamiento') !== false) {
            if (strpos($lower, '21.461') !== false || strpos($lower, 'devuélveme mi casa') !== false) {
                $solid[] = "Incorpora expresamente sumisión a la Ley N° 21.461 ('Devuélveme Mi Casa') con mérito ejecutivo.";
            } else {
                $critical[] = "Falta cláusula expresa de sumisión a la Ley N° 21.461. Se sugiere agregar el procedimiento monitorio para desalojo express en 10 días.";
            }

            if (strpos($lower, 'ipc') !== false || strpos($lower, 'reajuste') !== false) {
                $solid[] = "Estipula reajustabilidad periódica de la renta (IPC).";
            } else {
                $warnings[] = "No se observa mecanismo explícito de reajustabilidad por inflación (IPC trimestral o semestral).";
            }

            if (strpos($lower, 'garantía') !== false || strpos($lower, 'garantia') !== false) {
                $solid[] = "Contempla fondo de garantía para conservación del inmueble.";
            } else {
                $warnings[] = "Se aconseja regular el plazo de restitución del mes de garantía (habitualmente 30 días corridos post-entrega).";
            }
        }

        // Análisis de validez electrónica
        if (strpos($lower, '19.799') !== false || strpos($lower, 'firma electrónica') !== false) {
            $solid[] = "Consigna formalmente validez probatoria conforme a la Ley N° 19.799.";
        } else {
            $warnings[] = "Es recomendable explicitar la sumisión a la Ley N° 19.799 para ratificar el valor probatorio de la firma electrónica.";
        }

        return [
            'success' => true,
            'engine' => 'heuristic_chile_law',
            'audit' => [
                'is_notary_mandatory' => $isNotary,
                'notary_warning' => $notaryWarning,
                'solid_points' => $solid,
                'warnings' => $warnings,
                'critical_risks' => $critical,
                'summary' => "Diagnóstico estructurado conforme a la doctrina del Código Civil y leyes especiales chilenas."
            ]
        ];
    }

    private function callGeminiForDrafting(string $prompt, array $parameters): array {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $systemInstruction = "Eres un abogado chileno experto en redacción de contratos privados y acuerdos atípicos bajo el principio de Autonomía de la Voluntad (Art. 1545 Código Civil) y Ley N° 19.799.
Genera un contrato formal en texto claro y profesional, con cláusulas ordenadas (Comparecencia, Objeto, Obligaciones, Plazo, Precio o Penal, Sumisión a Ley 19.799 y Domicilio).
Devuelve un JSON con:
- title: string
- summary: string
- clauses: array de {title: string, text: string}";

        $body = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $systemInstruction . "\n\nSolicitud del usuario:\n" . $prompt]]]
            ],
            'generationConfig' => ['responseMimeType' => 'application/json']
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $rawJson = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $parsed = json_decode($rawJson, true);
        if ($parsed) return ['success' => true, 'engine' => 'gemini_ai', 'contract' => $parsed];

        return $this->heuristicLegalDrafter($prompt, $parameters);
    }

    private function heuristicLegalDrafter(string $prompt, array $params): array {
        return [
            'success' => true,
            'engine' => 'heuristic_drafter',
            'contract' => [
                'title' => 'Acuerdo Privado y Convención de Libre Albedrío',
                'summary' => 'Contrato estructurado bajo el principio de autonomía de la voluntad del Art. 1545 del Código Civil chileno.',
                'clauses' => [
                    ['title' => 'COMPARECENCIA', 'text' => 'En Santiago de Chile, comparecen las partes individualizadas al pie, mayores de edad, en pleno uso de sus facultades.'],
                    ['title' => 'PRIMERO: OBJETO DEL ACUERDO', 'text' => 'Las partes convienen en celebrar el presente acuerdo basado en la solicitud: ' . htmlspecialchars($prompt)],
                    ['title' => 'SEGUNDO: VIGENCIA Y OBLIGACIONES', 'text' => 'Las partes se comprometen a dar estricto y fiel cumplimiento a lo pactado de buena fe.'],
                    ['title' => 'TERCERO: VALIDEZ LEY N° 19.799', 'text' => 'Las partes reconocen plena fe probatoria a este instrumento suscrito mediante firma electrónica.']
                ]
            ]
        ];
    }
}

