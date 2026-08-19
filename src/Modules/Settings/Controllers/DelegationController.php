<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use DateTime;
use PDO;

/**
 * DelegationController — lets any user name a backup approver for a
 * date range ("while I'm away, X acts for me"). Read by
 * ApprovalService::substituteDelegates() when building the eligible-
 * approver list for a workflow step, under either assignment mode.
 *
 * Deliberately self-service and NOT permission-gated the way
 * Settings screens are — every user manages only their own row,
 * always scoped to Auth::id() server-side, never a posted user id.
 * There's nothing here for an admin to configure on someone else's
 * behalf.
 */
class DelegationController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        $this->db = DB::connect();
    }

    public function index()
    {
        $userId = Auth::id();

        $stmt = $this->db->prepare("
            SELECT ud.delegate_user_id, ud.starts_at, ud.ends_at, u.first_name, u.last_name, u.email
            FROM user_delegates ud
            INNER JOIN users u ON u.id = ud.delegate_user_id
            WHERE ud.user_id = ?
        ");
        $stmt->execute([$userId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $this->db->prepare("
            SELECT id, first_name, last_name, email
            FROM users
            WHERE is_active = 1 AND id != ?
            ORDER BY first_name ASC, last_name ASC
        ");
        $stmt->execute([$userId]);

        return $this->view('Settings::delegation', [
            'title'   => 'My Delegate',
            'current' => $current,
            'users'   => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function update(Request $request)
    {
        $userId     = Auth::id();
        $delegateId = (int) $request->input('delegate_user_id', 0);
        $startsRaw  = (string) $request->input('starts_at', '');
        $endsRaw    = (string) $request->input('ends_at', '');

        if ($delegateId <= 0 || $delegateId === $userId) {
            Response::abort(422, 'Choose a valid delegate — you cannot delegate to yourself.');
        }

        $check = $this->db->prepare("SELECT 1 FROM users WHERE id = ? AND is_active = 1");
        $check->execute([$delegateId]);
        if (!$check->fetch()) {
            Response::abort(422, 'That user is invalid or inactive.');
        }

        $starts = DateTime::createFromFormat('Y-m-d\TH:i', $startsRaw)
            ?: DateTime::createFromFormat('Y-m-d', $startsRaw);
        $ends = DateTime::createFromFormat('Y-m-d\TH:i', $endsRaw)
            ?: DateTime::createFromFormat('Y-m-d', $endsRaw);

        if (!$starts || !$ends || $ends <= $starts) {
            Response::abort(422, 'Invalid date range — the end date must be after the start date.');
        }

        $this->db->prepare("
            INSERT INTO user_delegates (user_id, delegate_user_id, starts_at, ends_at)
            VALUES (:uid, :did, :starts, :ends)
            ON DUPLICATE KEY UPDATE
                delegate_user_id = VALUES(delegate_user_id),
                starts_at        = VALUES(starts_at),
                ends_at          = VALUES(ends_at)
        ")->execute([
            ':uid'    => $userId,
            ':did'    => $delegateId,
            ':starts' => $starts->format('Y-m-d H:i:s'),
            ':ends'   => $ends->format('Y-m-d H:i:s'),
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Delegate saved.'];
        return $this->redirect('/settings/delegation');
    }

    public function clear()
    {
        $this->db->prepare("DELETE FROM user_delegates WHERE user_id = ?")->execute([Auth::id()]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Delegate removed.'];
        return $this->redirect('/settings/delegation');
    }
}
