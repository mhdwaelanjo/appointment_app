<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class OrdersController
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

    public function createOrder()
    {
        $body = $this->request['parsed_body'];

        $reseller = Capsule::table('mod_rapi_resellers')->where('id', $this->key->reseller_id)->first();

        // Lookup product
        $productId = $body['product_id'] ?? null;
        if (!$productId) {
            throw new ApiError(400, 'VALIDATION_FAILED', 'product_id is required');
        }

        $product = Capsule::table('tblproducts')
            ->join('mod_rapi_product_access', 'mod_rapi_product_access.product_id', '=', 'tblproducts.id')
            ->where(function($q) use ($reseller) {
                $q->whereNull('mod_rapi_product_access.reseller_id')
                  ->orWhere('mod_rapi_product_access.reseller_id', $reseller->id);
            })
            ->where(function($q) use ($productId) {
                $q->where('tblproducts.id', $productId)
                  ->orWhere('mod_rapi_product_access.slug', $productId);
            })
            ->select('tblproducts.*', 'mod_rapi_product_access.service_type')
            ->first();

        if (!$product) {
            throw new ApiError(422, 'PRODUCT_NOT_AVAILABLE');
        }

        // Validate cycle
        $billingCycle = strtolower($body['billing_cycle'] ?? '');
        $allowedCycles = ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'];
        if (!in_array($billingCycle, $allowedCycles)) {
             throw new ApiError(422, 'INVALID_BILLING_CYCLE');
        }

        // Prepare localAPI AddOrder call
        $command = 'AddOrder';
        $postData = [
            'clientid' => $reseller->client_id,
            'pid' => $product->id,
            'paymentmethod' => 'reseller_api_account',
            'billingcycle' => $billingCycle,
            'noinvoice' => true,
            'noinvoiceemail' => true,
            'noemail' => true
        ];

        // Set priceoverride to 0 if in free mode
        $mode = $reseller->billing_mode ?: ($this->settings['billing_mode'] ?? 'free');
        if ($mode === 'free') {
            $postData['priceoverride'] = 0;
        }

        if (isset($body['hostname'])) $postData['hostname'] = $body['hostname'];
        if (isset($body['domain'])) $postData['domain'] = $body['domain'];
        if (isset($body['config_options'])) {
            $postData['configoptions'] = base64_encode(serialize($body['config_options']));
        }
        if (isset($body['addons'])) $postData['addons'] = implode(',', $body['addons']);
        if (isset($body['custom_fields'])) {
             // Basic mapping for customfields array in real impl
        }

        // AddOrder returns orderid, productids (comma separated)
        $addOrderResult = \localAPI($command, $postData, 'Reseller API System');

        if ($addOrderResult['result'] !== 'success') {
             throw new ApiError(502, 'PROVISIONING_FAILED', $addOrderResult['message']);
        }

        $orderId = $addOrderResult['orderid'];
        $productIds = explode(',', $addOrderResult['productids']);
        $serviceId = $productIds[0];

        // Accept Order
        $acceptCommand = 'AcceptOrder';
        $acceptData = [
            'orderid' => $orderId,
            'autosetup' => true,
            'sendemail' => false
        ];
        $acceptResult = \localAPI($acceptCommand, $acceptData, 'Reseller API System');

        // Tag the service
        Capsule::table('mod_rapi_services')->insert([
            'service_id' => $serviceId,
            'reseller_id' => $this->key->reseller_id,
            'order_id' => $orderId,
            'key_id' => $this->key->id,
            'external_reference' => $body['external_reference'] ?? null,
            'label' => $body['label'] ?? null,
            'list_price' => 0.00,
            'currency' => 'USD',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Capsule::table('tblhosting')->where('id', $serviceId)->update([
            'overideautosuspend' => 1
        ]);

        return [
            'status' => 201,
            'data' => [
                'order' => [
                    'id' => $orderId,
                    'number' => (string)$orderId,
                    'status' => 'active',
                    'created_at' => date('Y-m-d\TH:i:s\Z')
                ],
                'services' => [
                    [
                        'id' => $serviceId,
                        'status' => 'active',
                        'product_id' => $product->id,
                        'type' => $product->service_type,
                        'hostname' => $body['hostname'] ?? null,
                        'dedicated_ip' => null,
                        'label' => $body['label'] ?? null,
                        'external_reference' => $body['external_reference'] ?? null
                    ]
                ],
                'job' => null,
                'charge' => ['amount' => '0.00', 'reason' => 'balance_free_reseller']
            ]
        ];
    }

    // Other stubs...
    public function validateOrder() { return ['status' => 200, 'data' => []]; }
    public function listOrders() { return ['status' => 200, 'data' => []]; }
    public function getOrder() { return ['status' => 200, 'data' => []]; }
    public function cancelOrder() {
        $id = $this->request['params']['id'];
        $cancelResult = \localAPI('CancelOrder', ['orderid' => $id, 'cancelsub' => false, 'noemail' => true], 'Reseller API System');
        if ($cancelResult['result'] !== 'success') {
            throw new ApiError(400, 'ORDER_NOT_CANCELLABLE');
        }
        return ['status' => 200, 'data' => ['id' => $id, 'status' => 'cancelled']];
    }
}
