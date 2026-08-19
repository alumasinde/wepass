<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\DepartmentService;
use RuntimeException;

class DepartmentController extends Controller
{
    public function __construct(
        private DepartmentService $service
    ) {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
    }

    public function index()
    {
        return $this->view('Settings::departments', [
            'departments' => $this->service->all(),
        ]);
    }

    /**
     * Unified input handler (JSON + form-data safe)
     */
    private function input(Request $request): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (is_array($json)) {
            return $json;
        }

        // fallback for form POST
        return $request->all() ?? $_POST ?? [];
    }

 public function store(Request $request)
{
    $body = $this->input($request);

    $name = trim($body['name'] ?? '');
    $code = trim($body['code'] ?? '');
    if ($name === '') {
        return Response::redirect('/settings/departments?error=Department name is required');
    }

    if ($code === '') {
        return Response::redirect('/settings/departments?error=Department code is required');
    }

    try {
        $this->service->create($name, $code);

        return Response::redirect('/settings/departments?success=Department created successfully');

    } catch (RuntimeException $e) {
        return Response::redirect('/settings/departments?error=' . urlencode($e->getMessage()));

    } catch (\Throwable $e) {
        return Response::redirect('/settings/departments?error=Server error');
    }
}

    public function update(Request $request)
    {
        $body = $this->input($request);

        $id = (int) ($body['id'] ?? 0);
        $name = trim($body['name'] ?? '');
        $code = trim($body['code'] ?? '');

        if ($id <= 0) {
            return Response::json(['message' => 'Invalid department ID.'], 422);
        }

        if ($name === '') {
            return Response::json(['message' => 'Department name is required.'], 422);
        }

        try {
            $updated = $this->service->update($id, $name);

            return Response::json([
                'success' => true,
                'data' => $updated
            ]);

        } catch (RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            return Response::json(['message' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        $body = $this->input($request);
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            return Response::json(['message' => 'Invalid department ID.'], 422);
        }

        try {
            $this->service->delete($id);

            return Response::json(['success' => true]);

        } catch (RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            return Response::json(['message' => $e->getMessage()], 500);
        }
    }
    public function toggle(Request $request)
    {
        $body = $this->input($request);
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            return Response::json([
                'message' => 'Invalid department ID.'
            ], 422);
        }

        try {
            $this->service->toggle($id);

            return Response::json([
                'success' => true
            ]);

        } catch (RuntimeException $e) {
            return Response::json([
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            return Response::json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}