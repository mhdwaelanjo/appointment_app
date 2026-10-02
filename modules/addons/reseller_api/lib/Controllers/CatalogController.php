<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class CatalogController
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

    public function listProducts()
    {
        $page = (int)($this->request['params']['page'] ?? 1);
        $perPage = (int)($this->request['params']['per_page'] ?? 25);
        $offset = ($page - 1) * $perPage;

        $query = Capsule::table('mod_rapi_product_access')
            ->join('tblproducts', 'tblproducts.id', '=', 'mod_rapi_product_access.product_id')
            ->where(function($q) {
                $q->whereNull('mod_rapi_product_access.reseller_id')
                  ->orWhere('mod_rapi_product_access.reseller_id', $this->key->reseller_id);
            });

        if (!empty($this->request['params']['type'])) {
            $query->where('mod_rapi_product_access.service_type', $this->request['params']['type']);
        }

        // Count total for pagination
        $total = $query->count();

        $products = $query->select('tblproducts.*', 'mod_rapi_product_access.slug', 'mod_rapi_product_access.service_type', 'mod_rapi_product_access.allowed_cycles', 'mod_rapi_product_access.driver')
            ->offset($offset)->limit($perPage)->get();

        $items = [];
        foreach ($products as $p) {
            // Fetch pricing
            $pricing = Capsule::table('tblpricing')
                ->where('type', 'product')
                ->where('relid', $p->id)
                ->first();

            // Load driver to get capabilities
            $driverClass = "\\WHMCS\\Module\\Addon\\ResellerApi\\Drivers\\" . ucfirst($p->driver) . "Driver";
            $capabilities = [];
            if (class_exists($driverClass)) {
                $driver = new $driverClass();
                $capabilities = $driver->capabilities();
            }

            $items[] = [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'type' => $p->service_type,
                'group' => '', // Real impl requires fetching from tblproductgroups
                'description' => $p->description,
                'billing_cycles' => json_decode($p->allowed_cycles, true) ?: ['monthly'],
                'pricing' => $pricing ? [
                    'currency' => 'USD', // Simplified
                    'monthly' => $pricing->monthly,
                    'quarterly' => $pricing->quarterly,
                    'annually' => $pricing->annually,
                    'setup_fee' => $pricing->msetupfee
                ] : null,
                'capabilities' => $capabilities
            ];
        }

        return [
            'status' => 200,
            'data' => [
                'items' => $items,
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => ceil($total / $perPage)]
            ]
        ];
    }

    public function getProduct()
    {
        $productId = $this->request['params']['id'];

        $p = Capsule::table('tblproducts')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblproducts.id')
            ->where(function($q) {
                $q->whereNull('mod_rapi_product_access.reseller_id')
                  ->orWhere('mod_rapi_product_access.reseller_id', $this->key->reseller_id);
            })
            ->where(function($q) use ($productId) {
                $q->where('tblproducts.id', $productId)
                  ->orWhere('mod_rapi_product_access.slug', $productId);
            })
            ->select('tblproducts.*', 'mod_rapi_product_access.slug', 'mod_rapi_product_access.service_type', 'mod_rapi_product_access.allowed_cycles', 'mod_rapi_product_access.driver')
            ->first();

        if (!$p) throw new ApiError(404, 'NOT_FOUND');

        $pricing = Capsule::table('tblpricing')
                ->where('type', 'product')
                ->where('relid', $p->id)
                ->first();

        $driverClass = "\\WHMCS\\Module\\Addon\\ResellerApi\\Drivers\\" . ucfirst($p->driver) . "Driver";
        $capabilities = class_exists($driverClass) ? (new $driverClass())->capabilities() : [];

        return ['status' => 200, 'data' => [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'type' => $p->service_type,
            'billing_cycles' => json_decode($p->allowed_cycles, true) ?: ['monthly'],
            'pricing' => $pricing ? [
                'currency' => 'USD',
                'monthly' => $pricing->monthly,
                'setup_fee' => $pricing->msetupfee
            ] : null,
            'has_config_options' => Capsule::table('tblproductconfiglinks')->where('pid', $p->id)->exists(),
            'has_addons' => Capsule::table('tbladdons')->whereRaw("FIND_IN_SET(?, packages)", [$p->id])->exists(),
            'stock' => ['enabled' => (bool)$p->stockcontrol, 'available' => $p->stockcontrol ? $p->qty : null],
            'capabilities' => $capabilities
        ]];
    }

    public function getConfigOptions()
    {
        $productId = $this->request['params']['id'];

        // Resolve ID/slug to actual ID
        $p = Capsule::table('mod_rapi_product_access')
            ->where('slug', $productId)->orWhere('product_id', $productId)->first();

        if (!$p) throw new ApiError(404, 'NOT_FOUND');
        $realPid = $p->product_id;

        $links = Capsule::table('tblproductconfiglinks')->where('pid', $realPid)->get();
        $group_ids = array_column($links->toArray(), 'gid');

        $optionsData = [];
        if (!empty($group_ids)) {
            $options = Capsule::table('tblproductconfigoptions')->whereIn('gid', $group_ids)->where('hidden', 0)->get();

            foreach ($options as $opt) {
                $subOptions = Capsule::table('tblproductconfigoptionssub')->where('configid', $opt->id)->where('hidden', 0)->get();
                $values = [];

                foreach ($subOptions as $sub) {
                    $price = Capsule::table('tblpricing')->where('type', 'configoptions')->where('relid', $sub->id)->first();
                    $values[] = [
                        'value' => $sub->id,
                        'label' => $sub->optionname,
                        'price' => $price ? ['monthly' => $price->monthly] : ['monthly' => '0.00']
                    ];
                }

                $typeMap = ['1' => 'dropdown', '2' => 'yesno', '3' => 'yesno', '4' => 'quantity'];

                $optionsData[] = [
                    'key' => preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($opt->optionname)),
                    'whmcs_option_id' => $opt->id,
                    'name' => $opt->optionname,
                    'type' => $typeMap[$opt->optiontype] ?? 'dropdown',
                    'values' => $values
                ];
            }
        }

        return ['status' => 200, 'data' => [
            'product_id' => $realPid,
            'options' => $optionsData
        ]];
    }

    public function getOsTemplates()
    {
        // Real implementation fetches OS templates dynamically from Proxmox/Virtualizor module
        return ['status' => 200, 'data' => [
            'items' => [
                ['id' => 'ubuntu-24.04', 'name' => 'Ubuntu 24.04 LTS', 'family' => 'linux', 'arch' => 'x86_64']
            ]
        ]];
    }

    public function getAddons()
    {
        $productId = $this->request['params']['id'];
        $p = Capsule::table('mod_rapi_product_access')
            ->where('slug', $productId)->orWhere('product_id', $productId)->first();

        if (!$p) throw new ApiError(404, 'NOT_FOUND');
        $realPid = $p->product_id;

        $addons = Capsule::table('tbladdons')
            ->whereRaw("FIND_IN_SET(?, packages)", [$realPid])
            ->where('showonorder', 1)
            ->get();

        $items = [];
        foreach ($addons as $addon) {
            $price = Capsule::table('tblpricing')->where('type', 'addon')->where('relid', $addon->id)->first();
            $items[] = [
                'id' => $addon->id,
                'name' => $addon->name,
                'billing_cycles' => ['monthly'],
                'pricing' => $price ? ['monthly' => $price->monthly] : null
            ];
        }

        return ['status' => 200, 'data' => ['items' => $items]];
    }

    public function getLocations()
    {
        // Real implementation looks for 'location' configurable options
        return ['status' => 200, 'data' => [
            'items' => [
                ['code' => 'default', 'name' => 'Default Location', 'types' => ['shared', 'vps', 'vds', 'mac', 'vmware']]
            ]
        ]];
    }
}
