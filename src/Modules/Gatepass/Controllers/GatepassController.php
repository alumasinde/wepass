<?php

namespace App\Modules\Gatepass\Controllers;

use App\Core\Auth;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Gatepass\Services\GateSecurityService;
use App\Modules\Gatepass\Services\GatepassService;
use App\Modules\Gatepass\Repositories\GatepassTypeRepository;
use App\Modules\Gatepass\Repositories\GatepassStatusRepository;
use App\Modules\Gatepass\DTOs\GatepassDTO;

class GatepassController
{
    private GatepassService $service; private GateSecurityService $security; private GatepassTypeRepository $typeRepo; private GatepassStatusRepository $statusRepo;
    public function __construct(){ $this->service=new GatepassService();$this->security=new GateSecurityService();$this->typeRepo=new GatepassTypeRepository();$this->statusRepo=new GatepassStatusRepository(); }
    private function user():array{if(empty($_SESSION['user']))Response::abort(401,'Not authenticated.');return $_SESSION['user'];}
    private function findOrFail(int $id):array{$gatepass=$this->service->find($id);if(!$gatepass)Response::abort(404,'Gatepass not found.');return $gatepass;}
    public function index(Request $request):void{$user=$this->user();$gatepasses=$this->service->list((int)$user['id'],(new Permission(Auth::userId()))->allows('gatepass.view_all'));foreach($gatepasses as &$g)$g['actions']=$this->service->getAvailableActions($g);unset($g);View::render('Gatepass::index',['title'=>'Gatepasses','gatepasses'=>$gatepasses,'user'=>$user],'app');}
    public function create(Request $request):void{$user=$this->user();View::render('Gatepass::create',['title'=>'Create Gatepass','types'=>$this->typeRepo->findAll(),'visits'=>$this->service->getVisitsForUser($user['department_id']??null),'user'=>$user,'error'=>$_SESSION['flash']['message']??null],'app');unset($_SESSION['flash']);}
    public function store(Request $request):void{$user=$this->user();try{$dto=GatepassDTO::fromRequest($request->all(),(int)$user['id']);$id=$this->service->create($dto);try{$this->security->issueQrCredential($id);}catch(\Throwable $qrError){error_log('Gatepass QR issuance: '.$qrError->getMessage());}$_SESSION['flash']=['type'=>'success','message'=>'Gatepass created successfully.'];header("Location: /gatepasses/{$id}");exit;}catch(\Throwable $e){$_SESSION['flash']=['type'=>'danger','message'=>$e->getMessage()];header('Location: /gatepasses/create');exit;}}
    public function show(Request $request,int $id):void{$user=$this->user();$gatepass=$this->findOrFail($id);View::render('Gatepass::show',['title'=>'Gatepass '.($gatepass['gatepass_number']??''),'gatepass'=>$gatepass,'items'=>$gatepass['items']??[],'actions'=>$this->service->getAvailableActions($gatepass)],'app');}
    public function edit(Request $request,int $id):void{$user=$this->user();$gatepass=$this->findOrFail($id);View::render('Gatepass::edit',['title'=>'Edit Gatepass','gatepass'=>$gatepass,'items'=>$gatepass['items']??[],'types'=>$this->typeRepo->findAll(),'statuses'=>$this->statusRepo->findAll(),'visits'=>$this->service->getVisitsForUser($user['department_id']??null),'error'=>$_SESSION['flash']['message']??null],'app');unset($_SESSION['flash']);}
    public function update(Request $request,int $id):void{$user=$this->user();$this->findOrFail($id);try{$dto=GatepassDTO::fromRequest($request->all(),(int)$user['id']);$this->service->update($id,$dto);$_SESSION['flash']=['type'=>'success','message'=>'Gatepass updated.'];header("Location: /gatepasses/{$id}");exit;}catch(\Throwable $e){$_SESSION['flash']=['type'=>'danger','message'=>$e->getMessage()];header("Location: /gatepasses/{$id}/edit");exit;}}
    public function delete(Request $request,int $id):void{$this->findOrFail($id);try{$this->service->delete($id);$_SESSION['flash']=['type'=>'success','message'=>'Gatepass deleted.'];}catch(\Throwable $e){$_SESSION['flash']=['type'=>'danger','message'=>$e->getMessage()];}header('Location: /gatepasses');exit;}
    public function checkIn(Request $request,mixed $id):never{$id=(int)$id;$user=$this->user();try{$this->service->checkIn($id,(int)$user['id']);if($request->wantsJson())Response::json(['success'=>true,'message'=>'Checked in successfully.']);$_SESSION['flash']=['type'=>'success','message'=>'Checked in successfully.'];}catch(\Throwable $e){if($request->wantsJson())Response::json(['success'=>false,'message'=>$e->getMessage()],400);$_SESSION['flash']=['type'=>'danger','message'=>$e->getMessage()];}header("Location: /gatepasses/{$id}");exit;}
    public function checkOut(Request $request,mixed $id):never{$id=(int)$id;$user=$this->user();try{$this->service->checkOut($id,(int)$user['id']);if($request->wantsJson())Response::json(['success'=>true,'message'=>'Checked out successfully.']);$_SESSION['flash']=['type'=>'success','message'=>'Checked out successfully.'];}catch(\Throwable $e){if($request->wantsJson())Response::json(['success'=>false,'message'=>$e->getMessage()],400);$_SESSION['flash']=['type'=>'danger','message'=>$e->getMessage()];}header("Location: /gatepasses/{$id}");exit;}
}
