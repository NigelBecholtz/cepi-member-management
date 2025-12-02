<?php

namespace Cepi\Utils;

class FileValidator {
    private $allowedExtensions = ['csv', 'xls', 'xlsx'];
    private $maxFileSize;
    
    public function __construct() {
        $this->maxFileSize = $_ENV['UPLOAD_MAX_SIZE'] ?? 10485760; // 10MB default
    }
    
    public function validate($file) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Bestand upload mislukt. Error code: " . ($file['error'] ?? 'unknown');
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $errors[] = "Bestand is te groot. Maximum grootte: " . ($this->maxFileSize / 1024 / 1024) . "MB";
        }
        
        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $errors[] = "Ongeldig bestandsformaat. Toegestaan: " . implode(', ', $this->allowedExtensions);
        }
        
        // Check MIME type (basic check)
        $allowedMimes = [
            'text/csv',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes) && !in_array($extension, $this->allowedExtensions)) {
            $errors[] = "Ongeldig bestandstype gedetecteerd.";
        }
        
        return $errors;
    }
    
    public function getExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
}



