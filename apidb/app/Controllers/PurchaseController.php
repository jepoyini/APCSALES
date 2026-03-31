<?php
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';

class PurchaseController extends ResourceController
{
    use ResponseTrait;

    protected $format = 'json';

    public function purchaseItem()
    {



        $data = $this->request->getJSON();

        if (!isset($data->uid) || !isset($data->plan_type) || !isset($data->plan_id) || !isset($data->amount) ) {
            return $this->failValidationErrors('Required fields are missing.');
        }

        // $db = \Config\Database::connect();

        // // Get the plan_id from sharing_donation based on plan_type and level
        // $planQuery = $db->table('sharing_donation')
        //     ->select('id')
        //     ->where('plan_type', $data->plan_type)
        //     ->where('level', $data->plan_id) // plan_id here refers to the LEVEL
        //     ->get()
        //     ->getRow();

        // if (!$planQuery) {
        //     return $this->respond([
        //         'status' => 'error',
        //         'message' => 'Invalid plan type or level.'
        //     ]);
        // }

        // $plan_id = $planQuery->id;

        // $db->table('sharing_purchase')->insert([
        //     'user_id' => $data->uid,
        //     'plan_id' => $plan_id,
        //     'status' => 'completed',
        //     'amount' => $data->amount
        // ]);

        $ret = processDonation($data->uid, $data->plan_type, $data->plan_id);

        if ($ret['status']=='success')
        {
            
            UpdateRewardCap($data->uid);

            return $this->respond([
                'success' => true,
                'message' => 'Purchase recorded successfully.'
            ]);
        } else {
            return $this->respond([
                'success' => false,
                'message' => $ret['message']
            ]);
        }

    }

    public function getOrderHistory()
    {

        $data = $this->request->getJSON();

        if (!isset($data->uid)) {
            return $this->failValidationErrors('Required fields are missing.');
        }

        $db = \Config\Database::connect();

        $query = $db->table('sharing_purchase p')
            ->select('p.*, d.plan_type')
            ->join('sharing_donation d', 'p.plan_id = d.id', 'left')
            ->where('p.user_id', $data->uid)
            ->where('p.status', 'completed')
            ->orderBy('p.created_at', 'DESC')
            ->get();

        return $this->respond([
            'success' => true,
            'orders' => $query->getResult()
        ]);
    }

    public function getPurchasedPlans()
    {
        $data = $this->request->getJSON();

        if (!isset($data->uid)) {
            return $this->failValidationErrors('User ID is required.');
        }

        $db = \Config\Database::connect();

        $query = $db->table('sharing_purchase p')
            ->select('p.plan_id, d.plan_type, d.level')
            ->join('sharing_donation d', 'p.plan_id = d.id', 'left')
            ->where('p.user_id', $data->uid)
            ->where('p.status', 'completed')
            ->get();

        return $this->respond([
            'success' => true,
            'data' => $query->getResult()
        ]);
    }

}
