<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

// 1. Include the main TCPDF library file.
require_once('tcpdf/tcpdf.php');

$user_id = $_SESSION['user_id'];

// 2. Get Setup ID and verify ownership
if (!isset($_GET['setup_id'])) { die('Setup ID not provided.'); }
$setup_id = intval($_GET['setup_id']);

$stmt_setup = $pdo->prepare("SELECT s.*, m.name as model_name FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ? AND m.user_id = ?");
$stmt_setup->execute([$setup_id, $user_id]);
$setup = $stmt_setup->fetch(PDO::FETCH_ASSOC);
if (!$setup) { die('Setup not found or you do not have permission to view it.'); }

// 3. Fetch all related data for this setup
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
$stmt_weights = $pdo->prepare("SELECT * FROM weight_distribution WHERE setup_id = ?");
$stmt_weights->execute([$setup_id]);
$data['weights'] = $stmt_weights->fetch(PDO::FETCH_ASSOC);

// Extend TCPDF to create custom Header and Footer
class MYPDF extends TCPDF {
    public function Header() {
        // You could add a logo here if you have one:
        // $this->Image('path/to/your/logo.png', 10, 10, 30);
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'RC Setup Sheet', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Generated by Pan Car Setup App - Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// 4. Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

// --- START PDF CONTENT ---

// Main Title Block
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 10, $setup['model_name'], 1, 1, 'L', 1);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, $setup['name'], 1, 1, 'L', 1);
$pdf->Ln(5);

// Helper function to generate HTML for a data section
function getSectionHtml($title, $section_data) {
    if (empty($section_data)) return '';
    $html = '<table cellpadding="4" cellspacing="0" style="width:100%;">';
    $html .= '<tr><td colspan="2" style="background-color:#EAEAEA; border:1px solid #CCCCCC;"><b>' . $title . '</b></td></tr>';
    foreach ($section_data as $key => $val) {
        if (!in_array($key, ['id', 'setup_id', 'position', 'notes', 'comment', 'charging_notes']) && isset($val) && $val !== '') {
            $label = htmlspecialchars(ucwords(str_replace('_', ' ', $key)));
            $value = htmlspecialchars($val);
            $html .= '<tr><td width="50%" style="border-bottom:1px solid #EEEEEE;">' . $label . '</td><td width="50%" style="border-bottom:1px solid #EEEEEE;">' . $value . '</td></tr>';
        }
    }
    $html .= '</table><br>';
    return $html;
}

// --- Build the HTML content for the PDF ---
$html_content = '<table cellpadding="5" cellspacing="0" width="100%"><tr>';

// --- LEFT COLUMN ---
$html_content .= '<td width="50%" valign="top">';
$html_content .= getSectionHtml('Front Suspension', $data['front_suspension']);
$html_content .= getSectionHtml('Front Tires', $data['tires_front']);
$html_content .= getSectionHtml('Drivetrain', $data['drivetrain']);
$html_content .= '</td>';

// --- RIGHT COLUMN ---
$html_content .= '<td width="50%" valign="top">';
$html_content .= getSectionHtml('Rear Suspension', $data['rear_suspension']);
$html_content .= getSectionHtml('Rear Tires', $data['tires_rear']);
$html_content .= getSectionHtml('Electronics', $data['electronics']);
$html_content .= '</td>';

$html_content .= '</tr></table>';

// --- WEIGHT DISTRIBUTION (Full Width) ---
if ($data['weights']) {
    $lf = floatval($data['weights']['lf_weight']); $rf = floatval($data['weights']['rf_weight']);
    $lr = floatval($data['weights']['lr_weight']); $rr = floatval($data['weights']['rr_weight']);
    $total = $lf + $rf + $lr + $rr;
    
    $html_content .= '<br><table cellpadding="4" cellspacing="0" style="width:100%;">';
    $html_content .= '<tr><td colspan="5" style="background-color:#EAEAEA; border:1px solid #CCCCCC;"><b>Weight Distribution</b></td></tr>';
    $html_content .= '<tr style="background-color:#F5F5F5; text-align:center;"><td></td><td><b>Left</b></td><td><b>Right</b></td><td><b>Total</b></td><td><b>Percent</b></td></tr>';
    $html_content .= '<tr style="text-align:center;"><td><b>Front</b></td><td>'.$lf.' g</td><td>'.$rf.' g</td><td>'.number_format($lf+$rf, 1).' g</td><td>'.($total > 0 ? number_format((($lf+$rf)/$total)*100, 1) : 0).'%</td></tr>';
    $html_content .= '<tr style="text-align:center;"><td><b>Rear</b></td><td>'.$lr.' g</td><td>'.$rr.' g</td><td>'.number_format($lr+$rr, 1).' g</td><td>'.($total > 0 ? number_format((($lr+$rr)/$total)*100, 1) : 0).'%</td></tr>';
    $html_content .= '<tr style="text-align:center;"><td><b>Total</b></td><td>'.number_format($lf+$lr, 1).' g</td><td>'.number_format($rf+$rr, 1).' g</td><td><b>'.number_format($total, 1).' g</b></td><td></td></tr>';
    $html_content .= '<tr style="text-align:center;"><td><b>Percent</b></td><td>'.($total > 0 ? number_format((($lf+$lr)/$total)*100, 1) : 0).'%</td><td>'.($total > 0 ? number_format((($rf+$rr)/$total)*100, 1) : 0).'%</td><td></td><td><b>Cross: </b>'.($total > 0 ? number_format((($lf+$rr)/$total)*100, 1) : 0).'%</td></tr>';
    $html_content .= '</table>';
}

// --- NOTES (Full Width) ---
$notes_html = '';
if (!empty($data['front_suspension']['notes'])) $notes_html .= '<b>Front Suspension:</b> ' . htmlspecialchars($data['front_suspension']['notes']) . '<br>';
if (!empty($data['rear_suspension']['notes'])) $notes_html .= '<b>Rear Suspension:</b> ' . htmlspecialchars($data['rear_suspension']['notes']) . '<br>';
if (!empty($data['electronics']['charging_notes'])) $notes_html .= '<b>Charging Notes:</b> ' . htmlspecialchars($data['electronics']['charging_notes']) . '<br>';
if (!empty($data['comments']['comment'])) $notes_html .= '<b>General Comments:</b> ' . htmlspecialchars($data['comments']['comment']) . '<br>';

if (!empty($notes_html)) {
    $html_content .= '<br><table cellpadding="4" cellspacing="0" style="width:100%;">';
    $html_content .= '<tr><td style="background-color:#EAEAEA; border:1px solid #CCCCCC;"><b>Notes & Comments</b></td></tr>';
    $html_content .= '<tr><td>' . $notes_html . '</td></tr>';
    $html_content .= '</table>';
}


// Write the HTML to the PDF
$pdf->writeHTML($html_content, true, false, true, false, '');

// 5. Close and output PDF document
$pdf_filename = 'setup-sheet-' . $setup_id . '-' . str_replace(' ', '-', $setup['name']) . '.pdf';
$pdf->Output($pdf_filename, 'I');

?>