<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Controllers;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Gatepass\Services\GateScanDecisionService;
use App\Modules\Gatepass\Services\GateScanExecutionService;
use App\Modules\Gatepass\Services\GateSecurityService;
use App\Modules\Gatepass\Services\ScanIdempotencyService;
use Throwable;

final class GateScanController
{
    private GateSecurityService $security;
    private GateScanDecisionService $decisions;
    private GateScanExecutionService $execution;
    private ScanIdempotencyService $idempotency;

    public function __construct()
    {
        $this->security = new GateSecurityService();
        $this->decisions = new GateScanDecisionService($this->security);
        $this->execution = new GateScanExecutionService();
        $this->idempotency = new ScanIdempotencyService();
    }

    public function index(Request $request): void
    {
        View::render('Gatepass::scan', ['title'=>'Gate Scanner','user'=>$_SESSION['user']??null], 'app');
    }

    public function process(Request $request): never
    {
        $user=$_SESSION['user']??null;
        if(!$user)Response::json(['success'=>false,'message'=>'Not authenticated.'],401);
        $gateId=(int)$request->input('gate_id',$request->header('X-Gate-Id')??0);
        $deviceUuid=trim((string)$request->input('device_uuid',$request->header('X-Device-UUID')??''));
        $deviceSecret=trim((string)$request->input('device_secret',$request->header('X-Device-Secret')??''));
        $qrToken=trim((string)$request->input('qr_token',''));
        $scanType=strtoupper(trim((string)$request->input('scan_type','ENTRY')));
        $requestId=trim((string)$request->input('request_id',''))?:bin2hex(random_bytes(16));
        if($gateId<1||$deviceUuid===''||$deviceSecret===''||$qrToken==='')Response::json(['success'=>false,'message'=>'Scanner configuration or QR credential is missing.'],422);

        $clientIp=$_SERVER['REMOTE_ADDR']??'unknown';
        $rateKey='gate-scan:'.hash('sha256',$deviceUuid.'|'.$gateId.'|'.$clientIp);
        if(RateLimiter::tooManyAttempts($rateKey,30))Response::json(['success'=>false,'message'=>'Too many scan attempts. Please try again shortly.','reason_code'=>'RATE_LIMITED','retry_after'=>RateLimiter::availableIn($rateKey)],429);
        RateLimiter::hit($rateKey,60);

        try{
            $claim=$this->idempotency->claim($requestId,[
                'gate_id'=>$gateId,
                'device_id'=>0,
                'guard_user_id'=>(int)$user['id'],
                'qr_token_hash'=>$this->security->hashQrToken($qrToken),
                'client_ip'=>$clientIp,
                'user_agent'=>$_SERVER['HTTP_USER_AGENT']??null,
            ]);
            if(!$claim['claimed']){
                $event=$claim['event']??[];
                Response::json(['success'=>($event['result']??'')==='allowed','message'=>'This scan request has already been processed.','reason_code'=>$event['reason_code']??'REQUEST_ALREADY_PROCESSED','replayed_request'=>true],($event['result']??'')==='allowed'?200:409);
            }

            $decision=$this->decisions->decide($deviceUuid,$deviceSecret,$gateId,$qrToken,(int)$user['id'],$scanType);
            $this->idempotency->complete((int)$claim['event_id'],[
                'gatepass_id'=>$decision['gatepass_id']??null,
                'visit_id'=>null,
                'scan_type'=>$scanType,
                'result'=>strtolower($decision['decision']),
                'reason_code'=>$decision['reason_code'],
                'metadata'=>['action'=>$decision['action'],'device_id'=>$decision['device_id']??null],
            ]);
            if($decision['decision']!=='ALLOW')Response::json(['success'=>false,'message'=>'Scan denied.','reason_code'=>$decision['reason_code'],'action'=>'NONE'],409);
            $this->execution->execute($decision,(int)$user['id'],date('Y-m-d H:i:s'));
            $gatepass=$this->security->resolveQrToken($qrToken);
            Response::json(['success'=>true,'message'=>$decision['action']==='CHECK_IN'?'Gatepass checked in successfully.':'Gatepass checked out successfully.','scan_type'=>$scanType,'reason_code'=>$decision['reason_code'],'action'=>$decision['action'],'gate'=>$decision['gate_id'],'gatepass'=>['id'=>(int)$decision['gatepass_id'],'status'=>$gatepass['status_id']??null],'replayed_request'=>false]);
        }catch(Throwable $e){error_log('GateScanController: '.$e->getMessage());Response::json(['success'=>false,'message'=>config('app.debug',false)?$e->getMessage():'Could not process this gatepass.'],400);}
    }
}
