<?php

declare(strict_types=1);

final class Msp3DeliverySkeletonGenerator
{
    public const NAME_PATTERN = '/^msp3[A-Z][A-Za-z0-9]+$/';
    public const PROVIDER_PATTERN = '/^[A-Z][A-Za-z0-9]+$/';
    public const VENDOR_PATTERN = '/^[A-Z][A-Za-z0-9]+$/';
    public const ALLOWED_KEEP = ['shipment', 'webhook', 'manager'];
    private const ALLOWED_FLAGS = ['name', 'provider', 'vendor', 'keep', 'skip-checks'];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $root,
        private readonly array $options,
    ) {
    }

    /**
     * @param list<string> $argv
     * @return array<string, string|true>
     */
    public static function parseArgv(array $argv): array
    {
        $opts = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (!str_starts_with($arg, '--')) {
                throw new InvalidArgumentException('Unexpected argument: ' . $arg);
            }
            $eq = strpos($arg, '=');
            $key = $eq === false ? substr($arg, 2) : substr($arg, 2, $eq - 2);
            $value = $eq === false ? true : substr($arg, $eq + 1);
            if (!in_array($key, self::ALLOWED_FLAGS, true)) {
                throw new InvalidArgumentException('Unknown flag: --' . $key);
            }
            $opts[$key] = $value;
        }

        return $opts;
    }

    public static function isValidName(string $name): bool
    {
        return (bool) preg_match(self::NAME_PATTERN, $name);
    }

    public static function isValidProvider(string $provider): bool
    {
        return (bool) preg_match(self::PROVIDER_PATTERN, $provider);
    }

    public static function isValidVendor(string $vendor): bool
    {
        return (bool) preg_match(self::VENDOR_PATTERN, $vendor);
    }

    public static function namespaceFromName(string $name): string
    {
        return 'Msp3' . substr($name, 4);
    }

    /**
     * @param list<string> $keep
     * @return list<string>
     */
    public static function normalizeKeep(array $keep): array
    {
        $keep = array_values(array_unique(array_filter($keep)));
        foreach ($keep as $mod) {
            if (!in_array($mod, self::ALLOWED_KEEP, true)) {
                throw new InvalidArgumentException('Unknown --keep module: ' . $mod);
            }
        }
        if (in_array('manager', $keep, true) && !in_array('shipment', $keep, true)) {
            $keep[] = 'shipment';
        }

        return $keep;
    }

    /**
     * @return array<string, string>
     */
    public static function replacements(string $name, string $provider, string $vendor): array
    {
        $namespace = self::namespaceFromName($name);
        $kebab = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));

        $map = [
            'Ibochkarev\\Msp3DeliverySkeleton' => $vendor . '\\' . $namespace,
            'Ibochkarev\\\\Msp3DeliverySkeleton' => $vendor . '\\\\' . $namespace,
            'SkeletonDelivery' => $provider . 'Delivery',
            'SkeletonShipment' => $provider . 'Shipment',
            'msp3DeliverySkeleton' => $name,
            'Msp3DeliverySkeleton' => $namespace,
            'msp3deliveryskeleton' => strtolower($name),
            'msp3-delivery-skeleton' => $kebab,
            'Delivery Skeleton' => $provider,
        ];
        if ($vendor !== 'Ibochkarev') {
            $map['Ibochkarev'] = $vendor;
            $map['ibochkarev'] = strtolower($vendor);
        }

        return $map;
    }

    public function run(): int
    {
        $name = (string) ($this->options['name'] ?? '');
        $provider = (string) ($this->options['provider'] ?? '');
        $vendor = (string) ($this->options['vendor'] ?? 'Ibochkarev');
        if ($name === '' || $provider === '') {
            throw new InvalidArgumentException(
                'Usage: php bin/init.php --name=msp3Cdek --provider=Cdek [--keep=shipment,webhook,manager]'
            );
        }
        if (!self::isValidName($name)) {
            throw new InvalidArgumentException('name must look like msp3Cdek');
        }
        if (!self::isValidProvider($provider)) {
            throw new InvalidArgumentException('provider must look like Cdek');
        }
        if (!self::isValidVendor($vendor)) {
            throw new InvalidArgumentException('vendor must look like Ibochkarev');
        }
        if (!is_file($this->root . '/core/components/msp3deliveryskeleton/src/Delivery/SkeletonDelivery.php')) {
            throw new RuntimeException('Already initialized or not a skeleton tree');
        }

        $keep = self::normalizeKeep(array_filter(array_map(
            'trim',
            explode(',', (string) ($this->options['keep'] ?? ''))
        )));
        if (in_array('manager', $keep, true)) {
            echo "Note: --keep=manager implies --keep=shipment\n";
        }

        $this->removeUnusedModules($keep);
        $this->stripInitBlocks(array_values(array_diff(self::ALLOWED_KEEP, $keep)));
        $this->replaceTokens(self::replacements($name, $provider, $vendor));
        $this->renamePaths(self::replacements($name, $provider, $vendor));
        $this->writeGeneratedReadme($name, $provider, $vendor, $keep);

        if (empty($this->options['skip-cleanup'])) {
            $this->cleanupGeneratorFiles();
            $this->rewritePhpunitConfig();
        }

        $leaks = $this->findLeaks();
        if ($leaks !== []) {
            throw new RuntimeException("Leftover skeleton tokens:\n" . implode("\n", $leaks));
        }

        $this->lintPhp();
        if (!isset($this->options['skip-checks'])) {
            $this->runChecks();
        }

        echo "Done. Fill // PROVIDER: markers, then php _build/build.php\n";

        return 0;
    }

    /**
     * @param list<string> $keep
     */
    private function removeUnusedModules(array $keep): void
    {
        $files = [
            'webhook' => [
                'core/components/msp3deliveryskeleton/src/Webhook/WebhookParser.php',
                'tests/Unit/WebhookParserTest.php',
                'tests/Fixtures/webhook-valid.json',
                'tests/Fixtures/webhook-invalid.json',
            ],
            'shipment' => [
                'core/components/msp3deliveryskeleton/src/Shipment/SkeletonShipment.php',
                'core/components/msp3deliveryskeleton/src/Shipment/ShipmentResponse.php',
                'tests/Unit/SkeletonShipmentTest.php',
            ],
            'manager' => [
                'core/components/msp3deliveryskeleton/config/routes.manager.php',
                'core/components/msp3deliveryskeleton/src/Manager/ShipmentActions.php',
                'assets/components/msp3deliveryskeleton/js/mgr/order-tab.js',
                'assets/components/msp3deliveryskeleton/css/mgr/order-tab.css',
                '_build/resolvers/resolver_03_routes.php',
                'tests/Unit/ShipmentActionsTest.php',
            ],
        ];
        $alwaysRemove = [
            'tests/Unit/LeakageTest.php',
        ];
        foreach ($alwaysRemove as $relative) {
            $path = $this->root . '/' . $relative;
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach ($files as $module => $paths) {
            if (in_array($module, $keep, true)) {
                continue;
            }
            foreach ($paths as $relative) {
                $path = $this->root . '/' . $relative;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        if (!in_array('shipment', $keep, true) && !in_array('webhook', $keep, true)) {
            foreach ([
                'core/components/msp3deliveryskeleton/src/Service/StatusMap.php',
                'tests/Unit/StatusMapTest.php',
            ] as $relative) {
                $path = $this->root . '/' . $relative;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @param list<string> $remove
     */
    private function stripInitBlocks(array $remove): void
    {
        if ($remove === []) {
            return;
        }
        foreach ($this->listTextFiles() as $file) {
            $contents = (string) file_get_contents($file);
            $updated = $contents;
            foreach ($remove as $module) {
                $updated = (string) preg_replace(
                    '#^[ \t]*// INIT:' . preg_quote($module, '#') . ':begin.*?// INIT:' . preg_quote($module, '#') . ':end\R?#ms',
                    '',
                    $updated
                );
            }
            if ($updated !== $contents) {
                file_put_contents($file, $updated);
            }
        }
    }

    /**
     * @param array<string, string> $replacements
     */
    private function replaceTokens(array $replacements): void
    {
        foreach ($this->listTextFiles() as $file) {
            $contents = (string) file_get_contents($file);
            $updated = str_replace(array_keys($replacements), array_values($replacements), $contents);
            if ($updated !== $contents) {
                file_put_contents($file, $updated);
            }
        }
    }

    /**
     * @param array<string, string> $replacements
     */
    private function renamePaths(array $replacements): void
    {
        $dirs = [];
        $files = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            if ($this->isSkipped($path)) {
                continue;
            }
            $base = $file->getFilename();
            $newBase = str_replace(array_keys($replacements), array_values($replacements), $base);
            if ($newBase === $base) {
                continue;
            }
            $target = $file->getPath() . '/' . $newBase;
            if ($file->isDir()) {
                $dirs[] = [$path, $target];
            } else {
                $files[] = [$path, $target];
            }
        }
        foreach ($files as [$from, $to]) {
            if (!is_dir(dirname($to))) {
                mkdir(dirname($to), 0777, true);
            }
            rename($from, $to);
        }
        foreach ($dirs as [$from, $to]) {
            if (is_dir($from) && !is_dir($to)) {
                rename($from, $to);
            }
        }
    }

    /**
     * @param list<string> $keep
     */
    private function writeGeneratedReadme(string $name, string $provider, string $vendor, array $keep): void
    {
        $template = $this->root . '/bin/templates/README.md';
        if (!is_file($template)) {
            return;
        }
        $keepList = $keep === [] ? 'delivery only' : implode(', ', $keep);
        $readme = str_replace(
            ['{{NAME}}', '{{PROVIDER}}', '{{VENDOR}}', '{{NAMESPACE}}', '{{NAME_LOWER}}', '{{KEEP}}'],
            [$name, $provider, $vendor, self::namespaceFromName($name), strtolower($name), $keepList],
            (string) file_get_contents($template)
        );
        file_put_contents($this->root . '/README.md', $readme);
    }

    private function cleanupGeneratorFiles(): void
    {
        $this->removePath($this->root . '/bin');
        $this->removePath($this->root . '/tests/Generator');
    }

    private function rewritePhpunitConfig(): void
    {
        $path = $this->root . '/phpunit.xml';
        if (!is_file($path)) {
            return;
        }
        $xml = (string) file_get_contents($path);
        $xml = (string) preg_replace(
            '#\s*<testsuite name="Generator">.*?</testsuite>#s',
            '',
            $xml
        );
        file_put_contents($path, $xml);
    }

    /**
     * @return list<string>
     */
    public function findLeaks(): array
    {
        $needles = [
            'msp3DeliverySkeleton',
            'Msp3DeliverySkeleton',
            'SkeletonDelivery',
            'SkeletonShipment',
            'msp3deliveryskeleton',
            'Ibochkarev\\Msp3DeliverySkeleton',
        ];
        $hits = [];
        foreach (['core', '_build', 'assets'] as $dir) {
            $path = $this->root . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $contents = (string) file_get_contents($file->getPathname());
                foreach ($needles as $needle) {
                    if (str_contains($contents, $needle)) {
                        $hits[] = $file->getPathname() . ': ' . $needle;
                    }
                }
            }
        }

        return $hits;
    }

    private function lintPhp(): void
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            if ($this->isSkipped($path)) {
                continue;
            }
            $source = (string) file_get_contents($path);
            try {
                $tokens = token_get_all($source, TOKEN_PARSE);
                unset($tokens);
            } catch (ParseError $exception) {
                throw new RuntimeException('php -l failed: ' . $path . ': ' . $exception->getMessage());
            }
        }
    }

    private function runChecks(): void
    {
        $this->runOrFail(['composer', 'install', '--no-interaction']);
        $this->runOrFail(['composer', 'test']);
    }

    /**
     * @param list<string> $command
     */
    private function runOrFail(array $command): void
    {
        echo implode(' ', $command) . "\n";
        $spec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];
        $process = proc_open($command, $spec, $pipes, $this->root);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start: ' . implode(' ', $command));
        }
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException('Command failed (' . $code . '): ' . implode(' ', $command));
        }
    }

    /**
     * @return list<string>
     */
    private function listTextFiles(): array
    {
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if ($this->isSkipped($path)) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['php', 'md', 'txt', 'js', 'css', 'json', 'xml', 'inc', 'neon'], true)) {
                continue;
            }
            $out[] = $path;
        }

        return $out;
    }

    private function isSkipped(string $path): bool
    {
        foreach (['/.git/', '/vendor/', '/.phpunit.cache/', '/node_modules/'] as $skip) {
            if (str_contains($path, $skip)) {
                return true;
            }
        }

        return false;
    }

    private function removePath(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    }
}
