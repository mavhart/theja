<?php

namespace Tests\Unit;

use App\Models\Prescription;
use App\Services\PrescriptionAlertService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrescriptionAlertServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function none_when_no_prescription(): void
    {
        $this->assertSame('none', PrescriptionAlertService::resolve(null));
    }

    #[Test]
    public function none_when_visit_within_12_months(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        $rx = new Prescription;
        $rx->visit_date = Carbon::parse('2025-08-01');
        $rx->next_recall_at = null;

        $this->assertSame('none', PrescriptionAlertService::resolve($rx));
    }

    #[Test]
    public function warning_when_between_12_and_18_months(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        $rx = new Prescription;
        $rx->visit_date = Carbon::parse('2025-01-10');
        $rx->next_recall_at = null;

        $this->assertSame('warning', PrescriptionAlertService::resolve($rx));
    }

    #[Test]
    public function expired_when_over_18_months(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        $rx = new Prescription;
        $rx->visit_date = Carbon::parse('2024-01-01');
        $rx->next_recall_at = null;

        $this->assertSame('expired', PrescriptionAlertService::resolve($rx));
    }

    #[Test]
    public function expired_when_next_recall_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        $rx = new Prescription;
        $rx->visit_date = Carbon::parse('2026-01-01');
        $rx->next_recall_at = Carbon::parse('2026-03-01');

        $this->assertSame('expired', PrescriptionAlertService::resolve($rx));
    }
}
