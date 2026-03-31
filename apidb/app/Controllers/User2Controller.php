<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';


class User2Controller extends ResourceController
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // GET all users + summary
    public function list()
    {
        $search = $this->request->getVar('search') ?? '';
        $role   = $this->request->getVar('role') ?? '';
        $status = $this->request->getVar('status') ?? '';

        $sql = "
            SELECT u.id, u.name, u.email, u.avatar, u.status,
                   r.name AS role_name, r.permissions AS role_permissions
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE (u.name LIKE ? OR u.email LIKE ?)
              AND (? = '' OR r.name = ?)
              AND (? = '' OR u.status = ?)
            ORDER BY u.id DESC
        ";

        $users = $this->db->query($sql, [
            "%$search%", "%$search%",
            $role, $role,
            $status, $status
        ])->getResultArray();

        // Decode role permissions JSON
        foreach ($users as &$u) {
            $u['permissions'] = $u['role_permissions'] ? json_decode($u['role_permissions'], true) : [];
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
        $summary = $this->db->query($summarySql)->getRowArray();

        return $this->response->setJSON([
            'users' => $users,
            'summary' => $summary
        ]);
    }

    // GET user details
    public function details()
    {
        $id = $this->request->getVar('id');

        $sql = "
            SELECT u.*, r.name AS role_name, r.permissions AS role_permissions
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
        ";
        $user = $this->db->query($sql, [$id])->getRowArray();

        if (!$user) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'User not found']);
        }

        $user['permissions'] = $user['role_permissions'] ? json_decode($user['role_permissions'], true) : [];
        unset($user['role_permissions']);

        return $this->response->setJSON(['user' => $user]);
    }

    // POST save user (insert or update)
    public function save()
    {
        $id       = $this->request->getPost('id');
        $name     = $this->request->getPost('name');
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');
        $role_id  = $this->request->getPost('role_id');
        $status   = $this->request->getPost('status') ?? 'active';

        $avatarPath = null;
        $file = $this->request->getFile('avatar');
        if ($file && $file->isValid()) {
            $newName = $file->getRandomName();
            $file->move(FCPATH . 'uploads/avatars', $newName);
            $avatarPath = 'uploads/avatars/' . $newName;
        }

        if ($id) {
            // UPDATE
            $sql = "UPDATE users SET name=?, email=?, role_id=?, status=?, updated_at=NOW()";
            $params = [$name, $email, $role_id, $status];

            if ($avatarPath) {
                $sql .= ", avatar=?";
                $params[] = $avatarPath;
            }

            if ($password) {
                $sql .= ", password=?";
                $params[] = password_hash($password, PASSWORD_BCRYPT);
            }

            $sql .= " WHERE id=?";
            $params[] = $id;

            $this->db->query($sql, $params);
        } else {
            // INSERT
            $sql = "INSERT INTO users (name,email,password,avatar,role_id,status) VALUES (?,?,?,?,?,?)";
            $this->db->query($sql, [
                $name,
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                $avatarPath,
                $role_id,
                $status
            ]);
        }

        return $this->response->setJSON(['status' => 'success']);
    }

    // DELETE user
    public function delete($id = null)
    {
        if (!$id) $id = $this->request->getVar('id');
        $this->db->query("DELETE FROM users WHERE id=?", [$id]);
        return $this->response->setJSON(['status' => 'deleted']);
    }
}
