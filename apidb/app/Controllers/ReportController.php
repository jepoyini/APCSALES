<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';

// require_once APPPATH . 'ThirdParty/PhpSpreadsheet/autoload.php';

// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends ResourceController
{
	public function summary()
	{
	    global $conn;

	    $period = (int)($this->request->getVar('period') ?? 7);
	    if ($period <= 0) $period = 30;

	    // Dates
	    $end   = date('Y-m-d');
	    $start = date('Y-m-d', strtotime("-$period days"));

	    // --- Warehouses summary
	    $sql = "SELECT w.id, w.name, w.location, COUNT(i.id) AS items
	            FROM warehouses w
	            LEFT JOIN items i ON w.id=i.current_warehouse_id AND i.status <> 'CREATED'
	            GROUP BY w.id, w.name, w.location";
	    $warehouses = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

	    // --- Daily activity (scans / movements from product_tracking)
	    $sql = "SELECT DATE(created_at) AS d, COUNT(*) AS scans
	            FROM product_tracking
	            WHERE created_at BETWEEN ? AND ?
	            GROUP BY DATE(created_at)";
	    $stmt = $conn->prepare($sql);
	    $stmt->bind_param("ss", $start, $end);
	    $stmt->execute();
	    $daily = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	    $stmt->close();

	    // --- Breakdown (status changes)
	    $sql = "SELECT DATE(created_at) AS d, status, COUNT(*) AS c
	            FROM product_tracking
	            WHERE created_at BETWEEN ? AND ?
	            GROUP BY DATE(created_at), status";
	    $stmt = $conn->prepare($sql);
	    $stmt->bind_param("ss", $start, $end);
	    $stmt->execute();
	    $rawBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	    $stmt->close();

	    $breakdown = [];
	    foreach ($rawBreakdown as $r) {
	        $breakdown[$r['d']][$r['status']] = (int)$r['c'];
	    }

	    // --- System metrics
	    $metrics = [
	        'totalScansToday' => (int)($conn->query("SELECT COUNT(*) AS c FROM product_tracking WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0),
	        'activeWarehouses' => (int)($conn->query("SELECT COUNT(*) AS c FROM warehouses")->fetch_assoc()['c'] ?? 0),
	        'inTransit' => (int)($conn->query("SELECT COUNT(*) AS c FROM items WHERE status='IN_TRANSIT'")->fetch_assoc()['c'] ?? 0),
	    ];

	    return $this->response->setJSON([
	        'status' => 'success',
	        'warehouses' => $warehouses,
	        'daily' => $daily,
	        'breakdown' => $breakdown,
	        'metrics' => $metrics,
	    ]);
	}

	public function exportInventoryExcel()
	{
	    global $conn;

	    $spreadsheet = new Spreadsheet();
	    $sheet = $spreadsheet->getActiveSheet();

	    // Header row
	    $sheet->fromArray(["Warehouse", "Location", "Items"], NULL, 'A1');

	    $sql = "SELECT w.name, w.location, COUNT(i.id) AS items
	            FROM warehouses w
	            LEFT JOIN items i ON w.id=i.current_warehouse_id AND i.status <> 'CREATED'
	            GROUP BY w.id";
	    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

	    $r = 2;
	    foreach ($rows as $row) {
	        $sheet->setCellValue("A$r", $row['name']);
	        $sheet->setCellValue("B$r", $row['location']);
	        $sheet->setCellValue("C$r", $row['items']);
	        $r++;
	    }

	    // Download
	    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	    header('Content-Disposition: attachment;filename="inventory-report.xlsx"');
	    header('Cache-Control: max-age=0');

	    $writer = new Xlsx($spreadsheet);
	    $writer->save('php://output');
	    exit;
	}


	public function exportPdf()
	{
	    require_once APPPATH . 'ThirdParty/dompdf/autoload.inc.php';

	    $dompdf = new \Dompdf\Dompdf();
	    $dompdf->loadHtml('<h1>Hello World from Dompdf</h1>');
	    $dompdf->setPaper('A4', 'portrait');
	    $dompdf->render();
	    $dompdf->stream("report.pdf", ["Attachment" => false]);
	}

	public function exportMovementPdf()
	{
	    global $conn;
	    require_once APPPATH . 'ThirdParty/tcpdf/tcpdf.php';

	    $pdf = new TCPDF();
	    $pdf->AddPage();
	    $pdf->SetFont('helvetica', '', 10);

	    $pdf->Cell(0, 10, 'Movement Report', 0, 1, 'C');

	    $pdf->Ln(5);
	    $pdf->SetFont('helvetica', 'B', 9);
	    $pdf->Cell(40, 7, 'Date', 1);
	    $pdf->Cell(40, 7, 'Received', 1);
	    $pdf->Cell(40, 7, 'Assigned', 1);
	    $pdf->Cell(40, 7, 'Moved', 1);
	    $pdf->Ln();

	    $sql = "SELECT DATE(created_at) AS d,
	                   SUM(status='IN_STOCK') AS received,
	                   SUM(status='AVAILABLE') AS assigned,
	                   SUM(status='IN_TRANSIT') AS moved
	            FROM product_tracking
	            GROUP BY DATE(created_at)
	            ORDER BY d DESC
	            LIMIT 30";
	    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

	    $pdf->SetFont('helvetica', '', 9);
	    foreach ($rows as $row) {
	        $pdf->Cell(40, 6, $row['d'], 1);
	        $pdf->Cell(40, 6, $row['received'], 1);
	        $pdf->Cell(40, 6, $row['assigned'], 1);
	        $pdf->Cell(40, 6, $row['moved'], 1);
	        $pdf->Ln();
	    }

	    $pdf->Output('movement-report.pdf', 'D');
	}


}
