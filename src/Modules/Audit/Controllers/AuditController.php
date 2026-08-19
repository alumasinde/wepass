<?php

namespace App\Modules\Audit\Controllers;

use App\Core\DB;
use App\Modules\Audit\Repositories\AuditLogRepository;
use App\Modules\Audit\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * AuditController — per-database isolation model.
 * tenant_id attribute removed from all methods.
 */
class AuditController
{
    private AuditService $service;

    public function __construct()
    {
        $db            = DB::connect();
        $repo          = new AuditLogRepository($db);
        $this->service = new AuditService($repo);
    }

    public function index(Request $request, Response $response): Response
    {
        // FIX: removed $request->getAttribute('tenant_id') — per-database isolation
        $params  = $request->getQueryParams();
        $perPage = min((int) ($params['per_page'] ?? 50), 200);
        $page    = max((int) ($params['page']     ?? 1),  1);
        $offset  = ($page - 1) * $perPage;

        $filters    = $this->extractFilters($params);
        // FIX: getLogs() no longer takes tenantId as first arg
        $result     = $this->service->getLogs($filters, $perPage, $offset);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        return $this->json($response, [
            'data' => $result['items'],
            'meta' => [
                'total'       => $result['total'],
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        // FIX: removed $request->getAttribute('tenant_id') — per-database isolation
        $id  = (int) $args['id'];
        $db  = DB::connect();

        $stmt = $db->prepare("
            SELECT al.*,
                   CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                   u.email AS user_email
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$entry) {
            return $this->json($response, ['error' => 'Audit log entry not found'], 404);
        }

        $entry['metadata'] = $entry['metadata'] ? json_decode($entry['metadata'], true) : null;

        return $this->json($response, ['data' => $entry]);
    }

    public function export(Request $request, Response $response): Response
    {
        // FIX: removed $request->getAttribute('tenant_id')
        $params  = $request->getQueryParams();
        $filters = $this->extractFilters($params);
        $result  = $this->service->getLogs($filters, 10000, 0);

        $body    = $response->getBody();
        $columns = ['id', 'created_at', 'user_name', 'user_email', 'action', 'message',
                    'entity_type', 'entity_id', 'ip_address'];

        ob_start();
        $handle = fopen('php://output', 'w');
        fputcsv($handle, $columns);
        foreach ($result['items'] as $row) {
            fputcsv($handle, array_map(fn($col) => $row[$col] ?? '', $columns));
        }
        fclose($handle);
        $body->write(ob_get_clean());

        $filename = 'audit-logs-' . date('Y-m-d') . '.csv';

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->withBody($body);
    }

    private function extractFilters(array $params): array
    {
        $filters = [];
        foreach (['user_id', 'action', 'entity_type', 'entity_id', 'date_from', 'date_to'] as $key) {
            if (isset($params[$key]) && $params[$key] !== '') {
                $filters[$key] = $params[$key];
            }
        }
        return $filters;
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
