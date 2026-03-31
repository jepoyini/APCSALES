<?php
namespace App\Controllers\Cron;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

/**
 * Tiny cron controller to expire overdue P2P orders.
 * - Expires orders where status='PENDING_PAYMENT' AND payment_deadline_at < NOW()
 * - Calls sp_p2p_refund_escrow(order_id) then sets status='EXPIRED'
 * - Batches to avoid long-running locks
 */
class P2PExpire extends BaseController
{
    protected $format = 'json';

    private function db() {
        return Database::connect();
    }

    /**
     * CLI:    php spark route:run cron/p2p-expire
     * HTTP:   GET /cron/p2p-expire/run?key=YOUR_SECRET (optional, if enabled below)
     */
    public function run(): ResponseInterface
    {
        // Safety: allow CLI by default; HTTP requires a secret key.
        $allowHttp = true; // set to false to disable HTTP entirely
        if (!is_cli() && (!$allowHttp || !$this->isValidHttpKey())) {
            return $this->response->setStatusCode(403)->setJSON(['success'=>false,'message'=>'Forbidden']);
        }

        $db = $this->db();

        // Batch size per cron tick (tune as needed)
        $batchSize = (int) (getenv('P2P_EXPIRE_BATCH') ?: 200);

        // Find overdue orders (small lock window, sorted oldest first)
        $overdue = $db->query("
            SELECT id
            FROM p2p_orders
            WHERE status='PENDING_PAYMENT'
              AND payment_deadline_at < NOW()
            ORDER BY payment_deadline_at ASC
            LIMIT ?
        ", [$batchSize])->getResultArray();

        $total = count($overdue);
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($overdue as $row) {
            $oid = (int)$row['id'];
            try {
                // Lock the single order row before action
                $db->transStart();

                $order = $db->query("SELECT id, status FROM p2p_orders WHERE id=? FOR UPDATE", [$oid])->getRowArray();
                if (!$order || $order['status'] !== 'PENDING_PAYMENT') {
                    // Someone else may have updated it
                    $db->transRollback();
                    continue;
                }

                // Refund escrow (remove active lock)
                $db->query("CALL sp_p2p_refund_escrow(?)", [$oid]);

                // Mark expired
                $db->query("UPDATE p2p_orders SET status='EXPIRED', expired_at=NOW(), updated_at=NOW() WHERE id=?", [$oid]);

                $db->transComplete();
                if ($db->transStatus()) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = ['order_id'=>$oid,'error'=>'trans failed'];
                }
            } catch (\Throwable $e) {
                $failed++;
                // Ensure transaction is clean
                if ($db->transStatus() === false) {
                    $db->transRollback();
                }
                $errors[] = ['order_id'=>$oid,'error'=>$e->getMessage()];
            }
        }

        $payload = [
            'success' => true,
            'checked' => $total,
            'expired' => $success,
            'failed'  => $failed,
        ];
        if ($failed > 0) $payload['errors'] = $errors;

        // Human-friendly plain text if CLI; JSON otherwise
        if (is_cli()) {
            $text = sprintf(
                "[%s] P2PExpire done: checked=%d expired=%d failed=%d\n",
                date('Y-m-d H:i:s'),
                $total, $success, $failed
            );
            return $this->response->setStatusCode(200)->setBody($text);
        }
        return $this->response->setStatusCode(200)->setJSON($payload);
    }

    private function isValidHttpKey(): bool
    {
        $key = $this->request->getGet('key') ?? '';
        $secret = getenv('CRON_SECRET') ?: '';
        return $secret !== '' && hash_equals($secret, (string)$key);
    }
}
