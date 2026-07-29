<?php
// =========================================================================
// OCR.Space Provider
// Free tier: register at https://ocr.space/ocrapi to get an API key.
// Supports: JPG, PNG, GIF, TIFF, BMP, PDF (up to 1 MB free / 5 MB paid).
// Docs: https://ocr.space/ocrapi
// =========================================================================

require_once __DIR__ . '/OcrProvider.php';

class OcrSpaceProvider extends OcrProvider {

    const PROVIDER_NAME  = 'ocr_space';
    const PROVIDER_LABEL = 'OCR.Space';
    const API_ENDPOINT   = 'https://api.ocr.space/parse/image';

    /** @var string */
    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Send the file to OCR.Space and return extracted plain text.
     */
    public function extractText($filePath, $mimeType) {
        if (!$this->apiKey) {
            return ['success' => false, 'text' => '', 'message' => 'No OCR.Space API key configured.'];
        }
        if (!file_exists($filePath)) {
            return ['success' => false, 'text' => '', 'message' => 'Uploaded file not found on server.'];
        }

        // File-size advisory (free tier = 1 MB)
        $sizeKB = round(filesize($filePath) / 1024);

        $postFields = [
            'apikey'            => $this->apiKey,
            'file'              => new CURLFile($filePath, $mimeType, basename($filePath)),
            'language'          => 'eng',
            'isOverlayRequired' => 'false',
            'detectOrientation' => 'true',
            'scale'             => 'true',
            // Engine 2 handles printed documents (passports, IDs) better
            'OCREngine'         => '2',
        ];

        $ch = curl_init(self::API_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Travio-ERP/1.0',
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // Network-level error
        if ($curlErr || !$raw) {
            return ['success' => false, 'text' => '',
                'message' => 'Could not reach OCR.Space: ' . ($curlErr ?: 'empty response. Check server internet access.')];
        }

        // HTTP error
        if ($httpCode === 403) {
            return ['success' => false, 'text' => '',
                'message' => 'OCR.Space rejected the request (HTTP 403). Check that your API key is valid and active.'];
        }
        if ($httpCode === 429) {
            return ['success' => false, 'text' => '',
                'message' => 'OCR.Space rate limit reached (HTTP 429). Wait a moment or upgrade your plan.'];
        }
        if ($httpCode !== 200) {
            return ['success' => false, 'text' => '',
                'message' => "OCR.Space returned HTTP {$httpCode}. Please try again."];
        }

        $resp = json_decode($raw, true);
        if (!is_array($resp)) {
            return ['success' => false, 'text' => '', 'message' => 'OCR.Space returned unreadable data.'];
        }

        // API-level error
        if (!empty($resp['IsErroredOnProcessing'])) {
            $errMsg = $resp['ErrorMessage'] ?? '';
            if (is_array($errMsg)) $errMsg = implode('; ', $errMsg);
            if (!$errMsg && !empty($resp['ParsedResults'][0]['ErrorMessage']))
                $errMsg = $resp['ParsedResults'][0]['ErrorMessage'];
            if (!$errMsg) $errMsg = 'OCR processing failed.';
            // Auth error embedded in message
            if (stripos($errMsg, 'Unauthorized') !== false || stripos($errMsg, 'Invalid API') !== false)
                $errMsg = 'Invalid API key. Update it in OCR Settings.';
            return ['success' => false, 'text' => '', 'message' => $errMsg];
        }

        // ExitCode: 1=OK, 2=partial, 3=failed, 4=timeout
        $exitCode = $resp['OCRExitCode'] ?? 3;
        if ($exitCode >= 3) {
            $errMsg = $resp['ErrorMessage'] ?? 'No text could be extracted.';
            if (is_array($errMsg)) $errMsg = implode('; ', $errMsg);
            return ['success' => false, 'text' => '', 'message' => $errMsg];
        }

        // Aggregate text from all pages/results
        $text = '';
        foreach (($resp['ParsedResults'] ?? []) as $result) {
            $text .= ($result['ParsedText'] ?? '') . "\n";
        }
        $text = trim($text);

        if (!$text) {
            $hint = $sizeKB > 900
                ? " The file ({$sizeKB} KB) may exceed the free-tier 1 MB limit."
                : ' Try a higher-resolution or clearer image.';
            return ['success' => false, 'text' => '',
                'message' => 'OCR returned no text.' . $hint];
        }

        return ['success' => true, 'text' => $text, 'message' => 'Text extracted via OCR.Space.'];
    }
}
