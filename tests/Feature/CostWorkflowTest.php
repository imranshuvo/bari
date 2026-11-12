<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Filament\Forms\SupplierSelect;
use App\Filament\Pages\Tenancy\RegisterProject;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Costs\Pages\ManageCosts;
use App\Filament\Resources\Memberships\Pages\ManageMemberships;
use App\Livewire\AddCostButton;
use App\Models\Agreement;
use App\Models\Category;
use App\Models\Cost;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CategoryTemplate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CostWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create(['currency' => 'BDT', 'total_budget' => 10_000_000_00]);
        $this->category = Category::factory()->for($this->project)->create(['name' => 'Foundation']);
    }

    private function as(ProjectRole $role): User
    {
        $user = User::factory()->create();
        $this->project->addMember($user, $role);
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->project);
        Filament::bootCurrentPanel();

        return $user;
    }

    public function test_an_editor_adds_a_cost_with_a_new_supplier_typed_in_bangla_digits(): void
    {
        $this->as(ProjectRole::Editor);

        Livewire::test(AddCostButton::class)
            ->callAction('addCost', data: [
                'amount' => '১,৮৫,০০০',
                'date' => '2026-10-01',
                'supplier_name' => 'Shah Cement Depot',
                'category_id' => $this->category->id,
                'is_paid' => true,
            ])
            ->assertHasNoErrors();

        $cost = Cost::sole();
        $this->assertSame(185_000_00, $cost->amount);
        $this->assertSame('Shah Cement Depot', $cost->supplier->name);
        $this->assertTrue($cost->is_paid);
        $this->assertSame($this->project->id, $cost->project_id);
    }

    public function test_the_same_supplier_name_is_reused_not_duplicated(): void
    {
        $this->as(ProjectRole::Owner);
        $existing = Supplier::factory()->for($this->project)->create(['name' => 'Karim Construction']);

        Livewire::test(AddCostButton::class)
            ->callAction('addCost', data: [
                'amount' => '5000', 'date' => '2026-10-01', 'supplier_name' => 'karim construction',
                'category_id' => $this->category->id, 'is_paid' => true,
            ])
            ->assertHasNoErrors();

        $this->assertSame(1, Supplier::count());
        $this->assertSame($existing->id, Cost::sole()->supplier_id);
    }

    public function test_paid_to_lists_only_this_projects_suppliers_and_offers_to_add_a_new_name(): void
    {
        Supplier::factory()->for(Project::factory())->create(['name' => 'Karim Steel']);
        $this->as(ProjectRole::Editor);
        Supplier::factory()->for($this->project)->create(['name' => 'Karim Construction']);

        $this->assertSame(['Karim Construction' => 'Karim Construction'], SupplierSelect::searchResults(''));
        $this->assertSame(['Karim Construction' => 'Karim Construction'], SupplierSelect::searchResults('karim construction'));
        $this->assertSame(
            ['Karim Steel' => 'Add “Karim Steel”'],
            SupplierSelect::searchResults('  Karim   Steel '),
        );
    }

    public function test_spending_can_be_filtered_by_supplier_category_and_date_range(): void
    {
        $this->as(ProjectRole::Viewer);
        $roof = Category::factory()->for($this->project)->create(['name' => 'Roof']);
        $karim = Supplier::factory()->for($this->project)->create(['name' => 'Karim Construction']);
        $october = Cost::factory()->for($this->project)->create(['date' => '2026-10-05', 'category_id' => $this->category->id, 'supplier_id' => $karim->id]);
        $november = Cost::factory()->for($this->project)->create(['date' => '2026-11-05', 'category_id' => $this->category->id]);
        $roofCost = Cost::factory()->for($this->project)->create(['date' => '2026-11-06', 'category_id' => $roof->id]);

        Livewire::test(ManageCosts::class)
            ->filterTable('supplier_id', $karim->id)
            ->assertCanSeeTableRecords([$october])
            ->assertCanNotSeeTableRecords([$november, $roofCost])
            ->resetTableFilters()
            ->filterTable('category_id', $roof->id)
            ->assertCanSeeTableRecords([$roofCost])
            ->assertCanNotSeeTableRecords([$october, $november])
            ->resetTableFilters()
            ->filterTable('date', ['from' => '2026-11-01', 'until' => '2026-11-05'])
            ->assertCanSeeTableRecords([$november])
            ->assertCanNotSeeTableRecords([$october, $roofCost]);
    }

    public function test_dates_are_shown_day_first(): void
    {
        $this->as(ProjectRole::Owner);
        Cost::factory()->for($this->project)->create(['date' => '2026-03-07', 'category_id' => $this->category->id]);

        Livewire::test(ManageCosts::class)->assertSeeText('07-03-2026');
    }

    public function test_an_unpaid_bill_needs_no_paid_date_and_can_be_marked_paid(): void
    {
        $this->as(ProjectRole::Owner);

        Livewire::test(AddCostButton::class)
            ->callAction('addCost', data: [
                'amount' => '12000', 'date' => '2026-10-01', 'category_id' => $this->category->id,
                'is_paid' => 0, 'due_date' => '2026-10-20',
            ])
            ->assertHasNoErrors();

        $cost = Cost::sole();
        $this->assertFalse($cost->is_paid);
        $this->assertSame('2026-10-20', $cost->due_date->toDateString());

        Livewire::test(ManageCosts::class)
            ->callAction(TestAction::make('markPaid')->table($cost), data: ['paid_on' => '2026-10-15']);

        $cost->refresh();
        $this->assertTrue($cost->is_paid);
        $this->assertSame('2026-10-15', $cost->paid_on->toDateString());
    }

    public function test_an_agreement_is_linked_automatically_when_supplier_and_category_match(): void
    {
        $this->as(ProjectRole::Owner);
        $supplier = Supplier::factory()->for($this->project)->create(['name' => 'Karim Construction']);
        $agreement = Agreement::factory()->for_($this->category, $supplier)->create(['amount' => 1_000_000_00]);

        Livewire::test(AddCostButton::class)
            ->mountAction('addCost')
            ->fillForm(['supplier_name' => 'Karim Construction'])
            ->assertSchemaStateSet(['category_id' => $this->category->id, 'agreement_id' => $agreement->id])
            ->fillForm(['amount' => '400000', 'date' => '2026-10-01', 'is_paid' => true])
            ->callMountedAction()
            ->assertHasNoErrors();

        $this->assertSame($agreement->id, Cost::sole()->agreement_id);
    }

    public function test_the_top_bar_button_adds_a_cost(): void
    {
        $this->as(ProjectRole::Editor);

        Livewire::test(AddCostButton::class)
            ->callAction('addCost', data: [
                'amount' => '2500', 'date' => '2026-10-01', 'category_id' => $this->category->id, 'is_paid' => true,
            ])
            ->assertDispatched('cost-saved');

        $this->assertSame(2500_00, Cost::sole()->amount);
    }

    public function test_the_phone_plus_button_opens_the_form_only_for_people_who_can_add(): void
    {
        $this->as(ProjectRole::Editor);
        Livewire::test(AddCostButton::class)->dispatch('open-add-cost')->assertActionMounted('addCost');

        $this->as(ProjectRole::Viewer);
        Livewire::test(AddCostButton::class)->dispatch('open-add-cost')->assertActionNotMounted('addCost');
    }

    public function test_bad_amounts_are_rejected(): void
    {
        $this->as(ProjectRole::Owner);

        Livewire::test(AddCostButton::class)
            ->callAction('addCost', data: ['amount' => 'lots', 'date' => '2026-10-01', 'category_id' => $this->category->id, 'is_paid' => true])
            ->assertHasActionErrors(['amount']);

        $this->assertSame(0, Cost::count());
    }

    public function test_viewers_cannot_add_edit_or_delete(): void
    {
        $this->as(ProjectRole::Viewer);
        $cost = Cost::factory()->in($this->category)->create();

        Livewire::test(ManageCosts::class)
            ->assertActionHidden(TestAction::make(EditAction::class)->table($cost))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($cost));

        Livewire::test(AddCostButton::class)->assertActionHidden('addCost');
    }

    public function test_editors_can_only_delete_their_own_costs(): void
    {
        $editor = $this->as(ProjectRole::Editor);
        $mine = Cost::factory()->in($this->category)->create(['created_by' => $editor->id]);
        $theirs = Cost::factory()->in($this->category)->create(['created_by' => User::factory()->create()->id]);

        Livewire::test(ManageCosts::class)
            ->assertActionVisible(TestAction::make(DeleteAction::class)->table($mine))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($theirs));
    }

    public function test_only_owners_can_change_the_budget(): void
    {
        $this->as(ProjectRole::Editor);

        Livewire::test(ManageCategories::class)
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make(EditAction::class)->table($this->category));
    }

    public function test_costs_from_other_projects_are_never_listed(): void
    {
        // Created before a project is active, so Filament doesn't attach it to ours.
        $other = Cost::factory()->in(Category::factory()->create())->create();
        $this->as(ProjectRole::Owner);
        $mine = Cost::factory()->in($this->category)->create();
        $this->assertNotSame($this->project->id, $other->project_id);

        Livewire::test(ManageCosts::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_every_change_is_written_to_the_activity_log(): void
    {
        $user = $this->as(ProjectRole::Owner);
        $cost = Cost::factory()->in($this->category)->create(['amount' => 1000_00]);
        $cost->update(['amount' => 1500_00]);
        $cost->delete();

        $this->assertSame(['created', 'updated', 'deleted'], $this->project->activities()->where('subject_type', $cost->getMorphClass())->orderBy('id')->pluck('event')->all());
        $this->assertSame($user->id, $this->project->activities()->latest('id')->value('user_id'));
    }

    public function test_inviting_a_new_person_creates_a_link_they_can_accept(): void
    {
        $this->as(ProjectRole::Owner);

        Livewire::test(ManageMemberships::class)
            ->callAction('invite', data: ['contact' => 'Builder@Example.com', 'role' => 'editor'])
            ->assertHasNoActionErrors();

        $invitation = Invitation::sole();
        $this->assertSame('builder@example.com', $invitation->email);

        $builder = User::factory()->create(['email' => 'someone-else@example.com']);
        $this->actingAs($builder)->get($invitation->url())->assertRedirect();

        $this->assertSame(ProjectRole::Editor, $this->project->fresh()->roleOf($builder));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->actingAs($builder)->get($invitation->url())->assertRedirect('/');
    }

    public function test_signing_up_with_an_invited_email_joins_the_project(): void
    {
        $this->project->invitations()->create(['email' => 'partner@example.com', 'role' => ProjectRole::Viewer]);

        $partner = User::factory()->create(['email' => 'Partner@example.com']);

        $this->assertSame(ProjectRole::Viewer, $this->project->roleOf($partner));
    }

    public function test_inviting_an_existing_user_adds_them_straight_away(): void
    {
        $this->as(ProjectRole::Owner);
        $existing = User::factory()->create(['email' => 'bank@example.com']);

        Livewire::test(ManageMemberships::class)
            ->callAction('invite', data: ['contact' => 'bank@example.com', 'role' => 'viewer'])
            ->assertHasNoActionErrors();

        $this->assertSame(ProjectRole::Viewer, $this->project->fresh()->roleOf($existing));
        $this->assertSame(0, Invitation::count());
    }

    public function test_the_last_owner_cannot_be_removed(): void
    {
        $owner = $this->as(ProjectRole::Owner);
        $membership = $this->project->memberships()->where('user_id', $owner->id)->sole();

        Livewire::test(ManageMemberships::class)
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($membership))
            ->assertActionHidden(TestAction::make(EditAction::class)->table($membership));
    }

    public function test_creating_a_project_makes_you_owner_and_adds_the_ticked_categories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(RegisterProject::class)
            ->fillForm([
                'name' => 'Village house',
                'currency' => 'BDT',
                'vat_rate' => 0,
                'total_budget' => '50,00,000',
                'categories' => ['Foundation', 'Roof'],
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $project = Project::where('name', 'Village house')->sole();
        $this->assertSame(50_00_000_00, $project->total_budget);
        $this->assertSame(ProjectRole::Owner, $project->roleOf($user));
        $this->assertSame(['Foundation', 'Roof'], $project->categories()->pluck('name')->all());
        $this->assertCount(18, CategoryTemplate::NAMES);
    }
}
