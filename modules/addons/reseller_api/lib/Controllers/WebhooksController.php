<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

class WebhooksController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function listWebhooks() { return ['status'=>200, 'data'=>['items'=>[]]]; }
    public function createWebhook() { return ['status'=>201, 'data'=>['id'=>'wh_1', 'url'=>$this->request['parsed_body']['url'], 'status'=>'active']]; }
    public function deleteWebhook() { return ['status'=>204, 'data'=>[]]; }
    public function testWebhook() { return ['status'=>200, 'data'=>['result'=>'sent']]; }
}
