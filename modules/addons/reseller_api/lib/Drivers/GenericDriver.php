<?php
namespace WHMCS\Module\Addon\ResellerApi\Drivers;

class GenericDriver implements DriverInterface
{
    public function capabilities(): array
    {
        return ['suspend', 'unsuspend', 'terminate', 'password'];
    }

    public function status($serviceId): array
    {
        return ['service_status' => 'unknown', 'power_state' => 'unknown'];
    }

    public function power($serviceId, $action): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function console($serviceId, $type): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function sso($serviceId, $target): array
    {
        // Typically call ModuleServiceSingleSignOn via localAPI in WHMCS
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function rebuild($serviceId, $template, $password, $sshKeys): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function ips($serviceId): array
    {
        return [];
    }

    public function setRdns($serviceId, $ip, $rdns): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function snapshots($serviceId): array
    {
        return [];
    }

    public function createSnapshot($serviceId, $name): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function restoreSnapshot($serviceId, $snapshotId): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function deleteSnapshot($serviceId, $snapshotId): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function rescue($serviceId, $action): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function usage($serviceId, $period): array
    {
        // Try UpdateClientProductUsage in real impl
        return [];
    }
}
