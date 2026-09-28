<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Service;

use MiniShop3\Model\msDelivery;
use MODX\Revolution\modX;

/**
 * PROVIDER: add keys the carrier API needs. Prefer msDelivery.properties over system settings.
 */
final class Settings
{
    private const SECRET_KEYS = ['api_key', 'secret', 'secret_key', 'webhook_secret'];

    /**
     * @param array<string, scalar|null> $overrides
     */
    public function __construct(
        private readonly ?modX $modx = null,
        private readonly ?msDelivery $delivery = null,
        private readonly array $overrides = [],
    ) {
    }

    public static function fromDelivery(msDelivery $delivery, ?modX $modx = null): self
    {
        return new self($modx, $delivery);
    }

    public function apiUrl(): string
    {
        return $this->string('api_url');
    }

    public function apiKey(): string
    {
        return $this->string('api_key');
    }

    public function account(): string
    {
        return $this->string('account');
    }

    public function secret(): string
    {
        foreach (['secret', 'secret_key', 'webhook_secret'] as $key) {
            $secret = $this->string($key);
            if ($secret !== '') {
                return $secret;
            }
        }

        return '';
    }

    public function testMode(): bool
    {
        return $this->bool('test_mode', true);
    }

    public function timeout(): int
    {
        $timeout = (int) $this->string('timeout', '10');

        return $timeout > 0 ? $timeout : 10;
    }

    public function debug(): bool
    {
        return $this->bool('debug', false);
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl() !== '';
    }

    /**
     * @return array<string, scalar>
     */
    public function toSafeArray(): array
    {
        $raw = [
            'api_url' => $this->apiUrl(),
            'api_key' => $this->apiKey(),
            'account' => $this->account(),
            'secret' => $this->secret(),
            'test_mode' => $this->testMode(),
            'timeout' => $this->timeout(),
            'debug' => $this->debug(),
        ];

        return $this->maskArray($raw);
    }

    /**
     * @return array<string, scalar>
     */
    public function __debugInfo(): array
    {
        return $this->toSafeArray();
    }

    public static function maskSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $length = strlen($value);
        if ($length < 12) {
            return str_repeat('*', min($length, 8));
        }

        return str_repeat('*', 8) . substr($value, -4);
    }

    private function string(string $key, string $default = ''): string
    {
        $value = $this->raw($key);
        if ($value === null) {
            if ($this->modx instanceof modX) {
                return trim((string) $this->modx->getOption('msp3deliveryskeleton_' . $key, null, $default));
            }

            return $default;
        }

        return trim((string) $value);
    }

    private function bool(string $key, bool $default): bool
    {
        $value = $this->raw($key);
        if ($value === null) {
            $text = $this->string($key);
            if ($text === '') {
                return $default;
            }

            return in_array(strtolower($text), ['1', 'true', 'yes', 'on'], true);
        }
        if (is_bool($value)) {
            return $value;
        }
        $text = strtolower(trim((string) $value));
        if ($text === '') {
            return $default;
        }

        return in_array($text, ['1', 'true', 'yes', 'on'], true);
    }

    private function raw(string $key): bool|int|float|string|null
    {
        if (array_key_exists($key, $this->overrides)) {
            $override = $this->overrides[$key];

            return $override === '' ? null : $override;
        }
        if (!$this->delivery instanceof msDelivery) {
            return null;
        }
        $props = $this->delivery->get('properties');
        if (is_string($props) && $props !== '') {
            $decoded = json_decode($props, true);
            $props = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($props) || !array_key_exists($key, $props)) {
            return null;
        }
        $value = $props[$key];
        if ($value === '' || $value === null) {
            return null;
        }

        return is_scalar($value) ? $value : null;
    }

    /**
     * @param array<string, scalar> $raw
     * @return array<string, scalar>
     */
    private function maskArray(array $raw): array
    {
        $safe = [];
        foreach ($raw as $key => $value) {
            if (in_array($key, self::SECRET_KEYS, true) && is_string($value)) {
                $safe[$key] = self::maskSecret($value);
                continue;
            }
            $safe[$key] = $value;
        }

        return $safe;
    }
}
