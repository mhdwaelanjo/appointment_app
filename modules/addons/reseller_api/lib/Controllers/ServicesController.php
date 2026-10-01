<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class ServicesController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function listServices() { return ['status'=>200, 'data'=>['items'=>[],'pagination'=>['page'=>1,'per_page'=>25,'total'=>0,'total_pages'=>0]]]; }
    public function getService() { return ['status'=>200, 'data'=>['id'=>(int)$this->request['params']['id']]]; }
    public function updateService() { return ['status'=>200, 'data'=>['id'=>(int)$this->request['params']['id']]]; }
    public function getStatus() { return ['status'=>200, 'data'=>['service_status'=>'active','power_state'=>'running']]; }
    public function getUsage() { return ['status'=>200, 'data'=>['cpu_percent'=>10]]; }

    public function suspend() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];

        $res = \localAPI('ModuleSuspend', [
            'accountid' => $id,
            'suspendreason' => $body['reason'] ?? 'Reseller API Request'
        ], 'Reseller API System');

        if ($res['result'] !== 'success') {
            throw new ApiError(502, 'MODULE_ERROR', $res['message']);
        }

        return ['status'=>200, 'data'=>['id'=>$id, 'status'=>'suspended']];
    }

    public function unsuspend() {
        $id = (int)$this->request['params']['id'];

        $res = \localAPI('ModuleUnsuspend', [
            'accountid' => $id
        ], 'Reseller API System');

        if ($res['result'] !== 'success') {
            throw new ApiError(502, 'MODULE_ERROR', $res['message']);
        }

        return ['status'=>200, 'data'=>['id'=>$id, 'status'=>'active']];
    }

    public function terminate() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];

        if (!isset($body['confirm']) || (int)$body['confirm'] !== $id) {
            throw new ApiError(422, 'CONFIRMATION_REQUIRED');
        }

        $res = \localAPI('ModuleTerminate', [
            'accountid' => $id
        ], 'Reseller API System');

        if ($res['result'] !== 'success') {
             throw new ApiError(502, 'MODULE_ERROR', $res['message']);
        }

        return ['status'=>202, 'data'=>['id'=>$id, 'status'=>'pending', 'job'=>['id'=>'job_1','type'=>'terminate','status'=>'queued']]];
    }

    public function upgrade() { return ['status'=>202, 'data'=>['id'=>(int)$this->request['params']['id'], 'job'=>['id'=>'job_2','type'=>'upgrade','status'=>'running']]]; }
}
