<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class ServicesController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function listServices() {
        $page = (int)($this->request['params']['page'] ?? 1);
        $perPage = (int)($this->request['params']['per_page'] ?? 25);
        $offset = ($page - 1) * $perPage;

        $query = Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblhosting.packageid')
            ->where('mod_rapi_services.reseller_id', $this->key->reseller_id);

        $total = $query->count();
        $services = $query->select('tblhosting.*', 'mod_rapi_services.label', 'mod_rapi_services.external_reference', 'mod_rapi_services.created_at as rapi_created_at', 'mod_rapi_product_access.service_type')
            ->offset($offset)->limit($perPage)->get();

        $items = [];
        foreach ($services as $s) {
            $items[] = [
                'id' => $s->id,
                'status' => strtolower($s->domainstatus),
                'type' => $s->service_type,
                'label' => $s->label,
                'external_reference' => $s->external_reference,
                'hostname' => $s->domain,
                'dedicated_ip' => $s->dedicatedip,
                'created_at' => date('Y-m-d\TH:i:s\Z', strtotime($s->rapi_created_at))
            ];
        }

        return ['status'=>200, 'data'=>[
            'items'=>$items,
            'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>ceil($total/$perPage)]
        ]];
    }

    public function getService() {
        $id = (int)$this->request['params']['id'];

        $s = Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblproducts.id')
            ->where('mod_rapi_services.reseller_id', $this->key->reseller_id)
            ->where('mod_rapi_services.service_id', $id)
            ->select('tblhosting.*', 'tblproducts.name as product_name', 'mod_rapi_services.label', 'mod_rapi_services.external_reference', 'mod_rapi_services.created_at as rapi_created_at', 'mod_rapi_services.last_error', 'mod_rapi_product_access.slug', 'mod_rapi_product_access.service_type', 'mod_rapi_product_access.driver')
            ->first();

        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        $driverClass = "\\WHMCS\\Module\\Addon\\ResellerApi\\Drivers\\" . ucfirst($s->driver) . "Driver";
        $capabilities = class_exists($driverClass) ? (new $driverClass())->capabilities() : [];

        // Fetch Config Options
        $configOptionsData = [];
        $osTemplate = null;

        $configOptionsDb = Capsule::table('tblhostingconfigoptions')
            ->join('tblproductconfigoptions', 'tblproductconfigoptions.id', '=', 'tblhostingconfigoptions.configid')
            ->join('tblproductconfigoptionssub', 'tblproductconfigoptionssub.id', '=', 'tblhostingconfigoptions.optionid')
            ->where('tblhostingconfigoptions.relid', $id)
            ->select('tblproductconfigoptions.optionname as option_name', 'tblproductconfigoptionssub.optionname as option_value', 'tblhostingconfigoptions.qty')
            ->get();

        foreach ($configOptionsDb as $opt) {
            $optName = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($opt->option_name));
            $optValue = explode('|', $opt->option_value)[0] ?? $opt->option_value;
            $optValue = trim($optValue);

            if ($opt->qty > 0) {
                $configOptionsData[$optName] = $opt->qty;
            } else {
                $configOptionsData[$optName] = $optValue;
            }

            // Try to extract OS template if the option seems to represent it
            if (strpos(strtolower($opt->option_name), 'os') !== false || strpos(strtolower($opt->option_name), 'operating system') !== false) {
                 $osTemplate = $optValue;
            }
        }

        return ['status'=>200, 'data'=>[
            'id' => $s->id,
            'order_id' => $s->orderid,
            'status' => strtolower($s->domainstatus),
            'type' => $s->service_type,
            'product' => ['id' => $s->packageid, 'slug' => $s->slug, 'name' => $s->product_name],
            'label' => $s->label,
            'external_reference' => $s->external_reference,
            'hostname' => $s->domain,
            'domain' => $s->service_type === 'shared' ? $s->domain : null,
            'location' => 'default',
            'os_template' => $osTemplate,
            'dedicated_ip' => $s->dedicatedip,
            'assigned_ips' => $s->assignedips ? explode("\n", trim($s->assignedips)) : [],
            'billing_cycle' => strtolower($s->billingcycle),
            'created_at' => date('Y-m-d\TH:i:s\Z', strtotime($s->rapi_created_at)),
            'config_options' => $configOptionsData,
            'capabilities' => $capabilities,
            'last_error' => $s->last_error
        ]];
    }

    public function updateService() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];

        $s = Capsule::table('mod_rapi_services')->where('service_id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        $update = [];
        if (isset($body['label'])) $update['label'] = $body['label'];
        if (isset($body['external_reference'])) $update['external_reference'] = $body['external_reference'];

        if (!empty($update)) {
            Capsule::table('mod_rapi_services')->where('service_id', $id)->update($update);
        }

        if (isset($body['notes'])) {
             Capsule::table('tblhosting')->where('id', $id)->update(['notes' => $body['notes']]);
        }

        return ['status'=>200, 'data'=>['id'=>$id, 'label'=>$body['label'] ?? $s->label]];
    }

    public function getStatus() {
        $id = (int)$this->request['params']['id'];
        $s = Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblhosting.packageid')
            ->where('mod_rapi_services.reseller_id', $this->key->reseller_id)
            ->where('mod_rapi_services.service_id', $id)
            ->select('tblhosting.domainstatus', 'mod_rapi_product_access.driver')
            ->first();

        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        $driverClass = "\\WHMCS\\Module\\Addon\\ResellerApi\\Drivers\\" . ucfirst($s->driver) . "Driver";
        $statusInfo = ['service_status' => strtolower($s->domainstatus), 'power_state' => 'unknown'];

        if (class_exists($driverClass)) {
            $driver = new $driverClass();
            $statusInfo = $driver->status($id);
        }

        return ['status'=>200, 'data'=>[
            'service_status' => $statusInfo['service_status'],
            'power_state' => $statusInfo['power_state'],
            'uptime_seconds' => $statusInfo['uptime_seconds'] ?? null,
            'backend' => $statusInfo['backend'] ?? null,
            'locked' => false,
            'pending_job' => null,
            'checked_at' => date('Y-m-d\TH:i:s\Z')
        ]];
    }

    public function getUsage() {
        $id = (int)$this->request['params']['id'];
        $s = Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblhosting.packageid')
            ->where('mod_rapi_services.reseller_id', $this->key->reseller_id)
            ->where('mod_rapi_services.service_id', $id)
            ->select('mod_rapi_product_access.driver')
            ->first();

        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        $driverClass = "\\WHMCS\\Module\\Addon\\ResellerApi\\Drivers\\" . ucfirst($s->driver) . "Driver";
        $usageInfo = [];

        if (class_exists($driverClass)) {
            $driver = new $driverClass();
            $usageInfo = $driver->usage($id, $this->request['query']['period'] ?? 'current');
        }

        return ['status'=>200, 'data'=> array_merge([
            'disk' => ['used_mb' => 0, 'limit_mb' => 0],
            'bandwidth' => ['used_gb' => 0, 'limit_gb' => 0],
            'cpu_percent' => 0,
            'ram' => ['used_mb' => 0, 'limit_mb' => 0],
            'inodes' => null,
            'updated_at' => date('Y-m-d\TH:i:s\Z')
        ], $usageInfo)];
    }

    public function suspend() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];

        $s = Capsule::table('mod_rapi_services')->where('service_id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$s) throw new ApiError(404, 'NOT_FOUND');

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
        $s = Capsule::table('mod_rapi_services')->where('service_id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$s) throw new ApiError(404, 'NOT_FOUND');

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

        $s = Capsule::table('mod_rapi_services')->where('service_id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$s) throw new ApiError(404, 'NOT_FOUND');

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

    public function upgrade() {
        $id = (int)$this->request['params']['id'];
        $s = Capsule::table('mod_rapi_services')->where('service_id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        // Simplified. Real implementation calls UpgradeProduct localAPI
        return ['status'=>202, 'data'=>['id'=>$id, 'job'=>['id'=>'job_2','type'=>'upgrade','status'=>'running']]];
    }
}
