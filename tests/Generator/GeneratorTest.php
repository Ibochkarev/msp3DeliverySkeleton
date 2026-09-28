<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Generator;

use FilesystemIterator;
use InvalidArgumentException;
use Msp3DeliverySkeletonGenerator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/bin/Generator.php';

final class GeneratorTest extends TestCase
{
    public function testNameProviderNamespaceAndReplacements(): void
    {
        self::assertTrue(Msp3DeliverySkeletonGenerator::isValidName('msp3Cdek'));
        self::assertTrue(Msp3DeliverySkeletonGenerator::isValidName('msp3RussianPost'));
        self::assertTrue(Msp3DeliverySkeletonGenerator::isValidName('msp3YandexDelivery'));
        self::assertTrue(Msp3DeliverySkeletonGenerator::isValidName('msp3OzonDelivery'));
        self::assertFalse(Msp3DeliverySkeletonGenerator::isValidName('Cdek'));
        self::assertFalse(Msp3DeliverySkeletonGenerator::isValidName('ms3Cdek'));
        self::assertFalse(Msp3DeliverySkeletonGenerator::isValidName('msp3-cdek'));
        self::assertFalse(Msp3DeliverySkeletonGenerator::isValidName('msp3 cdek'));
        self::assertTrue(Msp3DeliverySkeletonGenerator::isValidProvider('Cdek'));
        self::assertFalse(Msp3DeliverySkeletonGenerator::isValidProvider('cdek'));
        self::assertSame('Msp3Cdek', Msp3DeliverySkeletonGenerator::namespaceFromName('msp3Cdek'));
        $map = Msp3DeliverySkeletonGenerator::replacements('msp3Cdek', 'Cdek', 'Ibochkarev');
        self::assertSame('Ibochkarev\\Msp3Cdek', $map['Ibochkarev\\Msp3DeliverySkeleton']);
        self::assertSame('CdekDelivery', $map['SkeletonDelivery']);
        self::assertSame('msp3cdek', $map['msp3deliveryskeleton']);
        self::assertSame('msp3-cdek', $map['msp3-delivery-skeleton']);
    }

    public function testKeepFlagsAndInvalidArguments(): void
    {
        self::assertSame(['manager', 'shipment'], Msp3DeliverySkeletonGenerator::normalizeKeep(['manager']));
        $this->expectException(InvalidArgumentException::class);
        Msp3DeliverySkeletonGenerator::normalizeKeep(['receipts']);
    }

    public function testUnknownFlag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Msp3DeliverySkeletonGenerator::parseArgv(['init.php', '--foo=1']);
    }

    public function testGenerateDefaultAndAllKeeps(): void
    {
        $default = $this->generate('msp3Cdek', 'Cdek', '');
        self::assertFileExists($default . '/core/components/msp3cdek/src/Delivery/CdekDelivery.php');
        self::assertFileDoesNotExist($default . '/core/components/msp3cdek/src/Shipment/CdekShipment.php');
        self::assertFileDoesNotExist($default . '/core/components/msp3cdek/src/Webhook/WebhookParser.php');
        self::assertFileDoesNotExist($default . '/_build/resolvers/resolver_03_routes.php');
        self::assertFileDoesNotExist($default . '/bin/init.php');
        self::assertFileDoesNotExist($default . '/tests/Unit/LeakageTest.php');
        self::assertDirectoryDoesNotExist($default . '/tests/Generator');
        $phpunit = (string) file_get_contents($default . '/phpunit.xml');
        self::assertStringNotContainsString('name="Generator"', $phpunit);
        $delivery = (string) file_get_contents($default . '/core/components/msp3cdek/src/Delivery/CdekDelivery.php');
        self::assertStringNotContainsString('ShipmentProviderInterface', $delivery);
        self::assertStringNotContainsString('SkeletonDelivery', $delivery);
        self::assertStringContainsString('Ibochkarev\\Msp3Cdek', $delivery);
        $this->assertNoLeaks($default);

        $full = $this->generate('msp3Cdek', 'Cdek', 'shipment,webhook,manager');
        self::assertFileExists($full . '/core/components/msp3cdek/src/Shipment/CdekShipment.php');
        self::assertFileExists($full . '/core/components/msp3cdek/src/Webhook/WebhookParser.php');
        self::assertFileExists($full . '/assets/components/msp3cdek/js/mgr/order-tab.js');
        $this->assertNoLeaks($full);
    }

    public function testRejectsSecondInit(): void
    {
        $dir = $this->generate('msp3Cdek', 'Cdek', '');
        $this->expectException(RuntimeException::class);
        (new Msp3DeliverySkeletonGenerator($dir, [
            'name' => 'msp3Cdek',
            'provider' => 'Cdek',
            'skip-checks' => true,
        ]))->run();
    }

    private function generate(string $name, string $provider, string $keep): string
    {
        $target = sys_get_temp_dir() . '/msp3ds-' . bin2hex(random_bytes(4));
        $this->copyTree(dirname(__DIR__, 2), $target);
        $opts = [
            'name' => $name,
            'provider' => $provider,
            'skip-checks' => true,
        ];
        if ($keep !== '') {
            $opts['keep'] = $keep;
        }
        (new Msp3DeliverySkeletonGenerator($target, $opts))->run();

        return $target;
    }

    private function assertNoLeaks(string $root): void
    {
        $needles = ['msp3DeliverySkeleton', 'Msp3DeliverySkeleton', 'SkeletonDelivery', 'msp3deliveryskeleton'];
        foreach (['core', '_build', 'assets'] as $dir) {
            $path = $root . '/' . $dir;
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
                    self::assertStringNotContainsString($needle, $contents, $file->getPathname());
                }
            }
        }
    }

    private function copyTree(string $from, string $to): void
    {
        mkdir($to, 0777, true);
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $relative = substr($item->getPathname(), strlen($from) + 1);
            if (str_starts_with($relative, 'vendor/') || str_starts_with($relative, '.git/') || str_starts_with($relative, '.phpunit.cache/')) {
                continue;
            }
            $target = $to . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
                continue;
            }
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}
