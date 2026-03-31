<?php
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';

class AdminController extends ResourceController
{
    use ResponseTrait;

    protected $format = 'json';


    public function getStats()
    {
        global $conn;
        $postData = json_decode(file_get_contents("php://input"), true);
 
        $uid = $postData['uid'];
        $fromdate = $postData['fromdate'];
        $todate = $postData['todate'];
        
        $total_users  =  0; 
        $total_deposits =  0; 
        $total_deposits_flr =  0; 
        $today_deposits=  0; 

        $total_purchases =  0; 
        $total_commissions =  0; 
        $pool_commissions =  0; 
        $total_purchases_matrix =  0; 
        $total_commissions_matrix =  0; 
        $pool_commissions_matrix =  0; 
    
        $deposits =  0; 
        $purchases =  0; 
        $commissions =  0; 
        $pool =  0; 
        $registeredusers =  0; 
        $memberlogins =  0; 
        $purchased_plan10 =  0; 
        $purchased_plan50 =  0; 
        $purchased_plan100 =  0; 
        $purchased_plan250 =  0; 
        $purchased_plan500 =  0; 
        $purchased_plan1000 =  0; 
        $purchased_plan2500 =  0; 
        $purchased_plan5000 =  0; 
        $purchased_plan7500 =  0; 
        $purchased_plan10000 =  0; 
        $purchased_plan15000 =  0; 
        $purchased_plan20000 =  0; 

        $total_wallet_balance =  0; 
        $root_wallet_balance =  0; 
        $total_withdrawn =  0; 
        $approval_balance = 0; 
        $pending_withdrawal = 0;
        $total_rewards= 0;
        $total_donations = 0;
        $total_holding = 0 ; 

            //Total users
            $sql = "Select count(0) as count from users where status <> 'deleted'";
            $row = $conn->query($sql);
            $row = $row->fetch_assoc();
            $total_users =$row['count'];

            //Total Holding
            $sql = "Select count(0) as count from users where status = 'holding'";
            $row = $conn->query($sql);
            $row = $row->fetch_assoc();
            $total_holding =$row['count'];


            //Total Deposits
            $sql = "SELECT SUM(amount) AS total FROM transactions WHERE type = 'deposit' and status='completed'";
            $row = $conn->query($sql);
            $row = $row->fetch_assoc();
            $total_deposits =$row['total'];

            //Total Deposits
            $sql = "SELECT SUM(amount) AS total FROM transactions WHERE type = 'payment' and status='completed'";
            $row = $conn->query($sql);
            $row = $row->fetch_assoc();
            $total_donations =$row['total'];            

            //Today Deposits
            $sql = "SELECT SUM(amount) AS total FROM transactions WHERE type = 'deposit' and status='completed'  AND DATE(date_created) = CURDATE();";
            $row = $conn->query($sql);
            $row = $row->fetch_assoc();
            $today_deposits =$row['total'];            


            //Total Commissions
            $sql = "Select sum(amount) as total from transactions where type ='commission' and status='completed';";
            $row = $conn->query($sql);
            $row = $row->fetch_assoc();
            $total_commissions =$row['total'];

            //Total Rewards
            $sql = "Select sum(amount) as total from rewards where status='completed';";
            $row = $conn->query($sql);
            $row = $row->fetch_assoc();
            $total_rewards =$row['total'];


        //     //2UP

        //     //Total Purchases
        //     $sql = "SELECT SUM(ABS(amount)) AS total FROM transactions WHERE type = 'payment' and status='completed' and is_matrix = 0";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $total_purchases =$row['total'];

        //     //Pool Commissions
        //     $sql = "Select sum(amount) as total from transactions t 
        // inner join plan_purchases p on t.id = p.pool_id and is_matrix=0";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $pool_commissions =$row['total'];


        //    //2x2

        //     //Total Purchases
        //     $sql = "SELECT SUM(ABS(amount)) AS total FROM transactions WHERE type = 'payment' and status='completed' and is_matrix = 1";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $total_purchases_matrix =$row['total'];

        //     //Pool Commissions
        //     $sql = "Select sum(amount) as total from transactions t 
        // inner join matrix_plan_purchases p on t.id = p.pool_id and is_matrix=1";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $pool_commissions_matrix =$row['total'];

        //     //Total Commissions
        //     $sql = "Select sum(amount) as total from transactions t 
        // inner join matrix_plan_purchases p on t.id = p.commission_id and is_matrix = 1;";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $total_commissions_matrix =$row['total'];



        //STATISTICS
        // if ($fromdate <> '')
        //     $fromdate = $fromdate . " 00:00:00"; 
        // if ($todate <> '')
        //     $todate = $todate . " 23:59:59"; 
             
        // if ($fromdate != '' || $todate != '') {
        //     $filter_date = " AND (date_created >= '$fromdate' AND date_created <= '$todate') ";
        //     //Deposits
        //     $sql = "SELECT IFNULL(SUM(amount), 0) AS total FROM transactions WHERE type = 'deposit' and status='completed' $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $deposits =$row['total'];

        //     //Purchases
        //     $sql = "SELECT IFNULL(SUM(ABS(amount)), 0) AS total FROM transactions WHERE type = 'payment' and status='completed' $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchases = $row['total'];

        //     //commissions
        //     $sql = "SELECT IFNULL(SUM(amount), 0) AS total FROM transactions WHERE type = 'commission' and status='completed' $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $commissions = $row['total'];

        //     ///pool
        //     $sql = "SELECT IFNULL(SUM(amount), 0) AS total FROM transactions WHERE type = 'pool' and status='completed' $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $pool = $row['total'];

        //     ///registeredusers
        //     $sql = "SELECT COUNT(0) AS count FROM users WHERE status <> 'deleted' $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $registeredusers = $row['count'];

        //     ///memberlogins
        //     $sql = "SELECT COUNT(0) AS count FROM activity_log WHERE type = 'login' $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $memberlogins = $row['count'];

        //     ///purchased_plan10
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=1 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan10 = $row['count'];

        //     ///purchased_plan50
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=2 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan50 = $row['count'];

        //     ///purchased_plan100
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=3 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan100 = $row['count'];

        //     ///purchased_plan250
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=4 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan250 = $row['count'];

        //     ///purchased_plan500
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=5 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan500 = $row['count'];

        //     ///purchased_plan1000
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=6 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan1000 = $row['count'];

        //     ///purchased_plan2500
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=7 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan2500 = $row['count'];

        //     ///purchased_plan5000
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=8 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan5000 = $row['count'];    

        //     ///purchased_plan7500
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=9 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan7500 = $row['count'];

        //     ///purchased_plan10000
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=10 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan10000 = $row['count'];

        //     ///purchased_plan15000
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=11 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan15000 = $row['count'];

        //     ///purchased_plan20000
        //     $sql = "SELECT COUNT(0) AS count FROM transactions WHERE type = 'payment' and plan_id=12 $filter_date";
        //     $row = $conn->query($sql);
        //     $row = $row->fetch_assoc();
        //     $purchased_plan20000 = $row['count'];   
        // }

        //Total Wallet Balance
        //$sql = "SELECT SUM(amount) AS total FROM transactions WHERE  user_id <> 1  and status='completed'";
        $sql = "
            WITH ranked_transactions AS (
                SELECT 
                    user_id,
                    running_total,
                    ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY date_created DESC) AS rn
                FROM transactions
                WHERE status = 'completed'
            )
            SELECT 
                SUM(running_total) AS total_sum
            FROM ranked_transactions
            WHERE rn = 1;";
        $row = $conn->query($sql);
        $row = $row->fetch_assoc();
        $total_wallet_balance =$row['total_sum'];


        //Total Withdrawn
        $sql = "SELECT SUM(ABS(amount)) AS total  
            FROM transactions 
            WHERE type = 'withdrawal' 
            AND status = 'completed'";
        $row = $conn->query($sql);
        $row = $row->fetch_assoc();
        $total_withdrawn =$row['total'];
        //$total_withdrawn = number_format((float)$row['total'], 2, '.', '');

        //Total Pending Withdrawn
        $sql = "SELECT SUM(ABS(amount)) AS total  
            FROM transactions 
            WHERE type = 'withdrawal' 
            AND status = 'pending'";
        $row = $conn->query($sql);
        $row = $row->fetch_assoc();
        $pending_withdrawal =$row['total'];        

        //Approval balance
        // $approval_balance = GetUSDTApprovalRemainingBalance();

        // //avoid chikits
        //  $total_commissions = $total_purchases - ($total_purchases *.2);
        //  $pool_commissions= $total_purchases - ($total_purchases *.8);

        $sql="";
         $data = [
                "total_users" => $total_users,
                "today_deposits" => $today_deposits,
                "total_deposits" => $total_deposits,
                "total_deposits_flr" => $total_deposits_flr,
                "total_purchases" => $total_purchases,
                "total_commissions" => $total_commissions,
                "pool_commissions" => $pool_commissions,
                "total_purchases_matrix" => $total_purchases_matrix,
                "total_commissions_matrix" => $total_commissions_matrix,
                "pool_commissions_matrix" => $pool_commissions_matrix,
                "deposits" => $deposits ,
                "purchases" => $purchases ,
                "commissions" => $commissions ,
                "pool" => $pool,
                "registeredusers" => $registeredusers,
                "memberlogins" => $memberlogins,
                "purchased_plan10" => $purchased_plan10,
                "purchased_plan50" => $purchased_plan50,
                "purchased_plan100" => $purchased_plan100,
                "purchased_plan250" => $purchased_plan250,
                "purchased_plan500" => $purchased_plan500,
                "purchased_plan1000" => $purchased_plan1000,
                "purchased_plan2500" => $purchased_plan2500,
                "purchased_plan5000" => $purchased_plan5000 ,
                "purchased_plan7500" => $purchased_plan7500,
                "purchased_plan10000" => $purchased_plan10000 ,
                "purchased_plan15000" => $purchased_plan15000,
                "purchased_plan20000" => $purchased_plan20000 ,
                "total_wallet_balance" => $total_wallet_balance ,
                "root_wallet_balance" => $root_wallet_balance,
                "total_withdrawn" => $total_withdrawn,
                "pending_withdrawal" => $pending_withdrawal,
                "approval_balance" => $approval_balance,
                "total_rewards" => $total_rewards,
                "total_donations"=> $total_donations * -1,
                "total_holding" => $total_holding,
                "sql" => $sql
            ];
            return  $this->response->setJSON(["status" => "success", "data" => $data]);     
  
    }


    public function AddDeposit() 
    {

        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) 
        {
            $uid             = $postData['uid'] ?? null;
            $id              = $postData['id'] ?? null;
            $amount          = $postData['amount'] ?? 0;
            $date            = $postData['date'] ?? date('Y-m-d');
            $note            = $postData['note'] ?? '';
            $type            = $postData['type'] ?? 'other';
            $status          = $postData['status'] ?? 'pending';
            $sender_wallet   = $postData['sender_wallet'] ?? '';
            $transaction_hash= $postData['transaction_hash'] ?? '';

            // Validate required field: amount
            if (empty($amount) || !is_numeric($amount) ) {
                $ret['status'] = 'error';
                $ret['message'] = 'Amount is required.';
                return $this->response->setJSON($ret);
            }


            $ret = Process_Deposit($id,$amount,$amount,$sender_wallet,$type,1,$transaction_hash,$note,$status );
            $deposit_id = $ret['id'];

            if ($status == 'success')
            {
                Deposit_Complete($id, $amount, $deposit_id, $transaction_hash, $sender_wallet, $amount, $type, false);
            }

            // Add activity Log
            add_admin_log($uid,'createmanualdeposit',json_encode($postData));

            return  $this->response->setJSON($ret);        
        }
          
    }

    public function matrixPlacement() 
    {

        $id = $this->request->getVar('id');
        $uid = $this->request->getVar('uid');
        $ret = UpdateMatrixPlacement($id); 

        return  $this->response->setJSON($ret);           
    }


    public function getDonations() 
    {
        global $conn;
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) 
        {
           $user_id = $postData['id'];
       
            $query = "SELECT 
                sharing_donation.*,
                CASE 
                    WHEN sharing_purchase.plan_id IS NOT NULL THEN 1
                    ELSE 0
                END AS is_taken,  ".$user_id." as `user_id`  FROM 
                sharing_donation
            LEFT JOIN 
                sharing_purchase 
            ON 
                sharing_donation.id = sharing_purchase.plan_id  and sharing_purchase.user_id = '".$user_id."' and sharing_purchase.paid=1 ORDER BY sharing_donation.id";

            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = $result->fetch_all(MYSQLI_ASSOC);

            return $this->respond([
                    "status" => "success",
                    'rows' => $rows
            ]);

        }
    }

    public function LoginasUser()
    {
        global $conn;

        helper(['form', 'url']);
        $session = session();

        $data = json_decode(file_get_contents("php://input"));

        if ($data)
        {
                
            $id = $data->id;

            // START 

            //$sql = "SELECT * FROM users WHERE email='$email'";
            $sql = "SELECT users.*,Concat(u2.firstname,' ',u2.lastname) as sponsor_name  FROM users left join users u2 on u2.id = users.sponsor_id WHERE users.status <> 'deleted' AND users.id='$id'";

            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                UpdateRunningTotals($id,1);
                UpdateRewardCap($id);

                $csrf = Logged_User($user['id']);

                if ($user['is_admin'])
                    $role= "Admin";
                else
                    $role= "Member";

                $sponsorName = !empty($user['sponsor_name']) && !empty($user['sponsor_id'])
                ? "{$user['sponsor_name']} #{$user['sponsor_id']}"
                : "N/A";
                // Set session data
                $sessionData = [

                    'id'  => $user['id'],
                    'username'     => $user['username'],
                    'firstname'     => $user['firstname'],
                    'lastname'     => $user['lastname'],
                    'status'     => $user['status'],
                    'sponsor_name'     => $sponsorName,
                    'sponsor_id' => $user['sponsor_id'],
                    'email'    => $user['email'],
                    'rank' => $user['rank'],
                    'role'=> $role,
                    'is_admin'=>$user['is_admin'],
                    'date_created' => $user['date_created'],
                    'csrf_token' => $csrf

                ];
                $session->set($sessionData);

                return $this->response->setJSON([
                    'status' => 'success',
                    'data'   => $sessionData,
                ]);             

            } else {
                return $this->respond(["status" => "error", "data" => "Invalid email or password!"]);
            }
        } else {
            return $this->respond(["status" => "error", "message" => "access denied!"]);
        }

    }

    public function Simulatematrixclear()
    {
              global $conn;

            $sql = "DELETE sm FROM sharing_matrix sm INNER JOIN users u ON sm.user_id = u.id WHERE u.dummy = 1;"; 
            $stmt= $conn->query($sql);
            //$stmt->execute();  

            $sql = "DELETE from users where dummy = 1"; 
            $stmt= $conn->query($sql);
            //$stmt->execute();  

            return $this->respond([
                    'status' => 'success',
                    'message' => 'Dummy data deleted.',
            ]);
    }

    public function Simulatematrixentry() 
    {

            $db = \Config\Database::connect();
                $postData = json_decode(file_get_contents("php://input"), true);

                if (!$postData || !isset($postData['username']) || !isset($postData['sponsor_id'])) {
                    return $this->respond([
                        'status' => 'error',
                        'message' => 'Required fields are missing.'
                    ]);
                }

                $username = $postData['username'];
                $sponsorId = $postData['sponsor_id'];

                // Insert new dummy user
                $builder = $db->table('users');
                $builder->insert([
                    'username' => $username,
                    'sponsor_id' => $sponsorId,
                    'dummy' => 1,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $insertId = $db->insertID();

                addToSharingMatrix($insertId ,$sponsorId);

                return $this->respond([
                    'status' => 'success',
                    'message' => 'Dummy user created successfully.',
                    'user_id' => $insertId
                ]);


    }

    public function getGlobalSharingmatrix() 
    {
       $db = \Config\Database::connect();
        $data = $this->request->getJSON();

        if (!isset($data->uid)) {
            return $this->respond(['status' => 'error', 'message' => 'User ID is required.']);
        }

        // Fetch all matrix entries related to the user or their downlines
        $query = $db->table('sharing_matrix sm')
            ->select('sm.*, u.username, u.sponsor_id,u.signup_sponsor_id')
            ->join('users u', 'u.id = sm.user_id', 'left')
            ->orderBy('sm.upline_user_id', 'ASC')
            ->orderBy('sm.entry_position', 'ASC')            
            ->get();

        $rows = $query->getResultArray();

        // Cast numeric fields to integers
        $formatted = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'],
                'upline_id' => (int)$row['upline_id'],
                'upline_user_id' => (int)$row['upline_user_id'],
                'entry_position' => isset($row['entry_position']) ? (int)$row['entry_position'] : 0,
                'sponsor_id' => (int)$row['sponsor_id'],
                'signup_sponsor_id' => (int)$row['signup_sponsor_id'],
                //'created_at' => $row['created_at'] ?? null
            ];
        }, $rows);

        return $this->respond([
            'status' => 'success',
            'rows' => $formatted
        ]);

    }


    public function getGlobalsharingmatrix2() {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

            $planId = intval($postData['plan_id']);  // Plan of the matrix
            $completed = 0; 
            // Recursive function to fetch downline members for the entire tree
            function getGlobalMatrixTree($conn, $rId, $planId) {
                global $completed;
                $query = "
                    SELECT mp.id, mp.user_id, u.username, mp.position_number, mp.complete, mp.payee_id , mp.re_entry_count 
                    FROM matrix13_plan_purchases mp
                    JOIN users u ON mp.user_id = u.id
                    WHERE mp.upline_id = ? AND mp.plan_id = ?
                    ORDER BY mp.position_number ASC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $rId, $planId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $tree = [];
                $children = [1 => null, 2 => null, 3 => null]; // Track child nodes for positions 1 and 2

                // Fill child nodes in the appropriate positions
                while ($row = $result->fetch_assoc()) {
                    $positionNumber = (int)$row['position_number'];

                    if ($row['complete']) 
                        $completed = $completed + 1; 

                    // Fetch each member's downline recursively and assign them to their position
                    $children[$positionNumber] = [
                        'name' => $row['username'],
                        'uid' => "#" . $row['user_id']. " (".$row['re_entry_count'].")",
                        'attributes' => [
                            'position' => $positionNumber,
                            'complete' => $row['complete'] ? 'completed' : 'incomplete',
                            'payee_id' => $row['payee_id']
                        ],
                        'children' => getGlobalMatrixTree($conn, $row['id'], $planId)
                    ];
                }

                // Add placeholders for any missing positions
                for ($i = 1; $i <= 3; $i++) {
                    if ($children[$i] === null) {
                        $children[$i] = [
                            'name' => 'Empty',
                            'uid' => '',
                            'attributes' => [
                                'position' => $i,
                                'complete' => 'incomplete',
                                'payee_id' => ''
                            ],
                            'children' => [
                                // [
                                //     'name' => 'Empty',
                                //     'uid' => '',
                                //     'attributes' => [
                                //         'position' => 1,
                                //         'complete' => 'incomplete',
                                //         'payee_id' => ''
                                //     ],
                                //     'children' => []
                                // ],
                                // [
                                //     'name' => 'Empty',
                                //     'uid' => '',
                                //     'attributes' => [
                                //         'position' => 2,
                                //         'complete' => 'incomplete',
                                //         'payee_id' => ''
                                //     ],
                                //     'children' => []
                                // ]
                            ]
                        ];
                    }
                }

                // Append filled children to the tree in correct order
                foreach ($children as $child) {
                    $tree[] = $child;
                }

                return $tree;
            }

            // Start from the root nodes (where upline_id = 0)
            $globalMatrixTree = [];
            $rootQuery = "SELECT mp.id, mp.user_id, u.username, mp.payee_id, mp.complete, mp.re_entry_count 
                          FROM matrix13_plan_purchases mp
                          JOIN users u ON mp.user_id = u.id
                          WHERE mp.upline_id = 0 AND mp.plan_id = ?
                          ORDER BY mp.position_number ASC";
            
            $stmt = $conn->prepare($rootQuery);
            $stmt->bind_param("i", $planId);
            $stmt->execute();
            $rootResult = $stmt->get_result();

            while ($rootRow = $rootResult->fetch_assoc()) {

                $matrixTree = getGlobalMatrixTree($conn, $rootRow['id'], $planId);

                if ($rootRow['complete']) 
                    $completed = $completed + 1; 
                $globalMatrixTree[] = [
                    'name' => $rootRow['username'],
                    'uid' => "#" . $rootRow['user_id'] . " (".$rootRow['re_entry_count'].")",
                    'attributes' => [
                        'position' => 0, // Root position
                        'complete' => $rootRow['complete'] ? 'completed' : 'incomplete',
                        'payee_id' => ''
                    ],
                    'children' => $matrixTree
                ];
            }

            // Count people who joined today in the selected plan
            $joinedTodayQuery = "SELECT COUNT(*) as joined_today FROM matrix13_plan_purchases WHERE plan_id = ? AND DATE(purchase_date) = CURDATE()";
            $joinedTodayStmt = $conn->prepare($joinedTodayQuery);
            $joinedTodayStmt->bind_param("i", $plan_id);
            $joinedTodayStmt->execute();
            $joinedTodayResult = $joinedTodayStmt->get_result();
            $joinedTodayRow = $joinedTodayResult->fetch_assoc();
            $joined_today = $joinedTodayRow['joined_today'];

            $sql = "Select COUNT(*) as joined_today from (SELECT distinct user_id FROM matrix13_plan_purchases WHERE plan_id = ? AND DATE(purchase_date) = CURDATE()) a";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $planId);
            $stmt->execute();
            $plan = $stmt->get_result()->fetch_assoc();
            $joined_today = $plan['joined_today'];

            $sql = "Select count(0) as total_people from (SELECT distinct user_id FROM matrix13_plan_purchases WHERE plan_id = ?) a";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $planId);
            $stmt->execute();
            $plan = $stmt->get_result()->fetch_assoc();
            $total_people = $plan['total_people'];

            $sql = "SELECT count(user_id) as total_filled FROM matrix13_plan_purchases WHERE plan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $planId);
            $stmt->execute();
            $plan = $stmt->get_result()->fetch_assoc();
            $total_filled = $plan['total_filled'];


            echo json_encode([
                'rows' => $globalMatrixTree,
                'total' => $total_people,
                'total_filled' => $total_filled,
                'total_cycled' => $completed,
                'joined_today' => $joined_today
            ]);



            return $this->respond(['success' => true, 'rows' => $rows,
                'totalPages' => $totalPages]); 
        }
    }

    public function getAllTransactions() {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {
            $userid = $postData['userid'];

            $uid = $postData['uid'];
            $page = isset($postData['page']) ? intval($postData['page']) : 1;
            $limit = isset($postData['limit']) ? intval($postData['limit']) : 10;
            $offset = ($page - 1) * $limit;
            $filter_user = "";
            if ($userid)
            {
                $filter_user = " AND c.user_id=" . $userid . " ";
            }
            // Query to fetch  with pagination
            //$query = "SELECT * FROM earnings where user_id = ? LIMIT ? OFFSET ?";
            $query = "SELECT c.id , CONCAT(u.firstname , ' ',u.lastname,' (#',u.id,')') as `user`, c.date_created, c.type, CONCAT(u1.firstname , ' ',u1.lastname,' (#',u1.id,')') as `from`,CONCAT(u2.firstname , ' ',u2.lastname,' (#',u2.id,')') as `To`,  c.plan_id, c.amount , c.status , c.note, c.ref_id, CONCAT(CONCAT(UPPER(LEFT(p1.plan_type, 1)), LOWER(SUBSTRING(p1.plan_type, 2))),' - Level ',p1.level) AS description , running_total 
            from transactions c
            left join users u on c.user_id = u.id 
            left join users u1 on c.from_id = u1.id 
            left join users u2 on c.to_id = u2.id 
            left join sharing_donation p1 on c.plan_id = p1.id
            WHERE (1=1 and c.type <> 'pool') $filter_user 
            order by date_created desc, id desc limit ? OFFSET ?";
              
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii",  $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = $result->fetch_all(MYSQLI_ASSOC);

            // Count total rows
            $totalResult = $conn->query("SELECT COUNT(*) as count from transactions;");
            $totalRow = $totalResult->fetch_assoc();
            $total = $totalRow['count'];

            // Calculate total pages
            $totalPages = ceil($total / $limit);

            return $this->respond(['success' => true, 'rows' => $rows,
                'totalPages' => $totalPages]); 
        }
    }

    public function getSharingPurchases()
    {    
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

            $page = isset($postData['page']) ? intval($postData['page']) : 1;
            $limit = isset($postData['limit']) ? intval($postData['limit']) : 10;
            $status = $postData['status'];
            $userid = $postData['userid'];
            $uid = $postData['uid'];

            $offset = ($page - 1) * $limit;
            $filter_userid="";
            if ($userid) {
                $filter_userid = " AND d.user_id=$userid ";
            }

            $query = " Select d.*,CONCAT(u2.firstname,' ',u2.lastname,' (#',u2.id,'):',commission_id) as commission,CONCAT(u.firstname,' ',u.lastname,' (#',u.id,')') as fullname, CONCAT(CONCAT(UPPER(LEFT(sd.plan_type, 1)),  LOWER(SUBSTRING(sd.plan_type, 2))),' - Level ',sd.level) AS description from sharing_purchase d  left join sharing_donation sd on d.plan_id = sd.id 
                         left join users u on d.user_id=u.id 
                         left join users u2 on d.commission_to=u2.id 
                         where not d.user_id is null and d.status <> '' and d.status <> 'deleted' $filter_userid  
                         order by d.created_at desc";
            if ($status <> "")
            {
                $query = " Select d.*,CONCAT(u.firstname,' ',u.lastname,' (#',u.id,')') as fullname, CONCAT(CONCAT(UPPER(LEFT(sd.plan_type, 1)), LOWER(SUBSTRING(sd.plan_type, 2))),' - Level ',sd.level) AS description from sharing_purchase d left join sharing_donation sd on d.plan_id = sd.id left join users u on d.user_id=u.id  left join users u2 on d.commission_to=u2.id 
                         where not d.user_id is null and d.status <> '' and d.status <> 'deleted'  $filter_userid and d.status = '$status' 
                         order by d.created_at desc";
            }

            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = $result->fetch_all(MYSQLI_ASSOC);

            // Count total rows
            $url = "SELECT COUNT(*) as count from sharing_purchase d left join sharing_donation sd on d.plan_id = sd.id WHERE not d.user_id is null and d.status <> '' and d.status <> 'deleted' $filter_userid ";
            if ($status <> "")
            {
                $url = "SELECT COUNT(*) as count from sharing_purchase d left join sharing_donation sd on d.plan_id = sd.id WHERE not d.user_id is null  and d.status <> '' and d.status <> 'deleted' and status = '$status' $filter_userid ";
            }
            $totalResult = $conn->query($url);
            $totalRow = $totalResult->fetch_assoc();
            $total = $totalRow['count'];

            // Calculate total pages
            $totalPages = ceil($total / $limit);

            return $this->respond(['success' => true, 'rows' => $rows,
                'totalPages' => $totalPages,
                'totalrows' => $totalRow]); 
        }
    } 

    public function deleteUser()
    {    
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {
            // Extract user data from JSON
                $id = $postData['id']; 
                $uid = $postData['uid']; 

                $stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE id = ?");
                $stmt->bind_param("i", $id);

                try {
                    // Execute the update statement
                    if ($stmt->execute()) {
                        $data = json_encode(['userid' => $id]);
                        add_admin_log($uid,"Delete User",$data);
                        return $this->respond(['success' => true, 'message' => 'Row Deleted successfully']); 
                    } else {
                        return $this->respond(['success' => false, 'message' => 'Execution failed']); 
                    }
                } catch (Exception $e) {
                    // Handle any exceptions
                    return $this->respond(['success' => false, 'message' => 'Error: ' . $e->getMessage()]); 
                }

        }
    }   


    public function changeMatrixPlacement()
    {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

          // Extract user data from JSON
            $id = $postData['id']; 
            $sponsor_id = $postData['sponsor_id']; 
            $user_fullname = $postData['fullname'];
            $uid = $postData['uid'];

            $url = "SELECT id, upline_user_id from sharing_matrix WHERE user_id = $id";
            $res = $conn->query($url);
            $result = $res->fetch_assoc();
            $from = $result['upline_user_id'];            
            $record_id = $result['id'];     


            $url = "SELECT id from sharing_matrix WHERE user_id = $sponsor_id";
            $res = $conn->query($url);
            $result = $res->fetch_assoc();
            $record_id_sponsor = $result['id'];            

            $sql = "UPDATE sharing_matrix SET upline_id = $record_id_sponsor,  upline_user_id = $sponsor_id  WHERE id = $record_id ";
            $stmt = $conn->prepare($sql);
            $stmt->execute();

            $sql = "UPDATE users SET placement_id = $sponsor_id WHERE id = $id";
            $stmt = $conn->prepare($sql);
            $stmt->execute();            

            // Rearrange entry_position for previous placement
            $sql = "SELECT id FROM sharing_matrix WHERE upline_user_id = ? ORDER BY entry_position ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $from);
            $stmt->execute();
            $result = $stmt->get_result();

            $position = 1;
            while ($row = $result->fetch_assoc()) {
                $updateSql = "UPDATE sharing_matrix SET entry_position = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ii", $position, $row['id']);
                $updateStmt->execute();
                $position++;
            }

            // Rearrange entry_position for present placement
            $sql = "SELECT id FROM sharing_matrix WHERE upline_user_id = ? ORDER BY entry_position ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $sponsor_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $position = 1;
            while ($row = $result->fetch_assoc()) {
                $updateSql = "UPDATE sharing_matrix SET entry_position = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ii", $position, $row['id']);
                $updateStmt->execute();
                $position++;
            }


            //Add admin log
            $data = ['message'=>'Change placement of '.$user_fullname.' to '.$sponsor_id,
                     'userid' => $id,
                     'sharing_matrix_id' => $record_id,
                     'from'=> $from ,
                     'to' => $sponsor_id
            ];
            $data = json_encode($data);
            add_admin_log($uid,"Change Placement",$data);

            return $this->respond(['success' => true, 'message' => 'Record updated successfully']); 

        }
    }            


    public function changeCodedSponsor()
    {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

          // Extract user data from JSON
            $id = $postData['id']; 
            $user_fullname = $postData['fullname'];
            $uid = $postData['uid'];

            $sponsor_input = $postData['sponsor_id'];
            $sponsor_id = null;

            // Check if numeric (ID)
            if (is_numeric($sponsor_input)) {
                $query = "SELECT id FROM users WHERE id = $sponsor_input";
            } else {
                $sponsor_input_safe = $conn->real_escape_string($sponsor_input);
                $query = "SELECT id FROM users WHERE username = '$sponsor_input_safe' OR email = '$sponsor_input_safe'";
            }

            $res = $conn->query($query);
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $sponsor_id = $row['id'];
            } else {
                return $this->respond(['success' => false, 'message' => 'The Destination user is not found.']);
            }


            // Set default sponsor_id if null
            if (is_null($sponsor_id)) {
                $url = "SELECT signup_sponsor_id from users d WHERE d.id = $id";
                $res = $conn->query($url);
                $result = $res->fetch_assoc();
                $sponsor_id = $result['signup_sponsor_id'];
            }

            // START

            $ret = addToSharingMatrix($id,$sponsor_id);
            if ($ret['status'] == "error")
            {
                return $this->respond(['success' => false, 'message' =>$ret['message']]); 
            }

            $stmt = $conn->prepare("UPDATE users SET placement_id = ?, status='active' WHERE id = ? ");
            $stmt->bind_param("ii", $sponsor_id, $id);

            try {
                // Execute the update statement
                if ($stmt->execute()) {

                    //Add admin log
                    $data = ['message'=>'Change coded sponsor of '.$user_fullname.' to '.$sponsor_id,
                             'userid' => $id,
                             'to_sponsor_id' => $sponsor_id
                    ];
                    $data = json_encode($data);

                    //End admin log
                    return $this->respond(['success' => true, 'message' => 'Record updated successfully']); 

                } else {
                    // Return error response if execution failed
                    return $this->respond(['success' => false, 'message' => 'Execution failed']);                
                }
            } catch (Exception $e) {
                // Handle any exceptions
                return $this->respond(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);                
            }




            return $this->respond([
                'success' => false, 'message' => 'Invalid data received'
            ]);
        }
    }            


    public function changeRank()
    {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

          // Extract user data from JSON
            $id = $postData['id']; 
            $rank = $postData['rank']; 
            $uid = $postData['uid'];

            // START 
            $sql = "UPDATE users SET rank = '$rank' WHERE id = $id ";
            $stmt = $conn->prepare($sql);
            try {
                // Execute the update statement
                if ($stmt->execute()) {

                    //Add admin log
                    $data = ['message'=>'Rank change to  '.$rank,
                             'userid' => $id
                   ];
                    $data = json_encode($data);
                    add_admin_log($uid,"Change Rank",$data);
                    //End admin log
                    return $this->respond(['success' => true, 'message' => 'Record updated successfully','sql' => $sql]); 

                } else {
                    // Return error response if execution failed
                    return $this->respond(['success' => false, 'message' => 'Execution failed']);                
                }
            } catch (Exception $e) {
                // Handle any exceptions
                return $this->respond(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);                
            }




            return $this->respond([
                'success' => false, 'message' => 'Invalid data received'
            ]);
        }
    }  

    public function changeSponsor()
    {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

          // Extract user data from JSON
            $id = $postData['id']; 
            $sponsor_id = $postData['sponsor_id']; 
            $user_fullname = $postData['fullname'];
            $uid = $postData['uid'];

            // START 

            $stmt = $conn->prepare("UPDATE users SET sponsor_id = ? WHERE id = ? ");
            $stmt->bind_param("ii", $sponsor_id, $id);

            try {
                // Execute the update statement
                if ($stmt->execute()) {

                    //Add admin log
                    $data = ['message'=>'Change sponsor of '.$user_fullname.' to '.$sponsor_id,
                             'userid' => $id,
                             'to_sponsor_id' => $sponsor_id
                    ];
                    $data = json_encode($data);
                    add_admin_log($uid,"Change Sponsor",$data);
                    //End admin log
                    return $this->respond(['success' => true, 'message' => 'Record updated successfully']); 

                } else {
                    // Return error response if execution failed
                    return $this->respond(['success' => false, 'message' => 'Execution failed']);                
                }
            } catch (Exception $e) {
                // Handle any exceptions
                return $this->respond(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);                
            }




            return $this->respond([
                'success' => false, 'message' => 'Invalid data received'
            ]);
        }
    }            


    public function changeUsername()
    {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

          // Extract user data from JSON
            $userId = $postData['id'];
            $newusername= $postData['newusername']; 
            $uid = $postData['uid'];

            //Save the password separately
            if (!empty($newusername))
            {

                    // die($newpassword);
                    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->bind_param("si", $newusername, $userId);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE site_links SET reference = ? WHERE user_id = ? AND site_id IN ('41', '47')");
                    $stmt->bind_param("si", $newusername, $userId);  // "s" for string (newusername), "i" for integer (userId)
                    $stmt->execute();
                    $stmt->close();                    

                    //Add admin log
                    $data = ['message'=>'Change username of '.$newusername,
                             'userid' => $userId
                    ];
                    $data = json_encode($data);
                    add_admin_log($uid,"Change username",$data);
                    //End admin log

                    return $this->respond(['success' => true, 'message' => 'Username updated successfully']);

            }

            return $this->respond([
                'success' => false, 'message' => 'Invalid data received'
            ]);
        }
    }

    public function changePassword()
    {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

          // Extract user data from JSON
            $userId = $postData['id'];
            $newpassword= $postData['newpwd']; 
            $user_fullname = $postData['fullname'];
            $uid = $postData['uid'];

            // START 

            //Save the password separately
            if (!empty($postData['newpwd']))
            {

                    $password = password_hash($newpassword, PASSWORD_DEFAULT);    
                    // die($newpassword);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $password, $userId);
                    $stmt->execute();
                    // Close statement
                    $stmt->close();

                    //Add admin log
                    $data = ['message'=>'Change password of '.$user_fullname,
                             'userid' => $userId
                    ];
                    $data = json_encode($data);
                    add_admin_log($uid,"Change Password",$data);
                    //End admin log

                    return $this->respond(['success' => true, 'message' => 'Password updated successfully']);

            }

            return $this->respond([
                'success' => false, 'message' => 'Invalid data received'
            ]);
        }
    }

    public function adminAdjustment()
    {
        global $conn;  
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {

            // Extract user data from JSON
            $uid = $postData['uid'];
            $user_id = $postData['id']; 
            $amount = $postData['amount']; 
            $notes = $postData['notes']; 
            $selectedtype = $postData['selectedtype']; 

            $tablename = 'transactions'; 
            if ($selectedtype == 'ewallet')
                $tablename = 'ewallets'; 

            $sql = "INSERT INTO `$tablename`(`user_id`, `amount`, `type`, `note`, `status`, `date_created`) VALUES ($user_id, $amount, 'adminadjustment', '$notes','completed',NOW());"; 
            $stmt = $conn->prepare($sql);


            try {
                // Execute the update statement
                if ($stmt->execute()) {

                    if ($selectedtype == 'ewallet') {
                        UpdateRunningTotals_Ewallet($user_id);
                        add_activity_log($user_id,'ewallet_adminadjustment',"Adjustment of $amount USD");
                    } else {
                        UpdateRunningTotals($user_id);
                        add_activity_log($user_id,'adminadjustment',"Adjustment of $amount USD");
                    }

                    return $this->respond(['success' => true, 'message' => 'Admin Adjustment added  successfully']);
                } else {
                    // Return error response if execution failed
                    return $this->respond(['success' => false, 'message' => 'Execution failed']);
                }
            } catch (Exception $e) {
                // Handle any exceptions
                return $this->respond(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }

            // Close statement
            $stmt->close();
        } else {
            return $this->respond([
                'success' => false, 'message' => 'Invalid data received'
            ]);
        }

    }

    public function getHoldingCankCount() {
        global $conn; 
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) 
        {
            $uid = $postData['uid'];

            $url = "SELECT COUNT(*) as count from users d WHERE d.sponsor_id = $uid and d.status = 'holding'";
            $totalResult = $conn->query($url);
            $totalRow = $totalResult->fetch_assoc();
            $total = $totalRow['count'];

            return $this->respond([
                'success' => true,
                'count' => $total
            ]);
        }
    }

    public function getHoldingTank()
    {
        global $conn; 
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) 
        {
            $page = isset($postData['page']) ? intval($postData['page']) : 1;
            $limit = isset($postData['limit']) ? intval($postData['limit']) : 10;
            $status = $postData['status'];
            $userid = $postData['userid'];
            $offset = ($page - 1) * $limit;
            $uid = $postData['uid'];

            $filter_userid="";
            $filter_status="";
            
            if ($uid <> 2 && $uid <> 2 && $uid <> 173) {
                $filter_userid = " AND u.id <> 1 AND u.id <> 2 "; 
            }
                
            if ($userid) {
                $filter_userid = $filter_userid." AND u.id=$userid ";
            }
            if ($status <> "")
            {
                $filter_status = " AND u.status='$status' ";
            }

            $query = " SELECT 
                    u.id,
                    u.username,
                    CONCAT(CONCAT(u.firstname, ' ', u.lastname), ' (', u.username, ')') AS fullname, 
                    u.email,
                    u.status,
                    u.sponsor_id,
                    u.register_ip_location,
                    u.register_ip_address,
                    u.date_created,
                    CONCAT(s.firstname, ' ', s.lastname, ' (#', u.sponsor_id, ')') AS sponsor_name
                FROM 
                    users u
                LEFT JOIN 
                    users s ON u.sponsor_id = s.id
                WHERE 
                    (u.status = 'holding' and u.sponsor_id = '$uid' ) $filter_userid $filter_status
                GROUP BY 
                    u.id, u.firstname, u.lastname, u.sponsor_id, s.firstname, s.lastname
                ORDER BY 
                    u.id ASC;";


            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            $totalRows = $result->num_rows;
            $rows = $result->fetch_all(MYSQLI_ASSOC);


            // // Count total rows
            // $url = "SELECT COUNT(*) as count from users d WHERE d.status <> 'deleted' $filter_userid ";
            // if ($status <> "")
            // {
            //  $url = "SELECT COUNT(*) as count from users d WHERE d.status <> 'deleted' and status = '$status' $filter_userid ";
            // }
            // $totalResult = $conn->query($url);
            // $totalRow = $totalResult->fetch_assoc();
            // $total = $totalRow['count'];

            // // Calculate total pages
            // $totalPages = ceil($total / $limit);

            return $this->respond([
                'status' => 'success',
                'rows' => $rows,
               // 'totalPages' => $totalPages,
                'totalrows' => $totalRows
            ]);

        }


    }


    public function getAllUsers()
    {
        global $conn; 
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) 
        {
            $page = isset($postData['page']) ? intval($postData['page']) : 1;
            $limit = isset($postData['limit']) ? intval($postData['limit']) : 10;
            $status = $postData['status'];
            $userid = $postData['userid'];
            $offset = ($page - 1) * $limit;
            $uid = $postData['uid'];

            $filter_userid="";
            $filter_status="";
            
          //  if ($uid <> 2) {
            //    $filter_userid = " AND u.id <> 1 "; 
           // }
                
            if ($userid) {
                $filter_userid = $filter_userid." AND u.id=$userid ";
            }
            if ($status <> "")
            {
                $filter_status = " AND u.status='$status' ";
            }


$query = " SELECT 
    u.id,
    u.username,
    CONCAT(CONCAT(u.firstname, ' ', u.lastname), ' (', u.username, ')') AS fullname, 
    u.email,
    u.status,
    u.rank,
    u.sponsor_id,
    u.register_ip_location,
    u.register_ip_address,
    u.logged_ip,
    u.logged_location,
    u.logged_time,
    u.reward_cap,
    u.date_created,
    sm.upline_user_id as placement_id,
    CONCAT(s.firstname, ' ', s.lastname, ' (#', u.sponsor_id, ')') AS sponsor_name,
    CONCAT(mp.firstname, ' ', mp.lastname, ' (#', sm.upline_user_id, ')') AS placement,
    COALESCE(totals.total_credits, 0) AS total_credits,
    COALESCE(totals.total_debits, 0) AS total_debits,
    COALESCE(totals.total_commissions, 0) AS total_commissions,
    COALESCE(totals.total_payments, 0) AS total_payments,
    COALESCE(totals.total_amount, 0) AS total_amount,
    COALESCE(rtotals.rtotal_amount, 0) AS reward_total,
    COALESCE(ewtotals.ewtotal_amount, 0) AS ewallet_total,
    COALESCE(downline_counts.no_of_downlines, 0) AS no_of_downlines,
    COALESCE(deposits.total_deposit, 0) AS total_deposit,
    COALESCE(success_withdrawal.total_withdrawal, 0) AS total_withdrawal,
    COALESCE(pending_withdrawal.pending_withdrawal, 0) AS pending_withdrawal,

    GROUP_CONCAT(
        DISTINCT 
        CASE 
            WHEN pp.is_admin_made = 1 THEN 
                CONCAT('@', CONCAT(UPPER(LEFT(p.plan_type, 1)), LOWER(SUBSTRING(p.plan_type, 2))), '-L', p.level)
            ELSE 
                CONCAT(CONCAT(UPPER(LEFT(p.plan_type, 1)), LOWER(SUBSTRING(p.plan_type, 2))), '-L', p.level)
        END
        ORDER BY p.plan_type
        SEPARATOR '  '
    ) AS sharing_purchase 
FROM 
    users u
LEFT JOIN 
    users s ON u.sponsor_id = s.id
LEFT JOIN 
    sharing_matrix sm ON u.id = sm.user_id 
LEFT JOIN 
    users mp ON sm.upline_user_id = mp.id
LEFT JOIN (
    SELECT 
        t.user_id,
        SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END) AS total_credits,
        SUM(CASE WHEN t.amount < 0 THEN t.amount ELSE 0 END) AS total_debits,
        SUM(CASE WHEN t.type = 'commission' THEN t.amount ELSE 0 END) AS total_commissions,
        SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) AS total_payments,
        SUM(t.amount) AS total_amount
    FROM 
        transactions t
    GROUP BY 
        t.user_id
) totals ON u.id = totals.user_id
LEFT JOIN (
    SELECT 
        rt.user_id,
        SUM(CASE WHEN rt.amount > 0 THEN rt.amount ELSE 0 END) AS rtotal_credits,
        SUM(CASE WHEN rt.amount < 0 THEN rt.amount ELSE 0 END) AS rtotal_debits,
        SUM(CASE WHEN rt.type = 'commission' THEN rt.amount ELSE 0 END) AS rtotal_commissions,
        SUM(CASE WHEN rt.type = 'payment' THEN rt.amount ELSE 0 END) AS rtotal_payments,
        SUM(rt.amount) AS rtotal_amount
    FROM 
        rewards rt
    GROUP BY 
        rt.user_id
) rtotals ON u.id = rtotals.user_id                
LEFT JOIN (
    SELECT 
        ew.user_id,
        SUM(CASE WHEN ew.amount > 0 THEN ew.amount ELSE 0 END) AS ewtotal_credits,
        SUM(CASE WHEN ew.amount < 0 THEN ew.amount ELSE 0 END) AS ewtotal_debits,
        SUM(CASE WHEN ew.type = 'commission' THEN ew.amount ELSE 0 END) AS ewtotal_commissions,
        SUM(CASE WHEN ew.type = 'payment' THEN ew.amount ELSE 0 END) AS ewtotal_payments,
        SUM(ew.amount) AS ewtotal_amount
    FROM 
        ewallets ew
    GROUP BY 
        ew.user_id
) ewtotals ON u.id = ewtotals.user_id 
LEFT JOIN (
    SELECT 
        sponsor_id, 
        COUNT(*) AS no_of_downlines
    FROM 
        users
    GROUP BY 
        sponsor_id
) downline_counts ON u.id = downline_counts.sponsor_id
LEFT JOIN (
    SELECT 
        user_id, 
        SUM(amount) AS total_deposit
    FROM 
        deposits
    WHERE 
        status = 'success'
    GROUP BY 
        user_id
) deposits ON u.id = deposits.user_id
LEFT JOIN (
    SELECT 
        user_id, 
        SUM(amount) AS total_withdrawal
    FROM 
        withdraws
    WHERE 
        status = 'success'
    GROUP BY 
        user_id
) success_withdrawal ON u.id = success_withdrawal.user_id
LEFT JOIN (
    SELECT 
        user_id, 
        SUM(amount) AS pending_withdrawal
    FROM 
        withdraws
    WHERE 
        status = 'pending'
    GROUP BY 
        user_id
) pending_withdrawal ON u.id = pending_withdrawal.user_id
LEFT JOIN 
    sharing_purchase pp ON u.id = pp.user_id and pp.paid=1
LEFT JOIN 
    sharing_donation p ON pp.plan_id = p.id
WHERE 
    (u.status <> 'deleted'  and u.id <> 1 ) $filter_userid $filter_status
GROUP BY 
    u.id, u.firstname, u.lastname, u.sponsor_id, s.firstname, s.lastname
ORDER BY 
    u.id DESC;";



            // return $this->respond([
            //     'status' => 'error',
            //     'message' => $query,
            // ]);

            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            $totalRows = $result->num_rows;

            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $rows = utf8ize($rows);


            // // Count total rows
            // $url = "SELECT COUNT(*) as count from users d WHERE d.status <> 'deleted' $filter_userid ";
            // if ($status <> "")
            // {
            //  $url = "SELECT COUNT(*) as count from users d WHERE d.status <> 'deleted' and status = '$status' $filter_userid ";
            // }
            // $totalResult = $conn->query($url);
            // $totalRow = $totalResult->fetch_assoc();
            // $total = $totalRow['count'];

            // // Calculate total pages
            // $totalPages = ceil($total / $limit);

            return $this->respond([
                'status' => 'success',
                'rows' => $rows,
               // 'totalPages' => $totalPages,
                'totalrows' => $totalRows
            ]);

        }


    }

    public function getActivities()
    {
        global $conn; 
        $postData = json_decode(file_get_contents("php://input"), true);

        $page = isset($postData['page']) ? intval($postData['page']) : 1;
        $limit = isset($postData['limit']) ? intval($postData['limit']) : 10;
        $event = $postData['event'];
        $userid = $postData['userid'];
        $offset = ($page - 1) * $limit;
        $uid = $postData['uid'];

        $filter_userid = "";
        $filter_event = "";

        if ($userid) {
            $filter_userid = " AND a.user_id = ?";
        }
        if ($event != "") {
            $filter_event = " AND a.type = ?";
        }

        $query = "SELECT a.id, a.user_id, 
                    CONCAT(CONCAT(u.firstname, ' ', u.lastname), ' (', u.username, ')') AS fullname, 
                    a.type, a.data, a.ip_address, a.ip_location, a.date_created 
                  FROM activity_log a 
                  LEFT JOIN users u ON a.user_id = u.id 
                  WHERE 1=1 $filter_userid $filter_event 
                  ORDER BY date_created DESC 
                  LIMIT ?, ?";

        $stmt = $conn->prepare($query);

        // Bind parameters dynamically
        $params = [];
        $types = "";

        if ($userid) {
            $types .= "i";
            $params[] = $userid;
        }
        if ($event != "") {
            $types .= "s";
            $params[] = $event;
        }

        // For limit and offset
        $types .= "ii";
        $params[] = $offset;
        $params[] = $limit;

        // Use ... to unpack parameters
        $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $rows = utf8ize($rows);

        return $this->respond([
            'status' => 'success',
            'rows' => $rows
        ]);
    }

    public function getAllusersdropdown() {
        global $conn; 
        $postData = json_decode(file_get_contents("php://input"), true);
        $search = $postData['search']; 
        $uid = $postData['uid'];

        if (empty($search))
            $query = "SELECT id, username, CONCAT(firstname,' ',lastname, ' (#',id,')') as full_name from users where (id <> 1 and status <> 'deleted' and status <> 'holding') order by full_name ";
        else 
            $query = "SELECT id, username, CONCAT(firstname,' ',lastname, ' (#',id,')') as full_name from users where (id <> 1 and status <> 'deleted' and status <> 'holding') AND (CONCAT(firstname, ' ', lastname, ' (#', id, ')') LIKE '%".$search."%' or email LIKE '%".$search."%' or username LIKE '%".$search."%') order by full_name ";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $rows = utf8ize($rows);
        return $this->respond([
            'status' => 'success',
            'rows' => $rows
        ]);
        
    }

    public function updateDonationPurchases() {

        global $conn; 

        $postData = json_decode(file_get_contents("php://input"), true);


        if ($postData) {

            // Extract user data from JSON
            $updatedPlans = $postData['data']; 
            // Extract user data from JSON
            $user_id = $postData['user_id']; 
            $user_fullname = $postData['fullname'];

            foreach ($updatedPlans as $id => $value) {
                if ($value)
                {

                    $planrow = $conn->query("Select plan_type,level from sharing_donation where id = $id ");
                    $planrow = $planrow->fetch_assoc();

                    $plan_type = $planrow['plan_type'];
                    $plan_level = $planrow['level'];

                    $row = $conn->query("Select paid from sharing_purchase where user_id = $user_id and plan_id = $id ");
                    $row = $row->fetch_assoc();

                    $ret = processDonation($user_id, $plan_type, $plan_level,1,1);

                    // $paid = 0;
                    // if ($row !== null && isset($row['paid']) && $row['paid'] !== null) {
                    //     $paid = $row['paid'];
                    // }
                    // if (!$paid)
                    // {
                    //     $ret = processDonation($user_id, $plan_type, $plan_level,1,1);
                    // }

                } else {
                    $stmt = $conn->prepare("UPDATE `sharing_purchase` SET paid = 0, status='deleted' where `user_id` = $user_id and `plan_id` = $id;"); 
                    $stmt->execute();
                }

            }

            UpdateRewardCap($user_id);
            
            return $this->respond([
            'status' => 'success',
            'message' => 'Database updated successfully'
            ]);

        } else {
            // Return error if no data received
            return $this->respond([
            'status' => 'error',
            'message' => 'Invalid data received'
            ]);
        }

    }

    public function getCategories() {

        global $conn; 

        $postData = json_decode(file_get_contents("php://input"), true);


        if ($postData) {

            $uid = $postData['uid']; 
            
            $sql = "SELECT id, category_name, created_at, updated_at FROM site_category ORDER BY id ASC";
            $result = $conn->query($sql);

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }

            return $this->respond([
                'status' => 'success',
                'data' => $data
            ]);

        } else {
            // Return error if no data received
            return $this->respond([
            'status' => 'error',
            'message' => 'Invalid data received'
            ]);
        }

    }

    public function addCategory() {
        global $conn; 
        $postData = json_decode(file_get_contents("php://input"), true);

        if ($postData) {
            $uid = $postData['uid'] ?? null;
            $category_name = trim($postData['category_name'] ?? "");

            if (empty($category_name)) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Category name is required'
                ]);
            }

            $stmt = $conn->prepare("INSERT INTO site_category (category_name, created_at, updated_at) VALUES (?, NOW(), NOW())");
            $stmt->bind_param("s", $category_name);

            if ($stmt->execute()) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'Category added'
                ]);
            } else {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Insert failed'
                ]);
            }
        }

        return $this->respond([
            'status' => 'error',
            'message' => 'Invalid data received'
        ]);
    }

    public function updateCategory() {
        global $conn;
        $postData = json_decode(file_get_contents("php://input"), true);

        if ($postData) {
            $uid = $postData['uid'] ?? null;
            $id = $postData['id'] ?? null;
            $category_name = trim($postData['category_name'] ?? "");

            if (!$id || empty($category_name)) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'ID and category name are required'
                ]);
            }

            $stmt = $conn->prepare("UPDATE site_category SET category_name = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $category_name, $id);

            if ($stmt->execute()) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'Category updated'
                ]);
            } else {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Update failed'
                ]);
            }
        }

        return $this->respond([
            'status' => 'error',
            'message' => 'Invalid data received'
        ]);
    }

    public function deleteCategory() {
        global $conn;
        $postData = json_decode(file_get_contents("php://input"), true);

        if ($postData) {
            $uid = $postData['uid'] ?? null;
            $id = $postData['id'] ?? null;

            if (!$id) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'ID is required'
                ]);
            }

            // Optional: check if sites are using this category
            $check = $conn->prepare("SELECT COUNT(*) as cnt FROM sites WHERE category_id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $res = $check->get_result()->fetch_assoc();

            if ($res['cnt'] > 0) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Cannot delete category. It is used by some sites.'
                ]);
            }

            $stmt = $conn->prepare("DELETE FROM site_category WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'Category deleted'
                ]);
            } else {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Delete failed'
                ]);
            }
        }

        return $this->respond([
            'status' => 'error',
            'message' => 'Invalid data received'
        ]);
    }


}
