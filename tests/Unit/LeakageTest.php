<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LeakageTest extends TestCase
{
    public function testProductionTreesHaveNoCarrierNames(): void
    {
        $pattern = '/CDEK|Yandex|Ozon|Russian Post|Dalli|Boxberry/i';
        $hits = [];
        foreach (['core', '_build', 'assets'] as $dir) {
            $root = dirname(__DIR__, 2) . '/' . $dir;
            if (!is_dir($root)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $contents = (string) file_get_contents($file->getPathname());
                if (preg_match($pattern, $contents) === 1) {
                    $hits[] = $file->getPathname();
                }
            }
        }
        self::assertSame([], $hits);
    }
}
