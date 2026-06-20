<?php

namespace Tests\Unit;

use App\Support\BeybladeLines;
use PHPUnit\Framework\TestCase;

class BeybladeLinesTest extends TestCase
{
    public function test_returns_standard_slots_for_bx(): void
    {
        $this->assertSame(['blade', 'ratchet', 'bit'], BeybladeLines::slotsFor('bx'));
    }

    public function test_ratchet_optional_only_for_infinity(): void
    {
        $this->assertTrue(BeybladeLines::isOptional('bx_infinity', 'ratchet'));
        $this->assertFalse(BeybladeLines::isOptional('bx', 'ratchet'));
        $this->assertFalse(BeybladeLines::isOptional('cx_infinity', 'ratchet'));
    }

    public function test_required_slots_exclude_optional_ratchet(): void
    {
        $this->assertSame(['blade', 'bit'], BeybladeLines::requiredSlotsFor('bx_infinity'));
        $this->assertSame(['blade', 'ratchet', 'bit'], BeybladeLines::requiredSlotsFor('bx'));
    }
}
