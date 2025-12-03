<?php

namespace Cepi\Services;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Utils/EmailHasher.php';

use Database;
use Cepi\Utils\EmailHasher;

class ExportService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function exportToCsv($organisationId, $includeInactive = false) {
        $sql = "
            SELECT email_hash, mm_cepi, is_active
            FROM members
            WHERE organisation_id = :org_id
        ";
        
        if (!$includeInactive) {
            $sql .= " AND is_active = TRUE";
        }
        
        $sql .= " ORDER BY email_lookup_hash";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':org_id' => $organisationId]);
        $members = $stmt->fetchAll();
        
        // Genereer CSV
        $filename = 'cepi_export_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // BOM voor UTF-8 (Excel compatibiliteit)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, ['email_address', 'mm_cepi']);
        
        // Data - decrypt emails
        foreach ($members as $member) {
            $email = '';
            if (!empty($member['email_hash'])) {
                $email = EmailHasher::unhash($member['email_hash']);
                if ($email === false) {
                    $email = ''; // Fallback if decryption fails
                }
            }
            
            fputcsv($output, [
                $email,
                $member['mm_cepi'] ? 'TRUE' : 'FALSE'
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    public function exportToExcel($organisationId, $includeInactive = false) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $sql = "
            SELECT email_hash, mm_cepi, is_active
            FROM members
            WHERE organisation_id = :org_id
        ";
        
        if (!$includeInactive) {
            $sql .= " AND is_active = TRUE";
        }
        
        $sql .= " ORDER BY email_lookup_hash";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':org_id' => $organisationId]);
        $members = $stmt->fetchAll();
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set sheet title
        $sheet->setTitle('CEPI Members');
        
        // Header styling
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getStyle('A1:B1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
        
        // Header
        $sheet->setCellValue('A1', 'email_address');
        $sheet->setCellValue('B1', 'mm_cepi');
        
        // Data - decrypt emails
        $row = 2;
        foreach ($members as $member) {
            $email = '';
            if (!empty($member['email_hash'])) {
                $email = EmailHasher::unhash($member['email_hash']);
                if ($email === false) {
                    $email = ''; // Fallback if decryption fails
                }
            }
            
            $sheet->setCellValue('A' . $row, $email);
            $sheet->setCellValue('B' . $row, $member['mm_cepi'] ? 'TRUE' : 'FALSE');
            $row++;
        }
        
        // Auto-width columns
        foreach (range('A', 'B') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $filename = 'cepi_export_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

