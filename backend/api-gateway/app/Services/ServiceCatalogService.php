<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ServiceCatalogService
{
    private const PUBLIC_CACHE_KEY = 'navigation:public:service-catalog:v1';

    public function __construct(private readonly ServiceAvailabilityResolver $availability)
    {
    }

    public function publicCatalog(): array
    {
        return Cache::remember(
            self::PUBLIC_CACHE_KEY,
            max(1, (int) config('navigation.catalog_cache_seconds', 300)),
            fn (): array => $this->catalogPayload(null),
        );
    }

    public function catalogForUser(User $user): array
    {
        $catalog = $this->catalogPayload($user);
        $preferences = (array) ($user->preferences ?? []);
        $dashboard = (array) ($preferences['dashboard'] ?? []);
        $savedShortcuts = $dashboard['quick_access_services'] ?? null;
        if ($savedShortcuts === null && isset($dashboard['quick_access']) && is_array($dashboard['quick_access'])) {
            $savedShortcuts = $dashboard['quick_access'];
        }

        $catalog['quick_access'] = [
            'services' => $this->resolveQuickAccess($savedShortcuts, $user),
            'max_items' => max(1, min(4, (int) config('navigation.quick_access_max_items', 4))),
        ];
        $catalog['preferences'] = [
            'language' => (string) data_get($preferences, 'language_region.language_code', 'en'),
            'display_currency' => (string) data_get($preferences, 'currency_preference.display_currency', 'USD'),
        ];

        return $catalog;
    }

    public function defaultQuickAccess(?User $user = null): array
    {
        return $this->resolveQuickAccess(
            (array) config('navigation.quick_access_defaults', []),
            $user,
        );
    }

    public function resolveQuickAccessIds(mixed $requested, ?User $user = null): array
    {
        return $this->resolveQuickAccess($requested, $user);
    }

    public function quickAccessServiceIds(): array
    {
        return collect((array) config('navigation.services', []))
            ->filter(fn ($service): bool => is_array($service)
                && ($service['is_public'] ?? false) === true
                && ($service['is_quick_access_eligible'] ?? false) === true)
            ->pluck('id')
            ->filter(fn ($id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
    }

    private function catalogPayload(?User $user): array
    {
        $services = collect((array) config('navigation.services', []))
            ->filter(fn ($service): bool => is_array($service) && ($service['is_public'] ?? false) === true)
            ->map(fn (array $service): array => $this->publicService($service, $user))
            ->filter(fn (array $service): bool => $service['route'] !== null)
            ->sortBy([
                ['category_sort_order', 'asc'],
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(fn (array $service): array => collect($service)->except(['category_sort_order', 'sort_order'])->all())
            ->all();

        $availableQuickAccessIds = array_column(array_values(array_filter(
            $services,
            fn (array $service): bool => $service['is_quick_access_eligible'] && $service['available'],
        )), 'id');
        $defaults = array_values(array_filter(
            (array) config('navigation.quick_access_defaults', []),
            fn ($id): bool => is_string($id) && in_array($id, $availableQuickAccessIds, true),
        ));

        $categoriesWithServices = array_fill_keys(array_column($services, 'category'), true);
        $categories = collect((array) config('navigation.categories', []))
            ->filter(fn ($category): bool => is_array($category) && isset($categoriesWithServices[$category['id'] ?? '']))
            ->sortBy('sort_order')
            ->map(fn (array $category): array => [
                'id' => (string) $category['id'],
                'name' => (string) $category['name'],
            ])
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'services' => $services,
            'quick_access_defaults' => $defaults,
            'quick_access_max_items' => max(1, min(4, (int) config('navigation.quick_access_max_items', 4))),
            'primary_navigation' => $this->safeNavigationItems((array) config('navigation.primary_navigation', [])),
            'trade_subnavigation' => $this->tradeSubnavigation($user),
            'mobile_app' => $this->mobileAppAvailability(),
            'support' => ['route' => $this->safeInternalRoute(config('navigation.support.route'))],
        ];
    }

    private function publicService(array $service, ?User $user): array
    {
        $resolution = $this->availability->resolve($service, $user);
        $category = collect((array) config('navigation.categories', []))
            ->firstWhere('id', $service['category'] ?? null);
        $searchable = (bool) ($service['is_searchable'] ?? false);

        return [
            'id' => (string) ($service['id'] ?? ''),
            'slug' => (string) ($service['slug'] ?? ''),
            'name' => (string) ($service['name'] ?? ''),
            'short_label' => (string) ($service['short_label'] ?? $service['name'] ?? ''),
            'short_description' => (string) ($service['short_description'] ?? ''),
            'category' => (string) ($service['category'] ?? ''),
            'route' => $this->safeInternalRoute($service['route'] ?? null),
            'route_target' => in_array($service['route_target'] ?? null, ['website', 'app'], true) ? $service['route_target'] : 'website',
            'icon_key' => (string) ($service['icon_key'] ?? 'service'),
            'status' => (string) ($service['status'] ?? 'unavailable'),
            'available' => $resolution['available'],
            'reason_code' => $resolution['reason_code'],
            'is_quick_access_eligible' => (bool) ($service['is_quick_access_eligible'] ?? false),
            'requires_auth' => (bool) ($service['requires_auth'] ?? false),
            'requires_kyc' => (bool) ($service['requires_kyc'] ?? false),
            'is_searchable' => $searchable,
            'keywords' => $searchable ? array_values(array_filter((array) ($service['keywords'] ?? []), 'is_string')) : [],
            'aliases' => $searchable ? array_values(array_filter((array) ($service['aliases'] ?? []), 'is_string')) : [],
            'sort_order' => (int) ($service['sort_order'] ?? 0),
            'category_sort_order' => (int) ($category['sort_order'] ?? 0),
        ];
    }

    private function resolveQuickAccess(mixed $requested, ?User $user): array
    {
        $items = is_array($requested) ? $requested : (array) config('navigation.quick_access_defaults', []);
        $eligibleIds = [];
        foreach ((array) config('navigation.services', []) as $service) {
            if (! is_array($service) || ! ($service['is_public'] ?? false) || ! ($service['is_quick_access_eligible'] ?? false)) {
                continue;
            }
            if ($this->availability->resolve($service, $user)['available']) {
                $eligibleIds[] = (string) $service['id'];
            }
        }

        $maxItems = max(1, min(4, (int) config('navigation.quick_access_max_items', 4)));
        $resolved = [];
        foreach ($items as $id) {
            if (! is_string($id) || ! in_array($id, $eligibleIds, true) || in_array($id, $resolved, true)) {
                continue;
            }
            $resolved[] = $id;
            if (count($resolved) >= $maxItems) {
                break;
            }
        }

        return $resolved;
    }

    private function safeNavigationItems(array $items): array
    {
        return collect($items)
            ->filter(fn ($item): bool => is_array($item) && $this->safeInternalRoute($item['route'] ?? null) !== null)
            ->sortBy('sort_order')
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => (string) $item['name'],
                'route' => $this->safeInternalRoute($item['route']),
            ])
            ->values()
            ->all();
    }

    private function tradeSubnavigation(?User $user): array
    {
        return collect((array) config('navigation.trade_subnavigation', []))
            ->filter(fn ($item): bool => is_array($item) && $this->safeInternalRoute($item['route'] ?? null) !== null)
            ->sortBy('sort_order')
            ->map(function (array $item) use ($user): array {
                $resolution = $this->availability->resolve($item, $user);
                return [
                    'id' => (string) $item['id'],
                    'name' => (string) $item['name'],
                    'route' => $this->safeInternalRoute($item['route']),
                    'status' => (string) ($item['status'] ?? 'unavailable'),
                    'available' => $resolution['available'],
                    'reason_code' => $resolution['reason_code'],
                ];
            })
            ->values()
            ->all();
    }

    private function mobileAppAvailability(): array
    {
        $apps = (array) config('navigation.mobile_app', []);
        $result = [];
        foreach (['ios', 'android'] as $platform) {
            $app = (array) ($apps[$platform] ?? []);
            $status = in_array($app['status'] ?? null, ['live', 'beta', 'coming_soon', 'in_development', 'unavailable'], true)
                ? $app['status']
                : 'coming_soon';
            $url = $this->safeStoreUrl($app['url'] ?? null, $platform);
            if ($url === null && in_array($status, ['live', 'beta'], true)) {
                $status = 'coming_soon';
            }
            $result[$platform] = ['status' => $status, 'url' => $url];
        }

        return $result;
    }

    private function safeStoreUrl(mixed $url, string $platform): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }
        $parts = parse_url($url);
        $allowedHosts = $platform === 'ios' ? ['apps.apple.com'] : ['play.google.com'];
        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), $allowedHosts, true)
            || (isset($parts['port']) && $parts['port'] !== 443)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $url;
    }

    private function safeInternalRoute(mixed $route): ?string
    {
        if (! is_string($route) || ! str_starts_with($route, '/') || str_starts_with($route, '//')) {
            return null;
        }
        $decodedRoute = rawurldecode($route);
        if (str_contains($decodedRoute, '\\') || preg_match('/[\x00-\x20\x7f?#]/', $decodedRoute) === 1) {
            return null;
        }
        foreach (explode('/', $decodedRoute) as $segment) {
            if ($segment === '..' || $segment === '.') {
                return null;
            }
        }

        return $route;
    }
}
