    <?php
    session_start();
    header('Content-Type: application/json');
    require_once __DIR__ . '/db.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
    $userId = (int) $_SESSION['user_id'];

    // SAVE sale
    if ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
            exit;
        }

        $saleDate = trim($input['sale_date'] ?? date('Y-m-d'));
        $custName = trim($input['customer_name'] ?? '');
        $plateNum = trim($input['plate_number'] ?? '');
        $items = $input['items'] ?? [];

        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'No items provided.']);
            exit;
        }

        $partsTotal = 0;
        $laborTotal = 0;
        foreach ($items as $item) {
            $amt = (float) ($item['amount'] ?? 0);
            $type = ($item['type'] ?? 'parts') === 'labor' ? 'labor' : 'parts';
            if ($type === 'labor')
                $laborTotal += $amt;
            else
                $partsTotal += $amt;
        }

        $conn->begin_transaction();
        try {
            // Insert sale header
            $stmt = $conn->prepare(
                "INSERT INTO sales (sale_date, customer_name, plate_number, parts_total, labor_total, created_by)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssddi', $saleDate, $custName, $plateNum, $partsTotal, $laborTotal, $userId);
            $stmt->execute();
            $saleId = $conn->insert_id;
            $stmt->close();

            $stockSummary = [];

            foreach ($items as $item) {
                // Sanitise line_type to only valid ENUM values
                $type = ($item['type'] ?? 'parts') === 'labor' ? 'labor' : 'parts';
                $productId = (int) ($item['product_id'] ?? 0);
                $desc = trim($item['description'] ?? '');
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $amount = (float) ($item['amount'] ?? 0);

                // Insert sale line item
                $si = $conn->prepare(
                    "INSERT INTO sale_items (sale_id, line_type, product_id, description, quantity, unit_price, amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $pidParam = $productId ?: null;
                $si->bind_param('isiisdd', $saleId, $type, $pidParam, $desc, $qty, $unitPrice, $amount);
                $si->execute();
                $si->close();

                // Deduct inventory for parts lines with a linked product
                if ($type === 'parts' && $productId > 0) {
                    $it = $conn->prepare(
                        "INSERT INTO inventory_transactions
                        (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
                        VALUES (?, ?, ?, 'sale', ?, ?)"
                    );
                    $negQty = -$qty;
                    $remarks = "Sale #$saleId" . ($custName ? " - $custName" : '');
                    $it->bind_param('iissi', $productId, $saleDate, $negQty, $remarks, $userId);
                    $it->execute();
                    $it->close();

                    // Fetch updated stock for summary
                    $st = $conn->prepare(
                        "SELECT current_stock, description, reorder_threshold FROM product_stock WHERE product_id = ?"
                    );
                    $st->bind_param('i', $productId);
                    $st->execute();
                    $stockRow = $st->get_result()->fetch_assoc();
                    $st->close();

                    if ($stockRow) {
                        $left = (int) $stockRow['current_stock'];
                        $stockSummary[] = [
                            'description' => $stockRow['description'],
                            'qty_removed' => $qty,
                            'stock_left' => $left,
                            'low_stock' => $left <= (int) $stockRow['reorder_threshold'],
                        ];
                    }
                }
            }

            $conn->commit();
            echo json_encode(['success' => true, 'sale_id' => $saleId, 'stock_summary' => $stockSummary]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // GET sale detaik
    if ($action === 'detail') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sale = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$sale) {
            echo json_encode(['success' => false, 'message' => 'Sale not found.']);
            exit;
        }

        $si = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id");
        $si->bind_param('i', $id);
        $si->execute();
        $items = [];
        $result = $si->get_result();
        while ($row = $result->fetch_assoc())
            $items[] = $row;
        $si->close();

        echo json_encode(['success' => true, 'sale' => $sale, 'items' => $items]);
        exit;
    }

    // DELETE sale (owner only)
    if ($action === 'delete') {
        if (!$isOwner) {
            echo json_encode(['success' => false, 'message' => 'Owner only.']);
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $saleDate = date('Y-m-d');
            $reverseRemark = "Sale #$id deleted";

            $items = $conn->query(
                "SELECT product_id, quantity FROM sale_items
                WHERE sale_id = $id AND line_type = 'parts' AND product_id IS NOT NULL"
            );
            while ($row = $items->fetch_assoc()) {
                $pid = (int) $row['product_id'];
                $qty = (int) $row['quantity'];
                $conn->query(
                    "INSERT INTO inventory_transactions
                    (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
                    VALUES ($pid, '$saleDate', $qty, 'adjustment', '$reverseRemark', $userId)"
                );
            }

            $conn->query("DELETE FROM sale_items WHERE sale_id = $id");
            $conn->query("DELETE FROM sales WHERE id = $id");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Sale deleted.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);