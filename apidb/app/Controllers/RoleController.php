<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

include 'app/Helpers/db.php';
include 'app/Helpers/functions.php';

class RoleController extends ResourceController
{
    // GET all roles
    public function list()
    {
        global $conn;

        try {
            $sql = "SELECT * FROM roles ORDER BY id ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $roles = $result->fetch_all(MYSQLI_ASSOC);

            foreach ($roles as &$r) {
                $r['permissions'] = $r['permissions'] ? json_decode($r['permissions'], true) : [];
            }

            return $this->response->setJSON(['status' => 'success', 'roles' => $roles]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // POST save (insert/update) role
    public function save()
    {
        global $conn;

        $postData = json_decode(file_get_contents("php://input"), true);
        $id          = $postData['id'] ?? null;
        $name        = $postData['name'] ?? "";
        $permissions = $postData['permissions'] ?? [];
        $permissionsJson = is_array($permissions) ? json_encode($permissions) : (string)$permissions;

        try {
            if ($id) {
                $sql = "UPDATE roles SET name=?, permissions=?, updated_at=NOW() WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $name, $permissionsJson, $id);
                $stmt->execute();
            } else {
                $sql = "INSERT INTO roles (name, permissions) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $name, $permissionsJson);
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

    // DELETE role
    public function delete($id = null)
    {
        global $conn;

        $postData = json_decode(file_get_contents("php://input"), true);
        $id = $id ?? ($postData['id'] ?? null);

        if (!$id) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Missing role ID.'
            ]);
        }

        try {
            $sql = "DELETE FROM roles WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();

            return $this->response->setJSON(['status' => 'deleted']);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
