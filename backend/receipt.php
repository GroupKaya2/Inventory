<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'generate') {
    $saleId = (int) ($_GET['id'] ?? 0);
    if ($saleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
        exit;
    }

    // Get sale details
    $stmt = $conn->prepare("SELECT s.*, u.name as created_by_name 
                           FROM sales s 
                           LEFT JOIN users u ON s.created_by = u.id 
                           WHERE s.id = ?");
    $stmt->bind_param('i', $saleId);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sale) {
        echo json_encode(['success' => false, 'message' => 'Sale not found']);
        exit;
    }

    // Get sale items
    $si = $conn->prepare("SELECT si.*, p.code 
                         FROM sale_items si 
                         LEFT JOIN products p ON si.product_id = p.product_id 
                         WHERE si.sale_id = ? 
                         ORDER BY si.id");
    $si->bind_param('i', $saleId);
    $si->execute();
    $items = $si->get_result()->fetch_all(MYSQLI_ASSOC);
    $si->close();

    // Generate receipt HTML
    $receiptHtml = generateReceiptHtml($sale, $items);

    echo json_encode([
        'success' => true,
        'receipt_html' => $receiptHtml,
        'sale' => $sale,
        'items' => $items
    ]);
    exit;
}

function generateReceiptHtml($sale, $items) {
    $total = $sale['parts_total'] + $sale['labor_total'];
    $date = date('F j, Y', strtotime($sale['sale_date']));
    $time = date('g:i A', strtotime($sale['created_at'] ?? 'now'));
    
    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 300px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
        <div style="text-align: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 24px;">DSPEEDWAY</h2>
            <p style="margin: 5px 0; font-size: 12px;">Motorcycle Repair Shop</p>
            <p style="margin: 5px 0; font-size: 11px;">Official Receipt</p>
        </div>
        
        <div style="margin-bottom: 15px; font-size: 12px;">
            <p style="margin: 5px 0;"><strong>Date:</strong> ' . $date . '</p>
            <p style="margin: 5px 0;"><strong>Time:</strong> ' . $time . '</p>
            <p style="margin: 5px 0;"><strong>Receipt #:</strong> ' . str_pad($sale['id'], 6, '0', STR_PAD_LEFT) . '</p>';
    
    if ($sale['customer_name']) {
        $html .= '<p style="margin: 5px 0;"><strong>Customer:</strong> ' . htmlspecialchars($sale['customer_name']) . '</p>';
    }
    if ($sale['plate_number']) {
        $html .= '<p style="margin: 5px 0;"><strong>Plate #:</strong> ' . htmlspecialchars($sale['plate_number']) . '</p>';
    }
    
    $html .= '<p style="margin: 5px 0;"><strong>Payment:</strong> ' . strtoupper($sale['payment_method']) . '</p>
        </div>
        
        <div style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 10px 0; margin-bottom: 15px;">
            <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                <tr>
                    <th style="text-align: left; padding: 5px 0; border-bottom: 1px solid #ddd;">Item</th>
                    <th style="text-align: center; padding: 5px 0; border-bottom: 1px solid #ddd;">Qty</th>
                    <th style="text-align: right; padding: 5px 0; border-bottom: 1px solid #ddd;">Price</th>
                    <th style="text-align: right; padding: 5px 0; border-bottom: 1px solid #ddd;">Total</th>
                </tr>';
    
    foreach ($items as $item) {
        $itemTotal = $item['quantity'] * $item['unit_price'];
        $html .= '
                <tr>
                    <td style="padding: 5px 0; vertical-align: top;">' . htmlspecialchars($item['description']) . '</td>
                    <td style="text-align: center; padding: 5px 0;">' . $item['quantity'] . '</td>
                    <td style="text-align: right; padding: 5px 0;">₱' . number_format($item['unit_price'], 2) . '</td>
                    <td style="text-align: right; padding: 5px 0;">₱' . number_format($itemTotal, 2) . '</td>
                </tr>';
    }
    
    $html .= '
            </table>
        </div>
        
        <div style="font-size: 12px; margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span>Parts Total:</span>
                <span>₱' . number_format($sale['parts_total'], 2) . '</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span>Labor Total:</span>
                <span>₱' . number_format($sale['labor_total'], 2) . '</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 2px solid #000; font-weight: bold; font-size: 14px;">
                <span>GRAND TOTAL:</span>
                <span>₱' . number_format($total, 2) . '</span>
            </div>
        </div>
        
        <div style="text-align: center; font-size: 10px; margin-top: 20px; color: #666;">
            <p style="margin: 5px 0;">Thank you for your business!</p>
            <p style="margin: 5px 0;">For inquiries, please contact us.</p>
            <p style="margin: 10px 0;">*** This is a computer-generated receipt ***</p>
        </div>
    </div>';
    
    return $html;
}

$conn->close();