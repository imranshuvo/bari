<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Support\DateFormat;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * "Rahim added a cost: ৳25,000 · Cement shop · Roof".
 *
 * Holds a plain snapshot (see CostNotifier) instead of the model, so it still
 * works for deleted costs and is rendered in each recipient's own language.
 */
class CostChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    /** @param array<string, mixed> $cost */
    public function __construct(public array $cost)
    {
        $this->afterCommit();
    }

    /** One recipient = one route, so send on exactly that channel. */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : [WebPushChannel::class];
    }

    public function headline(): string
    {
        $name = $this->cost['actor'] ?? __('Someone');

        return match ($this->cost['event']) {
            'created' => __(':name added a cost', ['name' => $name]),
            'deleted' => __(':name deleted a cost', ['name' => $name]),
            'paid' => __(':name marked a cost as paid', ['name' => $name]),
            'unpaid' => __(':name marked a cost as unpaid', ['name' => $name]),
            default => __(':name changed a cost', ['name' => $name]),
        };
    }

    /** "৳25,000 · Cement shop · 40 bags of cement · Roof" */
    public function summary(): string
    {
        return collect([
            $this->money($this->cost['amount']),
            $this->cost['supplier'],
            $this->cost['description'],
            $this->cost['category'],
        ])->filter()->implode(' · ');
    }

    public function status(): string
    {
        if ($this->cost['is_paid']) {
            return __('Paid');
        }

        return $this->cost['due_date']
            ? __('Pay by :date', ['date' => $this->date($this->cost['due_date'])])
            : __('Unpaid');
    }

    /** @return array<int, string> e.g. "Amount: ৳20,000 → ৳25,000" */
    public function changeLines(): array
    {
        return collect($this->cost['changes'] ?? [])
            ->map(fn (array $change) => $this->fieldLabel($change['field']).': '
                .$this->display($change['field'], $change['old']).' → '
                .$this->display($change['field'], $change['new']))
            ->all();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->cost['project'].': '.$this->headline())
            ->greeting($this->headline())
            ->line('**'.$this->summary().'**')
            ->line($this->status());

        foreach ($this->changeLines() as $line) {
            $mail->line($line);
        }

        return $mail->action(__('Open :project', ['project' => $this->cost['project']]), $this->cost['url']);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return collect([
            '🏠 *'.$this->cost['project'].'*',
            $this->headline(),
            '*'.$this->summary().'*',
            $this->status(),
            ...$this->changeLines(),
            $this->cost['url'],
        ])->implode("\n");
    }

    /** Short: SMS in Bangla only fits 70 letters per text. */
    /** @return array{title: string, body: string, url: string, tag: string} */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->cost['project'],
            'body' => $this->headline()."\n".$this->summary(),
            'url' => $this->cost['url'],
            'tag' => 'cost-'.md5($this->cost['url'].$this->headline().$this->summary()),
        ];
    }

    public function toSms(object $notifiable): string
    {
        return $this->cost['project'].': '.$this->headline().'. '.$this->summary().' · '.$this->status();
    }

    private function money(?int $minor): string
    {
        return Money::format($minor, $this->cost['currency'] ?? 'BDT');
    }

    private function date(?string $date): string
    {
        return $date ? Carbon::parse($date)->format(DateFormat::DATE) : '—';
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'amount' => __('Amount'),
            'description' => __('What was it for?'),
            'category' => __('Category'),
            'supplier' => __('Supplier'),
            'date' => __('Date'),
            'due_date' => __('Pay by'),
            'reference' => __('Invoice / receipt number'),
            'is_paid' => __('Status'),
            default => $field,
        };
    }

    private function display(string $field, mixed $value): string
    {
        return match (true) {
            $field === 'amount' => $this->money($value),
            $field === 'is_paid' => $value ? __('Paid') : __('Unpaid'),
            in_array($field, ['date', 'due_date'], true) => $this->date($value),
            default => filled($value) ? (string) $value : '—',
        };
    }
}
