<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Email\Email;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';
use App\Helpers\AuthHelper;

class AnalyticsController extends ResourceController
{
    public function dashboard()
    {
        global $conn;

        // --- SUMMARY CARDS ---
        $summaryQuery = "
            SELECT 
                SUM(total) AS total_revenue,
                COUNT(*) AS total_orders,
                AVG(total) AS avg_order_value,
                COUNT(DISTINCT customer_email) AS customers
            FROM analytics_orders
        ";

        $summaryResult = $conn->query($summaryQuery);
        $summary = $summaryResult->fetch_assoc();


        // --- REVENUE TREND (PER SITE) ---
        $trendQuery = "
            SELECT 
                DATE(order_date) AS date,

                SUM(CASE WHEN site = 'apc' THEN total ELSE 0 END) AS apc,
                SUM(CASE WHEN site = 'mp' THEN total ELSE 0 END) AS mp,
                SUM(CASE WHEN site = 'pnp' THEN total ELSE 0 END) AS pnp

            FROM analytics_orders
            GROUP BY DATE(order_date)
            ORDER BY DATE(order_date) ASC
        ";

        $trendResult = $conn->query($trendQuery);
        $revenueTrend = $trendResult->fetch_all(MYSQLI_ASSOC);


        // --- REVENUE BY WEBSITE ---
        $siteQuery = "
            SELECT 
                site,
                SUM(total) AS revenue
            FROM analytics_orders
            GROUP BY site
        ";

        $siteResult = $conn->query($siteQuery);
        $revenueBySite = $siteResult->fetch_all(MYSQLI_ASSOC);


        // --- ORDER STATUS BREAKDOWN ---
        $statusQuery = "
            SELECT 
                status,
                COUNT(*) AS total
            FROM analytics_orders
            GROUP BY status
        ";

        $statusResult = $conn->query($statusQuery);
        $orderStatus = $statusResult->fetch_all(MYSQLI_ASSOC);


        return $this->response->setJSON([
            'status' => 'success',
            'summary' => $summary,
            'revenue_trend' => $revenueTrend,
            'revenue_by_site' => $revenueBySite,
            'order_status' => $orderStatus
        ]);
    }
    
    public function auditlogs()
    {
        global $conn;

        $stmt = $conn->prepare("
            SELECT 
                id,
                user_name,
                action,
                resource,
                details,
                ip_address,
                created_at
            FROM analytics_audit_logs
            ORDER BY created_at DESC
        ");

        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $this->response->setJSON([
            'status' => 'success',
            'logs' => $logs
        ]);
    }

    public function orders()
    {
        global $conn;

        $stmt = $conn->prepare("
            SELECT 
                id,
                order_number,
                customer_name,
                customer_email,
                site,
                channel,
                total,
                status,
                payment_method,
                order_date
            FROM analytics_orders
            ORDER BY order_date DESC
        ");

        $stmt->execute();
        $result = $stmt->get_result();
        $orders = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $this->response->setJSON([
            'status' => 'success',
            'orders' => $orders
        ]);
    }

    public function addOrder()
    {
        global $conn;

        $request = service('request');
        $data = $request->getJSON(true);

        if (empty($data)) {
            $data = $request->getPost();
        }

        $order_number   = trim($data['order_number'] ?? '');
        $customer_name  = trim($data['customer_name'] ?? '');
        $customer_email = trim($data['customer_email'] ?? '');
        $site           = strtoupper(trim($data['site'] ?? ''));
        $channel        = strtolower(trim($data['channel'] ?? ''));
        $total          = isset($data['total']) ? (float)$data['total'] : 0;
        $status         = trim($data['status'] ?? '');
        $payment_method = trim($data['payment_method'] ?? '');
        $order_date     = trim($data['order_date'] ?? '');

        if ($order_number === '') {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Order number is required.'
            ]);
        }

        if ($customer_name === '') {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Customer name is required.'
            ]);
        }

        if ($total <= 0) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Total must be greater than 0.'
            ]);
        }

        if ($status === '') {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Status is required.'
            ]);
        }

        if ($order_date === '') {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Order date is required.'
            ]);
        }

        $allowed_sites = ['APC', 'MP', 'PNP'];
        if (!in_array($site, $allowed_sites, true)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid site.'
            ]);
        }

        $allowed_channels = ['website', 'walkin', 'callin', 'distributor'];
        if (!in_array($channel, $allowed_channels, true)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid channel.'
            ]);
        }

        $allowed_statuses = ['Completed', 'Processing', 'Pending', 'Refunded', 'Cancelled', 'On-Hold'];
        if (!in_array($status, $allowed_statuses, true)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid status.'
            ]);
        }

        if ($customer_email !== '' && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid customer email.'
            ]);
        }

        $checkStmt = $conn->prepare("SELECT id FROM analytics_orders WHERE order_number = ? LIMIT 1");
        $checkStmt->bind_param("s", $order_number);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $existing = $checkResult->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Order number already exists.'
            ]);
        }

        $stmt = $conn->prepare("
            INSERT INTO analytics_orders
            (
                order_number,
                customer_name,
                customer_email,
                site,
                channel,
                total,
                status,
                payment_method,
                order_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssdsss",
            $order_number,
            $customer_name,
            $customer_email,
            $site,
            $channel,
            $total,
            $status,
            $payment_method,
            $order_date
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to add order.',
                'error' => $error
            ]);
        }

        $newId = $stmt->insert_id;
        $stmt->close();

        $fetchStmt = $conn->prepare("
            SELECT 
                id,
                order_number,
                customer_name,
                customer_email,
                site,
                channel,
                total,
                status,
                payment_method,
                order_date,
                created_at
            FROM analytics_orders
            WHERE id = ?
            LIMIT 1
        ");
        $fetchStmt->bind_param("i", $newId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $order = $result->fetch_assoc();
        $fetchStmt->close();

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Order added successfully.',
            'order' => $order
        ]);
    }

    public function updateOrder()
    {
        global $conn;

        $request = service('request');
        $data = $request->getJSON(true);

        if (empty($data)) {
            $data = $request->getPost();
        }

        $id             = isset($data['id']) ? (int)$data['id'] : 0;
        $order_number   = trim($data['order_number'] ?? '');
        $customer_name  = trim($data['customer_name'] ?? '');
        $customer_email = trim($data['customer_email'] ?? '');
        $site           = strtoupper(trim($data['site'] ?? ''));
        $channel        = strtolower(trim($data['channel'] ?? ''));
        $total          = isset($data['total']) ? (float)$data['total'] : 0;
        $status         = trim($data['status'] ?? '');
        $payment_method = trim($data['payment_method'] ?? '');
        $order_date     = trim($data['order_date'] ?? '');

        if ($id <= 0) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Valid order ID is required.'
            ]);
        }

        if ($order_number === '') {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Order number is required.'
            ]);
        }

        if ($customer_name === '') {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Customer name is required.'
            ]);
        }

        if ($total <= 0) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Total must be greater than 0.'
            ]);
        }

        if ($status === '') {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Status is required.'
            ]);
        }

        if ($order_date === '') {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Order date is required.'
            ]);
        }

        $allowed_sites = ['APC', 'MP', 'PNP'];
        if (!in_array($site, $allowed_sites, true)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid site.'
            ]);
        }

        $allowed_channels = ['website', 'walkin', 'callin', 'distributor'];
        if (!in_array($channel, $allowed_channels, true)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid channel.'
            ]);
        }

        $allowed_statuses = ['Completed', 'Processing', 'Pending', 'Refunded', 'Cancelled', 'On-Hold'];
        if (!in_array($status, $allowed_statuses, true)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid status.'
            ]);
        }

        if ($customer_email !== '' && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid customer email.'
            ]);
        }

        $existsStmt = $conn->prepare("SELECT id FROM analytics_orders WHERE id = ? LIMIT 1");
        $existsStmt->bind_param("i", $id);
        $existsStmt->execute();
        $existsResult = $existsStmt->get_result();
        $orderExists = $existsResult->fetch_assoc();
        $existsStmt->close();

        if (!$orderExists) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Order not found.'
            ]);
        }

        $duplicateStmt = $conn->prepare("
            SELECT id
            FROM analytics_orders
            WHERE order_number = ?
              AND id != ?
            LIMIT 1
        ");
        $duplicateStmt->bind_param("si", $order_number, $id);
        $duplicateStmt->execute();
        $duplicateResult = $duplicateStmt->get_result();
        $duplicate = $duplicateResult->fetch_assoc();
        $duplicateStmt->close();

        if ($duplicate) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Order number already exists on another record.'
            ]);
        }

        $stmt = $conn->prepare("
            UPDATE analytics_orders
            SET
                order_number = ?,
                customer_name = ?,
                customer_email = ?,
                site = ?,
                channel = ?,
                total = ?,
                status = ?,
                payment_method = ?,
                order_date = ?
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "sssssdsssi",
            $order_number,
            $customer_name,
            $customer_email,
            $site,
            $channel,
            $total,
            $status,
            $payment_method,
            $order_date,
            $id
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to update order.',
                'error' => $error
            ]);
        }

        $stmt->close();

        $fetchStmt = $conn->prepare("
            SELECT 
                id,
                order_number,
                customer_name,
                customer_email,
                site,
                channel,
                total,
                status,
                payment_method,
                order_date,
                created_at
            FROM analytics_orders
            WHERE id = ?
            LIMIT 1
        ");
        $fetchStmt->bind_param("i", $id);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $order = $result->fetch_assoc();
        $fetchStmt->close();

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Order updated successfully.',
            'order' => $order
        ]);
    }

    public function users()
    {
        global $conn;

        $stmt = $conn->prepare("
            SELECT
                id,
                username,
                firstname,
                lastname,
                email,
                phone,
                role_id,
                status,
                date_created
            FROM users
            WHERE (deleted IS NULL OR deleted = 0)
            ORDER BY date_created DESC
        ");

        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $this->response->setJSON([
            'status' => 'success',
            'users' => $users
        ]);
    }

    public function addUser()
    {
        global $conn;

        $postData = json_decode(file_get_contents("php://input"), true);
        if (!$postData) {
            $postData = $_POST;
        }

        $username  = trim($postData['username'] ?? '');
        $firstname = trim($postData['firstname'] ?? '');
        $lastname  = trim($postData['lastname'] ?? '');
        $email     = trim($postData['email'] ?? '');
        $phone     = trim($postData['phone'] ?? '');
        $role_id   = (int)($postData['role_id'] ?? 0);
        $status    = trim($postData['status'] ?? 'active');
        $password  = $postData['password'] ?? '';

        if (!$username || !$firstname || !$lastname || !$email || !$password || !$role_id) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Missing required fields."
            ]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Invalid email address."
            ]);
        }

        if (!in_array($role_id, [1, 2, 3], true)) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Invalid role."
            ]);
        }

        if (!in_array(strtolower($status), ['active', 'inactive'], true)) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Invalid status."
            ]);
        }

        try {
            $check = "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1";
            $stmt  = $conn->prepare($check);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result   = $stmt->get_result();
            $existing = $result->fetch_assoc();

            if ($existing) {
                return $this->response->setJSON([
                    "status"  => "error",
                    "message" => "Username or email already exists."
                ]);
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users
                (
                    username,
                    firstname,
                    lastname,
                    role_id,
                    email,
                    phone,
                    password,
                    status,
                    date_created,
                    date_updated,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssissss",
                $username,
                $firstname,
                $lastname,
                $role_id,
                $email,
                $phone,
                $hashedPassword,
                $status
            );
            $stmt->execute();

            $newId = $conn->insert_id;

            $fetch = "SELECT
                        id,
                        username,
                        firstname,
                        lastname,
                        email,
                        phone,
                        role_id,
                        status,
                        date_created
                    FROM users
                    WHERE id = ?
                    LIMIT 1";
            $stmt = $conn->prepare($fetch);
            $stmt->bind_param("i", $newId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();

            return $this->response->setJSON([
                "status"  => "success",
                "message" => "User added successfully.",
                "user"    => $user
            ]);

        } catch (\Throwable $e) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function updateUser()
    {
        global $conn;

        $postData = json_decode(file_get_contents("php://input"), true);
        if (!$postData) {
            $postData = $_POST;
        }

        $id        = (int)($postData['id'] ?? 0);
        $username  = trim($postData['username'] ?? '');
        $firstname = trim($postData['firstname'] ?? '');
        $lastname  = trim($postData['lastname'] ?? '');
        $email     = trim($postData['email'] ?? '');
        $phone     = trim($postData['phone'] ?? '');
        $role_id   = (int)($postData['role_id'] ?? 0);
        $status    = trim($postData['status'] ?? 'active');
        $password  = $postData['password'] ?? '';

        if (!$id || !$username || !$firstname || !$lastname || !$email || !$role_id) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Missing required fields."
            ]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Invalid email address."
            ]);
        }

        if (!in_array($role_id, [1, 2, 3], true)) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Invalid role."
            ]);
        }

        if (!in_array(strtolower($status), ['active', 'inactive'], true)) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Invalid status."
            ]);
        }

        try {
            $exists = "SELECT id FROM users WHERE id = ? LIMIT 1";
            $stmt   = $conn->prepare($exists);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();

            if (!$user) {
                return $this->response->setJSON([
                    "status"  => "error",
                    "message" => "User not found."
                ]);
            }

            $check = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1";
            $stmt  = $conn->prepare($check);
            $stmt->bind_param("ssi", $username, $email, $id);
            $stmt->execute();
            $result    = $stmt->get_result();
            $duplicate = $result->fetch_assoc();

            if ($duplicate) {
                return $this->response->setJSON([
                    "status"  => "error",
                    "message" => "Username or email already exists."
                ]);
            }

            if ($password !== '') {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $sql = "UPDATE users
                        SET username = ?,
                            firstname = ?,
                            lastname = ?,
                            email = ?,
                            phone = ?,
                            role_id = ?,
                            status = ?,
                            password = ?,
                            date_updated = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                        LIMIT 1";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssssissi",
                    $username,
                    $firstname,
                    $lastname,
                    $email,
                    $phone,
                    $role_id,
                    $status,
                    $hashedPassword,
                    $id
                );
            } else {
                $sql = "UPDATE users
                        SET username = ?,
                            firstname = ?,
                            lastname = ?,
                            email = ?,
                            phone = ?,
                            role_id = ?,
                            status = ?,
                            date_updated = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                        LIMIT 1";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssssissi",
                    $username,
                    $firstname,
                    $lastname,
                    $email,
                    $phone,
                    $role_id,
                    $status,
                    $id
                );
            }

            $stmt->execute();

            $fetch = "SELECT
                        id,
                        username,
                        firstname,
                        lastname,
                        email,
                        phone,
                        role_id,
                        status,
                        date_created
                    FROM users
                    WHERE id = ?
                    LIMIT 1";
            $stmt = $conn->prepare($fetch);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();

            return $this->response->setJSON([
                "status"  => "success",
                "message" => "User updated successfully.",
                "user"    => $user
            ]);

        } catch (\Throwable $e) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function customers()
    {
        global $conn;

        $stmt = $conn->prepare("
            SELECT 
                id,
                customer_name,
                customer_email,
                site,
                channel,
                orders,
                lifetime_value,
                date_joined,
                last_order
            FROM analytics_customers
            ORDER BY last_order DESC, customer_name ASC
        ");

        $stmt->execute();
        $result = $stmt->get_result();
        $customers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $this->response->setJSON([
            'status' => 'success',
            'customers' => $customers
        ]);
    }

    public function products()
    {
        global $conn;

        $query = "
            SELECT 
                id,
                product_name,
                sku,
                site,
                revenue,
                units_sold,
                aov_contribution,
                date_created
            FROM analytics_products
            ORDER BY revenue DESC
        ";

        $result = $conn->query($query);

        $products = [];

        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }

        return $this->response->setJSON([
            'status' => 'success',
            'products' => $products
        ]);
    }

    public function auditlogs2()
    {

        return $this->response->setJSON([
            'status' => 'success',
            'logs' => 'test'
        ]);

        global $conn;
        $postData = json_decode(file_get_contents("php://input"), true);
        $id = $postData['id'] ?? null;

        if (!$id) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'User ID required'
            ]);
        }

        $stmt = $conn->prepare("
            SELECT id, type, data, ip_address, ip_location, date_created
            FROM activity_log
            WHERE user_id = ?
            ORDER BY date_created DESC
        ");
        $stmt->bind_param("i", $id); // i = integer
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();


        return $this->response->setJSON([
            'status' => 'success',
            'logs' => $logs
        ]);
    }

    // GET all users + summary
    public function list()
    {
        global $conn;

        // ✅ read JSON or POST
        $postData = json_decode(file_get_contents("php://input"), true);
        $search   = $postData['search'] ?? '';
        $role     = $postData['role'] ?? '';
        $status   = $postData['status'] ?? '';

        try {
            $sql = "
                SELECT 
                    u.*,
                    r.name AS role_name, 
                    r.permissions AS role_permissions,
                    w.name AS warehouse_name       -- ✅ warehouse label
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN warehouses w ON w.id = u.warehouse_id  -- ✅ join warehouses
                WHERE (? = '' OR u.username LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)
                  AND (? = '' OR r.name = ?)
                  AND (? = '' OR u.status = ?)
                  AND (u.status <> 'deleted') 
                ORDER BY u.id DESC
            ";

            $stmt = $conn->prepare($sql);
            $likeSearch = "%$search%";
            $stmt->bind_param(
                "sssssssss",
                $search, $likeSearch, $likeSearch, $likeSearch, $likeSearch,
                $role, $role,
                $status, $status
            );
            $stmt->execute();
            $result = $stmt->get_result();
            $users = $result->fetch_all(MYSQLI_ASSOC);

            foreach ($users as &$u) {
                $u['permissions'] = $u['role_permissions']
                    ? json_decode($u['role_permissions'], true)
                    : [];
                unset($u['role_permissions']);
            }

            // Summary
            $summarySql = "
                SELECT 
                  (SELECT COUNT(*) FROM users) AS total,
                  (SELECT COUNT(*) FROM users WHERE status = 'active') AS active,
                  (SELECT COUNT(*) FROM roles) AS roles,
                  (SELECT COUNT(*) FROM users WHERE status = 'locked') AS locked
            ";
            $summary = $conn->query($summarySql)->fetch_assoc();

            return $this->response->setJSON([
                'users'   => $users,
                'sql'     => $sql,
                'summary' => $summary
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }



}
