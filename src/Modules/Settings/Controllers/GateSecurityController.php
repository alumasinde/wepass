<?php

declare(strict_types=1);

namespace App\Modules\Settings\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\GateSecurityAdminService;
use Throwable;

final class GateSecurityController extends Controller
{
    public function __construct(private GateSecurityAdminService $service) {}

    public function index(): mixed
    {
        return $this->view('Settings::gate-security',[
            'gates'=>$this->service->gates(),
            'devices'=>$this->service->devices(),
            'assignments'=>$this->service->assignments(),
            'users'=>$this->service->users(),
            'scans'=>$this->service->recentScans(),
            'flash'=>$_SESSION['gate_security_flash']??null,
        ]);
    }

    private function input(Request $request): array
    {
        $json=json_decode(file_get_contents('php://input'),true);
        return is_array($json)?$json:($request->all()??$_POST??[]);
    }

    private function redirect(string $message,string $type='success'): never
    {
        $_SESSION['gate_security_flash']=['type'=>$type,'message'=>$message];
        Response::redirect('/settings/gate-security');
    }

    public function createGate(Request $request): never
    {
        try{$b=$this->input($request);$this->service->createGate((string)($b['name']??''),(string)($b['code']??''),$b['location']??null);$this->redirect('Gate created successfully.');}
        catch(Throwable $e){$this->redirect($e->getMessage(),'danger');}
    }

    public function createDevice(Request $request): never
    {
        try{$b=$this->input($request);$secret=$this->service->createDevice((string)($b['device_uuid']??''),(string)($b['device_name']??''));$_SESSION['gate_security_flash']=['type'=>'success','message'=>'Device approved. Save this secret now: '.$secret.' It cannot be retrieved later.'];Response::redirect('/settings/gate-security');}
        catch(Throwable $e){$this->redirect($e->getMessage(),'danger');}
    }

    public function assign(Request $request): never
    {
        try{$b=$this->input($request);$this->service->assign((int)($b['gate_id']??0),(int)($b['device_id']??0),isset($b['guard_user_id'])&&$b['guard_user_id']!==''?(int)$b['guard_user_id']:null,$b['starts_at']??null,$b['ends_at']??null);$this->redirect('Device assignment saved.');}
        catch(Throwable $e){$this->redirect($e->getMessage(),'danger');}
    }

    public function revokeDevice(Request $request): never
    {
        try{$b=$this->input($request);$this->service->revokeDevice((int)($b['id']??0));$this->redirect('Device revoked.');}
        catch(Throwable $e){$this->redirect($e->getMessage(),'danger');}
    }

    public function deactivateAssignment(Request $request): never
    {
        try{$b=$this->input($request);$this->service->deactivateAssignment((int)($b['id']??0));$this->redirect('Assignment deactivated.');}
        catch(Throwable $e){$this->redirect($e->getMessage(),'danger');}
    }
}
