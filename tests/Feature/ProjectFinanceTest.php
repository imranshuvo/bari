<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Category;
use App\Models\Cost;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\ProjectFinance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expected_total_uses_agreements_and_unlinked_costs(): void
    {
        $project = Project::factory()->create(['total_budget' => 1_000_000]);
        $roof = Category::factory()->for($project)->create(['budget' => 400_000]);
        $walls = Category::factory()->for($project)->create(['budget' => 300_000]);
        $roofer = Supplier::factory()->for($project)->create();
        $mason = Supplier::factory()->for($project)->create();

        // Roofer agreed 300k, billed 100k so far → expect 300k.
        $roofAgreement = Agreement::factory()->for_($roof, $roofer)->create(['amount' => 300_000]);
        Cost::factory()->in($roof)->create(['agreement_id' => $roofAgreement->id, 'amount' => 100_000]);

        // Mason agreed 200k but billed 250k already → expect 250k.
        $wallAgreement = Agreement::factory()->for_($walls, $mason)->create(['amount' => 200_000]);
        Cost::factory()->in($walls)->create(['agreement_id' => $wallAgreement->id, 'amount' => 150_000]);
        Cost::factory()->in($walls)->unpaid()->create(['agreement_id' => $wallAgreement->id, 'amount' => 100_000]);

        // A loose receipt in roof, not linked to an agreement.
        Cost::factory()->in($roof)->create(['amount' => 20_000]);

        $summary = ProjectFinance::for($project)->summary();

        $this->assertSame(370_000, $summary['billed']);
        $this->assertSame(270_000, $summary['paid']);
        $this->assertSame(100_000, $summary['unpaid']);
        $this->assertSame(300_000 + 250_000 + 20_000, $summary['expected']);
        $this->assertSame(1_000_000 - 570_000, $summary['left']);

        $byCategory = ProjectFinance::for($project)->categories()->keyBy(fn ($row) => $row['category']->id);
        $this->assertSame(320_000, $byCategory[$roof->id]['expected']);
        $this->assertSame(80_000, $byCategory[$roof->id]['left']);
        $this->assertSame(250_000, $byCategory[$walls->id]['expected']);
        $this->assertSame(50_000, $byCategory[$walls->id]['left']);
    }

    public function test_a_linked_cost_takes_the_agreement_category_and_supplier(): void
    {
        $project = Project::factory()->create();
        [$roof, $other] = Category::factory()->for($project)->count(2)->create();
        $roofer = Supplier::factory()->for($project)->create();
        $agreement = Agreement::factory()->for_($roof, $roofer)->create();

        $cost = Cost::factory()->in($other)->create(['agreement_id' => $agreement->id]);

        $this->assertSame($roof->id, $cost->category_id);
        $this->assertSame($roofer->id, $cost->supplier_id);
    }

    public function test_paid_state_and_vat_are_kept_consistent(): void
    {
        $project = Project::factory()->create(['vat_rate' => 25]);
        $category = Category::factory()->for($project)->create();

        $cost = Cost::factory()->in($category)->unpaid('2026-12-01')->create(['amount' => 12_500, 'vat_rate' => 25]);
        $this->assertNull($cost->paid_on);
        $this->assertSame(2_500, $cost->vat_amount);

        $cost->update(['is_paid' => true]);
        $this->assertNotNull($cost->paid_on);
        $this->assertNull($cost->due_date);
    }
}
