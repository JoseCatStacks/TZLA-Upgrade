<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Wallet\XmrAddress;
use PHPUnit\Framework\TestCase;

final class XmrAddressTest extends TestCase
{
    public function test_accepts_standard_and_subaddress(): void
    {
        $this->assertTrue(XmrAddress::isValid('4'.str_repeat('A', 94)));
        $this->assertTrue(XmrAddress::isValid('8'.str_repeat('B', 94)));
        $this->assertTrue(XmrAddress::isValid('4'.str_repeat('C', 105)));
    }

    public function test_rejects_solana_and_short_strings(): void
    {
        $this->assertFalse(XmrAddress::isValid('Tzla26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3'));
        $this->assertFalse(XmrAddress::isValid('4short'));
        $this->assertFalse(XmrAddress::isValid(''));
        $this->assertFalse(XmrAddress::isValid('0'.str_repeat('A', 94)));
    }
}
