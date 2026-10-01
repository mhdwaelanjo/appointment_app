<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

class JobsController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function getJob() { return ['status'=>200, 'data'=>['id'=>$this->request['params']['id'], 'status'=>'succeeded']]; }
}
