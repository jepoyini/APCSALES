<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';

class WarehouseController extends ResourceController
{
    /** GET /warehouses/list */
    public function list()
    {
        global $conn;
        $sql = "SELECT id, name FROM warehouses WHERE status='Active'";
        $result = $conn->query($sql);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $this->respond(['warehouses' => $rows]);
    }



public function getWarehousesStat()
{
    $db = \Config\Database::connect();

    try {

        // --- Read uid ---
        $p   = json_decode(file_get_contents("php://input"), true) ?? [];
        $uid = isset($p['uid']) ? (int)$p['uid'] : 0;

        $userWarehouseId = null;

        if ($uid > 0) {
            $userRow = $db->table('users')
                ->select('warehouse_id')
                ->where('id', $uid)
                ->get()
                ->getRowArray();

            if (!empty($userRow['warehouse_id'])) {
                $userWarehouseId = (int)$userRow['warehouse_id'];
            }
        }

        // ============================================
        // CASE 1: USER IS ASSIGNED TO A WAREHOUSE
        // Only return that warehouse + compute sums
        // ============================================
        if ($userWarehouseId !== null) {

            // current_stock from products table (sum of qty)
            $stockRow = $db->table('products')
                ->select('SUM(qty) AS stock')
                ->where('warehouse_id', $userWarehouseId)
                ->get()
                ->getRowArray();

            $stock = (int)($stockRow['stock'] ?? 0);

            // available_qty from items joined to products
            $availRow = $db->table('items i')
                ->select('COUNT(*) AS c')
                ->join('products p', 'p.id = i.product_id')
                ->where('p.warehouse_id', $userWarehouseId)
                ->whereIn('i.status', ['AVAILABLE', 'IN_STOCK'])
                ->get()
                ->getRowArray();

            $availableQty = (int)($availRow['c'] ?? 0);

            // sold_qty from items joined to products
            $soldRow = $db->table('items i')
                ->select('COUNT(*) AS c')
                ->join('products p', 'p.id = i.product_id')
                ->where('p.warehouse_id', $userWarehouseId)
                ->where('i.status', 'SOLD')
                ->get()
                ->getRowArray();

            $soldQty = (int)($soldRow['c'] ?? 0);

            // fetch warehouse info
            $whRow = $db->table('warehouses')
                ->select('id, name, location')
                ->where('id', $userWarehouseId)
                ->get()
                ->getRowArray();

            return $this->response->setJSON([
                'status' => 'success',
                'data' => [[
                    'id'             => $whRow['id'],
                    'name'           => $whRow['name'],
                    'location'       => $whRow['location'] ?? '',
                    'current_stock'  => $stock,
                    'available_qty'  => $availableQty,
                    'sold_qty'       => $soldQty,
                ]],
                'warehouse_filter_applied' => true,
                'warehouse_id'             => $userWarehouseId,
            ]);
        }

        // ============================================
        // CASE 2: ADMIN (NO warehouse_id)
        // Return ALL warehouses with dynamic stock
        // ============================================

        // Get all warehouses first
        $whRows = $db->table('warehouses')
            ->select('id, name, location')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $result = [];

        foreach ($whRows as $wh) {

            $wid = (int)$wh['id'];

            // current_stock from products table (sum qty)
            $stockRow = $db->table('products')
                ->select('SUM(qty) AS stock')
                ->where('warehouse_id', $wid)
                ->get()
                ->getRowArray();

            $stock = (int)($stockRow['stock'] ?? 0);

            // available_qty from items joined to products
            $availRow = $db->table('items i')
                ->select('COUNT(*) AS c')
                ->join('products p', 'p.id = i.product_id')
                ->where('p.warehouse_id', $wid)
                ->whereIn('i.status', ['AVAILABLE', 'IN_STOCK'])
                ->get()
                ->getRowArray();

            $availableQty = (int)($availRow['c'] ?? 0);

            // sold_qty from items joined to products
            $soldRow = $db->table('items i')
                ->select('COUNT(*) AS c')
                ->join('products p', 'p.id = i.product_id')
                ->where('p.warehouse_id', $wid)
                ->where('i.status', 'SOLD')
                ->get()
                ->getRowArray();

            $soldQty = (int)($soldRow['c'] ?? 0);

            $result[] = [
                'id'             => $wid,
                'name'           => $wh['name'],
                'location'       => $wh['location'],
                'current_stock'  => $stock,
                'available_qty'  => $availableQty,
                'sold_qty'       => $soldQty,
            ];
        }

        return $this->response->setJSON([
            'status'                  => 'success',
            'data'                    => $result,
            'warehouse_filter_applied'=> false,
        ]);

    } catch (\Exception $e) {
        return $this->response->setJSON([
            'status'  => 'error',
            'message' => 'Failed to retrieve warehouses.',
            'error'   => $e->getMessage(),
        ]);
    }
}




    public function getWarehousesStat1()
    {
        $db = \Config\Database::connect();

        try {
            // Query warehouses with name + current_stock
            $builder = $db->table('warehouses');
            $builder->select('name, current_stock');
            $query = $builder->get();

            $warehouses = [];

            foreach ($query->getResultArray() as $row) {
                $warehouses[] = [
                    'name'          => $row['name'],
                    'current_stock' => (int) $row['current_stock']
                ];
            }

            return $this->response->setJSON([
                'status' => 'success',
                'data'   => $warehouses
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Failed to retrieve warehouses.',
                'error'   => $e->getMessage()
            ]);
        }
    }


    /** POST /warehouses (list + filter + pagination) */
    public function index()
    {
        global $conn;

        $postData = json_decode(file_get_contents("php://input"), true) ?? [];

        $page   = max(1, (int)($postData['page']  ?? 1));
        $limit  = max(1, min(100, (int)($postData['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $search = trim((string)($postData['search'] ?? ''));
        $status = trim((string)($postData['status'] ?? ''));

        $where  = [];
        $params = [];
        $types  = '';

        if ($search !== '') {
            $where[] = "(name LIKE ? OR location LIKE ? OR manager LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= 'sss';
        }
        if ($status !== '') {
            $where[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }
        $sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

        // total count
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM warehouses {$sqlWhere}");
        if ($types) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        // page list
        $listSql    = "SELECT id, name, location, manager, status, capacity, current_stock, created_at, logo 
                       FROM warehouses {$sqlWhere}
                       ORDER BY created_at DESC
                       LIMIT ? OFFSET ?";
        $listParams = $params;
        $listTypes  = $types . 'ii';
        $listParams[] = $limit;
        $listParams[] = $offset;

        $stmt = $conn->prepare($listSql);
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // summary
        $sumSql = "SELECT
                      COUNT(*) AS totalWarehouses,
                      COALESCE(SUM(capacity),0) AS totalCapacity,
                      COALESCE(SUM(current_stock),0) AS totalStock,
                      SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) AS activeWarehouses,
                      SUM(CASE WHEN capacity>0 AND (current_stock / capacity) > 0.8 THEN 1 ELSE 0 END) AS highUtilization
                   FROM warehouses {$sqlWhere}";
        $stmt = $conn->prepare($sumSql);
        if ($types) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        $avgUtil = (!empty($summary['totalCapacity']) && (int)$summary['totalCapacity'] > 0)
            ? (int)round(((int)$summary['totalStock'] / (int)$summary['totalCapacity']) * 100)
            : 0;

        return $this->respond([
            'warehouses'   => $rows,
            'totalRecords' => $total,
            'summary'      => [
                'totalWarehouses'    => (int)($summary['totalWarehouses'] ?? 0),
                'totalCapacity'      => (int)($summary['totalCapacity'] ?? 0),
                'totalStock'         => (int)($summary['totalStock'] ?? 0),
                'averageUtilization' => $avgUtil,
                'activeWarehouses'   => (int)($summary['activeWarehouses'] ?? 0),
                'highUtilization'    => (int)($summary['highUtilization'] ?? 0),
            ]
        ]);
    }

    /** POST /warehouses/create */
    public function create()
    {
        global $conn;

        // Detect form-data vs JSON
        if (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data')) {
            $p = $_POST;
        } else {
            $p = json_decode(file_get_contents("php://input"), true) ?? [];
        }

        if (empty($p['name']) || empty($p['location']) || empty($p['manager'])) {
            return $this->failValidationErrors('name, location, manager are required');
        }

        $status   = $p['status'] ?? 'Active';
        $capacity = (int)($p['capacity'] ?? 0);
        $current  = (int)($p['current_stock'] ?? 0);

        // Insert without logo first
        $sql = "INSERT INTO warehouses (name, location, manager, status, capacity, current_stock)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssii",
            $p['name'],
            $p['location'],
            $p['manager'],
            $status,
            $capacity,
            $current
        );
        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        if (!$ok || !$newId) {
            return $this->fail('Insert failed');
        }

        $relativePath = null;

        // Handle file upload (logo)
        if (!empty($_FILES['logo']['name'])) {
            $baseDir   = "/home/apc/public_html/apidb/uploads/warehouses/";
            $uploadDir = $baseDir . intval($newId) . "/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true); // make sure webserver can write here
            }

            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = "logo_" . time() . "." . $ext;
            $target   = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                $relativePath = "uploads/warehouses/" . intval($newId) . "/" . $filename;

                // Update logo field in DB
                $sql = "UPDATE warehouses SET logo=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $relativePath, $newId);
                $stmt->execute();
                $stmt->close();
            } else {
                return $this->fail("Logo upload failed");
            }
        }

        return $this->respondCreated([
            'warehouse_id' => $newId,
            'logo'         => $relativePath
        ]);
    }


    /** POST /warehouses/{id}/update */
    public function update($id = null)
    {
        global $conn;

        if (!$id) return $this->failValidationErrors('Missing id');

        $fields = [];
        $vals   = [];
        $types  = "";

        // Detect form-data vs JSON
        if (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data')) {
            $p = $_POST;
        } else {
            $p = json_decode(file_get_contents("php://input"), true) ?? [];
        }

        // Normal fields
        foreach (['name','location','manager','status','capacity','current_stock'] as $k) {
            if (array_key_exists($k, $p)) {
                $fields[] = "$k=?";
                if ($k === 'capacity' || $k === 'current_stock') {
                    $vals[]  = (int)$p[$k];
                    $types  .= "i";
                } else {
                    $vals[]  = $p[$k];
                    $types  .= "s";
                }
            }
        }
        // File upload (logo)
        if (!empty($_FILES['logo']['name'])) {
            $baseDir   = "/home/apc/public_html/apidb/uploads/warehouses/";
            //return $this->failValidationErrors($baseDir);
            $uploadDir = $baseDir . intval($id) . "/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = "logo_" . time() . "." . $ext;
            $target   = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                // save relative path for web access
                $relativePath = "uploads/warehouses/" . intval($id) . "/" . $filename;
                $fields[] = "logo=?";
                $vals[]   = $relativePath;
                $types   .= "s";
            } else {
                return $this->fail("Logo upload failed");
            }
        }

        if (!$fields) return $this->respond(['message'=>'Nothing to update']);

        $vals[] = (int)$id;
        $types .= "i";

        $sql = "UPDATE warehouses SET ".implode(',', $fields)." WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) return $this->fail('Update failed');

        return $this->respond([
            'updated' => true,
            'logo'    => $relativePath ?? null
        ]);
    }


    /** POST /warehouses/{id}/delete */
    public function delete($id = null)
    {
        global $conn;
        if (!$id) return $this->failValidationErrors('Missing id');

        $stmt = $conn->prepare("SELECT id FROM warehouses WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) return $this->failNotFound('Not found');

        $stmt = $conn->prepare("DELETE FROM warehouses WHERE id=?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) return $this->fail('Delete failed');

        return $this->respondDeleted(['deleted'=>true]);
    }

    /** POST /warehouses/{id}/details */
    public function details($id = null)
    {
        global $conn;
        if (!$id) return $this->failValidationErrors('Missing id');

        $stmt = $conn->prepare("SELECT * FROM warehouses WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $w = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$w) return $this->failNotFound('Warehouse not found');

        $stmt = $conn->prepare("
            SELECT product_id, COUNT(*) AS qty
            FROM items
            WHERE current_warehouse_id=? AND status<>'DISPOSED'
            GROUP BY product_id
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $products = [];
        if ($items) {
            $pids = array_column($items, 'product_id');
            $in   = implode(',', array_fill(0, count($pids), '?'));
            $types= str_repeat("i", count($pids));
            $stmt = $conn->prepare("SELECT id, sku, name, category FROM products WHERE id IN ($in)");
            $stmt->bind_param($types, ...$pids);
            $stmt->execute();
            $prows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $map = [];
            foreach ($prows as $pr) $map[$pr['id']] = $pr;

            foreach ($items as $it) {
                if (isset($map[$it['product_id']])) {
                    $pr = $map[$it['product_id']];
                    $products[] = [
                        'id'       => (int)$pr['id'],
                        'name'     => $pr['name'],
                        'sku'      => $pr['sku'],
                        'category' => $pr['category'],
                        'quantity' => (int)$it['qty'],
                        'status'   => 'IN_STOCK',
                    ];
                }
            }
        }

        $util = ((int)$w['capacity'] > 0)
            ? (int)round(((int)$w['current_stock'] / (int)$w['capacity']) * 100)
            : 0;

        return $this->respond([
            'warehouse'   => $w,
            'utilization' => $util,
            'products'    => $products
        ]);
    }

    /** POST /warehouses/{id}/add-product */
    public function addProduct($id = null)
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];
        if (!$id || empty($p['product_id']) || empty($p['quantity'])) {
            return $this->failValidationErrors('product_id and quantity required');
        }

        $qty       = max(1, (int)$p['quantity']);
        $productId = (int)$p['product_id'];

        // confirm warehouse exists
        $stmt = $conn->prepare("SELECT id FROM warehouses WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $w = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$w) return $this->failNotFound('Warehouse not found');

        $conn->begin_transaction();
        try {
            $ins = $conn->prepare(
                "INSERT INTO items (product_id, serial_or_batch, qr_code, current_warehouse_id, status)
                 VALUES (?, NULL, NULL, ?, 'IN_STOCK')"
            );
            for ($i = 0; $i < $qty; $i++) {
                $ins->bind_param("ii", $productId, $id);
                $ins->execute();
            }
            $ins->close();

            // refresh stock
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM items WHERE current_warehouse_id=? AND status<>'DISPOSED'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();

            $stmt = $conn->prepare("UPDATE warehouses SET current_stock=? WHERE id=?");
            $stmt->bind_param("ii", $c, $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            return $this->respond(['added' => $qty, 'current_stock' => $c]);
        } catch (\Throwable $e) {
            $conn->rollback();
            return $this->fail('Add failed: ' . $e->getMessage());
        }
    }

    /** POST /warehouses/{id}/move-product */
    public function moveProduct($id = null)
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];
        if (!$id || empty($p['product_id']) || empty($p['to_warehouse_id']) || empty($p['quantity'])) {
            return $this->failValidationErrors('product_id, to_warehouse_id, quantity required');
        }

        $qty  = max(1, (int)$p['quantity']);
        $pid  = (int)$p['product_id'];
        $toId = (int)$p['to_warehouse_id'];

        $conn->begin_transaction();
        try {
            // select N items to move
            $stmt = $conn->prepare("
                SELECT id FROM items
                WHERE current_warehouse_id=? AND product_id=? AND status='IN_STOCK'
                LIMIT ?
            ");
            $stmt->bind_param("iii", $id, $pid, $qty);
            $stmt->execute();
            $ids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!$ids) {
                $conn->rollback();
                return $this->failValidationErrors('No available items to move');
            }

            $idList = array_column($ids, 'id');
            $place  = implode(',', array_fill(0, count($idList), '?'));
            $types  = 'i' . str_repeat('i', count($idList));
            $params = array_merge([$toId], $idList);

            $sql = "UPDATE items SET current_warehouse_id=? WHERE id IN ($place)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();

            // refresh stocks for both warehouses
            foreach ([(int)$id, $toId] as $wid) {
                $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM items WHERE current_warehouse_id=? AND status<>'DISPOSED'");
                $stmt->bind_param("i", $wid);
                $stmt->execute();
                $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
                $stmt->close();

                $stmt = $conn->prepare("UPDATE warehouses SET current_stock=? WHERE id=?");
                $stmt->bind_param("ii", $c, $wid);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            return $this->respond(['moved'=>count($idList)]);
        } catch (\Throwable $e) {
            $conn->rollback();
            return $this->fail('Move failed: '.$e->getMessage());
        }
    }

    /** POST /warehouses/{id}/remove-product */
    public function removeProduct($id = null)
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];
        if (!$id || empty($p['product_id']) || empty($p['quantity'])) {
            return $this->failValidationErrors('product_id and quantity required');
        }

        $qty = max(1, (int)$p['quantity']);
        $pid = (int)$p['product_id'];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                SELECT id FROM items
                WHERE current_warehouse_id=? AND product_id=? AND status='IN_STOCK'
                LIMIT ?
            ");
            $stmt->bind_param("iii", $id, $pid, $qty);
            $stmt->execute();
            $ids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!$ids) {
                $conn->rollback();
                return $this->failValidationErrors('No available items to remove');
            }

            $idList = array_column($ids, 'id');
            $place  = implode(',', array_fill(0, count($idList), '?'));
            $types  = str_repeat('i', count($idList));

            $sql = "UPDATE items SET status='DISPOSED' WHERE id IN ($place)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$idList);
            $stmt->execute();
            $stmt->close();

            // refresh stock
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM items WHERE current_warehouse_id=? AND status<>'DISPOSED'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();

            $stmt = $conn->prepare("UPDATE warehouses SET current_stock=? WHERE id=?");
            $stmt->bind_param("ii", $c, $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            return $this->respond(['removed'=>count($idList), 'current_stock'=>$c]);
        } catch (\Throwable $e) {
            $conn->rollback();
            return $this->fail('Remove failed: '.$e->getMessage());
        }
    }
}
