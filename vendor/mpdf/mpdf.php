<?php
// Placeholder untuk mPDF
// Download library mPDF dari: https://github.com/mpdf/mpdf/releases
// Extract ke folder ini

// Untuk instalasi cepat tanpa composer:
// 1. Download: https://github.com/mpdf/mpdf/archive/refs/heads/development.zip
// 2. Extract ke folder vendor/mpdf/
// 3. Atau gunakan CDN/online converter

class mPDF {
    private $content = '';
    
    public function __construct($mode = '', $format = 'A4') {
        // Simplified version
    }
    
    public function WriteHTML($html) {
        $this->content = $html;
    }
    
    public function Output($filename, $mode = 'I') {
        // Fallback: use DOMPDF or browser print
        if ($mode == 'D') {
            header('Content-Type: text/html');
            echo $this->content;
            echo '<script>window.print();</script>';
        }
    }
}
?>
