<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Maak nieuwe spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('CEPI Members Template');

// Stel kolom breedtes in
$sheet->getColumnDimension('A')->setWidth(30);
$sheet->getColumnDimension('B')->setWidth(15);

// Header styling
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// Data cell styling
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

// Header rij voor data (rij 1)
$sheet->setCellValue('A1', 'email_address');
$sheet->setCellValue('B1', 'mm_cepi');

// Pas header styling toe
$sheet->getStyle('A1:B1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(25);

// Add instructions
$sheet->setCellValue('A3', 'INSTRUCTIONS:');
$sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
$sheet->setCellValue('A4', '1. Fill in all required columns (email_address and mm_cepi)');
$sheet->setCellValue('A5', '2. mm_cepi must be TRUE or FALSE (or 1/0, yes/no)');
$sheet->setCellValue('A6', '3. Delete these instruction rows (rows 3-6) before importing');
$sheet->setCellValue('A7', '4. Delete the example data rows (rows 8-10) and fill in your own data');
$sheet->setCellValue('A8', '5. Members will be automatically added to your logged in organization');

// Voorbeelddata rijen
$exampleData = [
    ['john.doe@example.com', 'TRUE'],
    ['jane.smith@example.com', 'FALSE'],
    ['bob.jones@example.com', 'TRUE']
];

$row = 8;
foreach ($exampleData as $data) {
    $sheet->setCellValue('A' . $row, $data[0]);
    $sheet->setCellValue('B' . $row, $data[1]);
    $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray($dataStyle);
    $row++;
}

// Data validatie voor email kolom
$validation = $sheet->getCell('A8')->getDataValidation();
$validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);
$validation->setFormula1('AND(ISNUMBER(FIND("@",A8)), LEN(A8)>5)');
$validation->setShowErrorMessage(true);
$validation->setErrorTitle('Invalid email address');
$validation->setError('Please enter a valid email address (e.g.: name@domain.com)');
$validation->setShowInputMessage(true);
$validation->setPromptTitle('Email address');
$validation->setPrompt('Please enter a valid email address');

// Data validatie voor mm_cepi kolom
$validation2 = $sheet->getCell('B8')->getDataValidation();
$validation2->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
$validation2->setFormula1('"TRUE,FALSE,1,0,yes,no,ja,nee"');
$validation2->setShowErrorMessage(true);
$validation2->setErrorTitle('Invalid value');
$validation2->setError('Please enter TRUE, FALSE, 1, 0, yes, or no');
$validation2->setShowInputMessage(true);
$validation2->setPromptTitle('MM CEPI');
$validation2->setPrompt('Is this member an MM CEPI member? (TRUE/FALSE)');

// Kopieer validatie naar meerdere rijen (rij 8-1000)
for ($i = 8; $i <= 1000; $i++) {
    $sheet->getCell('A' . $i)->setDataValidation(clone $validation);
    $sheet->getCell('B' . $i)->setDataValidation(clone $validation2);
}

// Freeze header rij
$sheet->freezePane('A2');

// Stel print gebied in
$sheet->getPageSetup()->setPrintArea('A1:B1000');

// Genereer en download bestand
$filename = 'CEPI_Members_Template_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

