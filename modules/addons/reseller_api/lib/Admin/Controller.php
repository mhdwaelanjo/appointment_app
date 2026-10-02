<?php
namespace WHMCS\Module\Addon\ResellerApi\Admin;

class Controller
{
    private $vars;

    public function __construct($vars)
    {
        $this->vars = $vars;
    }

    public function dispatch()
    {
        $action = $_GET['action'] ?? 'dashboard';

        switch ($action) {
            case 'resellers':
                return $this->render('resellers', ['title' => 'Resellers']);
            case 'keys':
                return $this->render('keys', ['title' => 'API Keys']);
            case 'ip_whitelist':
                return $this->render('ip_whitelist', ['title' => 'IP Whitelist']);
            case 'products':
                return $this->render('products', ['title' => 'Product Access']);
            case 'settings':
                return $this->render('settings', ['title' => 'Settings']);
            case 'logs':
                return $this->render('logs', ['title' => 'Logs']);
            case 'jobs':
                return $this->render('jobs', ['title' => 'Jobs']);
            case 'webhooks':
                return $this->render('webhooks', ['title' => 'Webhooks']);
            case 'dashboard':
            default:
                return $this->render('dashboard', ['title' => 'Dashboard']);
        }
    }

    private function render($template, $data = [])
    {
        extract($data);
        $modulelink = $this->vars['modulelink'];

        ob_start();
        include __DIR__ . '/templates/' . $template . '.tpl';
        return ob_get_clean();
    }
}
