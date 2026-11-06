<?php
/**
 * SinNotario.cl - Adaptador de Almacenamiento Criptográfico Cloudflare R2
 * Utiliza la API REST Nativa de Cloudflare Client v4 (Bearer Token de Alta Velocidad)
 */

class StorageR2Adapter {
    public string $accountId;
    public string $apiToken;
    public string $bucketName;

    public function __construct() {
        $this->accountId = getenv('CLOUDFLARE_ACCOUNT_ID') ?: (getenv('R2_ACCOUNT_ID') ?: 'cf9641882a84e35bbf80ae7681d7ee71');
        $this->apiToken  = getenv('CLOUDFLARE_API_TOKEN')  ?: (getenv('R2_API_TOKEN')  ?: 'cfat_i4KtjnZZ8GjFRttA23uEQC5hQvS6eI5Czcthy1g571fbf3a3');
        $this->bucketName= getenv('CLOUDFLARE_R2_BUCKET')  ?: (getenv('R2_BUCKET_NAME')?: 'sinnotario-vault-prod');
    }

    /**
     * Sube un archivo PDF cifrado al Bucket R2
     */
    public function uploadDocument(string $objectKey, string $fileContent, string $mimeType = 'application/pdf'): array {
        $objectKeyClean = ltrim($objectKey, '/');
        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/r2/buckets/{$this->bucketName}/objects/{$objectKeyClean}";

        $headers = [
            "Authorization: Bearer {$this->apiToken}",
            "Content-Type: {$mimeType}"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 200 && !empty($data['success'])) {
            return [
                'success' => true,
                'object_key' => $objectKeyClean,
                'sha256' => hash('sha256', $fileContent),
                'bucket' => $this->bucketName,
                'etag' => $data['result']['etag'] ?? null,
                'size' => $data['result']['size'] ?? strlen($fileContent)
            ];
        }

        return [
            'success' => false,
            'http_code' => $httpCode,
            'error' => $error ?: ($data['errors'][0]['message'] ?? $response)
        ];
    }

    /**
     * Descarga segura del contenido binario del documento
     */
    public function getDocumentContent(string $objectKey): ?string {
        $objectKeyClean = ltrim($objectKey, '/');
        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/r2/buckets/{$this->bucketName}/objects/{$objectKeyClean}";

        $headers = [
            "Authorization: Bearer {$this->apiToken}"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200) ? $response : null;
    }
}
