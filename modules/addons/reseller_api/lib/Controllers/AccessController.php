<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

class AccessController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function getCredentials() { return ['status'=>200, 'data'=>['username'=>'root', 'password'=>'REDACTED']]; }
    public function resetPassword() { return ['status'=>200, 'data'=>['id'=>(int)$this->request['params']['id'], 'password'=>'REDACTED']]; }
    public function sso() { return ['status'=>200, 'data'=>['url'=>'https://example.com/sso', 'expires_at'=>date('Y-m-d\TH:i:s\Z')]]; }
    public function console() { return ['status'=>200, 'data'=>['type'=>'novnc', 'url'=>'https://example.com/console', 'expires_at'=>date('Y-m-d\TH:i:s\Z')]]; }
}
