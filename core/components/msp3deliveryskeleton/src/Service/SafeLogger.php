<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Service;

use MODX\Revolution\modX;

final class SafeLogger
{
    private const SECRET_KEYS = [
        'api_key',
        'apikey',
        'secret',
        'secret_key',
        'webhook_secret',
        'password',
        'token',
        'authorization',
        'signature',
    ];

    private const PII_KEYS = [
        'phone',
        'email',
        'address',
        'text_address',
        'street',
        'building',
        'room',
        'index',
    ];

    /** @var list<string> */
    public array $records = [];

    public function __construct(
        private readonly ?modX $modx = null,
        private readonly bool $debug = false,
        private readonly string $prefix = '[msp3DeliverySkeleton]',
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        if (!$this->debug) {
            return;
        }
        $this->write('debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $line = $this->prefix . ' ' . $message;
        if ($context !== []) {
            $encoded = json_encode(self::redact($context), JSON_UNESCAPED_UNICODE);
            if (is_string($encoded)) {
                $line .= ' | ' . $encoded;
            }
        }
        $this->records[] = $line;
        if ($this->modx instanceof modX) {
            $this->modx->log(
                $level === 'error' ? modX::LOG_LEVEL_ERROR : modX::LOG_LEVEL_DEBUG,
                $line
            );
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function redact(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            $name = strtolower((string) $key);
            if (in_array($name, self::SECRET_KEYS, true)) {
                $clean[(string) $key] = is_string($value) ? Settings::maskSecret($value) : '[redacted]';
                continue;
            }
            if (in_array($name, self::PII_KEYS, true)) {
                $clean[(string) $key] = self::maskPii($name, $value);
                continue;
            }
            if (is_array($value)) {
                $clean[(string) $key] = self::redact($value);
                continue;
            }
            $clean[(string) $key] = $value;
        }

        return $clean;
    }

    private static function maskPii(string $name, mixed $value): string
    {
        if (!is_scalar($value)) {
            return '[redacted]';
        }
        $text = (string) $value;
        if ($text === '') {
            return '';
        }
        if ($name === 'email' && str_contains($text, '@')) {
            [$local, $domain] = explode('@', $text, 2);

            return substr($local, 0, 1) . '***@' . $domain;
        }
        if ($name === 'phone') {
            $digits = preg_replace('/\D+/', '', $text) ?? '';
            if (strlen($digits) < 3) {
                return '***';
            }

            return str_repeat('*', max(0, strlen($digits) - 2)) . substr($digits, -2);
        }

        return '[redacted]';
    }
}
