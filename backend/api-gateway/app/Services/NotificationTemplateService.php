<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationEventDefinition;
use App\Models\NotificationTemplate;
use App\Models\User;
use RuntimeException;

class NotificationTemplateService
{
    public function render(NotificationEventDefinition $definition, User $user, string $channel, array $variables): array
    {
        $this->ensureConfiguredTemplates($definition);
        $locale = $this->localeFor($user);
        $template = $this->template($definition, $channel, $locale)
            ?? $this->template($definition, 'in_app', $locale)
            ?? $this->template($definition, $channel, 'en')
            ?? $this->template($definition, 'in_app', 'en');

        if (!$template) {
            throw new RuntimeException("No approved notification template for {$definition->template_key}.");
        }

        $this->validateVariables($template, $variables);

        return [
            $this->interpolate($template->title, $variables, 180),
            $this->interpolate($template->body, $variables, 1000),
            $template,
        ];
    }

    public function preview(string $eventKey, string $channel, string $locale, array $variables): array
    {
        $definition = app(NotificationService::class)->definition($eventKey);
        $this->ensureConfiguredTemplates($definition);
        $template = $this->template($definition, $channel, $locale)
            ?? $this->template($definition, $channel, 'en')
            ?? $this->template($definition, 'in_app', 'en');

        if (!$template) {
            throw new RuntimeException("No approved notification template for {$eventKey}.");
        }

        $this->validateVariables($template, $variables);

        return [
            'event_key' => $eventKey,
            'channel' => $channel,
            'locale' => $template->locale,
            'template_key' => $template->template_key,
            'template_version' => $template->version,
            'title' => $this->interpolate($template->title, $variables, 180),
            'body' => $this->interpolate($template->body, $variables, 1000),
        ];
    }

    public function ensureConfiguredTemplates(NotificationEventDefinition $definition): void
    {
        $configured = (array) (((array) config('notifications.templates', []))[$definition->template_key] ?? []);
        if ($configured === []) {
            return;
        }

        foreach ($configured as $locale => $channels) {
            foreach ((array) $channels as $channel => $template) {
                NotificationTemplate::query()->firstOrCreate(
                    [
                        'template_key' => $definition->template_key,
                        'version' => (int) $definition->template_version,
                        'channel' => (string) $channel,
                        'locale' => (string) $locale,
                    ],
                    [
                        'title' => (string) $template['title'],
                        'body' => (string) $template['body'],
                        'variables' => $template['variables'] ?? [],
                        'status' => 'ACTIVE',
                        'effective_at' => now(),
                    ],
                );
            }
        }
    }

    public function localeFor(User $user): string
    {
        $preferences = (array) ($user->preferences ?? []);
        $locale = (string) data_get($preferences, 'language_region.locale', config('notifications.default_locale', 'en'));

        return preg_match('/^[A-Za-z]{2}([_-][A-Za-z]{2})?$/', $locale) ? str_replace('_', '-', $locale) : 'en';
    }

    private function template(NotificationEventDefinition $definition, string $channel, string $locale): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('template_key', $definition->template_key)
            ->where('version', $definition->template_version)
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->where('status', 'ACTIVE')
            ->first();
    }

    private function validateVariables(NotificationTemplate $template, array $variables): void
    {
        $allowed = (array) ($template->variables ?? []);
        $required = array_values(array_filter($allowed, fn ($name): bool => !str_ends_with((string) $name, '?')));
        $allowedNames = array_map(fn ($name): string => rtrim((string) $name, '?'), $allowed);

        foreach ($required as $name) {
            if (!array_key_exists((string) $name, $variables) || $variables[(string) $name] === '') {
                throw new RuntimeException("Missing notification template variable: {$name}");
            }
        }

        foreach (array_keys($variables) as $name) {
            if (in_array($name, ['title', 'message', 'deep_link', 'reference', 'entity_id'], true)) {
                continue;
            }
            if ($allowedNames !== [] && !in_array($name, $allowedNames, true)) {
                throw new RuntimeException("Unexpected notification template variable: {$name}");
            }
        }
    }

    private function interpolate(string $template, array $variables, int $max): string
    {
        $rendered = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $matches) use ($variables): string {
            $value = (string) ($variables[$matches[1]] ?? '');
            $value = strip_tags($value);
            $value = preg_replace('/\s+/', ' ', $value) ?? $value;

            return trim($value);
        }, $template) ?? $template;

        return substr(trim($rendered), 0, $max);
    }
}
