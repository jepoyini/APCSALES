<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';

class ProductController extends ResourceController
{


    public function WarehouseItems()
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        $page   = max(1, (int)($p['page'] ?? 1));
        $limit  = (int)($p['limit'] ?? 12); // allow 0 = ALL
        $offset = ($page - 1) * ($limit > 0 ? $limit : 1);

        $search  = trim((string)($p['search'] ?? ''));
        $status  = trim((string)($p['status'] ?? ''));
        $whName  = trim((string)($p['warehouse'] ?? ''));
        $uid     = trim((string)($p['uid'] ?? ''));   // ⭐ NEW

        $where  = [];
        $params = [];
        $types  = '';

        // ⭐ If uid is provided, get user's warehouse_id and force filter
        if ($uid !== '') {
            $stmt = $conn->prepare("SELECT warehouse_id FROM users WHERE id = ? LIMIT 1");
            $uidInt = (int)$uid;
            $stmt->bind_param("i", $uidInt);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($row['warehouse_id'])) {
                $userWarehouseId = (int)$row['warehouse_id'];
                // Force this user to see only their warehouse
                $where[]  = "w.id = ?";
                $params[] = $userWarehouseId;
                $types   .= 'i';
            }
        }

        // ✅ Search filter (warehouse name, item serial, product name/category)
        if ($search !== '') {
            $like = "%{$search}%";
            $where[] = "(
                w.name LIKE ? 
                OR i.serial LIKE ? 
                OR p.name LIKE ? 
                OR p.category LIKE ?
            )";
            array_push($params, $like, $like, $like, $like);
            $types .= 'ssss';
        }

        // ✅ Status filter
        if ($status !== '') {
            $where[] = "i.status = ?";
            $params[] = $status;
            $types   .= 's';
        }

        // ✅ Warehouse name filter
        if ($whName !== '') {
            $where[] = "w.name LIKE ?";
            $params[] = "%{$whName}%";
            $types   .= 's';
        }

        $sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : '';

        // ✅ Count distinct warehouses
        $countSql = "
            SELECT COUNT(DISTINCT w.id) AS c
            FROM warehouses w
            LEFT JOIN items i ON i.current_warehouse_id = w.id
            LEFT JOIN products p ON p.id = i.product_id
            $sqlWhere
        ";
        $stmt = $conn->prepare($countSql);
        if ($types) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        // ✅ Warehouse list with pagination
        $listSql = "
            SELECT DISTINCT w.id, w.name, w.location, w.created_at
            FROM warehouses w
            LEFT JOIN items i ON i.current_warehouse_id = w.id
            LEFT JOIN products p ON p.id = i.product_id
            $sqlWhere
            ORDER BY w.name ASC
        ";

        $stmt = null;
        if ($limit > 0) {
            $listSql .= " LIMIT ? OFFSET ?";
            $listParams = $params;
            $listTypes  = $types.'ii';
            $listParams[] = $limit;
            $listParams[] = $offset;

            $stmt = $conn->prepare($listSql);
            $stmt->bind_param($listTypes, ...$listParams);
        } else {
            $stmt = $conn->prepare($listSql);
            if ($types) { $stmt->bind_param($types, ...$params); }
        }

        $stmt->execute();
        $warehouses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ✅ Attach items to each warehouse
        if ($warehouses) {
            $ids = array_column($warehouses, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $tps = str_repeat('i', count($ids));

            $stmt = $conn->prepare("
                SELECT 
                    i.id, i.serial, i.status, i.updated_at, 
                    i.product_id, p.name AS product_name, p.category AS product_category, 
                    i.current_warehouse_id
                FROM items i
                LEFT JOIN products p ON p.id = i.product_id
                WHERE i.current_warehouse_id IN ($ph)
                ORDER BY i.id ASC
            ");
            $stmt->bind_param($tps, ...$ids);
            $stmt->execute();
            $itemRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // group items by warehouse_id
            $itemMap = [];
            foreach ($itemRows as $ir) {
                $itemMap[$ir['current_warehouse_id']][] = $ir;
            }

            foreach ($warehouses as &$w) {
                $w['items'] = $itemMap[$w['id']] ?? [];
            }
        }

        return $this->respond([
            'warehouses'   => $warehouses,
            'totalRecords' => $total
        ]);
    }

public function productsummary()
{
    global $conn;
    $p = json_decode(file_get_contents("php://input"), true) ?? [];

    $page   = max(1, (int)($p['page']  ?? 1));
    $limit  = (int)($p['limit'] ?? 12); // allow 0 = ALL
    $offset = ($page - 1) * ($limit > 0 ? $limit : 1);

    $search    = trim((string)($p['search'] ?? ''));
    $category  = trim((string)($p['category'] ?? ''));
    $warehouse = trim((string)($p['warehouse'] ?? ''));
    $status    = trim((string)($p['status'] ?? ''));
    $stockLvl  = trim((string)($p['stockLevel'] ?? '')); 
    $tagsIn    = is_array($p['tags'] ?? null) ? $p['tags'] : [];
    $uid       = trim((string)($p['uid'] ?? ''));

    $where  = [];
    $params = [];
    $types  = '';

    // ✅ If uid is given, filter by user's warehouse_id
    if ($uid !== '') {
        $stmt = $conn->prepare("SELECT warehouse_id FROM users WHERE id = ? LIMIT 1");
        $uidInt = (int)$uid;
        $stmt->bind_param("i", $uidInt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['warehouse_id'])) {
            $userWarehouseId = (int)$row['warehouse_id'];
            $where[]  = "p.warehouse_id = ?";
            $params[] = $userWarehouseId;
            $types   .= 'i';
        }
    }

    if ($search !== '') {
        if (ctype_digit($search)) {
            $where[] = "(p.id = ? OR p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)";
            $id = (int)$search;
            $like = "%{$search}%";
            array_push($params, $id, $like, $like, $like);
            $types .= 'isss';
        } else {
            $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)";
            $like = "%{$search}%";
            array_push($params, $like, $like, $like);
            $types .= 'sss';
        }
    }

    if ($category !== '') {
        $where[] = "p.category = ?";
        $params[] = $category; 
        $types   .= 's';
    }

    if ($status !== '') {
        $where[] = "p.status = ?";
        $params[] = $status; 
        $types   .= 's';
    }

    // ✅ explicit warehouse filter
    if ($warehouse !== '') {
        $where[]  = "p.warehouse_id = ?";
        $params[] = (int)$warehouse; 
        $types   .= 'i';
    }

    if ($tagsIn) {
        $ph = implode(',', array_fill(0, count($tagsIn), '?'));
        $where[] = "p.id IN (SELECT pt.product_id FROM products_tags pt WHERE pt.tag_text IN ($ph))";
        foreach ($tagsIn as $t) {
            $params[] = (string)$t;
            $types   .= 's';
        }
    }

    if ($stockLvl === 'low') {
        $where[] = "(p.reorder_point>0 AND (SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')) <= p.reorder_point)";
    } elseif ($stockLvl === 'out') {
        $where[] = "NOT EXISTS (SELECT 1 FROM items i WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED'))";
    }

    $sqlWhere = $where ? ("WHERE ".implode(" AND ", $where)) : '';

    // total count
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM products p {$sqlWhere}");
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    // main product list (✅ back to items-based aggregates, no p.available_qty)
    $listSql = "
        SELECT
          p.id, p.sku, p.name, p.category, p.status,
          p.price, p.cost, p.warehouse_id, p.reorder_point,
          p.brand, p.model, p.description, p.warehouse_price, 
          COALESCE((
            SELECT url FROM product_images pi
            WHERE pi.product_id=p.id
            ORDER BY is_primary DESC, id ASC LIMIT 1
          ), '') AS primary_image,

          COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id),0) AS total_qty,
          COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='IN_STOCK'),0) AS available_qty,
          COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='IN_TRANSIT'),0) AS in_transit_qty,
          COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='SOLD'),0) AS sold_qty

        FROM products p
        {$sqlWhere}
        ORDER BY p.created_at DESC
    ";

    $stmt = null;
    if ($limit > 0) {
        $listSql .= " LIMIT ? OFFSET ?";
        $listParams = $params;
        $listTypes  = $types.'ii';
        $listParams[] = $limit;
        $listParams[] = $offset;

        $stmt = $conn->prepare($listSql);
        $stmt->bind_param($listTypes, ...$listParams);
    } else {
        $stmt = $conn->prepare($listSql);
        if ($types) { $stmt->bind_param($types, ...$params); }
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // attach tags and items
    if ($rows) {
        $ids = array_column($rows, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $tps = str_repeat('i', count($ids));

        // tags
        $stmt = $conn->prepare("SELECT product_id, tag_text FROM products_tags WHERE product_id IN ($ph)");
        $stmt->bind_param($tps, ...$ids);
        $stmt->execute();
        $all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $tagMap = [];
        foreach ($all as $r) $tagMap[$r['product_id']][] = $r['tag_text'];

        // items
        $stmt = $conn->prepare("
            SELECT i.id, i.product_id, i.serial, i.status, i.created_at, i.updated_at, w.name AS warehouse_name
            FROM items i
            LEFT JOIN warehouses w ON w.id = i.current_warehouse_id
            WHERE i.product_id IN ($ph)
            ORDER BY i.id ASC
        ");
        $stmt->bind_param($tps, ...$ids);
        $stmt->execute();
        $itemRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $itemMap = [];
        foreach ($itemRows as $ir) $itemMap[$ir['product_id']][] = $ir;

        foreach ($rows as &$r) {
            $r['tags']  = $tagMap[$r['id']]  ?? [];
            $r['items'] = $itemMap[$r['id']] ?? [];
        }
    }

    // ✅ summary: totalProducts from products.qty, others from items
    $sumSql = "
        SELECT
          /* total quantity from products.qty */
          COALESCE(SUM(p.qty), 0) AS totalProducts,

          /* inventory value: price * available (IN_STOCK + RESERVED) */
          COALESCE(SUM(
            p.price * (
              SELECT COUNT(*) 
              FROM items i 
              WHERE i.product_id = p.id AND i.status IN ('IN_STOCK','RESERVED')
            )
          ), 0) AS totalValue,

          SUM(p.status='active') AS activeProducts,

          SUM(
            p.reorder_point > 0 AND (
              (SELECT COUNT(*) FROM items i
                WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')
              ) <= p.reorder_point
            )
          ) AS lowStock,

          SUM(
            NOT EXISTS (
              SELECT 1 FROM items i
              WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')
            )
          ) AS outOfStock,

          /* summary breakdown per status from items */
          COALESCE(SUM(
            (SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='IN_STOCK')
          ),0) AS available_qty,

          COALESCE(SUM(
            (SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='IN_TRANSIT')
          ),0) AS in_transit_qty,

          COALESCE(SUM(
            (SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='SOLD')
          ),0) AS sold_qty

        FROM products p
        $sqlWhere
    ";

    $stmt = $conn->prepare($sumSql);
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();

    return $this->respond([
        'products'     => $rows,
        'totalRecords' => $total,
        'summary'      => [
            'totalProducts'   => (int)($summary['totalProducts'] ?? 0),
            'totalValue'      => (float)($summary['totalValue'] ?? 0),
            'activeProducts'  => (int)($summary['activeProducts'] ?? 0),
            'lowStock'        => (int)($summary['lowStock'] ?? 0),
            'outOfStock'      => (int)($summary['outOfStock'] ?? 0),

            'available_qty'   => (int)($summary['available_qty'] ?? 0),
            'in_transit_qty'  => (int)($summary['in_transit_qty'] ?? 0),
            'sold_qty'        => (int)($summary['sold_qty'] ?? 0),
        ],
    ]);
}



    // ==============================================
    // Get all categories
    // Route: POST /categories
    // ==============================================
    public function categories()
    {
        global $conn; 

        $result = $conn->query("SELECT id, name FROM categories ORDER BY `order` ASC");

        if (!$result) {
            return $this->response->setJSON([
                "status" => "error",
                "message" => $conn->error
            ]);
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);

        return $this->response->setJSON($rows);
    }

    // ==============================================
    // Create new category if not exists
    // Route: POST /categories/create
    // ==============================================
    public function categories_create()
    {
        global $conn; 

        $request = $this->request->getJSON(true);
        $name = trim($request['name'] ?? "");

        if ($name === "") {
            return $this->response->setJSON([
                "status" => "error",
                "message" => "Category name is required"
            ]);
        }

        // Check if category exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            return $this->response->setJSON([
                "status" => "exists",
                "message" => "Category already exists",
                "id" => $result['id']
            ]);
        }

        // Insert new category
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $insertId = $stmt->insert_id;

        return $this->response->setJSON([
            "status" => "success",
            "message" => "Category created",
            "id" => $insertId,
            "name" => $name
        ]);
    }


    // ==============================================
    // Get all sizes
    // Route: POST /sizes
    // ==============================================
    public function sizes()
    {
        global $conn; 

        $result = $conn->query("SELECT id, name FROM sizes ORDER BY `order` ASC");

        if (!$result) {
            return $this->response->setJSON([
                "status" => "error",
                "message" => $conn->error
            ]);
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);

        return $this->response->setJSON($rows);
    }

    // ==============================================
    // Create new category if not exists
    // Route: POST /sizes/create
    // ==============================================
    public function sizes_create()
    {
        global $conn; 

        $request = $this->request->getJSON(true);
        $name = trim($request['name'] ?? "");

        if ($name === "") {
            return $this->response->setJSON([
                "status" => "error",
                "message" => "Sizes is required"
            ]);
        }

        // Check if category exists
        $stmt = $conn->prepare("SELECT id FROM sizes WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            return $this->response->setJSON([
                "status" => "exists",
                "message" => "Size already exists",
                "id" => $result['id']
            ]);
        }

        // Insert new category
        $stmt = $conn->prepare("INSERT INTO sizes (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $insertId = $stmt->insert_id;

        return $this->response->setJSON([
            "status" => "success",
            "message" => "Size created",
            "id" => $insertId,
            "name" => $name
        ]);
    }



public function recent()
{
    global $conn;
    $p = json_decode(file_get_contents("php://input"), true) ?? [];

    $limit  = max(1, min(50, (int)($p['limit'] ?? 10))); 
    $offset = max(0, (int)($p['offset'] ?? 0));
    $days   = isset($p['days']) ? (int)$p['days'] : 30;

    // 🔥 NEW: get uid
    $uid = isset($p['uid']) ? (int)$p['uid'] : 0;
    $userWarehouseId = null;

    if ($uid > 0) {
        // Get user's warehouse
        $stmt = $conn->prepare("SELECT warehouse_id FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['warehouse_id'])) {
            $userWarehouseId = (int)$row['warehouse_id'];
        }
    }

    // 🔥 BASE QUERY
    $sql = "
        SELECT 
            pt.id, pt.product_id, pt.user_id, pt.status, pt.created_at,
            p.name AS product_name,
            (
                SELECT pi.url 
                FROM product_images pi 
                WHERE pi.product_id = pt.product_id AND pi.is_primary = 1  
                ORDER BY pi.id ASC 
                LIMIT 1
            ) AS primary_image,
            CONCAT(u.firstname, ' ', u.lastname) AS user_name,
            w.name AS warehouse_name
        FROM product_tracking pt
        LEFT JOIN products p ON pt.product_id = p.id
        LEFT JOIN users u ON pt.user_id = u.id
        LEFT JOIN warehouses w ON pt.warehouse_id = w.id
        WHERE pt.created_at >= (NOW() - INTERVAL ? DAY)
    ";

    $params = [$days];
    $types  = "i";

    // 🔥 If user belongs to a warehouse → enforce the filter
    if ($userWarehouseId !== null) {
        $sql .= " AND p.warehouse_id = ? ";
        $params[] = $userWarehouseId;
        $types   .= "i";
    }

    // ORDER + PAGINATION
    $sql .= " ORDER BY pt.created_at DESC LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $types   .= "ii";

    // Execute
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $this->respond([
        'activities' => $rows,
        'limit'      => $limit,
        'offset'     => $offset,
        'days'       => $days,
        'warehouse_filter_applied' => ($userWarehouseId !== null),
        'warehouse_id' => $userWarehouseId
    ]);
}


public function recent1()
{
    global $conn;
    $p = json_decode(file_get_contents("php://input"), true) ?? [];

    $limit  = max(1, min(50, (int)($p['limit'] ?? 10))); // default 10
    $offset = max(0, (int)($p['offset'] ?? 0));

    // 🔹 default: last 30 days
    $days = isset($p['days']) ? (int)$p['days'] : 30;

    // 🔥 NEW: get uid
    $uid = isset($p['uid']) ? (int)$p['uid'] : 0;
    $userWarehouseId = null;

    if ($uid > 0) {
        // Get user's warehouse
        $stmt = $conn->prepare("SELECT warehouse_id FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['warehouse_id'])) {
            $userWarehouseId = (int)$row['warehouse_id'];
        }
    }


    $sql = "
        SELECT 
            pt.id, pt.product_id, pt.user_id, pt.status, pt.created_at,
            p.name AS product_name,
            (
                SELECT pi.url 
                FROM product_images pi 
                WHERE pi.product_id = pt.product_id and pi.is_primary = 1  
                ORDER BY pi.is_primary DESC, pi.id ASC 
                LIMIT 1
            ) AS primary_image,
            CONCAT(u.firstname, ' ', u.lastname) AS user_name,
            w.name AS warehouse_name
        FROM product_tracking pt
        LEFT JOIN products p ON pt.product_id = p.id
        LEFT JOIN users u ON pt.user_id = u.id
        LEFT JOIN warehouses w ON pt.warehouse_id = w.id
        WHERE pt.created_at >= (NOW() - INTERVAL ? DAY)
        ORDER BY pt.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $days, $limit, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $this->respond([
        'activities' => $rows,
        'limit'      => $limit,
        'offset'     => $offset,
        'days'       => $days
    ]);
}



        public function items($productId = null)
        {
            global $conn;

            if (!$productId) {
                return $this->failValidationErrors("Missing productId");
            }

            try {
                $stmt = $conn->prepare("
                    SELECT i.id, i.product_id, i.serial, i.batch, i.qr_code,
                           i.current_warehouse_id, w.name as warehouse_name,
                           i.location_code, i.condition_label, i.status, i.notes,
                           i.acquired_at, i.created_at, i.updated_at
                    FROM items i
                    LEFT JOIN warehouses w ON w.id = i.current_warehouse_id
                    WHERE i.product_id = ?
                    ORDER BY i.id DESC
                ");
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $res = $stmt->get_result();

                $items = [];
                while ($row = $res->fetch_assoc()) {
                    $items[] = [
                        "id"                  => (int)$row["id"],
                        "product_id"          => (int)$row["product_id"],
                        "serial"     => $row["serial"],
                        "batch"     => $row["batch"],
                        "qr_code"             => $row["qr_code"],
                        "current_warehouse_id"=> $row["current_warehouse_id"] ? (int)$row["current_warehouse_id"] : null,
                        "warehouse_name"      => $row["warehouse_name"] ?? null,
                        "location_code"       => $row["location_code"],
                        "condition_label"     => $row["condition_label"],
                        "status"              => $row["status"],
                        "acquired_at"         => $row["acquired_at"],
                        "created_at"          => $row["created_at"],
                        "updated_at"          => $row["updated_at"],
                        "notes"              => $row["notes"],
                    ];
                }
                $stmt->close();

                return $this->respond([
                    "status" => "success",
                    "items" => $items
                ]);
            } catch (\Exception $e) {
                return $this->fail($e->getMessage());
            }
        }

    /** GET /warehouses/list */
    public function listWarehouses()
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

    /** POST /items/create */
    public function createItem()
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        // validation
        foreach (['product_id','serial','current_warehouse_id','condition_label','status','acquired_at'] as $k) {
            if (empty($p[$k])) return $this->failValidationErrors("$k is required");
        }

        // Insert item
        $sql = "INSERT INTO items 
            (product_id, serial, current_warehouse_id, location_code, condition_label, status, acquired_at, created_at) 
            VALUES (?,?,?,?,?,?,?,NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "isissss",
            $p['product_id'],
            $p['serial'],
            $p['current_warehouse_id'],
            $p['location_code'],
            $p['condition_label'],
            $p['status'],
            $p['acquired_at']
        );
        $ok = $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        if (!$ok) return $this->fail("Insert failed");

        // 🔹 Recalculate qty for this product
        $sqlQty = "SELECT COUNT(*) AS total FROM items WHERE product_id = ?";
        $stmtQty = $conn->prepare($sqlQty);
        $stmtQty->bind_param("i", $p['product_id']);
        $stmtQty->execute();
        $resQty = $stmtQty->get_result();
        $rowQty = $resQty->fetch_assoc();
        $totalQty = $rowQty['total'] ?? 0;
        $stmtQty->close();

        // 🔹 Update products.qty
        $sqlUpdate = "UPDATE products SET qty = ? WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("ii", $totalQty, $p['product_id']);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        return $this->respondCreated(['id'=>$id, 'new_qty'=>$totalQty]);
    }

    /** POST /items/{id}/update */
    // public function updateItem($id = null)
    // {
    //     global $conn;
    //     if (!$id) return $this->failValidationErrors("Missing id");

    //     $p = json_decode(file_get_contents("php://input"), true) ?? [];

    //     $sql = "UPDATE items SET 
    //         serial=?, 
    //         current_warehouse_id=?, 
    //         location_code=?, 
    //         condition_label=?, 
    //         status=?, 
    //         acquired_at=?, 
    //         updated_at=NOW()
    //         WHERE id=?";
    //     $stmt = $conn->prepare($sql);
    //     $stmt->bind_param(
    //         "sissssi",
    //         $p['serial'],
    //         $p['current_warehouse_id'],
    //         $p['location_code'],
    //         $p['condition_label'],
    //         $p['status'],
    //         $p['acquired_at'],
    //         $id
    //     );
    //     $ok = $stmt->execute();
    //     $stmt->close();

    //     if (!$ok) return $this->fail("Update failed");

    //     return $this->respond(['updated'=>true]);
    // }

    public function lookup()
    {
        $db = \Config\Database::connect();
        $req = json_decode(file_get_contents("php://input"), true);
        $code = trim($req['code'] ?? '');

        if (!$code) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Missing code'
            ]);
        }

        // Try to match code against items or products
        $builder = $db->table('items i')
            ->select('i.id as item_id, i.serial as code, 
                      p.id as product_id, p.name, p.sku, 
                      pi.url as primary_image')
            ->join('products p', 'p.id=i.product_id')
            ->join('product_images pi', 'pi.product_id=p.id AND pi.is_primary=1', 'left')
            ->where('i.serial', $code);

        $row = $builder->get()->getRowArray();

        if (!$row) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Not found'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $row
        ]);
    }

    public function sca22()
    {
        global $conn;

        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        $code        = trim($p['code'] ?? '');
        $action      = $p['action'] ?? null;
        $warehouseId = $p['warehouse_id'] ?? null;
        $userId      = $p['uid'] ?? null;

        if (!$code) {
            return $this->respond(['status' => 'error', 'message' => 'Missing code']);
        }

        try {
            $productId = null;

            // 🔹 Lookup by item serial/batch
            $stmt = $conn->prepare("SELECT id, product_id FROM items WHERE serial=? LIMIT 1");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($item) {
                $productId = $item['product_id'];
            } else {
                // 🔹 fallback: product by SKU
                $stmt = $conn->prepare("SELECT id FROM products WHERE sku=? LIMIT 1");
                $stmt->bind_param("s", $code);
                $stmt->execute();
                $prod = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$prod) {
                    return $this->respond([
                        'status'  => 'error',
                        'message' => 'Code not found'
                    ]);
                }

                $productId = $prod['id'];
            }

            // 🔹 Insert tracking record
            $stmt = $conn->prepare("
                INSERT INTO product_tracking (product_id, tracking_code, action, user_id, warehouse_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issii", $productId, $code, $action, $userId, $warehouseId);
            $stmt->execute();
            $stmt->close();

            // 🔹 Fetch product info with image
            $sql = "
                SELECT p.id, p.sku, p.name, p.category, pi.url as primary_image
                FROM products p
                LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                WHERE p.id = ?
                LIMIT 1
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $productData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // 🔹 Fetch warehouse name
            $warehouseName = null;
            if ($warehouseId) {
                $stmt = $conn->prepare("SELECT name FROM warehouses WHERE id=? LIMIT 1");
                $stmt->bind_param("i", $warehouseId);
                $stmt->execute();
                $w = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $warehouseName = $w['name'] ?? null;
            }

            // 🔹 Activity log
            add_activity_log($userId, 'scanned', json_encode([
                'product_id'     => $productId,
                'name'           => $productData['name'] ?? null,
                'tracking_code'  => $code,
                'warehouse_name' => $warehouseName
            ]));

            // 🔹 Response
            return $this->respond([
                'status'         => 'success',
                'message'        => 'Scan saved',
                'product'        => $productData,
                'product_id'     => $productId,
                'name'           => $productData['name'] ?? null,
                'primary_image'  => $productData['primary_image'] ?? null,
                'tracking_code'  => $code,
                'action'         => $action,
                'warehouse_name' => $warehouseName
            ]);

        } catch (\Exception $e) {
            return $this->respond([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function scan()
    {
        global $conn; 
  
        $db = \Config\Database::connect();
        $req = $_POST ?: (json_decode(file_get_contents("php://input"), true) ?? []);
//        $req = $this->request->getJSON(true);

        $code        = trim($req['code'] ?? '');
        $action      = $req['action'] ?? null;
        $warehouseId = $req['warehouse_id'] ?? null; // ✅ new
        $userId      = $req['uid'] ?? null;

        
        if (!$code) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Missing code']);
        }
        if (!$action) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No Action Selected!']);
        }


        try {

            $productId = null;
            $item_id = null; 
            $serial = preg_replace('/-\d+$/', '', $code);

            // 🔹 Lookup by item serial/batch
            $item = $db->table('items')
                ->select('id, product_id')
                ->where('serial', $code)
                ->get()->getRowArray();

            if (!$item) {
                    return $this->response->setJSON([
                        'status'  => 'error',
                        'message' => 'Item not found'
                    ]);
            }

            $productId = $item['product_id'];

            $product = $db->table('products')
                ->select('id')
                ->where('id', $productId)
                ->get()->getRowArray();
            if (!$product) {
                    return $this->response->setJSON([
                        'status'  => 'error',
                        'message' => 'Code not found'
                    ]);
            }

            // 🔹 Insert tracking record
            $trackingData = [
                'product_id'    => $productId,
                'item_id'       => $item_id,
                'tracking_code' => $code,
                'action'        => 'scan',
                'status'        => $action,
                'user_id'       => $userId,
                'warehouse_id'  => $warehouseId,
                'created_at'    => date('Y-m-d H:i:s'),
            ];
            $db->table('product_tracking')->insert($trackingData);

            // 🔹 Update item status (only update warehouse if given)
            $itemUpdate = ['status' => $action];
            if (!empty($warehouseId)) {
                $itemUpdate['current_warehouse_id'] = $warehouseId;
            }

            $db->table('items')
                ->where('serial', $code)
                ->update($itemUpdate);


            // 🔹 Fetch product info with image
            $productData = $db->table('products p')
                ->select('p.id, p.sku, p.name, p.category, pi.url as primary_image')
                ->join('product_images pi', 'pi.product_id = p.id AND pi.is_primary = 1', 'left')
                ->where('p.id', $productId)
                ->get()->getRowArray();

            // 🔹 Fetch warehouse info if applicable

            $warehouseName = null;
            if ($warehouseId) {
                $w = $db->table('warehouses')->select('name')->where('id', $warehouseId)->get()->getRowArray();
                $warehouseName = $w['name'] ?? null;
            }

           // Add activity Log
            add_activity_log($userId,'scanned',json_encode([
                'product_id' => $productId,
                'name' => $productData['name'] ?? null,
                'tracking_code' => $code,
                'warehouse_name' => $warehouseName
            ])); 

            $res = [
                'status' => 'success',
                'message' => 'Scan saved',
                'product' => $productData,
                'product_id' => $productId,
                'name' => $product['name'] ?? null,
                'primary_image' => $product['primary_image'] ?? null,
                'tracking_code' => $code,
                'action' => $action,
                'warehouse_name' => $warehouseName
            ];
            return $this->response->setJSON($res);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function scan1()
    {
        $db = \Config\Database::connect();
        $req = $this->request->getJSON(true);

        $code   = trim($req['code'] ?? '');
        $action = $req['action'] ?? null;
        $userId = session()->get('uid'); // adjust depending on your auth/session

        if (!$code) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Missing code']);
        }

        try {
            $productId = null;

            // 🔹 Try to match by item serial/batch
            $builder = $db->table('items');
            $builder->select('id, product_id');
            $builder->where('serial', $code);
            $item = $builder->get()->getRowArray();

            if ($item) {
                $productId = $item['product_id'];
            } else {
                // 🔹 fallback: try product by SKU
                $builder = $db->table('products');
                $builder->select('id');
                $builder->where('sku', $code);
                $product = $builder->get()->getRowArray();

                if (!$product) {
                    return $this->response->setJSON([
                        'status'  => 'error',
                        'message' => 'Code not found'
                    ]);
                }
                $productId = $product['id'];
            }

            // 🔹 Log to tracking
            $db->table('product_tracking')->insert([
                'product_id'    => $productId,
                'tracking_code' => $code,
                'action'        => $action,
                'user_id'       => $userId,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            // 🔹 Fetch product details (with primary image)
            $builder = $db->table('products p');
            $builder->select('p.id, p.sku, p.name, p.category, pi.url as primary_image');
            $builder->join('product_images pi', 'pi.product_id = p.id AND pi.is_primary = 1', 'left');
            $builder->where('p.id', $productId);
            $productData = $builder->get()->getRowArray();

            if (!$productData) {
                $productData = ['id' => $productId, 'sku' => $code, 'name' => null, 'primary_image' => null];
            }

            return $this->response->setJSON([
                'status'   => 'success',
                'message'  => 'Scan saved',
                'action'   => $action,
                'product'  => $productData,
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }



    /** POST /products/{id}/images/upload  (multipart, field: files[]) */
    public function uploadImages($id = null)
    {

        global $conn;
        if (!$id) return $this->failValidationErrors('Missing id');

        // Check product
        $stmt = $conn->prepare("SELECT id FROM products WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) return $this->failNotFound('Product not found');

        if (empty($_FILES)) return $this->failValidationErrors('No files uploaded');
        $files = $this->normalizeFiles($_FILES['files'] ?? $_FILES);

        $destDir = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;
        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

     

        $allowed = ['jpg','jpeg','png','gif','webp'];
        $inserted = [];
        $errors = [];

        $conn->begin_transaction();
        try {
            // does a primary already exist?
            $stmt = $conn->prepare("SELECT id FROM product_images WHERE product_id=? AND is_primary=1 LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $hasPrimary = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();

            foreach ($files as $f) {

                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $errors[] = ['name'=>$f['name'] ?? 'file', 'error'=>$f['error']];
                    continue;
                }

                $ext = strtolower(pathinfo($f['name'] ?? 'upload', PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    $errors[] = ['name'=>$f['name'] ?? 'file', 'error'=>'invalid_type'];
                    continue;
                }
                if (($f['size'] ?? 0) > 10 * 1024 * 1024) { // 10MB limit
                    $errors[] = ['name'=>$f['name'] ?? 'file', 'error'=>'too_large'];
                    continue;
                }

                $basename = bin2hex(random_bytes(8)) . '.' . $ext;
                $target   = $destDir . $basename;
 
                if (!move_uploaded_file($f['tmp_name'], $target)) {
                    $errors[] = ['name'=>$f['name'] ?? 'file', 'error'=>'move_failed'];
                    continue;
                }

                $rel = '/uploads/products/' . $id . '/' . $basename;
                $primary = $hasPrimary ? 0 : 1; // first image becomes primary if none
                if ($primary) $hasPrimary = true;
    
                $alt = substr(pathinfo($f['name'] ?? $basename, PATHINFO_FILENAME), 0, 190);

                $stmt = $conn->prepare("INSERT INTO product_images (product_id, url, is_primary, alt, sort_order) VALUES (?,?,?,?,0)");
                $stmt->bind_param("isis", $id, $rel, $primary, $alt);
                $ok = $stmt->execute();
                $imgId = $stmt->insert_id;
                $stmt->close();

                if ($ok) {
                    $inserted[] = ['id'=>$imgId, 'url'=>$rel, 'is_primary'=>$primary, 'alt'=>$alt];
                } else {
                    $errors[] = ['name'=>$f['name'] ?? 'file', 'error'=>'db_insert_failed'];
                }
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            return $this->fail('Upload failed: ' . $e->getMessage());
        }

        return $this->respond(['uploaded'=>count($inserted), 'images'=>$inserted, 'errors'=>$errors]);
    }

    /** POST /products/{id}/images/update  (JSON or form: image_id, is_primary?, alt?, sort_order?) */
    public function updateImage($id = null)
    {
        global $conn;
        $p = $_POST ?: (json_decode(file_get_contents("php://input"), true) ?? []);
        if (!$id || empty($p['image_id'])) return $this->failValidationErrors('image_id required');
        $imageId = (int)$p['image_id'];
        $makePrimary = !empty($p['is_primary']);

        // image exists?
        $stmt = $conn->prepare("SELECT id FROM product_images WHERE id=? AND product_id=?");
        $stmt->bind_param("ii", $imageId, $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return $this->failNotFound('Image not found');

        if ($makePrimary) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();

                $one = 1;
                $stmt = $conn->prepare("UPDATE product_images SET is_primary=? WHERE id=?");
                $stmt->bind_param("ii", $one, $imageId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
            } catch (\Throwable $e) {
                $conn->rollback();
                return $this->fail('Update failed: ' . $e->getMessage());
            }
        } else {
            // optional alt/sort_order updates
            $fields=[]; $vals=[]; $types='';
            if (isset($p['alt'])) { $fields[]='alt=?'; $vals[]=$p['alt']; $types.='s'; }
            if (isset($p['sort_order'])) { $fields[]='sort_order=?'; $vals[]=(int)$p['sort_order']; $types.='i'; }
            if ($fields) {
                $vals[] = $imageId; $types.='i';
                $sql = "UPDATE product_images SET " . implode(',', $fields) . " WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$vals);
                $stmt->execute();
                $stmt->close();
            }
        }

        // return list back
        $stmt = $conn->prepare("SELECT id, url, is_primary, alt, sort_order, created_at FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order ASC, id DESC");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $imgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $this->respond(['updated'=>true, 'images'=>$imgs]);
    }

    /** POST /products/{id}/images/delete  (JSON or form: image_id) */
    public function deleteImage($id = null)
    {
        global $conn;
        $p = $_POST ?: (json_decode(file_get_contents("php://input"), true) ?? []);
        if (!$id || empty($p['image_id'])) return $this->failValidationErrors('image_id required');
        $imageId = (int)$p['image_id'];

        // fetch image
        $stmt = $conn->prepare("SELECT id, url, is_primary FROM product_images WHERE id=? AND product_id=?");
        $stmt->bind_param("ii", $imageId, $id);
        $stmt->execute();
        $img = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$img) return $this->failNotFound('Image not found');

        $filePath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($img['url'], '/');
        $wasPrimary = (int)$img['is_primary'] === 1;

        $conn->begin_transaction();
        try {
            // delete db record
            $stmt = $conn->prepare("DELETE FROM product_images WHERE id=?");
            $stmt->bind_param("i", $imageId);
            $stmt->execute();
            $stmt->close();

            // delete file best-effort
            if (is_file($filePath)) @unlink($filePath);

            if ($wasPrimary) {
                // promote a remaining image as primary
                $stmt = $conn->prepare("SELECT id FROM product_images WHERE product_id=? ORDER BY id DESC LIMIT 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $next = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($next) {
                    $one=1;
                    $stmt = $conn->prepare("UPDATE product_images SET is_primary=? WHERE id=?");
                    $stmt->bind_param("ii", $one, $next['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            return $this->fail('Delete failed: ' . $e->getMessage());
        }

        return $this->respondDeleted(['deleted'=>true]);
    }

    /** helper: normalize $_FILES */
    private function normalizeFiles($f)
    {
        $out = [];
        if (isset($f['name']) && is_array($f['name'])) {
            $n = count($f['name']);
            for ($i=0; $i<$n; $i++) {
                $out[] = [
                    'name' => $f['name'][$i] ?? null,
                    'type' => $f['type'][$i] ?? null,
                    'tmp_name' => $f['tmp_name'][$i] ?? null,
                    'error' => $f['error'][$i] ?? null,
                    'size' => $f['size'][$i] ?? null,
                ];
            }
        } elseif (isset($f['name'])) {
            $out[] = $f;
        } else {
            foreach ($f as $one) $out[] = $one;
        }
        return $out;
    }

    /** POST /products  — list + filters + pagination + summary */
    public function index()
    {
        global $conn;

        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        $page   = max(1, (int)($p['page']  ?? 1));
        $limit  = max(1, min(100, (int)($p['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;

        $search    = trim((string)($p['search'] ?? ''));
        $category  = trim((string)($p['category'] ?? ''));
        $warehouse = trim((string)($p['warehouse'] ?? ''));
        $status    = trim((string)($p['status'] ?? ''));
        $stockLvl  = trim((string)($p['stockLevel'] ?? '')); // '', 'low', 'out'
        $tagsIn    = is_array($p['tags'] ?? null) ? $p['tags'] : [];
        $uid       = trim((string)($p['uid'] ?? ''));

        $where  = [];
        $params = [];
        $types  = '';

        // 🔥 If uid is given, get the user's warehouse_id and filter products by it
        if ($uid !== '') {
            $userWarehouseId = null;

            $stmt = $conn->prepare("SELECT warehouse_id FROM users WHERE id = ? LIMIT 1");
            $uidInt = (int)$uid;
            $stmt->bind_param("i", $uidInt);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($row['warehouse_id'])) {
                $userWarehouseId = (int)$row['warehouse_id'];

                // Force filter to user's warehouse
                $where[]  = "p.warehouse_id = ?";
                $params[] = $userWarehouseId;
                $types   .= 'i';
            }
        }

        if ($search !== '') {
            if (ctype_digit($search)) {
                $where[] = "(p.id = ? OR p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)";
                $id   = (int)$search;
                $like = "%{$search}%";
                array_push($params, $id, $like, $like, $like);
                $types .= 'isss';
            } else {
                $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)";
                $like = "%{$search}%";
                array_push($params, $like, $like, $like);
                $types .= 'sss';
            }
        }

        if ($category !== '') {
            $where[]  = "p.category = ?";
            $params[] = $category;
            $types   .= 's';
        }

        if ($status !== '') {
            $where[]  = "p.status = ?";
            $params[] = $status;
            $types   .= 's';
        }

        // ✅ if a specific warehouse filter is sent, use products.warehouse_id
        if ($warehouse !== '') {
            $where[]  = "p.warehouse_id = ?";
            $params[] = (int)$warehouse;
            $types   .= 'i';
        }

        if ($tagsIn) {
            $ph = implode(',', array_fill(0, count($tagsIn), '?'));
            $where[] = "p.id IN (SELECT pt.product_id FROM products_tags pt WHERE pt.tag_text IN ($ph))";
            foreach ($tagsIn as $t) { 
                $params[] = (string)$t; 
                $types   .= 's'; 
            }
        }

        if ($stockLvl === 'low') {
            $where[] = "(p.reorder_point>0 AND (SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')) <= p.reorder_point)";
        } elseif ($stockLvl === 'out') {
            $where[] = "NOT EXISTS (SELECT 1 FROM items i WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED'))";
        }

        $sqlWhere = $where ? ("WHERE ".implode(" AND ", $where)) : '';

        // total
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM products p {$sqlWhere}");
        if ($types) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        // page list
        $listSql = "
            SELECT
              p.id, p.sku, p.name, p.category, p.last_status as status,
              p.price, p.cost, p.warehouse_id, p.reorder_point,
              p.brand, p.model, p.description, p.warehouse_price, 
              COALESCE((
                SELECT url FROM product_images pi
                WHERE pi.product_id=p.id
                ORDER BY is_primary DESC, id ASC LIMIT 1
              ), '') AS primary_image,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id),0) AS total_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='AVAILABLE'),0) AS available_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='RESERVED'),0) AS reserved_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='SHIPPED'),0) AS shipped_qty
            FROM products p
            {$sqlWhere}
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";

        $listParams   = $params; 
        $listTypes    = $types.'ii';
        $listParams[] = $limit; 
        $listParams[] = $offset;

        $stmt = $conn->prepare($listSql);
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // attach tags
        if ($rows) {
            $ids = array_column($rows, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $tps = str_repeat('i', count($ids));
            $stmt = $conn->prepare("SELECT product_id, tag_text FROM products_tags WHERE product_id IN ($ph)");
            $stmt->bind_param($tps, ...$ids);
            $stmt->execute();
            $all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $tagMap = [];
            foreach ($all as $r) $tagMap[$r['product_id']][] = $r['tag_text'];
            foreach ($rows as &$r) $r['tags'] = $tagMap[$r['id']] ?? [];
        }

        // ---- Summary (ONLY_FULL_GROUP_BY safe) ----
        $whereBlock = $sqlWhere; // e.g. "WHERE p.status = ? AND ..."
        $itemsAvailSql = "SELECT product_id, SUM(status IN ('IN_STOCK','RESERVED')) AS avail
                          FROM items GROUP BY product_id";


        $sumSql = "
            SELECT
  COALESCE(SUM(p.qty), 0) AS totalProducts,

              COALESCE(SUM(
                p.price * (
                  SELECT SUM(i.status IN ('IN_STOCK','RESERVED'))
                  FROM items i
                  WHERE i.product_id = p.id
                )
              ),0) AS totalValue,

              SUM(p.status='active') AS activeProducts,

              SUM(
                p.reorder_point > 0 AND (
                  (SELECT COUNT(*) FROM items i
                    WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')
                  ) <= p.reorder_point
                )
              ) AS lowStock,

              SUM(
                NOT EXISTS (
                  SELECT 1 FROM items i
                  WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')
                )
              ) AS outOfStock,

              /* ✅ TOTAL AVAILABLE (status = 'AVAILABLE') */
              COALESCE(SUM(
                (SELECT COUNT(*) 
                 FROM items i 
                 WHERE i.product_id = p.id AND i.status = 'AVAILABLE')
              ),0) AS available_qty,

              /* ✅ TOTAL IN-TRANSIT (status = 'IN_TRANSIT') */
              COALESCE(SUM(
                (SELECT COUNT(*) 
                 FROM items i 
                 WHERE i.product_id = p.id AND i.status = 'IN_TRANSIT')
              ),0) AS in_transit_qty,

              /* ✅ TOTAL SOLD (status = 'SOLD') */
              COALESCE(SUM(
                (SELECT COUNT(*) 
                 FROM items i 
                 WHERE i.product_id = p.id AND i.status = 'SOLD')
              ),0) AS sold_qty

            FROM products p
            $sqlWhere
        ";

        $stmt = $conn->prepare($sumSql);
        if ($types) { 
            $stmt->bind_param($types, ...$params); 
        }
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        return $this->respond([
            'products'     => $rows,
            'totalRecords' => $total,
            'userid'       => $uid,
            'summary'      => [
                'totalProducts'  => (int)($summary['totalProducts'] ?? 0),
                'totalValue'     => (float)($summary['totalValue'] ?? 0),
                'activeProducts' => (int)($summary['activeProducts'] ?? 0),
                'lowStock'       => (int)($summary['lowStock'] ?? 0),
                'outOfStock'     => (int)($summary['outOfStock'] ?? 0),
                'available_qty'   => (int)($summary['available_qty'] ?? 0),
                'in_transit_qty'  => (int)($summary['in_transit_qty'] ?? 0),
                'sold_qty'        => (int)($summary['sold_qty'] ?? 0),                
            ],
        ]);
    }

    public function index1()
    {
        global $conn;

        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        $page   = max(1, (int)($p['page']  ?? 1));
        $limit  = max(1, min(100, (int)($p['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;

        $search    = trim((string)($p['search'] ?? ''));
        $category  = trim((string)($p['category'] ?? ''));
        $warehouse = trim((string)($p['warehouse'] ?? ''));
        $status    = trim((string)($p['status'] ?? ''));
        $stockLvl  = trim((string)($p['stockLevel'] ?? '')); // '', 'low', 'out'
        $tagsIn    = is_array($p['tags'] ?? null) ? $p['tags'] : [];
        $uid    = trim((string)($p['uid'] ?? ''));

        $where  = [];
        $params = [];
        $types  = '';

        if ($search !== '') {
            // ✅ allow searching by id (exact match if numeric, or LIKE if string)
            if (ctype_digit($search)) {
                // if the search is all digits, check exact id match + other LIKE fields
                $where[] = "(p.id = ? OR p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)";
                $id = (int)$search;
                $like = "%{$search}%";
                array_push($params, $id, $like, $like, $like);
                $types .= 'isss';
            } else {
                // non-numeric, fallback to LIKE search
                $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)";
                $like = "%{$search}%";
                array_push($params, $like, $like, $like);
                $types .= 'sss';
            }
        }

        if ($category !== '') {
            $where[] = "p.category = ?";
            $params[] = $category; $types .= 's';
        }
        if ($status !== '') {
            $where[] = "p.status = ?";
            $params[] = $status; $types .= 's';
        }
        if ($warehouse !== '') {
            $where[] = "(p.default_warehouse_id = ?)";
            $params[] = (int)$warehouse; $types .= 'i';
        }
        if ($tagsIn) {
            $ph = implode(',', array_fill(0, count($tagsIn), '?'));
            $where[] = "p.id IN (SELECT pt.product_id FROM products_tags pt WHERE pt.tag_text IN ($ph))";
            foreach ($tagsIn as $t) { $params[] = (string)$t; $types .= 's'; }
        }
        if ($stockLvl === 'low') {
            $where[] = "(p.reorder_point>0 AND (SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')) <= p.reorder_point)";
        } elseif ($stockLvl === 'out') {
            $where[] = "NOT EXISTS (SELECT 1 FROM items i WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED'))";
        }

        $sqlWhere = $where ? ("WHERE ".implode(" AND ", $where)) : '';

        // total
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM products p {$sqlWhere}");
        if ($types) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        // page list
        $listSql = "
            SELECT
              p.id, p.sku, p.name, p.category, p.last_status as status,
              p.price, p.cost, p.default_warehouse_id, p.reorder_point,
              p.brand, p.model, p.description,
              COALESCE((
                SELECT url FROM product_images pi
                WHERE pi.product_id=p.id
                ORDER BY is_primary DESC, id ASC LIMIT 1
              ), '') AS primary_image,
              -- quantities
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id),0) AS total_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='AVAILABLE'),0) AS available_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='RESERVED'),0) AS reserved_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='SHIPPED'),0) AS shipped_qty
            FROM products p
            {$sqlWhere}
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";
        $listParams = $params; $listTypes = $types.'ii';
        $listParams[] = $limit; $listParams[] = $offset;

        $stmt = $conn->prepare($listSql);
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // attach tags
        if ($rows) {
            $ids = array_column($rows, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $tps = str_repeat('i', count($ids));
            $stmt = $conn->prepare("SELECT product_id, tag_text FROM products_tags WHERE product_id IN ($ph)");
            $stmt->bind_param($tps, ...$ids);
            $stmt->execute();
            $all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $tagMap = [];
            foreach ($all as $r) $tagMap[$r['product_id']][] = $r['tag_text'];
            foreach ($rows as &$r) $r['tags'] = $tagMap[$r['id']] ?? [];
        }

        // ---- Summary (ONLY_FULL_GROUP_BY safe) ----
        $whereBlock = $sqlWhere; // e.g. "WHERE p.status = ? AND ..."
        $itemsAvailSql = "SELECT product_id, SUM(status IN ('IN_STOCK','RESERVED')) AS avail
                          FROM items GROUP BY product_id";

        $sumSql = "
            SELECT
              COUNT(*) AS totalProducts,

              COALESCE(SUM(
                p.price * (
                  SELECT SUM(i.status IN ('IN_STOCK','RESERVED'))
                  FROM items i
                  WHERE i.product_id = p.id
                )
              ),0) AS totalValue,

              SUM(p.status='active') AS activeProducts,

              SUM(
                p.reorder_point > 0 AND (
                  (SELECT COUNT(*) FROM items i
                    WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')
                  ) <= p.reorder_point
                )
              ) AS lowStock,

              SUM(
                NOT EXISTS (
                  SELECT 1 FROM items i
                  WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')
                )
              ) AS outOfStock

            FROM products p
            $sqlWhere
        ";

        $stmt = $conn->prepare($sumSql);
        if ($types) { 
            $stmt->bind_param($types, ...$params); 
        }
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();




        return $this->respond([
            'products'     => $rows,
            'totalRecords' => $total,
            'userid'       => $uid,
            'summary'      => [
                'totalProducts'  => (int)($summary['totalProducts'] ?? 0),
                'totalValue'     => (float)($summary['totalValue'] ?? 0),
                'activeProducts' => (int)($summary['activeProducts'] ?? 0),
                'lowStock'       => (int)($summary['lowStock'] ?? 0),
                'outOfStock'     => (int)($summary['outOfStock'] ?? 0),
            ],
        ]);
    }

    /** POST /products/create */
    public function create()
    {
        global $conn;

        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        // basic validation
        foreach (['name','category'] as $k) {
            if (empty($p[$k])) return $this->failValidationErrors("$k is required");
        }

        // prepare variables (must be variables, not expressions)
        $sku   = (string)$p['sku'];
        $name  = (string)$p['name'];
        $cat   = (string)$p['category'];
        $stat  = isset($p['status']) && $p['status'] !== '' ? (string)$p['status'] : 'active';

        $price = isset($p['price']) ? (float)$p['price'] : 0.0;
        $cost  = isset($p['cost'])  ? (float)$p['cost']  : 0.0;

        // allow NULL for default_warehouse_id if blank/absent
        $dwid  = (isset($p['default_warehouse_id']) && $p['default_warehouse_id'] !== '' && $p['default_warehouse_id'] !== null)
                ? (int)$p['default_warehouse_id'] : null;

        $rop   = isset($p['reorder_point']) ? (int)$p['reorder_point'] : 0;

        // nullable strings are fine with "s" when value is NULL
        $brand = array_key_exists('brand',$p) ? ( ($p['brand'] === '' ? null : (string)$p['brand']) ) : null;
        $model = array_key_exists('model',$p) ? ( ($p['model'] === '' ? null : (string)$p['model']) ) : null;
        $desc  = array_key_exists('description',$p) ? ( ($p['description'] === '' ? null : (string)$p['description']) ) : null;

        $sql = "INSERT INTO products
                (sku, name, category, status, price, cost, default_warehouse_id, reorder_point, brand, model, description)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) return $this->fail('Prepare failed: '.$conn->error);

        // types: s s s s d d i i s s s
        $types = "ssssddiiiss";
        $stmt->bind_param(
            $types,
            $sku, $name, $cat, $stat,
            $price, $cost,
            $dwid, $rop,
            $brand, $model, $desc
        );

        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        if (!$ok) return $this->fail('Insert failed: '.$conn->error);

        // tags (optional)
        if (!empty($p['tags']) && is_array($p['tags'])) {
            $ins = $conn->prepare("INSERT INTO products_tags (product_id, tag_text) VALUES (?,?)");
            if ($ins) {
                foreach ($p['tags'] as $t) {
                    $tt = trim((string)$t);
                    if ($tt === '') continue;
                    $ins->bind_param("is", $newId, $tt);
                    $ins->execute();
                }
                $ins->close();
            }
        }

        return $this->respondCreated(['product_id' => $newId]);
    }

    /** POST /products/{id}/update */
    public function update($id = null)
    {
        global $conn;
        if (!$id) return $this->failValidationErrors('Missing id');
        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        $fields = []; 
        $vals   = []; 
        $types  = '';

        $map = [
            'sku'                   => 's',
            'name'                  => 's',
            'category'              => 's',
            'status'                => 's',
            'size'                  => 's',
            'price'                 => 'd',
            'cost'                  => 'd',
            'default_warehouse_id'  => 'i',
            'reorder_point'         => 'i',
            'max_stock'             => 'i',   // ✅ existing
            'brand'                 => 's',
            'model'                 => 's',
            'description'           => 's',

            // ✅ NEW pricing fields
            'markup_percent'        => 'd',
            'warehouse_price'       => 'd',

            // ✅ specification fields
            'length'                => 's',
            'width'                 => 's',
            'height'                => 's',
            'weight'                => 's',
            'material'              => 's',
            'base'                  => 's',
            'engraving'             => 's',
            'packaging'             => 's',
            'supplier'              => 's',
            'manufactured'          => 's',
            'warranty'              => 's'
        ];

        foreach ($map as $k => $t) {
            if (array_key_exists($k, $p)) {
                $fields[] = "$k = ?";
                if ($t === 'i') {
                    $vals[] = (int)$p[$k];
                } elseif ($t === 'd') {
                    $vals[] = (float)$p[$k];
                } else {
                    $vals[] = $p[$k];
                }
                $types .= $t;
            }
        }

        if (!$fields) return $this->respond(['message' => 'Nothing to update']);

        $vals[] = (int)$id;
        $types .= 'i';

        $sql = "UPDATE products SET " . implode(',', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) return $this->fail('Update failed');

        if (isset($p['tags']) && is_array($p['tags'])) {
            $conn->query("DELETE FROM products_tags WHERE product_id=".(int)$id);
            $ins = $conn->prepare("INSERT INTO products_tags (product_id, tag_text) VALUES (?,?)");
            foreach ($p['tags'] as $t) {
                $tt = trim((string)$t);
                if ($tt === '') continue;
                $pid = (int)$id;
                $ins->bind_param("is", $pid, $tt);
                $ins->execute();
            }
            $ins->close();
        }

        return $this->respond(['updated' => true]);
    }

    /** POST /products/{id}/update */
    public function update1($id = null)
    {
        global $conn;
        if (!$id) return $this->failValidationErrors('Missing id');
        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        $fields = []; $vals = []; $types = '';
        $map = [
            'sku' => 's',
            'name' => 's',
            'category' => 's',
            'status' => 's',
            'size' => 's',
            'price' => 'd',
            'cost' => 'd',
            'default_warehouse_id' => 'i',
            'reorder_point' => 'i',
            'max_stock' => 'i',   // ✅ new field
            'brand' => 's',
            'model' => 's',
            'description' => 's',

            // ✅ specification fields
            'length' => 's',
            'width' => 's',
            'height' => 's',
            'weight' => 's',
            'material' => 's',
            'base' => 's',
            'engraving' => 's',
            'packaging' => 's',
            'supplier' => 's',
            'manufactured' => 's',
            'warranty' => 's'
        ];
        foreach ($map as $k=>$t) {
            if (array_key_exists($k,$p)) {
                $fields[]="$k=?";
                $vals[] = ($t==='i' ? (int)$p[$k] : ($t==='d' ? (float)$p[$k] : $p[$k]));
                $types .= $t;
            }
        }
        if (!$fields) return $this->respond(['message'=>'Nothing to update']);

        $vals[] = (int)$id; $types .= 'i';
        $sql = "UPDATE products SET ".implode(',', $fields)." WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) return $this->fail('Update failed');

        if (isset($p['tags']) && is_array($p['tags'])) {
            $conn->query("DELETE FROM products_tags WHERE product_id=".(int)$id);
            $ins = $conn->prepare("INSERT INTO products_tags (product_id, tag_text) VALUES (?,?)");
            foreach ($p['tags'] as $t) {
                $tt = trim((string)$t);
                if ($tt==='') continue;
                $pid=(int)$id;
                $ins->bind_param("is", $pid, $tt);
                $ins->execute();
            }
            $ins->close();
        }

        return $this->respond(['updated'=>true]);
    }

    /** POST /products/{id}/delete */
    public function delete($id = null)
    {
        global $conn;
        if (!$id) return $this->failValidationErrors('Missing id');

        $stmt = $conn->prepare("DELETE FROM product_images WHERE product_id=?"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
        $stmt = $conn->prepare("DELETE FROM products_tags  WHERE product_id=?"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
        $stmt = $conn->prepare("DELETE FROM product_specs  WHERE product_id=?"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
        $stmt = $conn->prepare("DELETE FROM product_events WHERE product_id=?"); $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();

        $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
   

        if (!$ok) return $this->fail('Delete failed');
        return $this->respondDeleted(['deleted'=>true]);
    }

    // /** POST /products/{id}/details */
    // public function details($id = null)
    // {
    //     global $conn;
    //     if (!$id) return $this->failValidationErrors('Missing id');

    //     $stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
    //     $stmt->bind_param("i",$id); $stmt->execute();
    //     $product = $stmt->get_result()->fetch_assoc(); $stmt->close();
    //     if (!$product) return $this->failNotFound('Product not found');

    //     // Tags
    //     $stmt = $conn->prepare("SELECT tag_text FROM products_tags WHERE product_id=? ORDER BY tag_text ASC");
    //     $stmt->bind_param("i",$id); $stmt->execute();
    //     $tags = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC),'tag_text'); 
    //     $stmt->close();

    //     // Images
    //     $stmt = $conn->prepare("SELECT id,url,is_primary FROM product_images WHERE product_id=? ORDER BY is_primary DESC, id ASC");
    //     $stmt->bind_param("i",$id); $stmt->execute();
    //     $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
    //     $stmt->close();

    //     // Stock counts
    //     $stock = ['available'=>0,'reserved'=>0,'shipped'=>0,'total'=>0];
    //     foreach (['available'=>'IN_STOCK','reserved'=>'RESERVED','shipped'=>'SHIPPED'] as $k=>$label) {
    //         $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM items WHERE product_id=? AND status=?");
    //         $stmt->bind_param("is",$id,$label); $stmt->execute();
    //         $stock[$k] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0); 
    //         $stmt->close();
    //     }
    //     $stock['total'] = $stock['available'] + $stock['reserved'] + $stock['shipped'];

    //     // Item list
    //     $stmt = $conn->prepare("
    //         SELECT id, serial AS serial, location_code, condition_label, status, acquired_at
    //         FROM items WHERE product_id=? AND status<>'DISPOSED' ORDER BY id DESC LIMIT 200
    //     ");
    //     $stmt->bind_param("i",$id); $stmt->execute();
    //     $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
    //     $stmt->close();

    //     // Events
    //     $stmt = $conn->prepare("
    //         SELECT id, event_type, description, actor, quantity, warehouse_name, created_at
    //         FROM product_events WHERE product_id=? ORDER BY created_at DESC LIMIT 50
    //     ");
    //     $stmt->bind_param("i",$id); $stmt->execute();
    //     $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
    //     $stmt->close();

    //     // Tracking history
    //     $stmt = $conn->prepare("
    //         SELECT id, tracking_code, status, location, remarks, created_at
    //         FROM product_tracking
    //         WHERE product_id=? ORDER BY created_at DESC LIMIT 100
    //     ");
    //     $stmt->bind_param("i",$id); 
    //     $stmt->execute();
    //     $tracking = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    //     $stmt->close();

    //     // Specs
    //     $stmt = $conn->prepare("SELECT spec_key, spec_value FROM product_specs WHERE product_id=?");
    //     $stmt->bind_param("i",$id); $stmt->execute();
    //     $specs = [];
    //     foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    //         $specs[$r['spec_key']] = $r['spec_value'];
    //     }
    //     $stmt->close();

    //     // Primary image fallback
    //     $stmt = $conn->prepare("SELECT url FROM product_images WHERE product_id=? AND is_primary=1 LIMIT 1");
    //     $stmt->bind_param("i", $id);
    //     $stmt->execute();
    //     $rec = $stmt->get_result()->fetch_assoc();
    //     $stmt->close();

    //     $product['primary_image'] = ($rec && !empty($rec['url']))
    //         ? $rec['url']
    //         : "/images/noimage.png";

    //     $product['tags'] = $tags;

    //     return $this->respond([
    //         'product'  => $product,
    //         'images'   => $images,
    //         'tracking' => $tracking,   // ✅ now populated
    //         'stock'    => [
    //             'total'=>$stock['total'],
    //             'available'=>$stock['available'],
    //             'reserved'=>$stock['reserved'],
    //             'shipped'=>$stock['shipped'],
    //             'lowAlert'=>(int)($product['reorder_point'] ?? 0),
    //         ],
    //         'items'    => $items,
    //         'events'   => $events,
    //         'specs'    => $specs,
    //     ]);
    // }

    /** POST /products/{id}/details */
    public function details($id = null)
    {
        global $conn;
        if (!$id) return $this->failValidationErrors('Missing id');


        $stmt = $conn->prepare("
            SELECT 
                p.*,
                w.name AS warehouse_name
            FROM products p
            LEFT JOIN warehouses w ON w.id = p.warehouse_id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();


        if (!$product) return $this->failNotFound('Product not found');

        $stmt = $conn->prepare("SELECT tag_text FROM products_tags WHERE product_id=? ORDER BY tag_text ASC");
        $stmt->bind_param("i",$id); $stmt->execute();
        $tags = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC),'tag_text'); $stmt->close();

        $stmt = $conn->prepare("SELECT id,url,is_primary FROM product_images WHERE product_id=? ORDER BY is_primary DESC, id ASC");
        $stmt->bind_param("i",$id); $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

        $stock = ['available_qty'=>0,'in_stock_qty'=>0,'sold_qty'=>0,'total'=>0,'in_transit_qty'=>0];
        foreach (['available_qty'=>'AVAILABLE','in_stock_qty'=>'IN_STOCK','sold_qty'=>'SOLD','in_transit_qty'=>'IN_TRANSIT'] as $k=>$label) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM items WHERE product_id=? AND status=?");
            $stmt->bind_param("is",$id,$label); $stmt->execute();
            $stock[$k] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0); $stmt->close();
        }
        //$stock['total'] = $stock['available_qty'] + $stock['reserved'] + $stock['shipped'];

        $res = $conn->prepare("SELECT COUNT(*) as cnt FROM items WHERE product_id=? and status <> 'CREATED'");
        $res->bind_param("i", $id);
        $res->execute();
        $row = $res->get_result()->fetch_assoc();
        $total_qty = (int)($row['cnt'] ?? 0);        

        $stmt = $conn->prepare("
            SELECT id, serial AS serial, location_code, condition_label, status, acquired_at
            FROM items WHERE product_id=? AND status<>'DISPOSED' ORDER BY id DESC LIMIT 200
        ");
        $stmt->bind_param("i",$id); $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

        $stmt = $conn->prepare("
            SELECT id, event_type, description, actor, quantity, warehouse_name, created_at
            FROM product_events WHERE product_id=? ORDER BY created_at DESC LIMIT 50
        ");
        $stmt->bind_param("i",$id); $stmt->execute();
        $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

        $stmt = $conn->prepare("SELECT spec_key, spec_value FROM product_specs WHERE product_id=?");
        $stmt->bind_param("i",$id); $stmt->execute();
        $specs = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $specs[$r['spec_key']] = $r['spec_value'];
        $stmt->close();

        $product['tags'] = $tags;

        $stmt = $conn->prepare("SELECT url FROM product_images WHERE product_id=? AND is_primary=1 LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $rec = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($rec && !empty($rec['url'])) {
            $product['primary_image'] = $rec['url'];
        } else {
            $product['primary_image'] = "/images/noimage.png"; 
        }

         // Tracking history
        $stmt = $conn->prepare("
            SELECT 
                pt.*, 
                CONCAT(u.firstname, ' ', u.lastname) AS fullname,
                w.name AS warehouse_name
            FROM product_tracking pt
            LEFT JOIN users u ON pt.user_id = u.id
            LEFT JOIN warehouses w ON pt.warehouse_id = w.id
            WHERE pt.product_id = ?
            ORDER BY pt.created_at DESC 
            LIMIT 100
        ");
        $stmt->bind_param("i", $id); 
        $stmt->execute();
        $tracking = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $product['available_qty'] = $stock['available_qty'];
        $product['in_stock_qty'] = $stock['in_stock_qty'];
        $product['in_transit_qty'] = $stock['in_transit_qty'];
        $product['sold_qty'] = $stock['sold_qty'];
        $product['total_qty'] =$total_qty ;

        return $this->respond([
            'product' => $product,
            'images'  => $images,
            'tracking' => $tracking,
            'items'   => $items,
            'events'  => $events,
            'specs'   => $specs,
        ]);
    }

    /** POST /products/{id}/images/add  — url, is_primary */
    public function addImage($id = null)
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];
        if (!$id || empty($p['url'])) return $this->failValidationErrors('url required');

        if (!empty($p['is_primary'])) {
            $stmt = $conn->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=?");
            $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
        }
        $isP = !empty($p['is_primary']) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO product_images (product_id,url,is_primary) VALUES (?,?,?)");
        $stmt->bind_param("isi", $id, $p['url'], $isP);
        $ok = $stmt->execute(); $newId = $stmt->insert_id; $stmt->close();
        if (!$ok) return $this->fail('Add image failed');
        return $this->respondCreated(['image_id'=>$newId]);
    }

  

    public function updateItem($id = null)
    {
        global $conn;

        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        $productId   = $p['product_id'] ?? null;
        $itemIds     = $p['item_ids'] ?? [];
        $status      = $p['status'] ?? null;
        $notes       = $p['notes'] ?? '';
        $warehouseId = $p['warehouse_id'] ?? null;
        $userId      = $p['uid'] ?? '';

        if (!$productId || empty($itemIds) || !$status) {
            return $this->failValidationErrors("product_id, item_ids, and status are required");
        }

        $conn->begin_transaction();

        try {
            foreach ($itemIds as $itemId) {
                if (!$itemId) {
                    throw new \Exception("item_id required");
                }

                // 🔹 1. Update the item
                if ($warehouseId) {
                    $stmt = $conn->prepare("
                        UPDATE items 
                        SET status=?, notes=?, current_warehouse_id=?, updated_at=NOW() 
                        WHERE id=? AND product_id=?
                    ");
                    $stmt->bind_param("ssiii", $status, $notes, $warehouseId, $itemId, $productId);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE items 
                        SET status=?, notes=?, updated_at=NOW() 
                        WHERE id=? AND product_id=?
                    ");
                    $stmt->bind_param("ssii", $status, $notes, $itemId, $productId);
                }
                $stmt->execute();
                $stmt->close();

                // 🔹 2. Get tracking code
                $stmt = $conn->prepare("SELECT serial FROM items WHERE id=? AND product_id=? LIMIT 1");
                $stmt->bind_param("ii", $itemId, $productId);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();

                $trackingCode = $row ? $row['serial'] : null;

                // 🔹 3. Insert into product_tracking
                $action = "update";
                $stmt = $conn->prepare("
                    INSERT INTO product_tracking 
                        (product_id, item_id, action, tracking_code, status, location, remarks, created_at, warehouse_id, user_id) 
                    VALUES (?, ?, ?, ?, ?, NULL, ?, NOW(), ?, ?)
                ");
                $stmt->bind_param(
                    "iissssii",
                    $productId,
                    $itemId,
                    $action,
                    $trackingCode,
                    $status,
                    $notes,
                    $warehouseId,
                    $userId
                );
                $stmt->execute();
                $stmt->close();
            }

            // 🔹 4. Update product last_status with latest status
            $stmt = $conn->prepare("UPDATE products SET last_status=? WHERE id=?");
            $stmt->bind_param("si", $status, $productId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            return $this->respond([
                "status"  => "success",
                "message" => "Items updated, tracking records created, and product last_status updated",
                "updated" => count($itemIds),
                "last_status" => $status
            ]);
        } catch (\Throwable $e) {
            $conn->rollback();
            return $this->fail("Failed to update items: " . $e->getMessage());
        }
    }


/** POST /products/{id}/items/delete  — item_id or item_ids[] */
public function deleteItem($id = null)
{
    global $conn;
    $p = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!$id) return $this->failValidationErrors('Missing product_id');

    // ✅ Support single item_id or multiple item_ids
    $itemIds = [];
    if (!empty($p['item_id'])) {
        $itemIds = [(int)$p['item_id']];
    } elseif (!empty($p['item_ids']) && is_array($p['item_ids'])) {
        $itemIds = array_map('intval', $p['item_ids']);
    }

    if (empty($itemIds)) {
        return $this->failValidationErrors('item_id or item_ids[] required');
    }

    // Prepare placeholders
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

    // ✅ Step 1: delete from product_tracking first
    $types = str_repeat("i", count($itemIds) + 1); // product_id + item_ids
    $params = array_merge([(int)$id], $itemIds);

    $stmt = $conn->prepare("DELETE FROM product_tracking WHERE product_id=? AND item_id IN ($placeholders)");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $deletedTracking = $stmt->affected_rows;
    $stmt->close();

    // ✅ Step 2: delete from items
    $types = str_repeat("i", count($itemIds) + 1);
    $params = array_merge($itemIds, [(int)$id]);

    $stmt = $conn->prepare("DELETE FROM items WHERE id IN ($placeholders) AND product_id=?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $deletedItems = $stmt->affected_rows;
    $stmt->close();

    if ($deletedItems < 1) {
        return $this->failNotFound('No items deleted');
    }


    // 🔹 Update products.qty with total count of items
    $resQty = $conn->prepare("SELECT COUNT(*) as total FROM items WHERE product_id=?");
    $resQty->bind_param("i", $id);
    $resQty->execute();
    $rowQty = $resQty->get_result()->fetch_assoc();
    $resQty->close();

    $totalQty = (int)($rowQty['total'] ?? 0);

    $upd = $conn->prepare("UPDATE products SET qty=? WHERE id=?");
    $upd->bind_param("ii", $totalQty, $id);
    $upd->execute();
    $upd->close();    

    // ✅ Step 3: log events for each deleted item
    foreach ($itemIds as $itemId) {
        $this->logEvent((int)$id, 'DISPOSED', "Item $itemId removed", 1, null, $itemId);
    }

    return $this->respondDeleted([
        'deleted'   => true,
        'count'     => $deletedItems,
        'tracking'  => $deletedTracking,
        'item_ids'  => $itemIds
    ]);
}


    /** POST /products/{id}/events/add */
    public function addEvent($id = null)
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];
        if (!$id || empty($p['event_type'])) return $this->failValidationErrors('event_type required');

        $this->logEvent(
            (int)$id,
            (string)$p['event_type'],
            (string)($p['description'] ?? ''),
            (int)($p['quantity'] ?? 0),
            (string)($p['warehouse_name'] ?? ''),
            (string)($p['actor'] ?? '')
        );
        return $this->respondCreated(['logged'=>true]);
    }

    /** POST /products/{id}/qr  — returns text payload only */
    public function qr($id = null)
    {
        global $conn;
        if (!$id) return $this->failValidationErrors('Missing id');
        $p = json_decode(file_get_contents("php://input"), true) ?? [];
        $format = strtoupper((string)($p['format'] ?? 'SKU'));
        $custom = trim((string)($p['text'] ?? ''));

        $stmt=$conn->prepare("SELECT sku,name FROM products WHERE id=?");
        $stmt->bind_param("i",$id); $stmt->execute();
        $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$row) return $this->failNotFound('Product not found');

        $payload = $row['sku'];
        if ($format==='SKU+NAME') $payload = $row['sku'].' | '.$row['name'];
        if ($format==='CUSTOM' && $custom!=='') $payload = $custom;

        return $this->respond(['payload'=>$payload, 'sku'=>$row['sku'], 'name'=>$row['name']]);
    }

    /** POST /products/export — CSV for current filters */
    public function export()
    {
        // Reuse filters from POST body, run a compact query, return CSV.
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];
        $_POST_JSON = $p; // local alias for reuse

        // Build WHERE same as index (but only a subset needed for CSV)
        $where=[]; $params=[]; $types='';
        $search = trim((string)($p['search'] ?? ''));
        $category = trim((string)($p['category'] ?? ''));
        $status = trim((string)($p['status'] ?? ''));
        $tagsIn = is_array($p['tags'] ?? null) ? $p['tags'] : [];

        if ($search!=='') { $where[]="(p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)"; $like="%{$search}%"; array_push($params,$like,$like,$like); $types.='sss'; }
        if ($category!==''){ $where[]="p.category=?"; $params[]=$category; $types.='s'; }
        if ($status!=='')  { $where[]="p.status=?";   $params[]=$status;   $types.='s'; }
        if ($tagsIn) { $ph=implode(',', array_fill(0, count($tagsIn), '?')); $where[]="p.id IN (SELECT product_id FROM products_tags WHERE tag_text IN ($ph))"; foreach($tagsIn as $t){$params[]=(string)$t;$types.='s';} }
        $sqlWhere = $where ? ("WHERE ".implode(" AND ", $where)) : '';

        $sql = "
            SELECT
              p.sku, p.name, p.category, p.status, p.price, p.cost,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='IN_STOCK'),0) AS available_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='RESERVED'),0)  AS reserved_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id AND i.status='SHIPPED'),0)   AS shipped_qty,
              COALESCE((SELECT COUNT(*) FROM items i WHERE i.product_id=p.id),0)                          AS total_qty,
              p.default_warehouse_id
            FROM products p
            {$sqlWhere}
            ORDER BY p.created_at DESC
            LIMIT 5000";
        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $lines = ["SKU,Name,Category,Status,Price,Cost,Available,Reserved,Shipped,Total,WarehouseId"];
        while ($r = $res->fetch_assoc()) {
            $lines[] = sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",%s,%s,%d,%d,%d,%d,%s",
                $r['sku'],
                str_replace('"','""',$r['name']),
                str_replace('"','""',$r['category']),
                $r['status'],
                number_format((float)$r['price'],2,'.',''),
                number_format((float)$r['cost'],2,'.',''),
                (int)$r['available_qty'],
                (int)$r['reserved_qty'],
                (int)$r['shipped_qty'],
                (int)$r['total_qty'],
                ($r['default_warehouse_id'] ?? '')
            );
        }
        $stmt->close();

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="products_export.csv"')
            ->setBody(implode("\n", $lines));
    }

    // ---------------- helpers ----------------
    private function logEvent(int $productId, string $type, ?string $desc, int $qty = 0, ?string $warehouse = null, ?string $actor = null): void
    {
        global $conn;
        $stmt = $conn->prepare("
            INSERT INTO product_events (product_id, event_type, description, actor, quantity, warehouse_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("isssis", $productId, $type, $desc, $actor, $qty, $warehouse);
        $stmt->execute();
        $stmt->close();
    }

    /** POST /products/{id}/items/add  — qty, serial (opt), batch, warehouse_id, condition_label */
    public function addItem($id = null)
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        // validate qty
        $qty = isset($p['qty']) && (int)$p['qty'] > 0 ? (int)$p['qty'] : 1;

        $ser          = trim((string)($p['serial'] ?? '')); // base serial (optional)
        $batch        = (string)($p['batch'] ?? '');
        $warehouse_id = (int)($p['warehouse_id'] ?? 0);
        $condition    = (string)($p['condition'] ?? '');
        $status       = 'CREATED'; // default
        $notes        = (string)($p['notes'] ?? '');
        $id           = (int)($id ?? 0); // product_id must be passed
        $userId       = ($p['uid'] ?? 0);

        if (!$id) {
            return $this->failValidationErrors('Missing product_id');
        }

        // find current count of items for serial auto-generation
        $res = $conn->prepare("SELECT COUNT(*) as cnt FROM items WHERE product_id=?");
        $res->bind_param("i", $id);
        $res->execute();
        $row = $res->get_result()->fetch_assoc();
        $res->close();

        $currentCount = (int)($row['cnt'] ?? 0);

        // prepare insert for items
        $ins = $conn->prepare("
            INSERT INTO items 
            (product_id, serial, batch, current_warehouse_id, condition_label, status, notes, created_at)
            VALUES (?,?,?,?,?,?,?,NOW())
        ");

        // prepare insert for tracking
        $track = $conn->prepare("
            INSERT INTO product_tracking 
            (product_id, item_id, action, tracking_code, status, location, remarks, created_at, warehouse_id, user_id)
            VALUES (?, ?, ?, ?, ?, NULL, ?, NOW(), ?, ?)
        ");

        for ($i = 0; $i < $qty; $i++) {
            $serialToUse = $ser;

            // If serial is not given, auto-generate
            if (empty($serialToUse)) {
                $serialToUse = "MP" . $id . "-" . str_pad((string)($currentCount + $i + 1), 3, "0", STR_PAD_LEFT);
            }

            // 🔹 Insert into items
            $ins->bind_param(
                "ississs",
                $id,
                $serialToUse,
                $batch,
                $warehouse_id,
                $condition,
                $status,
                $notes
            );
            $ins->execute();

            $itemId = $conn->insert_id; // get new item id

            // 🔹 Insert tracking record
            $action = "ADD";
            $track->bind_param(
                "iissssii",
                $id,
                $itemId,
                $action,
                $serialToUse,  // use the serial as tracking_code
                $status,
                $notes,
                $warehouse_id,
                $userId
            );
            $track->execute();
        }

        $ins->close();
        $track->close();

        // 🔹 Update products.qty with total count of items
        $resQty = $conn->prepare("SELECT COUNT(*) as total FROM items WHERE product_id=?");
        $resQty->bind_param("i", $id);
        $resQty->execute();
        $rowQty = $resQty->get_result()->fetch_assoc();
        $resQty->close();

        $totalQty = (int)($rowQty['total'] ?? 0);

        $upd = $conn->prepare("UPDATE products SET qty=? WHERE id=?");
        $upd->bind_param("ii", $totalQty, $id);
        $upd->execute();
        $upd->close();

        $this->logEvent((int)$id, 'RECEIVED', "Items added", (int)$qty, null, null);

        return $this->respondCreated([
            'added' => $qty,
            'new_qty' => $totalQty
        ]);
    }


/** POST /products/{id}/items/add  — qty, serial (opt), batch, warehouse_id, condition_label */
public function addItem2($id = null)
{
    global $conn;
    $p = json_decode(file_get_contents("php://input"), true) ?? [];

    // validate qty
    $qty = isset($p['qty']) && (int)$p['qty'] > 0 ? (int)$p['qty'] : 1;

    $ser          = trim((string)($p['serial'] ?? '')); // base serial (optional)
    $batch        = (string)($p['batch'] ?? '');
    $warehouse_id = (int)($p['warehouse_id'] ?? 0);
    $condition    = (string)($p['condition'] ?? '');
    $status       =  'CREATED'; // (string)($p['status'] ?? '');
    $acquired_at  = (string)($p['acquired_at'] ?? null);
    $notes        = (string)($p['notes'] ?? '');
    $id           = (int)($id ?? 0); // product_id must be passed

    if (!$id) {
        return $this->failValidationErrors('Missing product_id');
    }

    // find current count of items for serial auto-generation
    $res = $conn->prepare("SELECT COUNT(*) as cnt FROM items WHERE product_id=?");
    $res->bind_param("i", $id);
    $res->execute();
    $row = $res->get_result()->fetch_assoc();
    $res->close();

    $currentCount = (int)($row['cnt'] ?? 0);

    $ins = $conn->prepare("
        INSERT INTO items 
        (product_id, serial, batch, current_warehouse_id, condition_label, status, acquired_at, notes, created_at)
        VALUES (?,?,?,?,?,?,?,?,NOW())
    ");

    for ($i = 0; $i < $qty; $i++) {
        $serialToUse = $ser;

        // If serial is not given, auto-generate
        if (empty($serialToUse)) {
            $serialToUse = "MP" . $id . "-" . str_pad((string)($currentCount + $i + 1), 3, "0", STR_PAD_LEFT);
        } else {
            // If base serial provided, append padded sequence
            $serialToUse = $ser; // . str_pad((string)($i + 1), 3, "0", STR_PAD_LEFT);
        }

        $ins->bind_param(
            "ississss",
            $id,
            $serialToUse,
            $batch,
            $warehouse_id,
            $condition,
            $status,
            $acquired_at,
            $notes
        );
        $ins->execute();
    }

    $ins->close();

    $this->logEvent((int)$id, 'RECEIVED', "Items added", (int)$qty, null, null);

    return $this->respondCreated(['added' => $qty]);
}


    /** POST /products/{id}/items/add-batch */
    public function addBatch($id = null)
    {
        global $conn;
        $p = json_decode(file_get_contents("php://input"), true) ?? [];

        if (!$id || empty($p['qty']) || empty($p['warehouse_id'])) {
            return $this->failValidationErrors('qty and warehouse required');
        }

        $qty   = max(1, (int)$p['qty']);
        $batch = (string)($p['batch_number'] ?? 'BATCH-' . time());
        $cond  = (string)($p['condition'] ?? 'new');
        $stat  = (string)($p['status'] ?? 'available');
        $wid   = (int)$p['warehouse_id'];

        $sql = "INSERT INTO items 
                (product_id, serial, current_warehouse_id, condition_label, status, acquired_at)
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);

        for ($i = 1; $i <= $qty; $i++) {
            $serial = $batch . "-" . $i;
            $stmt->bind_param("isiss", $id, $serial, $wid, $cond, $stat);
            $stmt->execute();
        }
        $stmt->close();

        // 🔹 Update products.qty with total count of items
        $resQty = $conn->prepare("SELECT COUNT(*) as total FROM items WHERE product_id=?");
        $resQty->bind_param("i", $id);
        $resQty->execute();
        $rowQty = $resQty->get_result()->fetch_assoc();
        $resQty->close();

        $totalQty = (int)($rowQty['total'] ?? 0);

        $upd = $conn->prepare("UPDATE products SET qty=? WHERE id=?");
        $upd->bind_param("ii", $totalQty, $id);
        $upd->execute();
        $upd->close();

        return $this->respondCreated([
            'added'    => $qty,
            'new_qty'  => $totalQty
        ]);
    }


}
