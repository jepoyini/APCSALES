<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = []; 
    protected $useTimestamps = true;

    public function __construct()
    {
        parent::__construct();
        $this->allowedFields = $this->db->getFieldNames($this->table);
    }

    public function getUserWithSponsor($emailOrUsername)
    {
        return $this->select("users.*, CONCAT(u2.firstname, ' ', u2.lastname) as sponsor_name, u2.id as sponsor_id")
                    ->join('users as u2', 'u2.id = users.sponsor_id', 'left')
                    ->where("users.status !=", "deleted")
                    ->where("users.status !=", "locked")
                    ->where("users.status !=", "blocked")
                    ->where("users.status !=", "banned")
                    ->groupStart() // Open group for OR conditions
                        ->where("users.email", $emailOrUsername)
                        ->orWhere("users.username", $emailOrUsername)
                    ->groupEnd() // Close group
                    ->first();
    }
    


}
