<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Writes a row to the project activity log when a model is created, updated or deleted.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->logActivity('created'));
        static::updated(fn (Model $model) => $model->logActivity('updated'));
        static::deleted(fn (Model $model) => $model->logActivity('deleted'));
    }

    abstract public function activityDescription(): string;

    protected function activityProjectId(): ?int
    {
        return $this->project_id;
    }

    protected function logActivity(string $event): void
    {
        $changes = null;

        if ($event === 'updated') {
            $new = Arr::except($this->getChanges(), ['updated_at', 'vat_amount']);

            if ($new === []) {
                return;
            }

            $changes = [
                'old' => Arr::only($this->getOriginal(), array_keys($new)),
                'new' => $new,
            ];
        }

        $projectId = $this->activityProjectId();

        if (! $projectId) {
            return;
        }

        Activity::create([
            'project_id' => $projectId,
            'user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'description' => mb_substr($this->activityDescription(), 0, 255),
            'changes' => $changes,
        ]);
    }
}
