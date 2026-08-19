<?php

namespace App\Modules\Gatepass\Services;

/** Cached QR image generator. The encoded payload is an opaque credential, never a DB id. */
class QRService
{
    private const CACHE_DIR='/uploads/qrcodes';
    private const MAX_ATTEMPTS=3;

    public function getOrCreate(string $gatepassNumber,?string $payload=null):?string{
        $safe=$this->safeFileName($gatepassNumber);$relative=self::CACHE_DIR."/{$safe}.png";$absolute=$this->publicPath().$relative;
        if(is_file($absolute)&&filesize($absolute)>0)return $relative;
        $bytes=$this->fetchWithRetry($payload??$gatepassNumber);if($bytes===null)return null;
        $dir=dirname($absolute);if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir)){error_log("QRService: could not create cache dir {$dir}");return null;}
        if(@file_put_contents($absolute,$bytes)===false){error_log("QRService: could not write {$absolute}");return null;}return $relative;
    }
    public function regenerate(string $gatepassNumber,?string $payload=null):?string{$safe=$this->safeFileName($gatepassNumber);$absolute=$this->publicPath().self::CACHE_DIR."/{$safe}.png";if(is_file($absolute))@unlink($absolute);return $this->getOrCreate($gatepassNumber,$payload);}
    private function fetchWithRetry(string $payload):?string{$base=rtrim((string)config('qr.service_url',''),'/');if($base===''){error_log('QRService: qr.service_url is not configured.');return null;}$url=$base.'/generate?code='.urlencode($payload);$timeout=(int)config('qr.timeout_seconds',5);for($attempt=1;$attempt<=self::MAX_ATTEMPTS;$attempt++){if(($result=$this->fetch($url,$timeout))!==null)return $result;if($attempt<self::MAX_ATTEMPTS)usleep(200000*$attempt);}error_log('QRService: QR generation failed after retries.');return null;}
    private function fetch(string $url,int $timeout):?string{if(!function_exists('curl_init'))return $this->fetchViaStream($url,$timeout);$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>$timeout,CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS]);$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);if($body===false||$error!==''||$status!==200||$body==='')return null;return $body;}
    private function fetchViaStream(string $url,int $timeout):?string{$context=stream_context_create(['http'=>['timeout'=>$timeout,'ignore_errors'=>true]]);$body=@file_get_contents($url,false,$context);return ($body===false||$body==='')?null:$body;}
    private function safeFileName(string $value):string{return preg_replace('/[^A-Za-z0-9_-]/','_',$value);}
    private function publicPath():string{return base_path('public');}
}
