<?php
namespace WHMCS\Module\Addon\ResellerApi\Drivers;

class ProxmoxDriver extends GenericDriver
{
    public function capabilities(): array
    {
        return ['power', 'console', 'rebuild', 'snapshots', 'rdns', 'usage', 'upgrade'];
    }
}
