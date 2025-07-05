<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

// 1. Include the main TCPDF library file.
require_once('tcpdf/tcpdf.php');

$user_id = $_SESSION['user_id'];

// 2. Get Setup ID and verify ownership (same as view_setup_sheet.php)
if (!isset($_GET['setup_id'])) {
    die('Setup ID not provided.');
}
$setup_id = intval($_GET['setup_id']);

$stmt_setup = $pdo->prepare("SELECT s.*, m.name as model_name FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ? AND m.user_id = ?");
$stmt_setup->execute([$setup_id, $user_id]);
$setup = $stmt_setup->fetch(PDO::FETCH_ASSOC);

if (!$setup) {
    die('Setup not found or you do not have permission to view it.');
}

// 3. Fetch all related data for this setup (this is the same logic from view_setup_sheet.php)
$data = [];
$tables = ['front_suspension', 'rear_suspension', 'drivetrain', 'body_chassis', 'electronics', 'esc_settings', 'comments'];
foreach ($tables as $table) {
    $stmt_data = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
    $stmt_data->execute([$setup_id]);
    $data[$table] = $stmt_data->fetch(PDO::FETCH_ASSOC);
}
$stmt_tires = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
$stmt_tires->execute([$setup_id, 'front']);
$data['tires_front'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);
$stmt_tires->execute([$setup_id, 'rear']);
$data['tires_rear'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);

// Extend TCPDF to create custom Header and Footer
class MYPDF extends TCPDF {
    public function Header() {
        // --- THIS IS THE CHANGE ---
        // Set the Y position to 15mm from the top of the page.
        // The default is ~10mm. Increase this value to move the header further down.
        $this->SetY(7);
        
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'RC Car Setup Sheet', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// 4. Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Pan Car Setup App');
$pdf->SetTitle('Setup Sheet - ' . $setup['name']);
$pdf->SetSubject('Setup Sheet');

// Add a page
$pdf->AddPage();

// --- START WRITING PDF CONTENT ---

// Main Title
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Write(0, $setup['model_name'], '', 0, 'L', true, 0, false, false, 0);
$pdf->SetFont('helvetica', '', 12);
$pdf->Write(0, $setup['name'], '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(5); // Add a line break

// Function to draw a section with a title and data
function drawSection($pdf, $title, $section_data) {
    if (empty($section_data)) return;
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(0, 8, $title, 1, 1, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    
    $html = '<table border="0" cellpadding="4">';
    foreach ($section_data as $key => $val) {
        if (!in_array($key, ['id', 'setup_id', 'position']) && isset($val) && $val !== '') {
            $label = htmlspecialchars(ucwords(str_replace('_', ' ', $key)));
            $value = htmlspecialchars($val);
            $html .= '<tr><td width="40%"><b>'.$label.'</b></td><td width="60%">'.$value.'</td></tr>';
        }
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(2);
}

// Use the function to draw each section
drawSection($pdf, 'Front Suspension', $data['front_suspension']);
drawSection($pdf, 'Rear Suspension', $data['rear_suspension']);
drawSection($pdf, 'Front Tires', $data['tires_front']);
drawSection($pdf, 'Rear Tires', $data['tires_rear']);
drawSection($pdf, 'Drivetrain', $data['drivetrain']);
drawSection($pdf, 'Electronics', $data['electronics']);
drawSection($pdf, 'ESC Settings', $data['esc_settings']);
drawSection($pdf, 'Body and Chassis', $data['body_chassis']);
drawSection($pdf, 'Comments', $data['comments']);

// 5. Close and output PDF document
$pdf_filename = 'setup-sheet-' . $setup_id . '-' . str_replace(' ', '-', $setup['name']) . '.pdf';
$pdf->Output($pdf_filename, 'I');

?>