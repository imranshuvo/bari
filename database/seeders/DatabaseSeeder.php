<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CategoryTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/** Demo data: log in as owner@example.com / password. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create(['name' => 'Demo Owner', 'email' => 'owner@example.com']);
        $editor = User::factory()->create(['name' => 'Partner', 'email' => 'editor@example.com']);
        $viewer = User::factory()->create(['name' => 'Bank Adviser', 'email' => 'viewer@example.com', 'locale' => 'bn']);

        Auth::login($owner);

        $this->dhakaHouse($owner, $editor, $viewer);
        $this->danishHouse($owner);

        Auth::logout();
    }

    private function dhakaHouse(User $owner, User $editor, User $viewer): void
    {
        $project = Project::create([
            'name' => 'Our house in Dhaka',
            'address' => 'Plot 12, Road 5, Bashundhara R/A, Dhaka',
            'currency' => 'BDT',
            'vat_rate' => 0,
            'total_budget' => 1_20_00_000_00,
            'start_date' => now()->subMonths(5)->startOfMonth(),
            'end_date' => now()->addMonths(9),
        ]);
        $project->addMember($owner, ProjectRole::Owner);
        $project->addMember($editor, ProjectRole::Editor);
        $project->addMember($viewer, ProjectRole::Viewer);

        $budgets = [
            'Design & permits' => 6_00_000, 'Site preparation' => 4_00_000, 'Foundation' => 18_00_000,
            'Structure & walls' => 32_00_000, 'Roof' => 8_00_000, 'Windows & doors' => 7_00_000,
            'Plumbing' => 5_00_000, 'Electrical' => 6_00_000, 'Flooring & tiles' => 9_00_000,
            'Kitchen' => 5_00_000, 'Bathrooms' => 6_00_000, 'Painting' => 4_00_000, 'Other' => 3_00_000,
        ];

        $categories = [];
        foreach (CategoryTemplate::NAMES as $i => $name) {
            $categories[$name] = $project->categories()->create(['name' => $name, 'sort' => $i + 1, 'budget' => ($budgets[$name] ?? 0) * 100]);
        }

        $supplier = fn (string $name, string $trade, ?string $phone = null) => $project->suppliers()->create(['name' => $name, 'trade' => $trade, 'phone' => $phone]);
        $architect = $supplier('Rahman Architects', 'Architect', '+880 1711-000111');
        $contractor = $supplier('Karim Construction Ltd.', 'Main contractor', '+880 1819-222333');
        $steel = $supplier('BSRM Dealer – Uttara', 'Steel rods');
        $cement = $supplier('Shah Cement Depot', 'Cement & sand');
        $bricks = $supplier('Gazipur Brick Field', 'Bricks');
        $electric = $supplier('Alam Electric Works', 'Electrician', '+880 1912-444555');
        $rajuk = $supplier('RAJUK', 'Building permit');

        $agree = fn (Supplier $s, string $category, string $title, int $amount, int $daysAgo) => $project->agreements()->create([
            'supplier_id' => $s->id, 'category_id' => $categories[$category]->id, 'title' => $title,
            'amount' => $amount * 100, 'agreed_on' => now()->subDays($daysAgo),
        ]);
        $design = $agree($architect, 'Design & permits', 'Full architectural & structural drawings', 4_50_000, 150);
        $foundation = $agree($contractor, 'Foundation', 'Piling and foundation work (labour)', 12_00_000, 120);
        $frame = $agree($contractor, 'Structure & walls', 'Frame, columns and brick walls (labour)', 15_00_000, 90);
        $wiring = $agree($electric, 'Electrical', 'Full house wiring incl. DB boards', 3_80_000, 20);

        $cost = function (Supplier $s, string $category, int $amount, int $daysAgo, ?string $what = null, $agreement = null, bool $paid = true, ?int $dueInDays = null) use ($project, $categories) {
            $project->costs()->create([
                'supplier_id' => $s->id, 'category_id' => $categories[$category]->id, 'agreement_id' => $agreement?->id,
                'amount' => $amount * 100, 'date' => now()->subDays($daysAgo), 'description' => $what,
                'is_paid' => $paid, 'due_date' => $dueInDays === null ? null : now()->addDays($dueInDays),
            ]);
        };

        $cost($architect, 'Design & permits', 2_25_000, 145, 'Advance – 50%', $design);
        $cost($architect, 'Design & permits', 2_25_000, 100, 'Final payment', $design);
        $cost($rajuk, 'Design & permits', 1_85_000, 110, 'Building approval fee');
        $cost($contractor, 'Site preparation', 3_10_000, 125, 'Soil test, levelling and boundary wall');
        $cost($contractor, 'Foundation', 4_00_000, 115, '1st instalment', $foundation);
        $cost($contractor, 'Foundation', 4_00_000, 85, '2nd instalment', $foundation);
        $cost($contractor, 'Foundation', 5_20_000, 60, 'Final instalment + extra piles', $foundation);
        $cost($steel, 'Foundation', 3_45_000, 112, '6 tonnes 500W rod');
        $cost($cement, 'Foundation', 1_62_500, 110, '325 bags cement');
        $cost($steel, 'Structure & walls', 4_80_000, 55, '8 tonnes rod for columns and slabs');
        $cost($cement, 'Structure & walls', 2_10_000, 50, '420 bags cement + sand');
        $cost($bricks, 'Structure & walls', 3_60_000, 40, '30,000 first-class bricks');
        $cost($contractor, 'Structure & walls', 5_00_000, 30, '1st instalment', $frame);
        $cost($contractor, 'Structure & walls', 5_00_000, 3, '2nd instalment', $frame, paid: false, dueInDays: -2);
        $cost($bricks, 'Structure & walls', 1_20_000, 6, '10,000 bricks', paid: false, dueInDays: 10);
        $cost($electric, 'Electrical', 1_00_000, 12, 'Advance', $wiring);
        $cost($cement, 'Structure & walls', 85_000, 1, '170 bags cement', paid: false, dueInDays: 25);
    }

    private function danishHouse(User $owner): void
    {
        $project = Project::create([
            'name' => 'Summer house, Hornbæk',
            'currency' => 'DKK',
            'vat_rate' => 25,
            'total_budget' => 2_400_000_00,
        ]);
        $project->addMember($owner, ProjectRole::Owner);

        $roof = $project->categories()->create(['name' => 'Roof', 'budget' => 320_000_00]);
        $project->categories()->create(['name' => 'Kitchen', 'budget' => 180_000_00]);
        $roofer = $project->suppliers()->create(['name' => 'Nordsjælland Tag ApS', 'trade' => 'Roofer']);
        $agreement = $project->agreements()->create(['supplier_id' => $roofer->id, 'category_id' => $roof->id, 'title' => 'New roof incl. tiles', 'amount' => 285_000_00]);
        $project->costs()->create(['supplier_id' => $roofer->id, 'category_id' => $roof->id, 'agreement_id' => $agreement->id, 'amount' => 95_000_00, 'date' => now()->subDays(10), 'description' => 'A conto 1']);
    }
}
