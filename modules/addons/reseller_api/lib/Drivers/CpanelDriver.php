<?php
namespace WHMCS\Module\Addon\ResellerApi\Drivers;

class CpanelDriver extends GenericDriver
{
    public function capabilities(): array
    {
        return ['suspend', 'unsuspend', 'terminate', 'password', 'sso', 'usage'];
    }
}
