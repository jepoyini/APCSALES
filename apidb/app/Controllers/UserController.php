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

class UserController extends ResourceController
{

    public function activitylogs()
    {
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

    // GET all users + summary
    public function list1()
    {
        global $conn;

        // ✅ read JSON or POST
        $postData = json_decode(file_get_contents("php://input"), true);
        $search = $postData['search'] ?? '';
        $role   = $postData['role'] ?? '';
        $status = $postData['status'] ?? '';

        try {
            $sql = "
                SELECT u.*,
                       r.name AS role_name, r.permissions AS role_permissions
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
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
                'users' => $users,
                'sql' => $sql,
                'summary' => $summary
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // GET user details
    public function details()
    {
        global $conn;
        $postData = json_decode(file_get_contents("php://input"), true);

        // fallback for form-data / urlencoded
        if (!$postData) {
            $postData = $_POST;
        }

        $id = $postData['uid'] ?? null;

        if (!$id) {
            return $this->response->setJSON(['status' => 'error','message' => 'Missing user ID']);
        }

        try {
            $sql = "
                SELECT 
                u.id,
                u.firstname, 
                u.lastname, 
                u.middlename, 
                u.role_id, 
                u.email ,
                u.phone ,
                u.address ,
                u.city ,
                u.state ,
                u.zip ,
                u.country ,
                u.status ,
                u.is_admin ,
                u.avatar,
                u.logged_ip,
                u.logged_time,
                u.logged_location,
                r.name AS role_name, 
                r.permissions AS role_permissions
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE u.id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (!$user) {
                return $this->response->setStatusCode(404)->setJSON(['status'=>'error','message'=>'User not found']);
            }

            $user['permissions'] = $user['role_permissions'] ? json_decode($user['role_permissions'], true) : [];
            unset($user['role_permissions']);

            return $this->response->setJSON(['status'=>'success','user'=>$user]);

        } catch (\Throwable $e) {
            return $this->response->setJSON(['status'=>'error','message'=>$e->getMessage()]);
        }
    }

    // POST save user (insert or update)
    public function save()
    {
        global $conn;

        $id        = $_POST['id'] ?? null;
        $username  = $_POST['username'] ?? "";
        $firstname = $_POST['firstname'] ?? "";
        $lastname  = $_POST['lastname'] ?? "";
        $phone     = $_POST['phone'] ?? "";
        $email     = $_POST['email'] ?? "";
        $address   = $_POST['address'] ?? "";
        $city      = $_POST['city'] ?? "";
        $country   = $_POST['country'] ?? "";
        $zip       = $_POST['zip'] ?? "";
        $password  = $_POST['password'] ?? "";
        $role_id   = $_POST['role_id'] ?? null;
        $status    = $_POST['status'] ?? "active";
        $warehouse_id    = $_POST['warehouse_id'] ?? null;

        // ✅ Check unique username
        $checkSql = "SELECT id FROM users WHERE username=? AND id!=?";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        $checkResult = $stmt->get_result();
        if ($checkResult->num_rows > 0) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Username already exists'
            ]);
        }

        // ✅ Handle avatar upload
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $newName = uniqid() . "_" . basename($_FILES['avatar']['name']);
            $targetPath = FCPATH . "uploads/avatars/" . $newName;
            if (!is_dir(FCPATH . "uploads/avatars")) {
                mkdir(FCPATH . "uploads/avatars", 0777, true);
            }
            move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath);
            $avatarPath = "uploads/avatars/" . $newName;
        }

        try {


            if ($id) {
                // ✅ UPDATE
                $sql = "UPDATE users 
                        SET firstname=?,
                            lastname=?,
                            phone=?,
                            email=?,
                            address=?,
                            city=?,
                            country=?,
                            zip=?,
                            role_id=?,
                            status=?,
                            warehouse_id=?,
                            updated_at=NOW()";

                $params = [
                    $firstname,
                    $lastname,
                    $phone,
                    $email,
                    $address,
                    $city,
                    $country,
                    $zip,
                    $role_id,
                    $status,
                    $warehouse_id
                ];
                $types  = "ssssssssisi";  // 8 strings + 1 int + 1 string + 1 int

                if ($avatarPath) {
                    $sql .= ", avatar=?";
                    $params[] = $avatarPath;
                    $types .= "s";
                }

                if (!empty($password)) {
                    $sql .= ", password=?";
                    $params[] = password_hash($password, PASSWORD_BCRYPT);
                    $types .= "s";
                }

                $sql .= " WHERE id=?";
                $params[] = $id;
                $types .= "i";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();

            } else {

                // ✅ INSERT
                $sql = "INSERT INTO users 
                        (username, firstname, lastname, phone, email, address, city, country, zip, password, avatar, role_id, status,warehouse_id) 
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

                $stmt = $conn->prepare($sql);
                $hashedPass = password_hash($password, PASSWORD_BCRYPT);

                $stmt->bind_param(
                    "sssssssssssisi",
                    $username,
                    $firstname,
                    $lastname,
                    $phone,
                    $email,
                    $address,
                    $city,
                    $country,
                    $zip,
                    $hashedPass,
                    $avatarPath,
                    $role_id,
                    $status,
                    $warehouse_id
                );
                $stmt->execute();
            }

            return $this->response->setJSON(['status' => 'success']);

        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    // DELETE user (soft delete)
    public function delete($id = null)
    {
        global $conn;

        $postData = json_decode(file_get_contents("php://input"), true);
        $id = $id ?? ($postData['id'] ?? null);

        if (!$id) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Missing user ID.'
            ]);
        }

        try {
            $sql = "UPDATE users SET status='deleted', updated_at=NOW() WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();

            return $this->response->setJSON(['status' => 'success', 'message' => 'User marked as deleted']);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function changepass()
    {
        global $conn;
        $postData = json_decode(file_get_contents("php://input"), true);
        // fallback for form-data / urlencoded
        if (!$postData) {
            $postData = $_POST;
        }        
        $id          = $postData['id'] ?? null;
        $oldPassword = $postData['oldPassword'] ?? "";
        $newPassword = $postData['newPassword'] ?? "";

        if (!$id || !$oldPassword || !$newPassword) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => "Missing required fields."
            ]);
        }

        try {
            // 1. Get current password
            $sql  = "SELECT password FROM users WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
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

            // 2. Verify old password
            if (!password_verify($oldPassword, $user['password'])) {
                return $this->response->setJSON([
                    "status"  => "error",
                    "message" => "Old password is incorrect."
                ]);
            }

            // 3. Hash new password
            $hashedNew = password_hash($newPassword, PASSWORD_BCRYPT);

            // 4. Update in DB
            $update = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $stmt   = $conn->prepare($update);
            $stmt->bind_param("si", $hashedNew, $id);
            $stmt->execute();

            return $this->response->setJSON([
                "status"  => "success",
                "message" => "Password updated successfully."
            ]);

        } catch (\Throwable $e) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

public function getAnnouncement()
{
    global $conn;

    $postData = json_decode(file_get_contents("php://input"), true);
    if ($postData) {

        // Extract user data from JSON
        $id = $postData['id'];
        $user_id = $postData['uid'];
        $appname = isset($postData['appname']) ? $postData['appname'] : 'clip';

        // Fetch all IDs of announcements for the app
        $sql_all = "SELECT id FROM announcements WHERE appname='$appname' AND active=1";
        $result_all = $conn->query($sql_all);
        $all_announcement_ids = [];

        while ($row = $result_all->fetch_assoc()) {
            $all_announcement_ids[] = $row['id'];
        }

        // Query to find which announcements have already been given to this user
        $sql_given = "SELECT announcement_id FROM user_announcements WHERE user_id='$user_id' AND appname='$appname'";
        $result_given = $conn->query($sql_given);
        $given_ids = [];

        while ($row = $result_given->fetch_assoc()) {
            $given_ids[] = $row['announcement_id'];
        }

        // If all rows have been given, reset and treat the user as a new user
        if (count($given_ids) == count($all_announcement_ids)) {
            // Reset the tracking in the database (optional, only if you want to restart for the user)
            $conn->query("DELETE FROM user_announcements WHERE user_id='$user_id' AND appname='$appname'");
            $given_ids = []; // Clear given rows so the user gets all rows again
        }

        // Select a random announcement that hasn't been given before
        $remaining_ids = array_diff($all_announcement_ids, $given_ids);

        if (empty($remaining_ids)) {
            // If no remaining IDs, reset the list of given IDs in the database
            $remaining_ids = $all_announcement_ids; // Select all rows again
        }

        // Randomly pick one of the remaining IDs
        $random_id = $remaining_ids[array_rand($remaining_ids)];

        // Insert the given announcement into the tracking table
        $stmt = $conn->prepare("INSERT INTO user_announcements (user_id, announcement_id, appname) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $random_id, $appname);
        $stmt->execute();

        // Query for the random row
        $sql = "SELECT * FROM announcements WHERE id=$random_id AND appname='$appname' AND active=1 LIMIT 1";
        //$sql = "SELECT * FROM announcements WHERE id=10 AND appname='$appname' LIMIT 1";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $formattedDate = date('n/j/Y', strtotime($user['posted_at']));

            // Insert the given announcement into the tracking table
            // $stmt = $conn->prepare("INSERT INTO user_announcements (user_id, announcement_id, appname) VALUES (?, ?, ?)");
            // $stmt->bind_param("iis", $user_id, $random_id, $appname);
            // $stmt->execute();

            // Return the announcement data
            $ret_data = [
                "content" => $user['content'],
                "posted" => $formattedDate,
                "previousid" => 0, // Logic for previous ID remains the same
                "showsideimage" => $user['sideimage']
            ];

            return $this->response->setJSON([
                'status' => 'success',
                'data' => $ret_data
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'data' => "Record not found!"
            ]);
        }
    }
}


public function getAnnouncement2()
{
    global $conn; 

    $postData = json_decode(file_get_contents("php://input"), true);
    if ($postData) {

        // Extract user data from JSON
        $id = $postData['id'];
        $user_id = $postData['uid'];
        $appname = isset($postData['appname']) ? $postData['appname'] : 'clip';
        $previous_id = 0; 
        $sql = "SELECT a.* FROM announcements a WHERE appname='$appname' ORDER BY RAND() LIMIT 1;";
        //$sql = "SELECT a.* FROM announcements a WHERE appname='$appname' and a.id = 1 ORDER BY RAND() LIMIT 1;";
        if ($id) {
            $sql = "SELECT a.*  FROM announcements a WHERE appname='$appname' and a.id = $id ;";    
        }

        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) 
        {
            $user = $result->fetch_assoc();
            $latest_id = $user['id'];
            
            $sql_previous = "SELECT id FROM announcements WHERE appname='$appname' and id < $latest_id ORDER BY RAND() DESC LIMIT 1";
            $result_previous = $conn->query($sql_previous);     
            if ($result_previous->num_rows > 0) {
                $previous_announcement = $result_previous->fetch_assoc();
                $previous_id = $previous_announcement['id'];
            }
            
            $formattedDate = date('n/j/Y', strtotime($user['posted_at']));
            $ret_data = [
                          "content"=>$user['content'],
                          "posted"=>$formattedDate,
                          "previousid"=>$previous_id,
                          "showsideimage" => $user['sideimage']
                        ];   

            return $this->response->setJSON([
                'status' => 'success',
                'data' => $ret_data
            ]);

        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'data' => "Record not found!"
            ]);
        }
    }



}

public function getAlluserstats()
{
    $db = \Config\Database::connect();
    $request = json_decode(file_get_contents("php://input"), true);

    try {
        // Get all active users
        $builder = $db->table('users');
        $builder->select('register_ip_location');
        $builder->where('status', 'active');
        $builder->where('register_ip_location IS NOT NULL', null, false); 
        $builder->where('register_ip_location !=', '');                    
        $query = $builder->get();

        $countries = [];

        foreach ($query->getResultArray() as $row) {
            $location = $row['register_ip_location'];

            if ($location) {
                $parts = explode(',', $location);
                $country = trim(end($parts));

                if ($country !== '') {
                    if (!isset($countries[$country])) {
                        $countries[$country] = 1;
                    } else {
                        $countries[$country]++;
                    }
                }
            }
        }

        // Sort by count descending and limit to top 10
        arsort($countries);
        //$countries = array_slice($countries, 0, 20, true);

        $result = [];
        foreach ($countries as $country => $count) {
            $result[] = [
                'country' => $country,
                'count' => $count
            ];
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $result
        ]);
    } catch (\Exception $e) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Failed to retrieve user stats.',
            'error' => $e->getMessage()
        ]);
    }
}


public function getDownlinestats()
{

    $db = \Config\Database::connect();

    $request = json_decode(file_get_contents("php://input"), true);
    $uid = isset($request['uid']) ? intval($request['uid']) : 0;

    if ($uid <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid user ID'
        ]);
        return;
    }

    try {
        // Get direct downlines
        $builder = $db->table('users');
        $builder->select('register_ip_location');
        $builder->where('sponsor_id', $uid);
        $builder->where('status', 'active');
        $query = $builder->get();

        $countries = [];

        foreach ($query->getResultArray() as $row) {
            $location = $row['register_ip_location'];

            if ($location) {
                $parts = explode(',', $location);
                $country = trim(end($parts));

                if ($country !== '') {
                    if (!isset($countries[$country])) {
                        $countries[$country] = 1;
                    } else {
                        $countries[$country]++;
                    }
                }
            }
        }

        $result = [];
        foreach ($countries as $country => $count) {
            $result[] = [
                'country' => $country,
                'count' => $count
            ];
        }
        return $this->response->setJSON([
            'status' => 'success',
            'data' => $result
        ]);

    } catch (\Exception $e) {

        return $this->response->setJSON([
                'status' => 'error',
            'message' => 'Failed to retrieve downline stats.',
            'error' => $e->getMessage()
        ]);


    }
}


public function deleteDeposit()
{
   global $conn;

    $postData = json_decode(file_get_contents("php://input"), true);
    // Extract user data from JSON
    $id = $postData['id']; 
    $uid = $postData['uid'];


    $stmt = $conn->prepare("UPDATE withdraws SET status = 'deleted' WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
        // Execute the update statement
        if ($stmt->execute()) {

            return $this->response->setJSON([
                'success' => true, 'message' => 'Row Deleted successfully'
            ]);

        } else {

            return $this->response->setJSON([
                'success' => false, 'message' => 'Execution failed'
            ]);

        }
    } catch (Exception $e) {
        // Handle any exceptions
            return $this->response->setJSON([
               'success' => false, 'message' => 'Error: ' . $e->getMessage()
            ]);        
    }

}

public function getVideos()
{

    global $conn;

    $postData = json_decode(file_get_contents("php://input"), true);
    $user_id = $postData['uid'] ?? null;
    $category = $postData['category'] ?? "";

    // Early validation
    if (!$user_id) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Missing user ID.'
        ]);
    }

    try {
        if ($category === "") {
            $query = "SELECT * FROM videos WHERE visible = 1 ORDER BY id DESC";
            $stmt = $conn->prepare($query);
        } else {
            $query = "SELECT * FROM videos WHERE visible = 1 AND category = ? ORDER BY sortorder DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $category);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        // Add activity log
        add_activity_log($user_id, 'videos', '');

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $rows
        ]);

    } catch (Exception $e) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'An error occurred: ' . $e->getMessage()
        ]);
    }

}

public function RunPlaceUser($userId)
{

    global $conn;
    $sponsor_id = null;
    $result = $conn->query("SELECT sponsor_id FROM users WHERE id = $userId;");
    $row = $result ? $result->fetch_assoc() : null;
    if ($row) 
        $sponsor_id =$row['sponsor_id'];

    if ($sponsor_id) {
        $ret = addToSharingMatrix($userId, $sponsor_id);
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'User has been placed successfully',
            'data' => $ret
        ]);
    } else {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Failed to place user'
        ]);
    }

}


public function transferFund()
{
    global $conn;

    // Extract user data from JSON
    $user_id = $this->request->getVar('uid');
    $transfer_to = $this->request->getVar('recipient_email'); 
    $amount = $this->request->getVar('amount');
    $transfer_fee = 0 ; // $postData['transfer_fee'];
    $message = $this->request->getVar('message'); 
    $transfer_to_name = '';
    // $csrf_token = $postData['csrf_token'];
    // $twoFA_code = $postData['twoFA_code'];
    // $g2aenabled = $postData['g2aenabled'];
    
    $ret = HasPendingWithdrawals($user_id);

    if ($ret) {
        return $this->response->setJSON([
            'status' => 'error', 'message' => 'Access Denied! Ongoing withdrawal found.'
        ]);
    }   

    //Check 2fa token if valid
    // $bypass_otp= false; 
    // if ($user_id==173 && $twoFA_code=='888888' )
    //     $bypass_otp= true; 
    // if ($user_id==2 && $twoFA_code=='888888' )
    //     $bypass_otp= true; 
    // if ($user_id==1 && $twoFA_code=='888888' )
    //     $bypass_otp= true;  
    // if (!$bypass_otp) { 
    
    //     if ($g2aenabled  === 'true') {
    
    //         $ret = verify_google2fa($user_id,$twoFA_code);
    //         if (!$ret['success'])
    //         {
    //             echo json_encode(["status" => "error", "message" => "Access Denied! OTP Code is incorrect"]);
    //             die; 
    //         }
    //     } else {
    //         $sql = "Select count(0) as count from users where `code_2fa_transfer`='$twoFA_code' and status = 'active'";
    //         $row = $conn->query($sql);
    //         $row = $row->fetch_assoc();
    //         if ($row['count'] == 0 ) {
    //             echo json_encode(["status" => "error", "message" => "Access Denied! OTP Code is incorrect"]);
    //             die; 
    //         }
    //     }

    // }
    
    //Check target if exist
    $sql = "Select count(0) as count, id, CONCAT(firstname,' ',lastname,' (#',id,')') as fullname from users where  (`id` = '$transfer_to' or `username`= '$transfer_to' or `email`= '$transfer_to') and status = 'active'";
    $row = $conn->query($sql);
    $row = $row->fetch_assoc();
    if ($row['count'] == 0)
    {
        return $this->response->setJSON([
                "status" => "error", "message" => "Target User not exist!"
        ]);
    }
    $transfer_to = $row['id'];
    // $transfer_to_name = $row['fullname'];


    // $sql = "Select CONCAT(firstname,' ',lastname,' (#',u.id,')') as fullname from users where  (`id` = '$user_id') and status = 'active'";
    // $row = $conn->query($sql);
    // $row = $row->fetch_assoc();
    // $transfer_from_name = $row['fullname'];

    //Check csrf_token if valid
    // $ip = getUserIP(); 
    // if (!$bypass_otp) {
    //     if ($g2aenabled) {
    //         $sql = "Select count(0) as count, id from users where  `csrf_token` = '$csrf_token' and `logged_ip`= '$ip' and status = 'active' and banned <> 1";
    //     } else {
    //         $sql = "Select count(0) as count, id from users where  `csrf_token` = '$csrf_token' and `logged_ip`= '$ip' and `code_2fa_transfer`='$twoFA_code' and status = 'active' and banned <> 1";
    //     }
    // }
    // else
    // {
    //     $sql = "Select count(0) as count, id from users where  `csrf_token` = '$csrf_token' and `logged_ip`= '$ip' and status = 'active' and banned <> 1";  
    // }
    // $row = $conn->query($sql);
    // $row = $row->fetch_assoc();
    // if ($row['count'] == 0 ) {
    //     return $this->response->setJSON([
    //         "status" => "error", "message" => "Access Denied! Failed Validation"
    //     ]);
    // }

    // if ($user_id != $row['id']) {
    //     return $this->response->setJSON([
    //         "status" => "error", "message" => "Access Denied! Failed Validation"
    //     ]);
    // }

    // echo json_encode(["status" => "error", "message" => $user_id]);die; 
    $ret = GetWithdrawableBalance($user_id);
    if ($ret['status']=='success') {
        $availabletowithdraw = $ret['availabletowithdraw']; 
        if ($availabletowithdraw < $amount) {

            return $this->response->setJSON([
                'status' => 'error', 'message' => 'Insufficient Wallet Balance!'
            ]);

        }
    }

    // remove amount to source 
    $amount2 = $amount * -1; 
    $sql = "INSERT INTO transactions (`user_id`, `to_id`, `amount` , `type` ,`date_created`,`note`,`status` ) VALUES ('$user_id','$transfer_to',".$amount2 .",'coin_transfer', NOW(),'$message','completed')";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    // Get the last inserted ID
    $last_id = $conn->insert_id;
    UpdateRunningTotals($user_id); 

    if ($transfer_fee > 0)
    {

        // remove transfer fees from source user
        $transfer_fee2 = $transfer_fee * -1; 
        $root_id = 1; 
        $sql = "INSERT INTO transactions (`user_id`, `to_id`, `amount` , `type` ,`date_created`,`note`,`ref_id`,`status` ) VALUES (?,?,".$transfer_fee2 .",'fees', NOW(),?,?,'completed')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisi", $user_id,$root_id,$message,$last_id);
        $stmt->execute();
        UpdateRunningTotals($user_id);

        // transfer fees to root
        $transfer_fee2 = $transfer_fee * -1; 
        $sql = "INSERT INTO transactions (`user_id`, `amount` , `type` ,`date_created`,`note`,`ref_id`,`from_id`,`status` ) VALUES (?,".$transfer_fee.",'fees_received', NOW(),?,?,?,'completed')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isii", $root_id,$message,$last_id,$user_id);
        $stmt->execute();
        UpdateRunningTotals($root_id);

    }
 
    // transfer to target user
    $sql = "INSERT INTO ewallets (`user_id`, `amount` , `type` ,`date_created`,`note`,`ref_id`,from_id ,`status` ) VALUES (?,".$amount.",'coin_received', NOW(),?,?,?,'completed')";
  
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $transfer_to,$message,$last_id,$user_id);
    $stmt->execute();
    UpdateRunningTotals_Ewallet($transfer_to);

    // Add activity Log
    add_activity_log($user_id,'fundtransfer',"Transfer $amount to $transfer_to");
    
    return $this->response->setJSON([
        'status' => 'success', 'message' => 'Done!'
    ]);

}

public function updateWithdraw()
{
   global $conn;

    // Extract user data from JSON
    $id = $this->request->getVar('id');
    $usd =  $this->request->getVar('USD');
    $transactionID =  $this->request->getVar('txtID');
    $receiverAddress = $this->request->getVar('receiverAdd');
    $notes = $this->request->getVar('notes');
    $uid = $this->request->getVar('uid');

    $filter_usd = ""; 
    if ($usd)
        $filter_usd = " ,amount='$usd' "; 

    if ($transactionID)
        $filter_txtID = " ,transactionID='$transactionID' "; 
    else
        $filter_txtID = " ,transactionID='' "; 
    
    if ($receiverAddress)
        $filter_receiverAddress = " ,receiverWalletAddress='$receiverAddress' "; 
    else 
        $filter_receiverAddress = " ,receiverWalletAddress=NULL "; 

    if ($notes)
        $notes = " , notes='$notes' "; 
    else
        $notes = " , notes='' "; 

    $sql = "UPDATE withdraws SET id = $id $filter_usd $filter_txtID $filter_receiverAddress $notes WHERE id = $id ";
    $stmt = $conn->prepare($sql);

    try {
        // Execute the update statement
        if ($stmt->execute()) {

            // Add activity Log
            //add_activity_log($user_id,'editdeposit',$sql);

           //Add admin log
            $data = ['amount'=> $usd,
                     'transactionID'=> $transactionID,
                     'receiverAddress' => $receiverAddress,
                     'id' => $id
            ];
            $data = json_encode($data);
            add_admin_log($uid,"Edit Withdrawal",$data);
            //End admin log

            return $this->response->setJSON([
                'success' => true, 'message' => 'Deposit updated successfully'
            ]);

        } else {

            return $this->response->setJSON([
                'success' => false, 'message' => 'Execution failed'
            ]);

        }
    } catch (Exception $e) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Error: ' . $e->getMessage()
            ]);
    }

}


public function updateWithdrawstatus()
{
   global $conn;
    // Extract user data from JSON
    $id = $this->request->getVar('id');
    $status =$this->request->getVar('status');
    $transactionID = $this->request->getVar('txtID'); 
    $receiverAddress = $this->request->getVar('receiverAdd'); 
    $uid = $this->request->getVar('uid');

    $filter_txtID = ""; 
    if ($transactionID)
        $filter_txtID = " ,transactionID='$transactionID' "; 
    $filter_receiverAddress = ""; 
    if ($receiverAddress)
        $filter_receiverAddress = " ,receiverWalletAddress='$receiverAddress' "; 

    $row = $conn->query("Select * from withdraws where `id` = $id;");
    $row = $row->fetch_assoc();
    $current_status =$row['status'];
    $user_id =$row['user_id'];
    $amount =$row['amount'];
    $coin_type =$row['coin_type'];

    $stmt = $conn->prepare("UPDATE withdraws SET status = ? $filter_txtID $filter_receiverAddress WHERE id = ? ");
    $stmt->bind_param("si", $status, $id);

    try {
        // Execute the update statement
        if ($stmt->execute()) {

            if ($status=='success' && $current_status <> 'success')
            {

                Withdraw_Complete($user_id, $amount, $id,$transactionID,$coin_type,false);

                // Add activity Log
                add_activity_log($user_id,'withdrawcompleted',"Withdrawed $amount coins,txtID=$transactionID");
            }

            add_admin_log($uid,'withdrawchangestatus',"$amount coins,txtID=$transactionID,receiver=$receiverAddress,id=$id,status=$status");

            return $this->response->setJSON([
                'success' => true, 'message' => 'Withdrawal updated successfully'
            ]);

        } else {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Execution failed'
            ]);
        }
    } catch (Exception $e) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Error: ' . $e->getMessage()
            ]);

    }

}


public function cancelWithdraw() 
{
    global $conn;     
    
    $user_id = intval($this->request->getVar('uid') ?? 0);
    $id = intval($this->request->getVar('id') ?? 0);    

    // Change status to success
    $sql = "UPDATE withdraws set status = 'cancelled' where id = $id";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    return $this->response->setJSON([
        "status" => "success", "message" => "Withdraw Cancelled!"          
    ]); 

}
    

public function requestWithdraw()
{

    global $conn; 

    $maxAmount = 250;
    
    $user_id = intval($this->request->getVar('uid') ?? 0);
    $amount = intval($this->request->getVar('amount') ?? 0);
    $coin_type = trim($this->request->getVar('currency') ?? "");  
    $destination = trim($this->request->getVar('address') ?? "");  

    // $twoFA_code = $postData['twoFA_code'];
    // $g2aenabled = $postData['g2aenabled'];



    //Check 2fa token if valid
    // $bypass_otp= false; 
    // if ($user_id==173 && $twoFA_code=='888888' )
    //     $bypass_otp= true; 
    // if ($user_id==2 && $twoFA_code=='888888' )
    //     $bypass_otp= true; 
    // if ($user_id==1 && $twoFA_code=='888888' )
    //     $bypass_otp= true; 
    // if (!$bypass_otp) {

    //     if ($g2aenabled  === 'true') {
    //         $ret = verify_google2fa($user_id,$twoFA_code);
    //         if (!$ret['success'])
    //         {
    //             echo json_encode(["status" => "error", "message" => "Access Denied! OTP Code is incorrect"]);
    //             die; 
    //         }
    //     } else {        
    //         $sql = "Select count(0) as count from users where `code_2fa_transfer`='$twoFA_code' and status = 'active'";
    //         $row = $conn->query($sql);
    //         $row = $row->fetch_assoc();
    //         if ($row['count'] == 0 ) {
    //             echo json_encode(["status" => "error", "message" => "Access Denied! OTP Code is incorrect"]);
    //             die; 
    //         }
    //     }
    // }
    
    //Check target if exist
    $row = $conn->query("Select count(0) as count, id from users where  (`id` = '$user_id') and status = 'active'");
    $row = $row->fetch_assoc();
    if ($row['count'] == 0)
    {
        return $this->response->setJSON([
            "status" => "error", "message" => "Target User not exist!"          
        ]); 
        exit; 
    }

    //Check csrf_token if valid
    // $ip = getUserIP(); 
    // if (!$bypass_otp)  {
    //     if ($g2aenabled) {
    //         $sql = "Select count(0) as count, id from users where  `csrf_token` = '$csrf_token' and `logged_ip`= '$ip' and status = 'active' and banned <> 1";
    //     } else {
    //         $sql = "Select count(0) as count, id from users where  `csrf_token` = '$csrf_token' and `logged_ip`= '$ip' and `code_2fa_transfer`='$twoFA_code' and status = 'active' and banned <> 1";
    //     }       
    // } else {
    //     $sql = "Select count(0) as count, id from users where  `csrf_token` = '$csrf_token' and `logged_ip`= '$ip' and status = 'active' and banned <> 1";
    // }
    
    // $row = $conn->query($sql);
    // $row = $row->fetch_assoc();
    // if ($row['count'] == 0 ) {
    //     echo json_encode(["status" => "error", "message" => "Access Denied! Failed Validation"]);
    //     die; 
    // }

    // if ($user_id != $row['id']) {
    //     echo json_encode(["status" => "error", "message" => "User Access Denied! Failed Validation"]);
    //     die; 
    // }
    
    // Uncomment if no withdrawal limit. 
    if ($amount > $maxAmount ) {
        return $this->response->setJSON([
            'status' => "error", 'message' =>  "The entered amount exceeds the available amount to withdraw."
        ]);         
    }

    //Daily limit   
    // if (  $user_id <> 5  && $user_id <> 42 ) {
    //  $daily = 0; 
    //  $sql = "SELECT SUM(amount) AS total FROM withdraws withdraws WHERE user_id = $user_id AND status = 'success' AND DATE(date_created) = CURDATE();";
    //  $stmt = $conn->query($sql);
    //  $row = $stmt->fetch_assoc();
    //  if ($row['total'] + $amount > $maxAmount) {
    //      return array('status' => "error", 'message' =>  "The  withdrawal exceeds the daily available amount to withdraw."); 
    //  }
    // }
    
    // Check if member has deposit.
    $sql = "Select count(0) as count from transactions where user_id = $user_id and type = 'deposit' and status = 'completed'";
    $row = $conn->query($sql);
    $row = $row->fetch_assoc();
    if ($row['count'] == 0 ) {
        return $this->response->setJSON([
            "status" => "error", "message" => "No deposit found. Please deposit first before you can make a withdrawal"
        ]);    
    }

    // Check if member has purchase.
    // $sql = "Select count(0) as count from transactions where user_id = $user_id and type = 'payment' and status = 'completed'";
    // $row = $conn->query($sql);
    // $row = $row->fetch_assoc();
    // if ($row['count'] == 0 ) {
    //  echo json_encode(["status" => "error", "message" => "No purchase found. Please purchase any package first before you can make a withdrawal"]);
    //  die;
    // }

    // Check if cointype is same use for deposit.
    // $sql = "Select count(0) as count from deposits where user_id = $user_id and coin_type = '$coin_type'";
    // $row = $conn->query($sql);
    // $row = $row->fetch_assoc();
    // if ($row['count'] == 0 ) {
    //     echo json_encode(["status" => "error", "message" => "The coin type used from your deposit should be the same as your withdrawal."]);
    //     die;
    // }

    $ret=Process_Withdrawal($user_id,$amount, $destination, $coin_type);

    return $this->response->setJSON($ret);  

}

public function getWithdrawals()
{
        global $conn; 
        $user_id = intval($this->request->getVar('uid') ?? 0);
        $page = intval($this->request->getVar('page') ?? 1);
        $limit = intval($this->request->getVar('limit') ?? 10);
        $status = trim($this->request->getVar('status') ?? "");        
        $offset = ($page - 1) * $limit;
        
        $query = "Select * from withdraws where (status <> 'deleted' and status <> '')  and user_id = ?  order by date_created desc";
        if ($status <> "")
        {
            $query = "Select * from withdraws where (status <> 'deleted' and status <> '') and user_id = ? and status = '$status' order by date_created desc"; 
        }

        //$query = "Select * from deposits where user_id = ?  order by date_created desc, id desc limit ? OFFSET ?";
        $stmt = $conn->prepare($query);
        //$stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        // Count total rows
        $url = "SELECT COUNT(*) as count from withdraws WHERE status <> 'deleted' and  user_id=$user_id";
        if ($status <> "")
        {
            $url = "SELECT COUNT(*) as count from withdraws WHERE status <> 'deleted' and user_id=$user_id and status = '$status'";
        }
        $totalResult = $conn->query($url);
        $totalRow = $totalResult->fetch_assoc();
        $total = $totalRow['count'];

        // Calculate total pages
        $totalPages = ceil($total / $limit);

        return $this->response->setJSON([
            'rows' => $rows,
            'totalPages' => $totalPages,
            'totalrows' => $totalRow            
        ]);       
}


public function RunMatrixPlacement()
{
    UpdateMatrixPlacement();
    echo "RunMatrixPlacement has been executed!";
    exit; 
}

public function ServerTime()
{
    echo 'Server Timezone: ' . date_default_timezone_get();
    echo '<br>Current Server Time: ' . date('Y-m-d H:i:s');
}

public function checkDepositstatus()
{
    global $conn;
    // Fetch all pending deposits
    echo "<span><strong>";
    echo "Fetch all pending deposits";
    echo "</strong></span>";
    echo "<br />";
    $query = "SELECT id, user_id, transactionID, senderWalletAddress, amount,amount_flr, status, coin_type FROM deposits WHERE status = 'pending' and not transactionID is null  and not senderWalletAddress is null ORDER BY id";

    $result = $conn->query($query);
    if ($result->num_rows > 0) {

  
        while ($deposit = $result->fetch_assoc()) 
        {

            $transactionID = $deposit['transactionID'];
            $id = $deposit['id'];
            $amount_flr = $deposit['amount_flr'];
            $senderWalletAddress = $deposit['senderWalletAddress'];
            $user_id = $deposit['user_id'];
            $amount = $deposit['amount'];
            $coin_type = $deposit['coin_type'];
            //Complete the deposit
 
            // if ($transactionID)
            //     ld("Deposit_Complete($user_id, $amount, $id,$transactionID,$senderWalletAddress,$amount_flr,$coin_type);");

            $r = Deposit_Complete($user_id, $amount, $id,$transactionID,$senderWalletAddress,$amount_flr,$coin_type);

            if ($r['success']) {
                // Add activity Log
                add_activity_log($user_id,'depositcompleted_auto',"Auto-Complete $amount coins,txtID=$transactionID");
                echo "Deposit ID $id marked as success.";
            } else {
                echo "Deposit ID $id not set to success: ".$r['message'];
            }
            
             echo "<br />";

        }

    } else {
        echo "No pending deposits found.<br />";
    }


    // Fetch all pending withdrawals
    // echo "\n\n";
    // echo "Fetch all pending withdrawals";
    // echo "\n";
    // $query = "SELECT id, user_id, transactionID, receiverAddress, amount,amount_flr, status,coin_type FROM withdraws WHERE status = 'pending' ORDER BY id";

    // $result = $conn->query($query);
    // if ($result->num_rows > 0) {
    //     while ($withdraw = $result->fetch_assoc()) 
    //     {
    //         $transactionID = $withdraw['transactionID'];
    //         $id = $withdraw['id'];
    //         $amount_flr = $withdraw['amount_flr'];
    //         $receiverAddress = $withdraw['receiverAddress'];
    //         $user_id = $withdraw['user_id'];
    //         $amount = $withdraw['amount'];
    //         $coin_type = $withdraw['coin_type'];
    //         //Complete the deposit

    //         $r = Withdraw_Complete($user_id, $amount, $id, $transactionID,$coin_type);   
    //         if ($r['success']) {
    //             // Add activity Log
    //             add_activity_log($user_id,'withdrawcompleted_auto',"Auto-Complete $amount FLR Coins,txtID=$transactionID");
    //             echo "Withdrawal ID $id marked as success.\n";
    //         } else {
    //             echo "Withdrawal ID $id not set to success: ".$r['message'];
    //         }
    //         echo "\n";
    //     }

    // } else {
    //     echo "No pending withdraws found.\n";
    // }

}

public function updateDepositstatus()
{
   global $conn;
    // Extract user data from JSON
    $id = $this->request->getVar('id');
    $status =$this->request->getVar('status');
    $transactionID = $this->request->getVar('txtID'); 
    $senderAddress = $this->request->getVar('senderAdd'); 
    $uid = $this->request->getVar('uid');

    $filter_txtID = ""; 
    if ($transactionID)
        $filter_txtID = " ,transactionID='$transactionID' "; 
    $filter_senderAddress = ""; 
    if ($senderAddress)
        $filter_senderAddress = " ,senderWalletAddress='$senderAddress' "; 

    $row = $conn->query("Select * from deposits where `id` = $id;");
    $row = $row->fetch_assoc();
    $current_status =$row['status'];
    $user_id =$row['user_id'];
    $amount =$row['amount'];
    $amount_flr =$row['amount_flr'];
    $coin_type =$row['coin_type'];

    $stmt = $conn->prepare("UPDATE deposits SET status = ? $filter_txtID $filter_senderAddress WHERE id = ? ");
    $stmt->bind_param("si", $status, $id);

    try {
        // Execute the update statement
        if ($stmt->execute()) {

            if ($status=='success' && $current_status <> 'success')
            {
                // Deposit_Complete($user_id, $amount, $id=0, $hash='',$walletaddress='',$amount_flr=0,$coin_type='flr',$hashcheck = true)
                Deposit_Complete($user_id, $amount, $id,$transactionID,$senderAddress,$amount_flr,$coin_type,false);

                // Add activity Log
                add_activity_log($user_id,'depositcompleted',"Transfer $amount coins,txtID=$transactionID");
            }

            return $this->response->setJSON([
                'success' => true, 'message' => 'Deposit updated successfully'
            ]);

        } else {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Execution failed'
            ]);
        }
    } catch (Exception $e) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Error: ' . $e->getMessage()
            ]);

    }

}

public function updateDeposit()
{
   global $conn;

    // Extract user data from JSON
    $id = $this->request->getVar('id');
    $usd =  $this->request->getVar('USD');
    $flr = $this->request->getVar('FLR');
    $transactionID =  $this->request->getVar('txtID');
    $senderAddress = $this->request->getVar('senderAdd');
    $notes = $this->request->getVar('notes');
    $uid =$this->request->getVar('uid');

    $filter_usd = ""; 
    if ($usd)
        $filter_usd = " ,amount='$usd' "; 
    $filter_flr = ""; 
    if ($flr)
        $filter_flr = " ,amount_flr='$flr' "; 

    if ($transactionID)
        $filter_txtID = " ,transactionID='$transactionID' "; 
    else
        $filter_txtID = " ,transactionID='' "; 
    
    if ($senderAddress)
        $filter_senderAddress = " ,senderWalletAddress='$senderAddress' "; 
    else 
        $filter_senderAddress = " ,senderWalletAddress=NULL "; 

    if ($notes)
        $notes = " , notes='$notes' "; 
    else
        $notes = " , notes='' "; 

    $sql = "UPDATE deposits SET id = $id $filter_usd $filter_flr $filter_txtID $filter_senderAddress $notes WHERE id = $id ";
    $stmt = $conn->prepare($sql);

    try {
        // Execute the update statement
        if ($stmt->execute()) {

            // Add activity Log
            //add_activity_log($user_id,'editdeposit',$sql);

           //Add admin log
            $data = ['usd'=> $usd,
                     'flr'=> $flr,
                     'transactionID'=> $transactionID,
                     'senderAddress' => $senderAddress,
                     'id' => $id
            ];
            $data = json_encode($data);
            add_admin_log($uid,"Edit Deposit",$data);
            //End admin log

            return $this->response->setJSON([
                'success' => true, 'message' => 'Deposit updated successfully'
            ]);

        } else {

            return $this->response->setJSON([
                'success' => false, 'message' => 'Execution failed'
            ]);

        }
    } catch (Exception $e) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Error: ' . $e->getMessage()
            ]);
    }

}

public function getallWithdrawals()
{
    global $conn;

    $page = intval($this->request->getVar('page') ?? 1); 
    $limit = intval($this->request->getVar('limit') ?? 10); 
    $status =  $this->request->getVar('status');
    $userid = intval($this->request->getVar('userid') ?? 0);
    $address = $this->request->getVar('address');
    $hash = $this->request->getVar('hash');
    $uid =$this->request->getVar('uid');
    $offset = ($page - 1) * $limit;
    $filter_userid="";
    if ($userid) {
        $filter_userid = " AND d.user_id=$userid ";
    }
    $filter_address="";
    if ($address) {
        $filter_address = " AND d.senderWalletAddress like '%$address%' ";
    }
    $filter_hash="";
    if ($hash) {
        $filter_hash = " AND d.transactionID like '%$hash%' ";
    }
    $query = " Select d.*,CONCAT(u.firstname,' ',u.lastname,' (#',u.id,')') as fullname from withdraws d
                 left join users u on d.user_id=u.id 
                 where not d.status is null and  d.status <> 'deleted' and not d.user_id is null  $filter_userid $filter_address $filter_hash 
                 order by d.date_created desc";

    if ($status <> "")
    {
        $query = " Select d.*,CONCAT(u.firstname,' ',u.lastname,' (#',u.id,')') as fullname from withdraws d
                 left join users u on d.user_id=u.id 
                 where not d.status is null and  d.status <> 'deleted' and  not d.user_id is null   $filter_userid $filter_address $filter_hash and d.status = '$status' 
                 order by d.date_created desc";
    }
  //  ld($query);
    //$query = "Select * from deposits where user_id = ?  order by date_created desc, id desc limit ? OFFSET ?";
    $stmt = $conn->prepare($query);
    //$stmt->bind_param("iii", $user_id, $limit, $offset);
    //$stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    // Count total rows
    $url = "SELECT COUNT(*) as count from withdraws d WHERE not d.user_id is null and d.status <> '' and d.status <> 'deleted' $filter_userid ";
    if ($status <> "")
    {
        $url = "SELECT COUNT(*) as count from withdraws d WHERE not d.user_id is null  and d.status <> '' and d.status <> 'deleted' and status = '$status' $filter_userid ";
    }
    $totalResult = $conn->query($url);
    $totalRow = $totalResult->fetch_assoc();
    $total = $totalRow['count'];

    // Calculate total pages
    $totalPages = ceil($total / $limit);

    return $this->response->setJSON([
        'rows' => $rows,
        'totalPages' => $totalPages,
        'totalrows' => $totalRow
    ]);

}
public function getallDeposits()
{
    global $conn;

    $page = intval($this->request->getVar('page') ?? 1); 
    $limit = intval($this->request->getVar('limit') ?? 10); 
    $status =  $this->request->getVar('status');
    $userid = intval($this->request->getVar('userid') ?? 0);
    $address = $this->request->getVar('address');
    $hash = $this->request->getVar('hash');
    $uid =$this->request->getVar('uid');
    $offset = ($page - 1) * $limit;
    $filter_userid="";
    if ($userid) {
        $filter_userid = " AND d.user_id=$userid ";
    }
    $filter_address="";
    if ($address) {
        $filter_address = " AND d.senderWalletAddress like '%$address%' ";
    }
    $filter_hash="";
    if ($hash) {
        $filter_hash = " AND d.transactionID like '%$hash%' ";
    }
    $query = " Select d.*,CONCAT(u.firstname,' ',u.lastname,' (#',u.id,')') as fullname from deposits d
                 left join users u on d.user_id=u.id 
                 where not d.status is null and not d.user_id is null  $filter_userid $filter_address $filter_hash 
                 order by d.date_created desc";

    if ($status <> "")
    {
        $query = " Select d.*,CONCAT(u.firstname,' ',u.lastname,' (#',u.id,')') as fullname from deposits d
                 left join users u on d.user_id=u.id 
                 where not d.status is null and  not d.user_id is null   $filter_userid $filter_address $filter_hash and d.status = '$status' 
                 order by d.date_created desc";
    }
    //ld($query);
    //$query = "Select * from deposits where user_id = ?  order by date_created desc, id desc limit ? OFFSET ?";
    $stmt = $conn->prepare($query);
    //$stmt->bind_param("iii", $user_id, $limit, $offset);
    //$stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    // Count total rows
    $url = "SELECT COUNT(*) as count from deposits d WHERE not d.user_id is null and d.status <> '' and d.status <> 'deleted' $filter_userid ";
    if ($status <> "")
    {
        $url = "SELECT COUNT(*) as count from deposits d WHERE not d.user_id is null  and d.status <> '' and d.status <> 'deleted' and status = '$status' $filter_userid ";
    }
    $totalResult = $conn->query($url);
    $totalRow = $totalResult->fetch_assoc();
    $total = $totalRow['count'];

    // Calculate total pages
    $totalPages = ceil($total / $limit);

    return $this->response->setJSON([
        'rows' => $rows,
        'totalPages' => $totalPages,
        'totalrows' => $totalRow
    ]);

}

public function updateDeposithash()
{
    global $conn;

    // Extract data from request
    $id = intval($this->request->getVar('id') ?? 0);
    $hash = trim($this->request->getVar('hash') ?? "");
    $sender = trim($this->request->getVar('sender') ?? "");

    // Input validation
    if ($id <= 0 || empty($hash) || empty($sender)) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Invalid input'
        ]);
    }

    // Update the deposit record
    $stmt = $conn->prepare("UPDATE deposits SET transactionid = ?, senderwalletaddress = ? WHERE id = ?");
    $stmt->bind_param("ssi", $hash, $sender, $id);

    try {
        if ($stmt->execute()) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Deposit details updated successfully'
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to update deposit'
            ]);
        }
    } catch (Exception $e) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}


    public function updateDepositstatus2()
    {
        global $conn;
        // Extract user data from JSON
        $uid =intval($this->request->getVar('uid') ?? 0);
        $id =intval($this->request->getVar('id') ?? 0);
        $status = trim($this->request->getVar('status') ?? "");

        $row = $conn->query("Select user_id,amount,status from deposits where `id` = $id;");
        $row = $row->fetch_assoc();
        $current_status =$row['status'];
        $user_id =$row['user_id'];
        $amount =$row['amount'];

        $stmt = $conn->prepare("UPDATE deposits SET status = ? WHERE id = ? ");
        $stmt->bind_param("si", $status, $id);

        try {
            // Execute the update statement
            if ($stmt->execute()) {
                return $this->response->setJSON([
                    'success' => true, 'message' => 'Deposit updated successfully'         
                ]); 
            } else {
                return $this->response->setJSON([
                    'success' => false, 'message' => 'Execution failed'
                ]); 
            }
        } catch (Exception $e) {
                return $this->response->setJSON([
                    'success' => false,  'message' => 'Error: ' . $e->getMessage()
                ]); 
        }
    }

    public function getDeposits()
    {
        global $conn; 
        $user_id = intval($this->request->getVar('uid') ?? 0);
        $page = intval($this->request->getVar('page') ?? 1);
        $limit = intval($this->request->getVar('limit') ?? 10);
        $status = trim($this->request->getVar('status') ?? "");        
        $offset = ($page - 1) * $limit;
        
        $query = "Select * from deposits where (status <> 'deleted' and status <> '')  and user_id = ?  order by date_created desc";
        if ($status <> "")
        {
            $query = "Select * from deposits where (status <> 'deleted' and status <> '') and user_id = ? and status = '$status' order by date_created desc"; 
        }

        //$query = "Select * from deposits where user_id = ?  order by date_created desc, id desc limit ? OFFSET ?";
        $stmt = $conn->prepare($query);
        //$stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        // Count total rows
        $url = "SELECT COUNT(*) as count from deposits WHERE status <> 'deleted' and  user_id=$user_id";
        if ($status <> "")
        {
            $url = "SELECT COUNT(*) as count from deposits WHERE status <> 'deleted' and user_id=$user_id and status = '$status'";
        }
        $totalResult = $conn->query($url);
        $totalRow = $totalResult->fetch_assoc();
        $total = $totalRow['count'];

        // Calculate total pages
        $totalPages = ceil($total / $limit);

        return $this->response->setJSON([
            'rows' => $rows,
            'totalPages' => $totalPages,
            'totalrows' => $totalRow            
        ]);              

    }

    public function Deposit() {

        global $conn; 

        $user_id = $this->request->getVar('uid');
        $amount = $this->request->getVar('amount'); 
        $amount_flr = $this->request->getVar('amount_flr');
        $wallet_address = $this->request->getVar('address');
        $coin_type = $this->request->getVar('coin_type'); 
        $is_manual = $this->request->getVar('is_manual') ?? 0;

        $ret = Process_Deposit($user_id,$amount,$amount_flr,$wallet_address,$coin_type,$is_manual);

        // Add activity Log
        add_activity_log($user_id,'createdeposit',"Deposited $amount USD = $amount_flr coins ");

        return $this->response->setJSON($ret); 

    }

    public function disableDeposit()
    {
        global $conn; 
        // Extract user data from JSON
        $uid = $this->request->getVar('uid');
        $id =  $this->request->getVar('id');
        
       $stmt = $conn->prepare("UPDATE deposits SET status = null WHERE id = ?");
       $stmt->bind_param("i", $id);

        try {
            // Execute the update statement
            if ($stmt->execute()) {
                
               //Add  log
                $data = [];
                $data = json_encode($data);
                add_activity_log($id,"Disable Deposit",$data);
                //End log

                return $this->response->setJSON([
                    'success' => true, 'message' => 'Deposit disabled successfully'
                ]);

            } else {

                return $this->response->setJSON([
                    'success' => false, 'message' => 'Execution failed'
                ]);

            }
        } catch (Exception $e) {

            return $this->response->setJSON([
                'success' => false, 'message' => 'Error: ' . $e->getMessage()
            ]);
        }

    }

    public function checkDeposit()
    {
        global $conn;

        $uid = $this->request->getVar('uid');
        
        if (isset($uid)) {
            $depositid =0; 
            $sql = "SELECT COUNT(*) AS pending_count, max(id) as id FROM deposits WHERE user_id = ? and status='pending'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $uid); 
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $pending_count = $row['pending_count'];
                $depositid = $row['id'];
                if (is_null($pending_count))
                    $pending_count = 0; 
            }

            $conn->close();    
          
            return $this->response->setJSON(["pendings" => $pending_count,"depositid" => $depositid]);              
     
        } else {
            return $this->response->setJSON(["pendings" => 0]);
        }

    }


    private function buildTribeTree($user_id, $db)
    {
        // Get user info
        $user = $db->table('users')
            ->select('id, firstname, lastname, username')
            ->where('id', $user_id)
            ->get()->getRowArray();

        $node = [
            'user_id' => $user['id'],
            'name' => $user['firstname'] . ' ' . $user['lastname'] . ' ('. $user['username'] . ")",
            'children' => []
        ];

        // Get up to 5 children from matrix
        $children = $db->table('sharing_matrix sm')
            ->select('u.id, u.firstname, u.lastname, u.username')
            ->join('users u', 'u.id = sm.user_id')
            ->where('sm.upline_user_id', $user_id)
            ->orderBy('sm.id', 'ASC')
            ->limit(5)
            ->get()->getResultArray();

        $hasAnyRealChild = false;

        // Loop to fill exactly 5 slots
        for ($i = 0; $i < 5; $i++) {
            if (isset($children[$i])) {
                $child = $children[$i];
                $hasAnyRealChild = true;
                $childNode = $this->buildTribeTree($child['id'], $db);
                $node['children'][] = $childNode;
            } else {
                // Always push an "empty" placeholder
                $node['children'][] = [
                    'user_id' => null,
                    'name' => 'Empty',
                    'children' => [] // important to allow for consistent spacing in frontend
                ];
            }
        }

        // Do NOT unset children — keep them even if all are empty
        return $node;
    }


    public function getSponsorTree()
    {
        $db = \Config\Database::connect();
        $post = $this->request->getJSON(true);
        $user_id = $post['uid'];

        $tree = $this->buildSponsorTree($user_id, $db);

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $tree
        ]);
    }

    private function buildSponsorTree($user_id, $db)
    {
        // Get user info
        $user = $db->table('users')
            ->select('id, firstname, lastname, username')
            ->where('id', $user_id)
            ->get()->getRowArray();

        if (!$user) {
            return null; // user not found
        }

        // Initialize node
        $node = [
            'user_id' => $user['id'],
            'name' => $user['firstname'] . ' ' . $user['lastname'],
            'username' => $user['username'],
            'children' => []
        ];

        // Find direct downlines (users where sponsor_id = current user_id)
        $downlines = $db->table('users')
            ->select('id')
            ->where('sponsor_id', $user_id)
            ->get()
            ->getResultArray();

        foreach ($downlines as $downline) {
            $childNode = $this->buildSponsorTree($downline['id'], $db);
            if ($childNode) {
                $node['children'][] = $childNode;
            }
        }

        return $node;
    }


    public function getTribe()
    {
        $db = \Config\Database::connect();
        $post = $this->request->getJSON(true);
        $user_id = $post['uid'];

        $tree = $this->buildTribeTree($user_id, $db);

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $tree
        ]);
    }


    public function getTribe3()
    {
        $db = \Config\Database::connect();
        $post = $this->request->getJSON(true);
        $user_id = $post['uid'];

        // Get root user info
        $user = $db->table('users')
            ->select('id, firstname, lastname')
            ->where('id', $user_id)
            ->get()->getRowArray();

        $tree = [
            'name' => $user['firstname'] . ' ' . $user['lastname'],
            'user_id' => $user['id'],
            'children' => []
        ];

        // Fetch all 5 slots under the user
        $downlines = $db->table('sharing_matrix sm')
            ->select('u.id, u.firstname, u.lastname, sm.id as slot_id')
            ->join('users u', 'u.id = sm.user_id')
            ->where('sm.upline_user_id', $user_id)
            ->orderBy('sm.id', 'ASC') // or sm.slot_number if available
            ->limit(5)
            ->get()->getResultArray();

        // Pad with empty slots
        for ($i = 0; $i < 5; $i++) {
            if (isset($downlines[$i])) {
                $child = $downlines[$i];
                $tree['children'][] = [
                    'name' => $child['firstname'] . ' ' . $child['lastname'],
                    'user_id' => $child['id']
                ];
            } else {
                $tree['children'][] = [
                    'name' => 'Empty',
                    'user_id' => null
                ];
            }
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $tree
        ]);
    }

    public function getTribe2()
    {
        $db = \Config\Database::connect();
        $post = $this->request->getJSON(true);
        $user_id = $post['uid'];

        // Get user info (optional if you want to show in the center)
        $user = $db->table('users')
            ->select('id, firstname, lastname')
            ->where('id', $user_id)
            ->get()->getRowArray();

        $tree = [
            'name' => $user['firstname'] . ' ' . $user['lastname'],
            'user_id' => $user['id'],
            'children' => []
        ];

        // Fetch up to 5 direct downlines
        $downlines = $db->table('sharing_matrix sm')
            ->select('u.id, u.firstname, u.lastname')
            ->join('users u', 'u.id = sm.user_id')
            ->where('sm.upline_user_id', $user_id)
            ->limit(5)
            ->get()->getResultArray();

        for ($i = 0; $i < 5; $i++) {
            if (isset($downlines[$i])) {
                $child = $downlines[$i];
                $tree['children'][] = [
                    'name' => $child['firstname'] . ' ' . $child['lastname'],
                    'user_id' => $child['id']
                ];
            } else {
                $tree['children'][] = [
                    'name' => 'Empty',
                    'user_id' => null
                ];
            }
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $tree
        ]);
    }


    public function getGenealogy()
    {
        $user_id = $this->request->getVar('uid');
        $rank = $this->request->getVar('rank');

        if ($rank=='partner') {
            $data = $this->fetchPartnerGenealogy($user_id);
        } else {
            $data = $this->fetchGenealogy($user_id);
        }

        if ($data) {
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $data,
                'totalmembers' => $data['totalmembers'] 
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'User not found'
            ]);
        }
    }

    private function fetchPartnerGenealogy($user_id, $depth = 1)
    {
        if ($depth > 11) {
            return null;
        }

        $db = \Config\Database::connect();

        // Get matrix entry for the user including personal info and sponsor details
        $user = $db->table('sharing_matrix sm')
            ->select('sm.id, u.signup_sponsor_id , sm.user_id, sm.upline_user_id, u.firstname, u.lastname, u.sponsor_id,u.placement_id, s.firstname AS sponsor_firstname, s.lastname AS sponsor_lastname,p.firstname AS placement_firstname, p.lastname AS placement_lastname')
            ->join('users u', 'u.id = sm.user_id', 'left')
            ->join('users s', 's.id = u.sponsor_id', 'left')
            ->join('users p', 'p.id = u.placement_id', 'left')
            ->where('sm.user_id', $user_id)
            ->get()
            ->getRowArray();

        if (!$user) return null;

        $user['name'] = $user['firstname'] . ' ' . $user['lastname'];
        $user['sponsor'] =  $user['sponsor_firstname'] . ' ' . $user['sponsor_lastname'];
        $user['placement'] = '12345'.$user['placement_firstname'] . ' ' . $user['placement_lastname'];
        $user['children'] = [];
        $user['totalmembers'] = 1; // Self

        // Find children in the matrix where the current user is the upline
        $children = $db->table('sharing_matrix')
            ->select('user_id')
            ->where('upline_user_id', $user_id)
            ->get()
            ->getResultArray();

        foreach ($children as $child) {
            $childNode = $this->fetchPartnerGenealogy($child['user_id'], $depth + 1);
            if ($childNode) {
                $user['children'][] = $childNode;
                $user['totalmembers'] += $childNode['totalmembers'];
            }
        }

        return $user;
    }



    private function fetchPartnerGenealogy_old($user_id, $depth = 1)
    {
        if ($depth > 11) {
            return null;
        }

        $db = \Config\Database::connect();

        $user = $db->table('users u')
           ->select('u.id, u.firstname, u.lastname, u.sponsor_id, s.firstname AS sponsor_firstname, s.lastname AS sponsor_lastname')
           ->join('users s', 's.id = u.sponsor_id', 'left')
           ->where('u.id', $user_id)
           ->get()
           ->getRowArray();

        if (!$user) return null;

        $user['name'] = $user['firstname'] . ' ' . $user['lastname'];
        $user['sponsor'] = $user['sponsor_firstname'] . ' ' . $user['sponsor_lastname'];
        $user['children'] = [];
        $user['totalmembers'] = 1; // Self

        $children = $db->table('users')
                       ->select('id')
                       ->where('sponsor_id', $user_id)
                       ->get()
                       ->getResultArray();

        foreach ($children as $child) {
            $childNode = $this->fetchPartnerGenealogy($child['id'], $depth + 1);
            if ($childNode) {
                $user['children'][] = $childNode;
                $user['totalmembers'] += $childNode['totalmembers'];
            }
        }

        return $user;
    }


    private function fetchGenealogy($user_id)
    {

        $db = \Config\Database::connect();

        // Get matrix entry for the user, including user's info and sponsor details
        $user = $db->table('sharing_matrix sm')
            ->select('sm.id, u.signup_sponsor_id, sm.user_id, sm.upline_user_id, u.firstname, u.lastname, u.sponsor_id,u.placement_id, s.firstname AS sponsor_firstname, s.lastname AS sponsor_lastname,p.firstname AS placement_firstname, p.lastname AS placement_lastname')
            ->join('users u', 'u.id = sm.user_id', 'left')
            ->join('users s', 's.id = u.sponsor_id', 'left')
            ->join('users p', 'p.id = u.placement_id', 'left')
            ->where('sm.user_id', $user_id)
            ->get()
            ->getRowArray();

        if (!$user) return null;

        // Full name with ID
        $user['name'] = $user['firstname'] . ' ' . $user['lastname'];
        $user['sponsor'] = $user['sponsor_firstname'] . ' ' . $user['sponsor_lastname'];
        $user['placement'] = $user['placement_firstname'] . ' ' . $user['placement_lastname'];

        // Initialize children array and totalmembers count (self)
        $user['children'] = [];
        $user['totalmembers'] = 1;

        // Fetch downline entries (children) where current user is the upline
        $children = $db->table('sharing_matrix')
            ->select('user_id')
            ->where('upline_user_id', $user_id)
            ->get()
            ->getResultArray();

        foreach ($children as $child) {
            $childNode = $this->fetchGenealogy($child['user_id']);
            if ($childNode) {
                $user['children'][] = $childNode;
                $user['totalmembers'] += $childNode['totalmembers'];
            }
        }

        return $user;
    }


    private function fetchGenealogy_old($user_id)
    {
        $db = \Config\Database::connect();

        // Get user with sponsor's firstname and lastname
        $user = $db->table('users u')
           ->select('u.id, u.firstname, u.lastname, u.sponsor_id, s.firstname AS sponsor_firstname, s.lastname AS sponsor_lastname')
           ->join('users s', 's.id = u.sponsor_id', 'left')
           ->where('u.id', $user_id)
           ->get()
           ->getRowArray();

        if (!$user) return null;

        // Full name with ID
        $user['name'] = $user['firstname'] . ' ' . $user['lastname'];
        $user['sponsor'] = $user['sponsor_firstname'] . ' ' . $user['sponsor_lastname'];

        // Initialize children array and totalmembers count (self)
        $user['children'] = [];
        $user['totalmembers'] = 1;

        // Fetch children recursively
        $children = $db->table('users')
                       ->select('id')
                       ->where('sponsor_id', $user_id)
                       ->get()
                       ->getResultArray();

        foreach ($children as $child) {
            $childNode = $this->fetchGenealogy($child['id']);
            if ($childNode) {
                $user['children'][] = $childNode;
                $user['totalmembers'] += $childNode['totalmembers'];
            }
        }

        return $user;
    }

    public function SiteLinks()
    {
     
        $db = \Config\Database::connect();
        $username = $this->request->getVar('username'); // or get from input


        // 1. Get starting user
        $user = $db->table('users')
            ->where('username', $username)
            ->get()
            ->getRow();

        if (!$user) {
            throw new \Exception("User not found.");
        }

        // 2. Climb sponsor chain until we find a usable reference
        $referenceMap = [];
        $currentUser = $user;

        while ($currentUser) {
            $siteLinks = $db->table('site_links')
                ->where('user_id', $currentUser->id)
                ->get()
                ->getResult();

            foreach ($siteLinks as $link) {
                if (!isset($referenceMap[$link->site_id])) {
                    $referenceMap[$link->site_id] = [
                        'reference' => $link->reference,
                        'username'  => $currentUser->username,
                    ];
                }
            }

            // Stop if we found reference for all sites
            if (count($referenceMap) === $db->table('sites')->countAll()) {
                break;
            }

            // Go to sponsor
            if ($currentUser->sponsor_id) {
                $currentUser = $db->table('users')
                    ->where('id', $currentUser->sponsor_id)
                    ->get()
                    ->getRow();
            } else {
                $currentUser = null;
            }
        }

        // 3. Fetch all sites
        $sites = $db->table('sites')
            ->where('site_url_format IS NOT NULL')
            ->where('site_url_format !=', '')
            ->get()
            ->getResult();

        // 4. Build final results
        $results = [];
        foreach ($sites as $site) {
            $refData = $referenceMap[$site->id] ?? null;

            $results[] = [
                'id' => $site->id,
                'name' => $site->site_name,
                'used_username' => $refData['username'] ?? null,
                'url' => isset($refData['reference'])
                    ? str_replace('{sharing}', $refData['reference'], $site->site_url_format)
                    : $site->site_url_format,
            ];
        }



        return $this->response->setJSON($results);
    }

    public function addSite()
    {
        $db = \Config\Database::connect();
        $validation = \Config\Services::validation();

        // Set validation rules
        $validation->setRules([
            'site_name' => 'required|max_length[255]',
            'site_url_format' => 'required|max_length[512]'
        ]);

        // Validate input
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $validation->getErrors()
            ]);
        }

        // Get validated data
        $data = [
            'site_name' => $this->request->getVar('site_name'),
            'site_url_format' => $this->request->getVar('site_url_format'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            $db->table('sites')->insert($data);
            
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Site added successfully',
                'id' => $db->insertID() // Return the new ID
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function deleteSite()
    {
        $db = \Config\Database::connect();
        
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric',
            'uid' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $validation->getErrors()
            ]);
        }

        $id = $this->request->getVar('id');
        $uid = $this->request->getVar('uid');

        try {
            $builder = $db->table('sites');
            $builder->where('id', $id);
            // Add user ID check if needed:
            // $builder->where('user_id', $uid);
            $result = $builder->delete();
            
            if ($db->affectedRows() === 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'No records were deleted - site not found'
                ]);
            }
            
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Site deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function updateSite()
    {
        $db = \Config\Database::connect();
        $validation = \Config\Services::validation();

        // Set validation rules
        $validation->setRules([
            'id' => 'required|numeric',
            'site_name' => 'required|max_length[255]',
            'site_url_format' => 'required|max_length[512]',
            'category_id' => 'required|numeric'
        ]);

        // Validate input
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $validation->getErrors()
            ]);
        }

        // Get validated data
        $data = [
            'site_name'       => $this->request->getVar('site_name'),
            'site_url_format' => $this->request->getVar('site_url_format'),
            'category_id'     => $this->request->getVar('category_id'),
            'updated_at'      => date('Y-m-d H:i:s')
        ];

        $id = $this->request->getVar('id');

        try {
            $builder = $db->table('sites');
            $builder->where('id', $id);
            $result = $builder->update($data);

            if ($db->affectedRows() === 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'No records were updated - site not found'
                ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Site updated successfully'
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function getSites()
    {
        $db = \Config\Database::connect();

        $builder = $db->table('sites s');
        $builder->select('s.id, s.site_name, s.site_url_format, s.category_id, c.category_name');
        $builder->join('site_category c', 'c.id = s.category_id', 'left');
        $builder->orderBy('s.id', 'ASC');

        $results = $builder->get()->getResult();

        return $this->response->setJSON([
            'success' => true,
            'data' => $results
        ]);
    }   

    public function UpdateSiteLinks()
    {
        // Initialize response array
        $response = ['success' => false, 'message' => ''];

        // Get user ID from request
        $uid = $this->request->getVar('uid');
        if (empty($uid)) {
            $response['message'] = 'User ID (uid) is required';
            return $this->response->setStatusCode(400)->setJSON($response);
        }

        // Get and validate JSON payload
        $postData = $this->request->getJSON(true);
        if (!$postData || !isset($postData['links'])) {
            $response['message'] = 'Invalid JSON payload or missing links data';
            return $this->response->setStatusCode(400)->setJSON($response);
        }

        $links = $postData['links'];
        $updatedCount = 0;
        $insertedCount = 0;

        try {
            $db = \Config\Database::connect();
            
            foreach ($links as $link) {

                // Validate required fields in each link
                if (empty($link['id']) || !isset($link['site_reference'])) {
                    continue;
                }

                $siteId = (int) $link['id'];
                $reference = trim($link['site_reference']);
                $password = trim($link['site_password']);

                if (!empty($reference))
                {
                    // Check if record exists
                    $exists = $db->table('site_links')
                                ->where('user_id', $uid)
                                ->where('site_id', $siteId)
                                ->countAllResults();

                    if ($exists) {
                        // Update existing record
                        $db->table('site_links')
                            ->where('user_id', $uid)
                            ->where('site_id', $siteId)
                            ->update([
                                'reference' => $reference,
                                'site_password' => $password,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);

                        if ($db->affectedRows() > 0) {
                            $updatedCount++;
                        }
                    } else {
                        // Insert new record
                        $db->table('site_links')
                            ->insert([
                                'user_id' => $uid,
                                'site_id' => $siteId,
                                'reference' => $reference,
                                'site_password' => $password,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);

                        if ($db->affectedRows() > 0) {
                            $insertedCount++;
                        }
                    }
                } else {
                    // Delete record if reference is empty
                    $db->table('site_links')
                        ->where('user_id', $uid)
                        ->where('site_id', $siteId)
                        ->delete();
                }
            }

            // Prepare success response
            $response = [
                'success' => true,
                'message' => "Operation completed: Updated {$updatedCount} links, Added {$insertedCount} new links",
                'updated_count' => $updatedCount,
                'inserted_count' => $insertedCount
            ];

            return $this->response->setJSON($response);

        } catch (\Exception $e) {
            $response['message'] = 'Operation failed: ' . $e->getMessage();
            return $this->response->setStatusCode(500)->setJSON($response);
        }
    }


    public function SiteLinks_old()
    {

        $db = \Config\Database::connect();
        $username = $this->request->getVar('username');

        $builder = $db->table('sites');
        $builder->select('sites.id,sites.site_name as name');
        $builder->select("
            CASE 
                WHEN site_links.reference IS NULL THEN sites.site_url_format
                ELSE REPLACE(sites.site_url_format, '{sharing}', site_links.reference)
            END AS url
        ", false);
        $builder->where('sites.site_url_format IS NOT NULL');
        $builder->where('sites.site_url_format !=', '');

        // Join with users table first to get the user_id from username
        $builder->join('users', "users.username = '$username'", 'inner');
        // Then join with site_links using the user_id from users table
        $builder->join('site_links', 'sites.id = site_links.site_id AND site_links.user_id = users.id', 'left');

        $query = $builder->get();
        $results = $query->getResult();

        // If no results with joins, fall back to just sites
        if (empty($results) ) {
            $builder = $db->table('sites');
            $builder->select('sites.id, sites.site_name as name');
            $builder->select("REPLACE(sites.site_url_format, '{sharing}', '') as url", false);
            $builder->where('sites.site_url_format IS NOT NULL');
            $builder->where('sites.site_url_format !=', '');
            $results = $builder->get()->getResult();
        }


        return $this->response->setJSON($results);
    }

public function getSiteLinks()
{
    global $conn; 
    $uid = intval($this->request->getVar('uid') ?? 0);

    if ($uid <= 0) {
        return $this->response->setJSON([
            'success' => false,
            'message' => 'Invalid uid'
        ]);
    }

    // 1) Fetch the user's rank (same as before)
    $rank = '';
    $stmtRank = $conn->prepare("SELECT `rank` FROM users WHERE id = ?");
    $stmtRank->bind_param("i", $uid);
    $stmtRank->execute();
    $resRank = $stmtRank->get_result();
    if ($resRank && $resRank->num_rows > 0) {
        $rankRow = $resRank->fetch_assoc();
        $rank = strtolower(trim($rankRow['rank'] ?? ''));
    }
    $stmtRank->close();

    // 2) Build the LOGISTICS filter based on the exact/starts-with rules
    // Default = hide LOGISTICS
        // Default = hide LOGISTICS completely
// Default = hide LOGISTICS completely
// Default = hide LOGISTICS completely
$logisticsFilter = " AND (c.category_name <> 'LOGISTICS')";

if ($rank === 'admin') {
    // admin → show everything
    $logisticsFilter = "";
} elseif ($rank === 'partner') {
    // exact 'partner' → only 48
    $logisticsFilter = " AND (c.category_name <> 'LOGISTICS' OR s.id IN (48))";
} elseif (strpos($rank, 'partner_') === 0) {
    // starts with 'partner_' → 48 and 63
    $logisticsFilter = " AND (c.category_name <> 'LOGISTICS' OR s.id IN (48,63))";
} elseif ($rank === 'humanitarian') {
    // exactly 'humanitarian' → show everything
    $logisticsFilter = "";
} elseif (strpos($rank, 'humanitarian_') === 0) {
    // starts with 'humanitarian_' → show all except 61
    $logisticsFilter = " AND NOT (c.category_name = 'LOGISTICS' AND s.id = 61 OR s.id = 72)";
} elseif ($rank === 'ambassador') {
    // exactly 'ambassador' → 58 + 48 + 63
    $logisticsFilter = " AND (c.category_name <> 'LOGISTICS' OR s.id IN (58,48,63,72))";
} elseif (strpos($rank, 'ambassador_') === 0) {
    // starts with 'ambassador_' → only 48 + 63
    $logisticsFilter = " AND (c.category_name <> 'LOGISTICS' OR s.id IN (48,63)) AND s.id <> 72";
}


    // 3) Main query (unchanged placeholders; we only add the filter in WHERE)
    $sql = "
        SELECT
            s.id AS site_id,
            s.site_name,
            s.site_url_format,
            s.category_id,
            c.category_name,
            l.reference,
            l.site_password,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM site_links sl
                    JOIN users u ON u.id = ?
                    WHERE sl.site_id = s.id
                      AND sl.user_id = u.sponsor_id
                ) THEN 1
                ELSE 0
            END AS sponsor_has_record,
            (
                SELECT sl.reference
                FROM site_links sl
                JOIN users u ON u.id = ?
                WHERE sl.site_id = s.id
                  AND sl.user_id = u.sponsor_id
                LIMIT 1
            ) AS sponsor_reference
        FROM sites s
        LEFT JOIN site_category c ON s.category_id = c.id
        LEFT JOIN site_links l
            ON s.id = l.site_id AND l.user_id = ?
        WHERE s.site_url_format IS NOT NULL
          AND s.site_url_format <> ''
          {$logisticsFilter}
        ORDER BY c.id, s.id
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $this->response->setJSON([
            'success' => false,
            'message' => "SQL prepare failed: " . $conn->error
        ]);
    }

    // keep existing bindings (3 integers)
    $stmt->bind_param("iii", $uid, $uid, $uid);
    $stmt->execute();

    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();



    // --- get sponsor_id of the current user ---
    $sponsor_id = null;
    $stmtSponsor = $conn->prepare("SELECT sponsor_id FROM users WHERE id = ?");
    $stmtSponsor->bind_param("i", $uid);
    $stmtSponsor->execute();
    $resSponsor = $stmtSponsor->get_result();
    if ($resSponsor && $resSponsor->num_rows > 0) {
        $sponsorRow = $resSponsor->fetch_assoc();
        // ensure int (or null if not set)
        $sponsor_id = isset($sponsorRow['sponsor_id']) ? (int)$sponsorRow['sponsor_id'] : null;
    }
    $stmtSponsor->close();

    // --- check if sponsor has completed & paid plans 1..5 ---
    $has_five_purchases = false;

    if (!empty($sponsor_id)) {
        // Fetch distinct plan_ids that match the criteria
        $stmtPlans = $conn->prepare("
            SELECT DISTINCT plan_id
            FROM sharing_purchase
            WHERE user_id = ?
              AND plan_id IN (1,2,3,4,5)
              AND status = 'completed'
              AND paid = 1
        ");
        $stmtPlans->bind_param("i", $sponsor_id);
        $stmtPlans->execute();
        $resPlans = $stmtPlans->get_result();

        $found = [];
        if ($resPlans) {
            while ($row = $resPlans->fetch_assoc()) {
                $found[(int)$row['plan_id']] = true;
            }
        }
        $stmtPlans->close();

        // Ensure all five plans exist
        $required = [1, 2, 3, 4, 5];
        $has_five_purchases = !array_diff($required, array_keys($found));
    }
    

    return $this->response->setJSON([
        'success' => true,
        'data' => $data,
        'sponsor_has_first_five' => $has_five_purchases
    ]);
}


    public function getSiteLinks2()
    {
        global $conn; 
        $uid = $this->request->getVar('uid');

        $sql1 = "
            SELECT 
                s.id AS site_id,
                s.site_name,
                s.site_url_format,
                s.category_id,
                c.category_name,
                l.reference,
                l.site_password
            FROM sites s
            LEFT JOIN site_category c ON s.category_id = c.id
            LEFT JOIN site_links l 
                ON s.id = l.site_id AND l.user_id = ?
            ORDER BY c.id, s.id
            ";


            $sql = "
            SELECT
                s.id AS site_id,
                s.site_name,
                s.site_url_format,
                s.category_id,
                c.category_name,
                l.reference,
                l.site_password,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM site_links sl
                        JOIN users u ON u.id = ?
                        WHERE sl.site_id = s.id
                          AND sl.user_id = u.sponsor_id
                    ) THEN 1
                    ELSE 0
                END AS sponsor_has_record,
                (
                    SELECT sl.reference
                    FROM site_links sl
                    JOIN users u ON u.id = ?
                    WHERE sl.site_id = s.id
                      AND sl.user_id = u.sponsor_id
                    LIMIT 1
                ) AS sponsor_reference
            FROM sites s
            LEFT JOIN site_category c ON s.category_id = c.id
            LEFT JOIN site_links l
                ON s.id = l.site_id AND l.user_id = ?
            ORDER BY c.id, s.id
            ";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => "SQL prepare failed: " . $conn->error
                ]);
            }

            // 3 placeholders → bind 3 integers
            $stmt->bind_param("iii", $uid, $uid, $uid);
            $stmt->execute();

            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }

        return $this->response->setJSON([
            'success' => true,
            'data' => $data
        ]);
    }


    public function getSiteLinksold()
    {

        $db = \Config\Database::connect();
        $uid = $this->request->getVar('uid');

        $builder = $db->table('sites');
        $builder->select('sites.*, site_links.user_id, site_links.reference,site_links.site_password ');
        $builder->select("
            CASE 
                WHEN site_links.reference IS NULL THEN sites.site_url_format
                ELSE REPLACE(sites.site_url_format, '{sharing}', site_links.reference)
            END AS formatted_url
        ", false);
        $builder->where('sites.site_url_format IS NOT NULL');
        $builder->where('sites.site_url_format !=', '');
        $builder->join('site_links', "sites.id = site_links.site_id AND site_links.user_id = $uid", 'left');

        $query = $builder->get();
        $results = $query->getResult();

        return $this->response->setJSON([
            'success' => true,
            'data' => $results
        ]);
    }

    public function getDownlines() {
        global $conn; 
        $uid = $this->request->getVar('uid');

        $postData = $this->request->getJSON(true);
        if (!$postData) {
            return $this->fail('Invalid JSON payload');
        }

        $user_id = intval($postData['id'] ?? 0);
        $user_id2 = intval($postData['id2'] ?? 0);
        $page = intval($postData['page'] ?? 1);
        $limit = intval($postData['limit'] ?? 10);
        $offset = ($page - 1) * $limit;

        // Recursive CTE query (inner)
        $recursiveCTE = "
            WITH RECURSIVE genealogy AS (
                SELECT
                    users.id,
                    CONCAT(users.firstname,' ',users.lastname) AS fullname,
                    CONCAT(u2.firstname,' ',u2.lastname) AS sponsor,
                    users.sponsor_id,
                    users.date_created,
                    users.username,
                    users.email,
                    users.phone,
                    1 AS level
                FROM users 
                LEFT JOIN users u2 ON users.sponsor_id = u2.id
                WHERE users.status <> 'deleted' AND users.sponsor_id = {$user_id}
                UNION ALL
                SELECT
                    u.id,
                    CONCAT(u.firstname,' ',u.lastname),
                    CONCAT(u2.firstname,' ',u2.lastname),
                    u.sponsor_id,
                    u.date_created,
                    u.username,
                    u.email,
                    u.phone,
                    g.level + 1
                FROM users u
                LEFT JOIN users u2 ON u.sponsor_id = u2.id
                JOIN genealogy g ON u.sponsor_id = g.id
                WHERE u.status <> 'deleted'
            )
            SELECT g.*, 
                (SELECT COUNT(*) FROM users WHERE sponsor_id = g.id) AS downline_count
            FROM genealogy g
            WHERE sponsor_id = {$user_id2}
            ORDER BY date_created DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        // Final full query with plan data
        $fullQuery = "
            SELECT main.*
            FROM (
                {$recursiveCTE}
            ) AS main
           
            GROUP BY id, fullname, sponsor, sponsor_id, date_created, username, email, phone, level, downline_count
        ";
 
        // $result = $conn->query($fullQuery);
        // $rows = $result->fetch_assoc();
        $stmt = $conn->prepare($fullQuery);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $rows = utf8ize($rows);
        
        // Get total count and sponsor name
        $totalResult = $conn->query("
            SELECT COUNT(0) AS count, CONCAT(u2.firstname,' ',u2.lastname) AS sponsor
            FROM users 
            LEFT JOIN users u2 ON users.sponsor_id = u2.id
            WHERE users.sponsor_id = {$user_id}
        ")->fetch_assoc();

        $total = $totalResult['count'] ?? 0;
        $sponsor = $totalResult['sponsor'] ?? '';
        $totalPages = ceil($total / $limit);

        return $this->response->setJSON([
            "status" => "success",
            'rows' => $rows,
            'totalPages' => $totalPages,
            'sponsor' => $sponsor,
            'query' => $fullQuery // optional, for debugging
        ]);

    }

    public function checkBalance()
    {
        global $conn; 
        $uid = (int)($this->request->getVar('uid') ?? 0);

        // Default values
        $total_items     = 0; 
        $in_transit      = 0; 
        $total_available = 0; 
        $low_stock       = 0; 

        try {
            
            // 🔹 Total items (exclude CREATED)
            $sql = "SELECT COUNT(*) AS c FROM items WHERE status <> 'CREATED'";
            $result = $conn->query($sql);
            if ($row = $result->fetch_assoc()) {
                $total_items = (int)$row['c'];
            }

            // 🔹 In-transit items
            $sql = "SELECT COUNT(*) AS c FROM items WHERE status='IN_TRANSIT'";
            $result = $conn->query($sql);
            if ($row = $result->fetch_assoc()) {
                $in_transit = (int)$row['c'];
            }

            // 🔹 Available = IN_STOCK + RESERVED
            $sql = "SELECT COUNT(*) AS c 
                    FROM items 
                    WHERE status IN ('IN_STOCK','RESERVED')";
            $result = $conn->query($sql);
            if ($row = $result->fetch_assoc()) {
                $total_available = (int)$row['c'];
            }

            // 🔹 Low stock = products where available <= reorder_point
            $sql = "
                SELECT COUNT(*) AS c
                FROM products p
                WHERE p.reorder_point > 0
                  AND (
                    SELECT COUNT(*) 
                    FROM items i 
                    WHERE i.product_id=p.id AND i.status IN ('IN_STOCK','RESERVED')
                  ) <= p.reorder_point
            ";
            $result = $conn->query($sql);
            if ($row = $result->fetch_assoc()) {
                $low_stock = (int)$row['c'];
            }

            return $this->response->setJSON([
                "status"          => "success",
                "total_items"     => $total_items,
                "in_transit"      => $in_transit,
                "total_available" => $total_available,
                "low_stock"       => $low_stock,
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                "status"  => "error",
                "message" => $e->getMessage()
            ]);
        }
    }


    public function listUsers()
    {
        $userModel = new UserModel();
        $users = $userModel->findAll();
        return $this->respond($users);
    }

	public function checkUsername()
    {
        $username = $this->request->getVar('username'); //

        if (empty($username)) {
            return $this->respond(['status' => 'error', 'message' => "Username is required"]);
        }

        $userModel = new UserModel();
        $user = $userModel->select("id, username, CONCAT(firstname, ' ', lastname) AS fullname")
                          ->where('username', $username)
                          ->where('status !=', 'deleted')
                          ->first();

        if ($user) {
            return $this->respond([
                'status' => 'success',
                'id' => $user['id'],
                'fullname' => $user['fullname'],
                'username' => $user['username']
            ]);
        } else {
            return $this->respond(['status' => 'error', 'message' => "Username not found"]);
        }
    }

    public function verifyToken()
    {
        $token = $this->request->getVar('token'); //
        if (empty($token)) {
            return $this->respond(['status' => 'error', 'message' => "Token is required"]);
        }

        $userModel = new UserModel();
        $user = $userModel->select("id")
                          ->where('reset_token', $token)
                          ->where('status !=', 'deleted')
                          ->first();

        if ($user) {
            return $this->respond([
                'status' => 'success',
                'id' => $user['id']
            ]);
        } else {
            return $this->respond(['status' => 'error', 'message' => "Token not found"]);
        }
    }
    
    public function resetPassword()
    {
        $token = $this->request->getVar('token');
        $password = $this->request->getVar('password');

        if (empty($token) || empty($password)) {
            return $this->respond(['status' => 'error', 'message' => "Token and Password are required"], 400);
        }

        // Hash the received token before lookup
        $hashedToken = hash('sha256', $token);

        $userModel = new UserModel();
        $user = $userModel->select("id")
                          ->where('reset_token', $hashedToken)
                          ->where('status !=', 'deleted')
                          ->first();

        if (!$user) {
            return $this->respond(['status' => 'error', 'message' => "Invalid or expired token"], 404);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $userModel->update($user['id'], [
            'password' => $hashedPassword,
            'reset_token' => null,
            'reset_requested_at' => null
        ]);

        return $this->respond([
            'status' => 'success',
            'message' => "Password has been reset successfully"
        ]);
    }

    public function resetPassword_old()
    {
        $token = $this->request->getVar('token');
        $password = $this->request->getVar('password');

        if (empty($token) || empty($password)) {
            return $this->respond(['status' => 'error', 'message' => "Token and Password are required"], 400);
        }

        $userModel = new UserModel();
        $user = $userModel->select("id")
                          ->where('reset_token', $token)
                          ->where('status !=', 'deleted')
                          ->first();

        if (!$user) {
            return $this->respond(['status' => 'error', 'message' => "Invalid or expired token"], 404);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $userModel->update($user['id'], [
            'password' => $hashedPassword,
            'reset_token' => null, 
            'reset_requested_at' => null 
        ]);

        return $this->respond([
            'status' => 'success',
            'message' => "Password has been reset successfully"
        ]);
    }


    public function forgotPassword()
    {
        $email = $this->request->getVar('email');
        $ret = ProcessForgotPassword($email);

        exit();
    }    

    public function sendInvite()
    {

        // return $this->response->setJSON([
        //                 'status' => 'success',
        //                 'message' => 'Invitation email sent successfully.'
        //             ]);

        // Load helpers
        helper(['form', 'url']);

        // Only allow POST request
        if (!$this->request->is('post')) {
            return $this->response->setStatusCode(405)->setJSON([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
        }

        // Get JSON data from the request
        $data = $this->request->getJSON(true);

        // Validate required fields
        $requiredFields = ['from_name', 'from_email', 'to_name', 'to_email', 'referral_link'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => "Missing required field: $field"
                ]);
            }
        }

        // Compose email
        $email = \Config\Services::email();

        $email->setTo($data['to_email']);
        $email->setFrom($data['from_email'], $data['from_name']);
        $email->setSubject("You're invited to join IBOPRO!");

        $message = "
            Hi {$data['to_name']},<br><br>
            {$data['from_name']} has invited you to join <strong>IBOPRO</strong>!<br><br>
            Click the button below to sign up and explore new opportunities:<br><br>
            <a href='{$data['referral_link']}' style='
                background-color: #28a745;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;'>Join Now</a><br><br>
            Or copy and paste this link into your browser:<br>
            {$data['referral_link']}<br><br>
            Cheers,<br>IBOPRO Team
        ";

        $email->setMessage($message);
        $email->setMailType('html');

        // Send the email
        if ($email->send()) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Invitation email sent successfully.'
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $email->printDebugger(['headers'])
            ]);
        }
    }



}
