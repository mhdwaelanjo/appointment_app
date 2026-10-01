<?php
namespace WHMCS\Module\Addon\ResellerApi\Drivers;

interface DriverInterface
{
    public function capabilities(): array;
    public function status($serviceId): array;
    public function power($serviceId, $action): array;
    public function console($serviceId, $type): array;
    public function sso($serviceId, $target): array;
    public function rebuild($serviceId, $template, $password, $sshKeys): array;
    public function ips($serviceId): array;
    public function setRdns($serviceId, $ip, $rdns): array;
    public function snapshots($serviceId): array;
    public function createSnapshot($serviceId, $name): array;
    public function restoreSnapshot($serviceId, $snapshotId): array;
    public function deleteSnapshot($serviceId, $snapshotId): array;
    public function rescue($serviceId, $action): array;
    public function usage($serviceId, $period): array;
}
