<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * All the money figures shown in the app, calculated in one place.
 *
 * Expected total = for each agreement, the higher of the agreed amount and what has been billed
 * against it, plus every cost that is not linked to an agreement.
 */
class ProjectFinance
{
    /** @var Collection<int, object>|null rows of category_id, agreement_id, billed, paid */
    private ?Collection $costTotals = null;

    /** @var Collection<int, object>|null rows of id, category_id, amount */
    private ?Collection $agreementRows = null;

    public function __construct(public readonly Project $project) {}

    public static function for(Project $project): self
    {
        return new self($project);
    }

    /**
     * @return array{budget:int, allocated:int, agreed:int, billed:int, paid:int, unpaid:int, overdue:int, expected:int, left:int, used_percent:int}
     */
    public function summary(): array
    {
        $billed = (int) $this->costTotals()->sum('billed');
        $paid = (int) $this->costTotals()->sum('paid');
        $expected = $this->expectedFor(null);
        $budget = (int) $this->project->total_budget;

        return [
            'budget' => $budget,
            'allocated' => (int) $this->project->categories()->sum('budget'),
            'agreed' => (int) $this->agreements()->sum('amount'),
            'billed' => $billed,
            'paid' => $paid,
            'unpaid' => $billed - $paid,
            'overdue' => (int) $this->project->costs()->overdue()->sum('amount'),
            'expected' => $expected,
            'left' => $budget - $expected,
            'used_percent' => $budget > 0 ? (int) round($expected / $budget * 100) : 0,
        ];
    }

    /**
     * @return Collection<int, array{category:Category, budget:int, agreed:int, billed:int, paid:int, expected:int, left:int, used_percent:int}>
     */
    public function categories(): Collection
    {
        return $this->project->categories()->get()->map(fn (Category $category) => $this->forCategory($category));
    }

    /**
     * @return array{category:Category, budget:int, agreed:int, billed:int, paid:int, expected:int, left:int, used_percent:int}
     */
    public function forCategory(Category $category): array
    {
        $rows = $this->costTotals()->where('category_id', $category->id);
        $expected = $this->expectedFor($category->id);

        return [
            'category' => $category,
            'budget' => (int) $category->budget,
            'agreed' => (int) $this->agreements()->where('category_id', $category->id)->sum('amount'),
            'billed' => (int) $rows->sum('billed'),
            'paid' => (int) $rows->sum('paid'),
            'expected' => $expected,
            'left' => (int) $category->budget - $expected,
            'used_percent' => $category->budget > 0 ? (int) round($expected / $category->budget * 100) : 0,
        ];
    }

    /** Billed so far against each agreement, keyed by agreement id. */
    public function billedByAgreement(): Collection
    {
        return $this->costTotals()
            ->whereNotNull('agreement_id')
            ->groupBy('agreement_id')
            ->map(fn (Collection $rows) => (int) $rows->sum('billed'));
    }

    private function expectedFor(?int $categoryId): int
    {
        $costs = $categoryId === null ? $this->costTotals() : $this->costTotals()->where('category_id', $categoryId);
        $agreements = $categoryId === null ? $this->agreements() : $this->agreements()->where('category_id', $categoryId);
        $billedByAgreement = $this->billedByAgreement();

        $unlinked = (int) $costs->whereNull('agreement_id')->sum('billed');

        $fromAgreements = $agreements->sum(
            fn (object $agreement) => max((int) $agreement->amount, (int) ($billedByAgreement[$agreement->id] ?? 0))
        );

        return $unlinked + (int) $fromAgreements;
    }

    private function costTotals(): Collection
    {
        return $this->costTotals ??= $this->project->costs()
            ->toBase()
            ->selectRaw('category_id, agreement_id, sum(amount) as billed, sum(case when is_paid then amount else 0 end) as paid')
            ->groupBy('category_id', 'agreement_id')
            ->get();
    }

    private function agreements(): Collection
    {
        return $this->agreementRows ??= $this->project->agreements()->toBase()->get(['id', 'category_id', 'amount']);
    }
}
