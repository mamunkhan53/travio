<?php
// =========================================================================
// OCR Provider — Abstract Base Class
// Implement a new provider by extending this class and registering it
// in ocr_get_provider() inside ocr_actions.php.
// =========================================================================

abstract class OcrProvider {

    /**
     * Extract plain text from an uploaded document file.
     *
     * @param  string $filePath  Absolute path to the temporary/uploaded file.
     * @param  string $mimeType  MIME type of the file (image/jpeg, application/pdf, …).
     * @return array  {
     *   'success' => bool,
     *   'text'    => string,   // extracted plain text (empty on failure)
     *   'message' => string    // human-readable status / error
     * }
     */
    /**
     * @param  string $originalName  Original client filename (e.g. "passport.jpg") — used to
     *                               preserve the extension when the tmp path has none.
     */
    abstract public function extractText($filePath, $mimeType, $originalName = '');

    /** Machine-readable provider identifier, e.g. "ocr_space". */
    public function getName()  { return static::PROVIDER_NAME;  }

    /** Human-readable label shown in the UI, e.g. "OCR.Space". */
    public function getLabel() { return static::PROVIDER_LABEL; }
}
