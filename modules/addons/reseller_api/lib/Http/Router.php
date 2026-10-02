<?php
namespace WHMCS\Module\Addon\ResellerApi\Http;

class Router
{
    private $routes = [];

    public function __construct()
    {
        // System
        $this->add('GET', 'ping', 'SystemController', 'ping', null);
        $this->add('GET', 'me', 'SystemController', 'me', null);

        // Catalog
        $this->add('GET', 'products', 'CatalogController', 'listProducts', 'catalog:read');
        $this->add('GET', 'products/{id}', 'CatalogController', 'getProduct', 'catalog:read');
        $this->add('GET', 'products/{id}/config-options', 'CatalogController', 'getConfigOptions', 'catalog:read');
        $this->add('GET', 'products/{id}/os-templates', 'CatalogController', 'getOsTemplates', 'catalog:read');
        $this->add('GET', 'products/{id}/addons', 'CatalogController', 'getAddons', 'catalog:read');
        $this->add('GET', 'locations', 'CatalogController', 'getLocations', 'catalog:read');

        // Orders
        $this->add('POST', 'orders/validate', 'OrdersController', 'validateOrder', 'orders:write');
        $this->add('POST', 'orders', 'OrdersController', 'createOrder', 'orders:write');
        $this->add('GET', 'orders', 'OrdersController', 'listOrders', 'orders:read');
        $this->add('GET', 'orders/{id}', 'OrdersController', 'getOrder', 'orders:read');
        $this->add('POST', 'orders/{id}/cancel', 'OrdersController', 'cancelOrder', 'orders:write');

        // Services
        $this->add('GET', 'services', 'ServicesController', 'listServices', 'services:read');
        $this->add('GET', 'services/{id}', 'ServicesController', 'getService', 'services:read');
        $this->add('PATCH', 'services/{id}', 'ServicesController', 'updateService', 'services:manage');
        $this->add('GET', 'services/{id}/status', 'ServicesController', 'getStatus', 'services:read');
        $this->add('GET', 'services/{id}/usage', 'ServicesController', 'getUsage', 'services:read');

        // Lifecycle
        $this->add('POST', 'services/{id}/suspend', 'ServicesController', 'suspend', 'services:lifecycle');
        $this->add('POST', 'services/{id}/unsuspend', 'ServicesController', 'unsuspend', 'services:lifecycle');
        $this->add('POST', 'services/{id}/terminate', 'ServicesController', 'terminate', 'services:terminate');
        $this->add('POST', 'services/{id}/upgrade', 'ServicesController', 'upgrade', 'services:manage');

        // Power
        $this->add('POST', 'services/{id}/power', 'PowerController', 'power', 'services:power');

        // Access
        $this->add('GET', 'services/{id}/credentials', 'AccessController', 'getCredentials', 'services:access');
        $this->add('POST', 'services/{id}/reset-password', 'AccessController', 'resetPassword', 'services:access');
        $this->add('POST', 'services/{id}/sso', 'AccessController', 'sso', 'services:access');
        $this->add('POST', 'services/{id}/console', 'AccessController', 'console', 'services:access');

        // Management
        $this->add('POST', 'services/{id}/rebuild', 'ManagementController', 'rebuild', 'services:manage');
        $this->add('PUT', 'services/{id}/hostname', 'ManagementController', 'setHostname', 'services:manage');
        $this->add('GET', 'services/{id}/ips', 'ManagementController', 'listIps', 'services:read');
        $this->add('PUT', 'services/{id}/ips/{ip}/rdns', 'ManagementController', 'setRdns', 'services:manage');
        $this->add('GET', 'services/{id}/snapshots', 'ManagementController', 'listSnapshots', 'services:read');
        $this->add('POST', 'services/{id}/snapshots', 'ManagementController', 'createSnapshot', 'services:manage');
        $this->add('POST', 'services/{id}/snapshots/{sid}/restore', 'ManagementController', 'restoreSnapshot', 'services:manage');
        $this->add('DELETE', 'services/{id}/snapshots/{sid}', 'ManagementController', 'deleteSnapshot', 'services:manage');
        $this->add('POST', 'services/{id}/rescue', 'ManagementController', 'rescue', 'services:manage');
        $this->add('GET', 'services/{id}/actions', 'ManagementController', 'listActions', 'services:read');
        $this->add('POST', 'services/{id}/actions/{action}', 'ManagementController', 'runAction', 'services:manage');

        // Jobs
        $this->add('GET', 'jobs/{id}', 'JobsController', 'getJob', 'services:read');

        // Webhooks
        $this->add('GET', 'webhooks', 'WebhooksController', 'listWebhooks', 'webhooks:manage');
        $this->add('POST', 'webhooks', 'WebhooksController', 'createWebhook', 'webhooks:manage');
        $this->add('DELETE', 'webhooks/{id}', 'WebhooksController', 'deleteWebhook', 'webhooks:manage');
        $this->add('POST', 'webhooks/{id}/test', 'WebhooksController', 'testWebhook', 'webhooks:manage');
    }

    private function add($method, $path, $controller, $func, $scope)
    {
        // Convert {id} and {ip} and {sid} to regex
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'controller' => $controller,
            'func' => $func,
            'scope' => $scope
        ];
    }

    public function match($method, $path)
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                    }
                }
                return [
                    'controller' => $route['controller'],
                    'method' => $route['func'],
                    'scope' => $route['scope'],
                    'params' => $params
                ];
            }
        }
        return null;
    }
}
