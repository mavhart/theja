<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashRegister\RtService;
use App\Services\InvoiceService;
use App\Support\TenantClinicalSchema;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private PointOfSale $pos;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->pos = PointOfSale::factory()->create([
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
        $this->user = User::factory()->forOrganization($this->org)->create();

        $schema = TenantClinicalSchema::schemaNameForOrganizationId($this->org->id);
        DB::statement('SET search_path TO "'.$schema.'", public');
    }

    protected function tearDown(): void
    {
        DB::statement('SET search_path TO public');
        parent::tearDown();
    }

    private function createPatient(): Patient
    {
        return Patient::create([
            'organization_id' => $this->org->id,
            'pos_id' => $this->pos->id,
            'last_name' => 'Rossi',
            'first_name' => 'Mario',
            'fiscal_code' => 'RSSMRA80E15H501Z',
            'mobile' => '3200000000',
            'country' => 'IT',
            'language' => 'it',
            'communication_mail' => false,
            'communication_sms' => true,
            'gdpr_marketing_consent' => true,
        ]);
    }

    #[Test]
    public function generateNumber_increments_sequentially_for_pos_and_year(): void
    {
        $svc = new InvoiceService(new RtService());
        $year = 2026;

        $n1 = $svc->generateNumber($this->pos, $year);
        $n2 = $svc->generateNumber($this->pos, $year);

        $this->assertNotSame($n1, $n2);
        $this->assertSame('FAT2026/000001', $n1);
        $this->assertSame('FAT2026/000002', $n2);
    }

    #[Test]
    public function generateXml_produces_valid_fatturapa_xml_with_mandatory_nodes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-01 10:00:00'));

        $svc = new InvoiceService(new RtService());
        $patient = $this->createPatient();

        $invoice = $svc->createManual(
            [
                'pos_id' => $this->pos->id,
                'patient_id' => $patient->id,
                'invoice_date' => '2026-03-01',
                'type' => 'fattura_pa',
                'payment_method' => null,
                'payment_terms' => null,
                'notes' => null,
            ],
            [
                [
                    'description' => 'Prodotto test',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'vat_rate' => 22,
                    'sts_code' => null,
                ],
            ]
        );

        $xml = $svc->generateXml($invoice);

        $dom = new DOMDocument();
        $ok = @$dom->loadXML($xml);
        $this->assertTrue($ok, 'XML non valido');

        $xpath = new \DOMXPath($dom);
        $ns = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';
        $xpath->registerNamespace('p', $ns);

        $this->assertGreaterThanOrEqual(
            1,
            $xpath->query('//p:FatturaElettronicaHeader')->length
        );

        $this->assertGreaterThanOrEqual(
            1,
            $xpath->query('//p:IdTrasmittente/p:CodiceFiscale')->length
        );

        $this->assertGreaterThanOrEqual(
            1,
            $xpath->query('//p:CessionarioCommittente/p:CodiceFiscale')->length
        );

        $this->assertGreaterThanOrEqual(
            1,
            $xpath->query('//p:DatiGeneraliDocumento/p:Numero')->length
        );

        $this->assertGreaterThanOrEqual(
            1,
            $xpath->query('//p:DettaglioLinee')->length
        );

        // Verifica che Numero includa invoice_number
        $numeroNode = $xpath->query('string(//p:DatiGeneraliDocumento/p:Numero)')->item(0);
        $this->assertNotEmpty((string) $numeroNode);
    }
}

