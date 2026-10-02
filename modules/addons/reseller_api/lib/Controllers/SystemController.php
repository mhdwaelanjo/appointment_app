<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

class SystemController
{
    private $request;
    private $key;
    private $settings;

    public function __construct($request, $key, $settings)
    {
        $this->request = $request;
        $this->key = $key;
        $this->settings = $settings;
    }

    public function ping()
    {
        return [
            'status' => 200,
            'data' => [
                'pong' => true,
                'server_time' => date('Y-m-d\TH:i:s\Z'),
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'key_id' => $this->key->key_id,
                'environment' => $this->key->environment
            ]
        ];
    }

    public function me()
    {
        $reseller = \WHMCS\Database\Capsule::table('mod_rapi_resellers')
            ->where('id', $this->key->reseller_id)
            ->first();

        // Count active services...
        $activeServices = \WHMCS\Database\Capsule::table('mod_rapi_services')
            ->join('tblhosting', 'tblhosting.id', '=', 'mod_rapi_services.service_id')
            ->where('mod_rapi_services.reseller_id', $reseller->id)
            ->where('tblhosting.domainstatus', 'Active')
            ->count();

        $quotas = json_decode($reseller->quota_json, true) ?: [
            'max_services' => 500,
            'active_services' => $activeServices,
            'orders_per_hour' => 60,
            'per_type' => []
        ];

        $quotas['active_services'] = $activeServices;

        return [
            'status' => 200,
            'data' => [
                'reseller' => [
                    'id' => $reseller->id,
                    'name' => $reseller->name,
                    'email' => 'reseller@example.com', // Fetch from tblclients in real implementation
                    'whmcs_client_id' => $reseller->client_id,
                    'status' => $reseller->status,
                    'billing_mode' => $reseller->billing_mode ?: $this->settings['billing_mode']
                ],
                'key' => [
                    'id' => $this->key->key_id,
                    'label' => $this->key->label,
                    'scopes' => json_decode($this->key->scopes, true),
                    'expires_at' => $this->key->expires_at ? date('Y-m-d\TH:i:s\Z', strtotime($this->key->expires_at)) : null
                ],
                'quotas' => $quotas,
                'rate_limit' => [
                    'per_minute' => (int)($this->settings['rate_limit_per_minute'] ?? 120),
                    'burst' => (int)($this->settings['rate_limit_burst'] ?? 30)
                ]
            ]
        ];
    }
}
