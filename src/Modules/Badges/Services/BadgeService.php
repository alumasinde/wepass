<?php

declare(strict_types=1);

namespace App\Modules\Badges\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Badges\Repositories\BadgeRepository;
use App\Modules\Visits\Repositories\VisitRepository;
use App\Modules\Visitors\Repositories\VisitorRepository;
use PDO;
use Exception;

final class BadgeService
{
    private BadgeRepository $badgeRepo;
    private VisitRepository $visitRepo;
    private VisitorRepository $visitorRepo;
    private PDO $db;

    public function __construct()
    {
        $this->badgeRepo   = new BadgeRepository();
        $this->visitRepo   = new VisitRepository();
        $this->visitorRepo = new VisitorRepository();
        $this->db          = DB::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUE BADGE (Transaction Safe)
    |--------------------------------------------------------------------------
    */
    public function issue(
        int $visitId
    ): string {

        $visit = $this->visitRepo->find($visitId);

        if (!$visit) {
            throw new Exception('Visit not found.');
        }

        if (!$visit['checkin_time']) {
            throw new Exception('Visitor must be checked in first.');
        }

        if ($visit['checkout_time']) {
            throw new Exception('Cannot issue badge after checkout.');
        }

        $visitor = $this->visitorRepo->find(
            (int) $visit['visitor_id']
        );

        if ((int) $visitor['is_blacklisted'] === 1) {
            throw new Exception('Blacklisted visitors cannot receive badges.');
        }

        $this->db->beginTransaction();

        try {
            $badgeCode = $this->generateBadgeCode();

            $this->badgeRepo->issue(
                $visitId,
                $badgeCode
            );

            Audit::log(
                'visit.badge_issued',
                'visit',
                $visitId,
                [
                    
                    'badge_code'=> $badgeCode
                ]
            );

            $this->db->commit();

            return $badgeCode;

        } catch (\Throwable $e) {

            $this->db->rollBack();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN BADGE
    |--------------------------------------------------------------------------
    */
    public function returnBadge(
        int $visitId
    ): void {

        $this->badgeRepo->returnActiveBadge(
            $visitId
        );

        Audit::log(
            'visit.badge_returned',
            'visit',
            $visitId,
            [
]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE BADGE CODE
    |--------------------------------------------------------------------------
    | Configurable via Settings > Badge Numbering (badge_numbering key in
    | tenant_settings) — same pattern as GatepassService::generateGatepassNumber().
    | Falls back to a random code if no setting row exists yet.
    */
    private function generateBadgeCode(): string
    {
        $stmt = $this->db->prepare("
            SELECT config_json
            FROM   tenant_settings
            WHERE  setting_key = 'badge_numbering'
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return 'BDG-' . strtoupper(bin2hex(random_bytes(4)));
        }

        $config = json_decode($row['config_json'], true) ?? [];
        $config = array_merge([
            'prefix'       => 'BDG',
            'mode'         => 'sequential', // sequential | random
            'include_year' => false,
            'padding'      => 5,
            'reset_yearly' => false,
            'current_year' => (int) date('Y'),
            'sequence'     => 1,
        ], $config);

        if ($config['mode'] === 'random') {
            return ($config['prefix'] ?: 'BDG') . '-' . strtoupper(bin2hex(random_bytes(4)));
        }

        $year = date('Y');

        if ($config['reset_yearly'] && (string) $config['current_year'] !== $year) {
            $config['sequence']     = 1;
            $config['current_year'] = (int) $year;
        }

        $parts = [];

        if (!empty($config['prefix'])) {
            $parts[] = $config['prefix'];
        }
        if (!empty($config['include_year'])) {
            $parts[] = $year;
        }

        $parts[] = str_pad((string) $config['sequence'], (int) $config['padding'], '0', STR_PAD_LEFT);
        $config['sequence']++;

        $this->db->prepare("
            UPDATE tenant_settings
            SET    config_json = :cfg
            WHERE  setting_key = 'badge_numbering'
        ")->execute([':cfg' => json_encode($config)]);

        return implode('-', $parts);
    }
}