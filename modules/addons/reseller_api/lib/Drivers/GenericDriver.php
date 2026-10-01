<?php
namespace WHMCS\Module\Addon\ResellerApi\Drivers;

use WHMCS\Database\Capsule;

class GenericDriver implements DriverInterface
{
    public function capabilities(): array
    {
        return ['suspend', 'unsuspend', 'terminate', 'password'];
    }

    public function status($serviceId): array
    {
        $s = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        return [
            'service_status' => strtolower($s->domainstatus),
            'power_state' => 'unknown',
            'uptime_seconds' => null,
            'backend' => null
        ];
    }

    public function power($serviceId, $action): array
    {
        $actionMap = [
            'start' => 'start',
            'stop' => 'stop',
            'reboot' => 'reboot',
            'shutdown' => 'shutdown',
            'reset' => 'reset'
        ];

        if (!isset($actionMap[$action])) {
             throw new \Exception('ACTION_NOT_SUPPORTED');
        }

        // Use ModuleCustom to call the mapped action if it exists on the module
        $res = \localAPI('ModuleCustom', [
            'accountid' => $serviceId,
            'func_name' => $actionMap[$action]
        ], 'Reseller API System');

        if ($res['result'] !== 'success') {
            throw new \Exception('ACTION_NOT_SUPPORTED');
        }

        return ['result' => 'success'];
    }

    public function console($serviceId, $type): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function sso($serviceId, $target): array
    {
        $res = \localAPI('ModuleServiceSingleSignOn', ['serviceid' => $serviceId], 'Reseller API System');
        if ($res['result'] !== 'success') {
             throw new \Exception('ACTION_NOT_SUPPORTED');
        }
        return ['url' => $res['redirectTo']];
    }

    public function rebuild($serviceId, $template, $password, $sshKeys): array
    {
        throw new \Exception('ACTION_NOT_SUPPORTED');
    }

    public function ips($serviceId): array
    {
        $s = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        $ips = [];
        if ($s->dedicatedip) {
            $ips[] = ['ip' => $s->dedicatedip, 'version' => (strpos($s->dedicatedip, ':') !== false ? 6 : 4), 'primary' => true];
        }
        if ($s->assignedips) {
            $assigned = explode("\n", trim($s->assignedips));
            foreach ($assigned as $ip) {
                 $ip = trim($ip);
                 if ($ip) $ips[] = ['ip' => $ip, 'version' => (strpos($ip, ':') !== false ? 6 : 4), 'primary' => false];
            }
        }
        return $ips;
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
        $res = \localAPI('UpdateClientProductUsage', ['serviceid' => $serviceId], 'Reseller API System');
        $s = Capsule::table('tblhosting')->where('id', $serviceId)->first();

        return [
            'disk' => ['used_mb' => $s->diskusage, 'limit_mb' => $s->disklimit],
            'bandwidth' => ['used_gb' => $s->bwusage, 'limit_gb' => $s->bwlimit]
        ];
    }
}
