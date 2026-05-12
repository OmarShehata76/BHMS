<?php
// includes/pdf_generator.php
// PDF Generation Module (UC-39 Pro-Forma Invoice + Reports)
// Uses TCPDF library — install via: composer require tecnickcom/tcpdf
// OR place tcpdf folder in /vendor/tcpdf/
// Falls back to HTML print if TCPDF not available

class PDFGenerator {

    private string $title;
    private string $hotel_name = "Boutique Hotel Management System";
    private array  $sections   = [];

    public function __construct(string $title) {
        $this->title = $title;
    }

    // ── Add a section ──────────────────────────────────
    public function addSection(string $heading, array $rows, array $headers = []): void {
        $this->sections[] = compact('heading', 'rows', 'headers');
    }

    // ── Generate HTML-based PDF (printable invoice) ────
    public function renderHTML(): string {
        $date = date('d M Y H:i');
        $html = '<!DOCTYPE html><html><head>
        <meta charset="UTF-8">
        <style>
          * { margin:0; padding:0; box-sizing:border-box; }
          body { font-family: Arial, sans-serif; font-size: 12px; color: #333; padding: 30px; }
          .header { border-bottom: 3px solid #B8962E; padding-bottom: 16px; margin-bottom: 20px; }
          .hotel-name { font-size: 24px; font-weight: bold; color: #1A1E28; }
          .doc-title { font-size: 16px; color: #B8962E; font-weight: bold; margin-top: 4px; }
          .meta { font-size: 11px; color: #888; margin-top: 4px; }
          h2 { font-size: 14px; color: #1F4E79; margin: 20px 0 8px; border-left: 3px solid #B8962E; padding-left: 8px; }
          table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
          th { background: #1F4E79; color: white; padding: 7px 10px; text-align: left; font-size: 11px; }
          td { padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 11px; }
          tr:nth-child(even) td { background: #f8f8f8; }
          .footer { border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; font-size: 10px; color: #aaa; text-align: center; }
          .total-row td { font-weight: bold; background: #E8F5E9 !important; color: #1E5E3E; }
          @media print {
            body { padding: 15px; }
            .no-print { display: none; }
            @page { margin: 15mm; }
          }
        </style>
        </head><body>';

        $html .= '<div class="header">
            <div class="hotel-name">🏨 ' . htmlspecialchars($this->hotel_name) . '</div>
            <div class="doc-title">' . htmlspecialchars($this->title) . '</div>
            <div class="meta">Generated: ' . $date . ' | System: BHMS v1.0</div>
        </div>';

        foreach ($this->sections as $section) {
            $html .= '<h2>' . htmlspecialchars($section['heading']) . '</h2>';
            if (!empty($section['headers'])) {
                $html .= '<table><thead><tr>';
                foreach ($section['headers'] as $h) {
                    $html .= '<th>' . htmlspecialchars($h) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                foreach ($section['rows'] as $row) {
                    $isTotal = isset($row['_total']) && $row['_total'];
                    $cls = $isTotal ? ' class="total-row"' : '';
                    $html .= '<tr' . $cls . '>';
                    foreach ($row as $k => $v) {
                        if ($k === '_total') continue;
                        $html .= '<td>' . htmlspecialchars((string)$v) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
            } else {
                foreach ($section['rows'] as $row) {
                    $html .= '<p style="margin:4px 0;font-size:12px">' . htmlspecialchars($row) . '</p>';
                }
            }
        }

        $html .= '<div class="footer">
            <p>' . htmlspecialchars($this->hotel_name) . ' | CS251 Software Engineering 1 | Capital University</p>
            <p>This document was generated automatically. Please verify all figures with your receptionist.</p>
        </div>';

        $html .= '<div class="no-print" style="margin-top:30px;text-align:center">
            <button onclick="window.print()" style="background:#B8962E;color:white;border:none;padding:10px 24px;font-size:14px;border-radius:6px;cursor:pointer">
                🖨 Print / Save as PDF
            </button>
            <button onclick="window.close()" style="background:#eee;color:#333;border:none;padding:10px 24px;font-size:14px;border-radius:6px;cursor:pointer;margin-left:10px">
                Close
            </button>
        </div>';

        $html .= '</body></html>';
        return $html;
    }

    // ── Output directly to browser (print window) ──────
    public function outputToBrowser(): void {
        // Log to audit trail
        AuditLogger::log('GENERATE_PDF', null, null, null, $this->title);
        echo $this->renderHTML();
        exit;
    }

    // ── Save HTML to file ──────────────────────────────
    public function saveToFile(string $path): bool {
        $html = $this->renderHTML();
        $result = file_put_contents($path, $html);
        AuditLogger::log('SAVE_PDF', null, null, null, basename($path));
        return $result !== false;
    }

    // ── Static: Generate Folio Invoice ─────────────────
    public static function folioInvoice(int $folio_id): self {
        $db     = db();
        $folio  = $db->fetchOne("
            SELECT f.*, r.check_in_date, r.check_out_date, r.adults,
                   g.name guest_name, g.email,
                   rm.room_number, rt.name room_type, rt.base_price
            FROM folios f
            JOIN reservations r ON f.reservation_id=r.reservation_id
            JOIN guests g ON r.guest_id=g.guest_id
            LEFT JOIN rooms rm ON r.room_id=rm.room_id
            LEFT JOIN room_types rt ON rm.type_id=rt.type_id
            WHERE f.folio_id=?", 'i', $folio_id
        );
        $charges = $db->fetchAll(
            "SELECT description, charge_type, amount, charged_at
             FROM folio_charges WHERE folio_id=? ORDER BY charged_at",
            'i', $folio_id
        );
        $payments = $db->fetchAll(
            "SELECT method, currency, amount, paid_at
             FROM payments WHERE folio_id=? AND status='Completed' ORDER BY paid_at",
            'i', $folio_id
        );

        $pdf = new self("INVOICE — Folio #{$folio_id}");

        // Guest info section
        $pdf->addSection("Guest Information", [
            "Guest: " . ($folio['guest_name'] ?? '—'),
            "Email: " . ($folio['email'] ?? '—'),
            "Room: " . ($folio['room_number'] ?? '—') . " (" . ($folio['room_type'] ?? '—') . ")",
            "Check-In: " . ($folio['check_in_date'] ?? '—'),
            "Check-Out: " . ($folio['check_out_date'] ?? '—'),
            "Adults: " . ($folio['adults'] ?? 1),
        ]);

        // Charges
        $chargeRows = [];
        foreach ($charges as $c) {
            $chargeRows[] = [
                'Description' => $c['description'],
                'Type'        => $c['charge_type'],
                'Date'        => date('d M Y', strtotime($c['charged_at'])),
                'Amount'      => 'EGP ' . number_format($c['amount'], 2),
            ];
        }
        $chargeRows[] = ['Description'=>'Subtotal','Type'=>'','Date'=>'','Amount'=>'EGP '.number_format($folio['total_amount']-$folio['tax_amount'],2),'_total'=>true];
        $chargeRows[] = ['Description'=>'VAT (14%)','Type'=>'Tax','Date'=>'','Amount'=>'EGP '.number_format($folio['tax_amount'],2),'_total'=>true];
        $chargeRows[] = ['Description'=>'TOTAL','Type'=>'','Date'=>'','Amount'=>'EGP '.number_format($folio['total_amount'],2),'_total'=>true];

        $pdf->addSection("Charges", $chargeRows, ['Description','Type','Date','Amount']);

        // Payments
        $payRows = [];
        foreach ($payments as $p) {
            $payRows[] = [
                'Method'   => $p['method'],
                'Currency' => $p['currency'],
                'Amount'   => 'EGP ' . number_format($p['amount'], 2),
                'Date'     => date('d M Y H:i', strtotime($p['paid_at'])),
            ];
        }
        if (!empty($payRows)) {
            $pdf->addSection("Payments Received", $payRows, ['Method','Currency','Amount','Date']);
        }

        return $pdf;
    }

    // ── Static: Generate Daily Revenue Report ──────────
    public static function revenueReport(string $from, string $to): self {
        $db  = db();
        $pdf = new self("Revenue Report: {$from} → {$to}");

        $daily = $db->fetchAll(
            "SELECT DATE(paid_at) d, COUNT(*) txn, SUM(amount) revenue
             FROM payments WHERE status='Completed' AND paid_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY)
             GROUP BY DATE(paid_at) ORDER BY d",
            'ss', $from, $to
        );
        $rows = [];
        foreach ($daily as $d) {
            $rows[] = ['Date'=>$d['d'], 'Transactions'=>$d['txn'], 'Revenue'=>'EGP '.number_format($d['revenue'],2)];
        }
        $total = array_sum(array_column($daily,'revenue'));
        $rows[] = ['Date'=>'TOTAL','Transactions'=>array_sum(array_column($daily,'txn')),'Revenue'=>'EGP '.number_format($total,2),'_total'=>true];

        $pdf->addSection("Daily Revenue Breakdown", $rows, ['Date','Transactions','Revenue']);
        return $pdf;
    }
}
