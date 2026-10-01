<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class PowerController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function power() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];
        $action = strtolower($body['action'] ?? '');

        $allowed = ['start', 'stop', 'shutdown', 'reboot', 'reset'];
        if (!in_array($action, $allowed)) {
            throw new ApiError(400, 'VALIDATION_FAILED', 'Invalid power action');
        }

        $s = Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblhosting.packageid')
            ->where('mod_rapi_services.reseller_id', $this->key->reseller_id)
            ->where('mod_rapi_services.service_id', $id)
            ->select('mod_rapi_product_access.driver', 'tblhosting.domainstatus')
            ->first();

        if (!$s) throw new ApiError(404, 'NOT_FOUND');
        if (strtolower($s->domainstatus) !== 'active') {
             throw new ApiError(422, 'SERVICE_NOT_ACTIVE');
        }

        $driverClass = "\\WHMCS\\Module\\Addon\\ResellerApi\\Drivers\\" . ucfirst($s->driver) . "Driver";
        if (!class_exists($driverClass)) {
            throw new ApiError(422, 'ACTION_NOT_SUPPORTED');
        }

        $driver = new $driverClass();
        $res = $driver->power($id, $action);

        return ['status'=>202, 'data'=>[
            'id'=>$id,
            'action'=>$action,
            'job'=>['id'=>'job_pwr','type'=>'power','status'=>'running']
        ]];
    }
}
