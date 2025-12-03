<?php

namespace Cepi\Services;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/ImportLog.php';
require_once __DIR__ . '/../Models/Organisation.php';
require_once __DIR__ . '/../Models/ActivityLog.php';
require_once __DIR__ . '/../Utils/FileValidator.php';
require_once __DIR__ . '/../Utils/EmailHasher.php';

use Database;
use Cepi\Models\ImportLog;
use Cepi\Models\Organisation;
use Cepi\Models\ActivityLog;
use Cepi\Utils\FileValidator;
use Cepi\Utils\EmailHasher;

class ImportService {
    private $db;
    private $uploadDir;
    private $validator;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->uploadDir = __DIR__ . '/../../uploads/';
        $this->validator = new FileValidator();
        
        // Zorg dat upload directory bestaat
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    public function importFromFile($organisationId = null, $file, $username = null) {
        // Valideer bestand
        $errors = $this->validator->validate($file);
        if (!empty($errors)) {
            throw new \Exception(implode(' ', $errors));
        }
        
        $filename = $file['name'];
        $filepath = $this->uploadDir . uniqid('import_') . '_' . basename($filename);
        
        // Verplaats geüploade bestand
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception("Kon bestand niet uploaden naar server");
        }
        
        try {
            // Detecteer bestandstype en lees data
            $extension = $this->validator->getExtension($filename);
            $readResult = $this->readFile($filepath, $extension, $organisationId);
            
            // Gebruik Organisation_ID uit bestand als beschikbaar, anders gebruik parameter
            $actualOrgId = $readResult['organisation_id'] ?? $organisationId;
            $data = $readResult['data'] ?? [];
            
            // Controleer of we een organisatie hebben
            if (empty($actualOrgId)) {
                throw new \Exception("Geen organisatie gevonden. Vul Organisation_ID in in het Excel bestand (rij 1, cel B1) of selecteer een organisatie.");
            }
            
            if (empty($data)) {
                throw new \Exception("Bestand bevat geen data");
            }
            
            // Verwerk import (sync mode - Excel is bron van waarheid)
            $result = $this->processSyncImport($actualOrgId, $data);
            
            // Log import
            $this->logImport($actualOrgId, $filename, $data, $result);
            
            // Log activity
            $activityLog = new ActivityLog();
            $activityLog->log('organisation', $actualOrgId, $username ?? 'unknown', 'upload', [
                'filename' => $filename,
                'rows_imported' => $result['rows_imported'],
                'rows_added' => $result['rows_added'],
                'rows_updated' => $result['rows_updated'],
                'rows_deleted' => $result['rows_deleted'] ?? 0,
                'success' => empty($result['errors'])
            ]);
            
            // Voeg informatie toe over aangemaakte organisatie
            if (isset($readResult['organisation_created'])) {
                $result['organisation_created'] = $readResult['organisation_created'];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            // Log failed upload
            if (isset($actualOrgId)) {
                $activityLog = new ActivityLog();
                $activityLog->log('organisation', $actualOrgId, $username ?? 'unknown', 'upload', [
                    'filename' => $filename,
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        } finally {
            // Verwijder tijdelijk bestand
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
    
    private function readFile($filepath, $extension, $defaultOrgId = null) {
        $data = [];
        $organisationId = $defaultOrgId;
        $organisationCreated = null;
        
        if ($extension === 'csv') {
            $handle = fopen($filepath, 'r');
            if ($handle === false) {
                throw new \Exception("Kon CSV bestand niet lezen");
            }
            
            // Lees header
            $headers = fgetcsv($handle);
            if ($headers === false) {
                fclose($handle);
                throw new \Exception("Ongeldig CSV bestand - geen headers gevonden");
            }
            
            // Normaliseer headers (case-insensitive, trim whitespace)
            $headers = array_map(function($h) {
                return strtolower(trim($h));
            }, $headers);
            
            // Controleer of email_address aanwezig is
            if (!in_array('email_address', $headers) && !in_array('email', $headers)) {
                fclose($handle);
                throw new \Exception("CSV bestand moet een 'email_address' of 'email' kolom bevatten");
            }
            
            // Lees data rijen
            $rowNum = 2;
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    continue; // Skip incomplete rows
                }
                
                $rowData = array_combine($headers, $row);
                
                // Normaliseer email kolom naam
                if (isset($rowData['email']) && !isset($rowData['email_address'])) {
                    $rowData['email_address'] = $rowData['email'];
                }
                
                $data[] = $rowData;
                $rowNum++;
            }
            fclose($handle);
            
        } elseif (in_array($extension, ['xls', 'xlsx'])) {
            // Gebruik PhpSpreadsheet library
            require_once __DIR__ . '/../../vendor/autoload.php';
            
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader(
                    $extension === 'xlsx' ? 'Xlsx' : 'Xls'
                );
                $spreadsheet = $reader->load($filepath);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Lees header (rij 1)
                $headers = [];
                $headerRow = $worksheet->getRowIterator(1, 1)->current();
                foreach ($headerRow->getCellIterator() as $cell) {
                    $value = $cell->getValue();
                    if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                        $value = $value->getPlainText();
                    }
                    $headers[] = strtolower(trim($value));
                }
                
                // Controleer of email_address aanwezig is
                if (!in_array('email_address', $headers) && !in_array('email', $headers)) {
                    throw new \Exception("Excel bestand moet een 'email_address' of 'email' kolom bevatten");
                }
                
                // Lees data rijen (start vanaf rij 2, na header)
                foreach ($worksheet->getRowIterator(2) as $row) {
                    $rowData = [];
                    $cellIterator = $row->getCellIterator();
                    $colIndex = 0;
                    
                    foreach ($cellIterator as $cell) {
                        if ($colIndex < count($headers)) {
                            $value = $cell->getValue();
                            // Converteer PhpSpreadsheet RichText naar string
                            if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                                $value = $value->getPlainText();
                            }
                            $rowData[$headers[$colIndex]] = $value;
                            $colIndex++;
                        }
                    }
                    
                    // Normaliseer email kolom naam
                    if (isset($rowData['email']) && !isset($rowData['email_address'])) {
                        $rowData['email_address'] = $rowData['email'];
                    }
                    
                    // Alleen toevoegen als er data is
                    if (!empty($rowData['email_address'])) {
                        $data[] = $rowData;
                    }
                }
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                throw new \Exception("Kon Excel bestand niet lezen: " . $e->getMessage());
            }
        } else {
            throw new \Exception("Ongeldig bestandsformaat. Alleen CSV, XLS en XLSX zijn toegestaan.");
        }
        
        $result = [
            'data' => $data,
            'organisation_id' => $organisationId
        ];
        
        if ($organisationCreated) {
            $result['organisation_created'] = $organisationCreated;
        }
        
        return $result;
    }
    
    private function processSyncImport($organisationId, $data) {
        $added = 0;
        $updated = 0;
        $deleted = 0;
        $errors = [];
        
        // Haal alle huidige leden op voor deze organisatie (actief en inactief)
        $currentMembersStmt = $this->db->prepare("
            SELECT email_hash, email_lookup_hash, mm_cepi 
            FROM members 
            WHERE organisation_id = :org_id
        ");
        $currentMembersStmt->execute([':org_id' => $organisationId]);
        $currentMembers = $currentMembersStmt->fetchAll();
        
        // Maak een array van huidige email lookup hashes voor snelle lookup
        $currentEmails = [];
        foreach ($currentMembers as $member) {
            if (!empty($member['email_lookup_hash'])) {
                $currentEmails[$member['email_lookup_hash']] = $member;
            }
        }
        
        // Verzamel alle email adressen uit het Excel bestand
        $excelEmails = [];
        $validData = [];
        
        // Valideer en verwerk Excel data
        foreach ($data as $index => $row) {
            try {
                $email = strtolower(trim($row['email_address'] ?? ''));
                
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Rij " . ($index + 2) . ": Ongeldig email adres '{$row['email_address']}'";
                    continue;
                }
                
                $mm_cepi = $this->parseBoolean($row['mm_cepi'] ?? $row['mmcepi'] ?? false);
                
                // Hash email for storage and lookup
                $emailHash = EmailHasher::hash($email);
                $emailLookupHash = EmailHasher::hashForLookup($email);
                
                $excelEmails[$emailLookupHash] = true;
                $validData[$emailLookupHash] = [
                    'email' => $email,
                    'email_hash' => $emailHash,
                    'email_lookup_hash' => $emailLookupHash,
                    'mm_cepi' => $mm_cepi
                ];
                
            } catch (\Exception $e) {
                $errors[] = "Rij " . ($index + 2) . ": Fout - " . $e->getMessage();
            }
        }
        
        // INSERT/UPDATE statement voor leden in Excel
        // Note: We use email_lookup_hash for the unique constraint instead of email_address
        $upsertStmt = $this->db->prepare("
            INSERT INTO members 
            (organisation_id, email_address, email_hash, email_lookup_hash, mm_cepi, is_active)
            VALUES (:org_id, '', :email_hash, :email_lookup_hash, :mm_cepi, TRUE)
            ON DUPLICATE KEY UPDATE
                email_hash = VALUES(email_hash),
                mm_cepi = VALUES(mm_cepi),
                is_active = TRUE,
                updated_at = CURRENT_TIMESTAMP
        ");
        
        // DELETE statement voor leden die verwijderd moeten worden
        $deleteStmt = $this->db->prepare("
            DELETE FROM members 
            WHERE organisation_id = :org_id AND email_lookup_hash = :email_lookup_hash
        ");
        
        // Verwerk alle leden uit Excel (ADD of UPDATE)
        foreach ($validData as $emailLookupHash => $memberData) {
            try {
                $upsertStmt->execute([
                    ':org_id' => $organisationId,
                    ':email_hash' => $memberData['email_hash'],
                    ':email_lookup_hash' => $memberData['email_lookup_hash'],
                    ':mm_cepi' => $memberData['mm_cepi']
                ]);
                
                if (isset($currentEmails[$emailLookupHash])) {
                    // Bestaand lid - check of mm_cepi is veranderd
                    if ($currentEmails[$emailLookupHash]['mm_cepi'] != $memberData['mm_cepi']) {
                        $updated++;
                    }
                } else {
                    // Nieuw lid
                    $added++;
                }
                
            } catch (\PDOException $e) {
                $errors[] = "Email {$memberData['email']}: Database fout - " . $e->getMessage();
            }
        }
        
        // Verwijder leden die in database staan maar niet in Excel
        foreach ($currentEmails as $emailLookupHash => $member) {
            if (!isset($excelEmails[$emailLookupHash])) {
                try {
                    $deleteStmt->execute([
                        ':org_id' => $organisationId,
                        ':email_lookup_hash' => $emailLookupHash
                    ]);
                    
                    if ($deleteStmt->rowCount() > 0) {
                        $deleted++;
                    }
                } catch (\PDOException $e) {
                    $errors[] = "Kon lid niet verwijderen - " . $e->getMessage();
                }
            }
        }
        
        return [
            'rows_imported' => count($validData),
            'rows_added' => $added,
            'rows_updated' => $updated,
            'rows_deleted' => $deleted,
            'errors' => $errors
        ];
    }
    
    private function parseBoolean($value) {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (bool)$value;
        }
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'ja', 'y', 'waar']);
        }
        return false;
    }
    
    private function logImport($organisationId, $filename, $data, $result) {
        $status = !empty($result['errors']) ? 'partial' : 'success';
        $errorMsg = !empty($result['errors']) ? implode('; ', array_slice($result['errors'], 0, 10)) : null;
        
        // Beperk error message lengte
        if ($errorMsg && strlen($errorMsg) > 1000) {
            $errorMsg = substr($errorMsg, 0, 1000) . '...';
        }
        
        $importLog = new ImportLog();
        $importLog->create([
            'organisation_id' => $organisationId,
            'filename' => $filename,
            'rows_imported' => $result['rows_imported'],
            'rows_added' => $result['rows_added'],
            'rows_updated' => $result['rows_updated'],
            'rows_inactivated' => $result['rows_deleted'] ?? 0, // Gebruik rows_deleted voor backwards compatibility
            'import_status' => $status,
            'error_message' => $errorMsg
        ]);
    }
}

