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
        $products = Capsule::table('mod_rapi_product_access')
            ->join('tblproducts', 'tblproducts.id', '=', 'mod_rapi_product_access.product_id')
            ->where(function($q) {
                $q->whereNull('mod_rapi_product_access.reseller_id')
                  ->orWhere('mod_rapi_product_access.reseller_id', $this->key->reseller_id);
            })
            ->select('tblproducts.*', 'mod_rapi_product_access.slug', 'mod_rapi_product_access.service_type', 'mod_rapi_product_access.allowed_cycles')
            ->get();

        $items = [];
        foreach ($products as $p) {
            $items[] = [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'type' => $p->service_type,
                'description' => $p->description,
                'billing_cycles' => json_decode($p->allowed_cycles, true) ?: ['monthly'],
                'pricing' => [
                    'currency' => 'USD',
                    'monthly' => '10.00',
                    'setup_fee' => '0.00'
                ],
                'capabilities' => ['power', 'rebuild', 'usage'] // In real world fetch from driver
            ];
        }

        return [
            'status' => 200,
            'data' => [
                'items' => $items,
                'pagination' => ['page' => 1, 'per_page' => 25, 'total' => count($items), 'total_pages' => 1]
            ]
        ];
    }

    // Other stubs...
    public function getProduct() { return ['status' => 200, 'data' => []]; }
    public function getConfigOptions() { return ['status' => 200, 'data' => []]; }
    public function getOsTemplates() { return ['status' => 200, 'data' => []]; }
    public function getAddons() { return ['status' => 200, 'data' => []]; }
    public function getLocations() { return ['status' => 200, 'data' => []]; }
}
