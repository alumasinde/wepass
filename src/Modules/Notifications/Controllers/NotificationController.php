<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Notifications\Repositories\NotificationRepository;

final class NotificationController
{
    private NotificationRepository $repository;

    public function __construct()
    {
        $this->repository = new NotificationRepository(DB::connect());
    }

    public function index(Request $request): never
    {
        $userId=(int)($_SESSION['user']['id']??0);
        if($userId<1) Response::json(['success'=>false,'message'=>'Not authenticated.'],401);
        $limit=max(1,min(100,(int)$request->input('limit',20)));
        $offset=max(0,(int)$request->input('offset',0));
        Response::json(['success'=>true,'data'=>$this->repository->listForUser($userId,$limit,$offset),'unread_count'=>$this->repository->unreadCount($userId)]);
    }

    public function unreadCount(Request $request): never
    {
        $userId=(int)($_SESSION['user']['id']??0);
        if($userId<1) Response::json(['success'=>false,'message'=>'Not authenticated.'],401);
        Response::json(['success'=>true,'unread_count'=>$this->repository->unreadCount($userId)]);
    }

    public function markRead(Request $request, int $id): never
    {
        $userId=(int)($_SESSION['user']['id']??0);
        if($userId<1) Response::json(['success'=>false,'message'=>'Not authenticated.'],401);
        if(!$this->repository->markRead($id,$userId)) Response::json(['success'=>false,'message'=>'Notification not found.'],404);
        Response::json(['success'=>true]);
    }

    public function markAllRead(Request $request): never
    {
        $userId=(int)($_SESSION['user']['id']??0);
        if($userId<1) Response::json(['success'=>false,'message'=>'Not authenticated.'],401);
        Response::json(['success'=>true,'updated'=>$this->repository->markAllRead($userId)]);
    }
}
