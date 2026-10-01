<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

class ManagementController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function rebuild() { return ['status'=>202, 'data'=>['id'=>(int)$this->request['params']['id'], 'job'=>['id'=>'job_rb','type'=>'rebuild','status'=>'queued']]]; }
    public function setHostname() { return ['status'=>200, 'data'=>['id'=>(int)$this->request['params']['id'], 'hostname'=>'new.example.com']]; }
    public function listIps() { return ['status'=>200, 'data'=>['items'=>[]]]; }
    public function setRdns() { return ['status'=>200, 'data'=>['ip'=>$this->request['params']['ip'], 'rdns'=>'rdns.example.com']]; }
    public function listSnapshots() { return ['status'=>200, 'data'=>['items'=>[]]]; }
    public function createSnapshot() { return ['status'=>202, 'data'=>['job'=>['id'=>'job_snap','type'=>'snapshot.create','status'=>'queued']]]; }
    public function restoreSnapshot() { return ['status'=>202, 'data'=>['job'=>['id'=>'job_res','type'=>'snapshot.restore','status'=>'queued']]]; }
    public function deleteSnapshot() { return ['status'=>202, 'data'=>['job'=>['id'=>'job_del','type'=>'snapshot.delete','status'=>'queued']]]; }
    public function rescue() { return ['status'=>202, 'data'=>['job'=>['id'=>'job_rescue','type'=>'rescue','status'=>'queued']]]; }
    public function listActions() { return ['status'=>200, 'data'=>['items'=>[]]]; }
    public function runAction() { return ['status'=>200, 'data'=>['action'=>$this->request['params']['action'],'result'=>'success']]; }
}
