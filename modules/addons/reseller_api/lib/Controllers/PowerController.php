<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

class PowerController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function power() {
        return ['status'=>202, 'data'=>[
            'id'=>(int)$this->request['params']['id'],
            'action'=>$this->request['parsed_body']['action'] ?? 'unknown',
            'job'=>['id'=>'job_pwr','type'=>'power','status'=>'running']
        ]];
    }
}
