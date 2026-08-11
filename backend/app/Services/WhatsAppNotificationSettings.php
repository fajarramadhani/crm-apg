<?php

namespace App\Services;

use App\Models\WhatsAppNotificationSetting;
use Illuminate\Support\Facades\DB;

final class WhatsAppNotificationSettings
{
    public function enabled(): bool
    {
        return (bool) config('whatsapp.enabled') && ($this->record()?->enabled ?? true);
    }

    public function eventEnabled(string $event): bool
    {
        $events = $this->events();

        return $events[$event] ?? true;
    }

    /** @return array<string, bool> */
    public function events(): array
    {
        $defaults = collect(config('whatsapp.events', []))->map(fn ($value): bool => (bool) $value)->all();
        $overrides = $this->record()?->events ?? [];

        return [...$defaults, ...array_intersect_key($overrides, $defaults)];
    }

    /** @return array{enabled: bool, events: array<string, bool>, config_defaults: array{enabled: bool, events: array<string, bool>}, has_database_override: bool} */
    public function effective(): array
    {
        $record = $this->record();
        $defaults = [
            'enabled' => (bool) config('whatsapp.enabled'),
            'events' => collect(config('whatsapp.events', []))->map(fn ($value): bool => (bool) $value)->all(),
        ];

        return [
            'enabled' => $defaults['enabled'] && ($record?->enabled ?? true),
            'events' => [...$defaults['events'], ...array_intersect_key($record?->events ?? [], $defaults['events'])],
            'config_defaults' => $defaults,
            'has_database_override' => $record !== null,
        ];
    }

    /** @param array<string, bool> $events */
    public function update(bool $enabled, array $events): WhatsAppNotificationSetting
    {
        return DB::transaction(function () use ($enabled, $events): WhatsAppNotificationSetting {
            $record = WhatsAppNotificationSetting::query()->lockForUpdate()->first();
            if (! $record) {
                return WhatsAppNotificationSetting::query()->createOrFirst(
                    ['scope' => 'global'],
                    ['enabled' => $enabled, 'events' => $events],
                );
            }
            $record->update(['enabled' => $enabled, 'events' => [...($record->events ?? []), ...$events]]);

            return $record->refresh();
        });
    }

    private function record(): ?WhatsAppNotificationSetting
    {
        return WhatsAppNotificationSetting::query()->where('scope', 'global')->first();
    }
}
