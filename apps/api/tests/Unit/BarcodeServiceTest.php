<?php

namespace Tests\Unit;

use App\Services\BarcodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BarcodeServiceTest extends TestCase
{
    #[Test]
    public function generateEan13_produces_valid_ean13_check_digit(): void
    {
        $svc = new BarcodeService();
        $code = $svc->generateEan13('f2f6c9b7-1111-2222-3333-444455556666');

        $this->assertTrue($svc->isValidEan13($code));
        $this->assertSame(13, strlen($code));
    }

    #[Test]
    public function isValidEan13_validates_known_examples(): void
    {
        $svc = new BarcodeService();

        $valid = '4006381333931';
        $this->assertTrue($svc->isValidEan13($valid));

        $invalid = substr($valid, 0, 12).'2';
        $this->assertFalse($svc->isValidEan13($invalid));
    }
}

