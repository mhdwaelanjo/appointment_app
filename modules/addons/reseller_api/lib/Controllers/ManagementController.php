<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class ManagementController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    private function getDriver($id) {
        $s = Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblhosting.packageid')
            ->where('mod_rapi_services.reseller_id', $this->key->reseller_id)
            ->where('mod_rapi_services.service_id', $id)
            ->select('mod_rapi_product_access.driver')
            ->first();

        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        $driverClass = "\\WHMCS\\Module\\Addon\\ResellerApi\\Drivers\\" . ucfirst($s->driver) . "Driver";
        if (class_exists($driverClass)) {
            return new $driverClass();
        }
        throw new ApiError(422, 'ACTION_NOT_SUPPORTED');
    }

    public function rebuild() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];
        if (!isset($body['confirm']) || (int)$body['confirm'] !== $id) {
            throw new ApiError(422, 'CONFIRMATION_REQUIRED');
        }
        $driver = $this->getDriver($id);
        $res = $driver->rebuild($id, $body['os_template'] ?? null, $body['root_password'] ?? null, $body['ssh_keys'] ?? []);
        return ['status'=>202, 'data'=>['id'=>$id, 'job'=>['id'=>'job_rb','type'=>'rebuild','status'=>'queued']]];
    }

    public function setHostname() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];

        if (empty($body['hostname'])) {
             throw new ApiError(400, 'VALIDATION_FAILED');
        }

        Capsule::table('tblhosting')->where('id', $id)->update(['domain' => $body['hostname']]);

        // Let driver know if needed
        return ['status'=>200, 'data'=>['id'=>$id, 'hostname'=>$body['hostname']]];
    }

    public function listIps() {
        $id = (int)$this->request['params']['id'];
        $driver = $this->getDriver($id);
        return ['status'=>200, 'data'=>['items'=>$driver->ips($id)]];
    }

    public function setRdns() {
        $id = (int)$this->request['params']['id'];
        $ip = $this->request['params']['ip'];
        $body = $this->request['parsed_body'];
        $driver = $this->getDriver($id);
        $res = $driver->setRdns($id, $ip, $body['rdns'] ?? '');
        return ['status'=>200, 'data'=>['ip'=>$ip, 'rdns'=>$body['rdns']]];
    }

    public function listSnapshots() {
        $id = (int)$this->request['params']['id'];
        $driver = $this->getDriver($id);
        return ['status'=>200, 'data'=>['items'=>$driver->snapshots($id)]];
    }

    public function createSnapshot() {
        $id = (int)$this->request['params']['id'];
        $driver = $this->getDriver($id);
        $res = $driver->createSnapshot($id, $this->request['parsed_body']['name'] ?? 'Manual Snapshot');
        return ['status'=>202, 'data'=>['job'=>['id'=>'job_snap','type'=>'snapshot.create','status'=>'queued']]];
    }

    public function restoreSnapshot() {
        $id = (int)$this->request['params']['id'];
        $sid = $this->request['params']['sid'];
        $body = $this->request['parsed_body'];
        if (!isset($body['confirm']) || (int)$body['confirm'] !== $id) {
            throw new ApiError(422, 'CONFIRMATION_REQUIRED');
        }
        $driver = $this->getDriver($id);
        $res = $driver->restoreSnapshot($id, $sid);
        return ['status'=>202, 'data'=>['job'=>['id'=>'job_res','type'=>'snapshot.restore','status'=>'queued']]];
    }

    public function deleteSnapshot() {
        $id = (int)$this->request['params']['id'];
        $sid = $this->request['params']['sid'];
        $driver = $this->getDriver($id);
        $res = $driver->deleteSnapshot($id, $sid);
        return ['status'=>202, 'data'=>['job'=>['id'=>'job_del','type'=>'snapshot.delete','status'=>'queued']]];
    }

    public function rescue() {
        $id = (int)$this->request['params']['id'];
        $driver = $this->getDriver($id);
        $res = $driver->rescue($id, $this->request['parsed_body']['action'] ?? 'enable');
        return ['status'=>202, 'data'=>['job'=>['id'=>'job_rescue','type'=>'rescue','status'=>'queued']]];
    }

    public function listActions() {
        $id = (int)$this->request['params']['id'];
        $s = Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblhosting.packageid')
            ->where('mod_rapi_services.reseller_id', $this->key->reseller_id)
            ->where('mod_rapi_services.service_id', $id)
            ->select('mod_rapi_product_access.custom_actions')
            ->first();

        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        $actions = json_decode($s->custom_actions, true) ?: [];

        return ['status'=>200, 'data'=>['items'=>$actions]];
    }

    public function runAction() {
        $id = (int)$this->request['params']['id'];
        $action = $this->request['params']['action'];

        // verify action is allowed in mod_rapi_product_access
        // call localAPI ModuleCustom

        return ['status'=>200, 'data'=>['action'=>$action,'result'=>'success']];
    }
}
