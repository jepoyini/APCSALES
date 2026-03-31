<?php
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';

class TransactionController extends ResourceController
{
    use ResponseTrait;

    protected $format = 'json';


    public function getEwalletList()
    {

        $data = $this->request->getJSON();

        if (!isset($data->uid)) {
            return $this->failValidationErrors('Required fields are missing.');
        }

        $db = \Config\Database::connect();

        $query = $db->table('ewallets t')
            ->select([
                't.*',
                'CONCAT(u.firstname, " ", u.lastname, " (#", u.id, ")") AS from_name',
                'CONCAT(sd.plan_type, " Level ", sd.level) AS plan'
            ])
            ->join('users u', 't.from_id = u.id', 'left')
            ->join('sharing_donation sd', 't.plan_id = sd.id', 'left')
            ->where('t.user_id', $data->uid)
            ->orderBy('t.date_created', 'DESC')
            ->get();

        $totalbalance = GetRunningTotal_Ewallet($data->uid);
            
        return $this->respond([
            'success' => true,
            'orders' => $query->getResult(),
            'ewalletbalance' => $totalbalance
        ]);
    }

    public function getRewardList()
    {

        $data = $this->request->getJSON();

        if (!isset($data->uid)) {
            return $this->failValidationErrors('Required fields are missing.');
        }

        $db = \Config\Database::connect();

        $query = $db->table('rewards t')
            ->select([
                't.*',
                'CONCAT(u.firstname, " ", u.lastname, " (#", u.id, ")") AS from_name',
                'CONCAT(sd.plan_type, " Level ", sd.level) AS plan'
            ])
            ->join('users u', 't.from_id = u.id', 'left')
            ->join('sharing_donation sd', 't.plan_id = sd.id', 'left')
            ->where('t.user_id', $data->uid)
            ->orderBy('t.date_created', 'DESC')
            ->get();

        $totalbalance = GetRunningRewardTotal($data->uid);
        $rewardcap = GetRewardCap($data->uid);
            
        return $this->respond([
            'success' => true,
            'orders' => $query->getResult(),
            'rewardbalance' => $totalbalance,
            'rewardcap' => $rewardcap,
        ]);
    }

    public function getList()
    {

        $data = $this->request->getJSON();

        if (!isset($data->uid)) {
            return $this->failValidationErrors('Required fields are missing.');
        }

        $db = \Config\Database::connect();

        $query = $db->table('transactions t')
            ->select([
                't.*',
                'CONCAT(u.firstname, " ", u.lastname, " (#", u.id, ")") AS from_name',
                'CONCAT(u2.firstname, " ", u2.lastname, " (#", u2.id, ")") AS to_name',
                'CONCAT(sd.plan_type, " Level ", sd.level) AS plan'
            ])
            ->join('users u', 't.from_id = u.id', 'left')
            ->join('users u2', 't.to_id = u2.id', 'left')
            ->join('sharing_donation sd', 't.plan_id = sd.id', 'left')
            ->where('t.user_id', $data->uid)
            ->orderBy('t.date_created', 'DESC')
            ->get();

        $totalbalance = GetRunningTotal($data->uid);
        $totaldeposit = GetTotalDeposits($data->uid);
        $totalwithdrawal = GetTotalPendingWithdrawal($data->uid);

        return $this->respond([
            'success' => true,
            'orders' => $query->getResult(),
            'totalbalance' => $totalbalance,
            'totaldeposit' => $totaldeposit,
            'totalwithdrawal' => $totalwithdrawal,
        ]);
    }


}
