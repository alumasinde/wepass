<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Modules\Notifications\Services\NotificationPreferenceService;

final class NotificationPreferenceController
{
    public function __construct(private readonly NotificationPreferenceService $service = new NotificationPreferenceService()){}

    private function userId(): int
    {
        $id=(int)($_SESSION['user']['id']??0);
        if($id<1) Response::json(['success'=>false,'message'=>'Not authenticated.'],401);
        return $id;
    }

    public function index(Request $request): never
    {
        Response::json(['success'=>true,'data'=>$this->service->listForUser($this->userId())]);
    }

    public function update(Request $request): never
    {
        $userId=$this->userId();
        $eventCode=trim((string)$request->input('event_code',''));
        $channel=strtolower(trim((string)$request->input('channel','')));
        $enabled=(bool)$request->input('enabled',true);
        if($eventCode===''||$channel==='') Response::json(['success'=>false,'message'=>'event_code and channel are required.'],422);
        $this->service->set($userId,$eventCode,$channel,$enabled);
        Response::json(['success'=>true,'event_code'=>$eventCode,'channel'=>$channel,'is_enabled'=>$this->service->isEnabled($userId,$eventCode,$channel)]);
    }
}
