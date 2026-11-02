<?php

namespace App\Support;

use App\Filament\Resources\Costs\CostResource;
use App\Models\Category;
use App\Models\Cost;
use App\Models\NotificationRecipient;
use App\Models\Supplier;
use App\Notifications\CostChanged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Tells everyone on the project's notification list when a cost is added, changed or deleted. */
class CostNotifier
{
    /** Changes worth a message. Others (VAT, attachments, notes) stay quiet. */
    private const WATCHED = ['amount', 'description', 'category_id', 'supplier_id', 'date', 'is_paid', 'due_date', 'reference'];

    public static function created(Cost $cost): void
    {
        self::send($cost, 'created');
    }

    public static function deleted(Cost $cost): void
    {
        self::send($cost, 'deleted');
    }

    public static function updated(Cost $cost): void
    {
        $changed = array_values(array_intersect(self::WATCHED, array_keys($cost->getChanges())));

        if ($changed === []) {
            return;
        }

        // The cached relations may still point at the old category or supplier.
        $cost->unsetRelation('category')->unsetRelation('supplier');

        // Marking paid also clears the due date; that's one event, not two changes.
        if (in_array('is_paid', $changed, true) && array_diff($changed, ['is_paid', 'due_date']) === []) {
            self::send($cost, $cost->is_paid ? 'paid' : 'unpaid');

            return;
        }

        $changes = collect($changed)
            ->reject(fn (string $field) => $field === 'due_date' && in_array('is_paid', $changed, true))
            ->map(fn (string $field) => self::change($cost, $field))
            ->values()
            ->all();

        self::send($cost, 'updated', $changes);
    }

    /** @param array<int, array{field: string, old: mixed, new: mixed}> $changes */
    private static function send(Cost $cost, string $event, array $changes = []): void
    {
        $recipients = NotificationRecipient::where('project_id', $cost->project_id)->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $snapshot = self::snapshot($cost, $event, $changes);

        foreach ($recipients as $recipient) {
            if (! $recipient->channel->isConfigured()) {
                Log::warning("Cost notification skipped: {$recipient->channel->getLabel()} is not set up on the server.");

                continue;
            }

            // A message problem must never stop someone saving a cost.
            try {
                Notification::route($recipient->channel->driver(), $recipient->address)
                    ->notify((new CostChanged($snapshot))->locale($recipient->locale));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function snapshot(Cost $cost, string $event, array $changes): array
    {
        $project = $cost->project;

        return [
            'event' => $event,
            'project' => $project->name,
            'currency' => $project->currency,
            'actor' => auth()->user()?->name,
            'amount' => $cost->amount,
            'supplier' => $cost->supplier?->name,
            'description' => $cost->description,
            'category' => $cost->category?->name,
            'is_paid' => (bool) $cost->is_paid,
            'due_date' => $cost->due_date?->toDateString(),
            'changes' => $changes,
            'url' => self::url($cost),
        ];
    }

    /** @return array{field: string, old: mixed, new: mixed} */
    private static function change(Cost $cost, string $field): array
    {
        $old = $cost->getOriginal($field);
        $new = $cost->getAttribute($field);

        return match ($field) {
            'category_id' => ['field' => 'category', 'old' => Category::find($old)?->name, 'new' => $cost->category?->name],
            'supplier_id' => ['field' => 'supplier', 'old' => Supplier::find($old)?->name, 'new' => $cost->supplier?->name],
            'date', 'due_date' => ['field' => $field, 'old' => $old?->toDateString(), 'new' => $new?->toDateString()],
            default => ['field' => $field, 'old' => $old, 'new' => $new],
        };
    }

    private static function url(Cost $cost): string
    {
        try {
            return CostResource::getUrl('index', panel: 'app', tenant: $cost->project);
        } catch (Throwable) {
            return url('/');
        }
    }
}
