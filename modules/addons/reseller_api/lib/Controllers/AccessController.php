<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class AccessController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function getCredentials() {
        $id = (int)$this->request['params']['id'];

        $s = Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblhosting.packageid')
            ->where('mod_rapi_services.reseller_id', $this->key->reseller_id)
            ->where('mod_rapi_services.service_id', $id)
            ->select('tblhosting.username', 'tblhosting.password', 'tblhosting.server', 'mod_rapi_product_access.service_type')
            ->first();

        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        Capsule::table('mod_rapi_audit_log')->insert([
            'actor' => 'key:' . $this->key->key_id,
            'action' => 'read_credentials',
            'target' => 'service:' . $id,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $serverIp = null;
        if ($s->server) {
            $server = Capsule::table('tblservers')->where('id', $s->server)->first();
            if ($server) $serverIp = $server->ipaddress ?: $server->hostname;
        }

        return ['status'=>200, 'data'=>[
            'username' => $s->username,
            'password' => \decrypt($s->password),
            'ssh' => in_array($s->service_type, ['vps', 'vds', 'mac']) ? ['host' => $serverIp, 'port' => 22] : null,
            'panel' => $s->service_type === 'shared' ? ['url' => "https://{$serverIp}:2083"] : null,
            'vnc' => null,
            'rdp' => null
        ]];
    }

    public function resetPassword() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];

        $s = Capsule::table('mod_rapi_services')->where('service_id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        $newPass = $body['password'] ?? substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()'), 0, 20);

        Capsule::table('tblhosting')->where('id', $id)->update([
            'password' => \encrypt($newPass)
        ]);

        $res = \localAPI('ModuleChangePw', ['accountid' => $id], 'Reseller API System');
        if ($res['result'] !== 'success') {
            throw new ApiError(502, 'MODULE_ERROR', $res['message']);
        }

        return ['status'=>200, 'data'=>['id'=>$id, 'password'=>$newPass]];
    }

    public function sso() {
        $id = (int)$this->request['params']['id'];
        $body = $this->request['parsed_body'];

        $s = Capsule::table('mod_rapi_services')->where('service_id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$s) throw new ApiError(404, 'NOT_FOUND');

        $res = \localAPI('ModuleServiceSingleSignOn', ['serviceid' => $id], 'Reseller API System');

        if ($res['result'] !== 'success') {
             throw new ApiError(502, 'MODULE_ERROR', $res['message'] ?? 'SSO not supported');
        }

        return ['status'=>200, 'data'=>[
            'url' => $res['redirectTo'],
            'expires_at' => date('Y-m-d\TH:i:s\Z', time() + 60)
        ]];
    }

    public function console() {
        $id = (int)$this->request['params']['id'];
        // Require driver implementation
        return ['status'=>200, 'data'=>['type'=>'novnc', 'url'=>'https://example.com/console', 'expires_at'=>date('Y-m-d\TH:i:s\Z', time() + 60)]];
    }
}
