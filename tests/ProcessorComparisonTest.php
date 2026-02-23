<?php

declare(strict_types=1);

namespace DanielKm\Deepzoom\Test;

use DanielKm\Deepzoom\Deepzoom;
use PHPUnit\Framework\TestCase;

/**
 * Cross-processor comparison tests for Deepzoom.
 *
 * All processors should produce the same structural output: same
 * levels, same tile filenames, same tile dimensions and same DZI
 * metadata. Pixel content and file sizes are NOT compared.
 */
class ProcessorComparisonTest extends TestCase
{
    use TestHelpersTrait;

    /**
     * Cached tiling results per processor.
     *
     * @var array<string, array{dest: string, dzi: string, filesDir: string}>
     */
    private static array $results = [];

    /**
     * @var string[]
     */
    private static array $staticTempDirs = [];

    public static function tearDownAfterClass(): void
    {
        // Clean up all temp directories created during the test.
        foreach (self::$staticTempDirs as $dir) {
            if (is_dir($dir)) {
                (new self('cleanup'))->removeTempDir($dir);
            }
        }
        self::$results = [];
        self::$staticTempDirs = [];
    }

    /**
     * Tile the fixture image with the given processor and cache the
     * result so that subsequent tests can compare across processors.
     */
    private function ensureTiled(string $processor): array
    {
        if (isset(self::$results[$processor])) {
            return self::$results[$processor];
        }

        $this->skipIfProcessorUnavailable($processor);

        $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'deepzoom_cmp_' . strtolower($processor) . '_' . uniqid();
        mkdir($dest, 0755, true);
        self::$staticTempDirs[] = $dest;

        $dz = new Deepzoom($this->getProcessorConfig($processor));
        $dz->process($this->fixtureImagePath(), $dest);

        $dziFiles = glob($dest . '/*.dzi');
        $filesDirs = glob($dest . '/*_files');

        self::$results[$processor] = [
            'dest' => $dest,
            'dzi' => $dziFiles[0],
            'filesDir' => $filesDirs[0],
        ];

        return self::$results[$processor];
    }

    // ------------------------------------------------------------------
    // Per-processor structural checks
    // ------------------------------------------------------------------

    /**
     * @dataProvider processorProvider
     */
    public function testDziMetadataIsCorrect(string $processor): void
    {
        $r = $this->ensureTiled($processor);
        $dzi = $this->parseDzi($r['dzi']);
        $this->assertSame('jpg', $dzi['format']);
        $this->assertSame(1, $dzi['overlap']);
        $this->assertSame(256, $dzi['tileSize']);
        $this->assertSame(1217, $dzi['width']);
        $this->assertSame(797, $dzi['height']);
    }

    /**
     * @dataProvider processorProvider
     */
    public function testAllTilesAreValidJpeg(string $processor): void
    {
        $r = $this->ensureTiled($processor);
        $tiles = $this->getTileFiles($r['filesDir']);
        $this->assertNotEmpty($tiles);
        foreach ($tiles as $relPath) {
            $abs = $r['filesDir'] . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $this->assertFileIsValidJpeg($abs);
        }
    }

    public static function processorProvider(): array
    {
        return [
            'GD' => ['GD'],
            'Imagick' => ['Imagick'],
            'ImageMagick' => ['ImageMagick'],
            'Vips' => ['Vips'],
            'PhpVips' => ['PhpVips'],
        ];
    }

    // ------------------------------------------------------------------
    // Cross-processor comparison
    // ------------------------------------------------------------------

    public function testAllProcessorsProduceSameLevels(): void
    {
        $available = $this->getAvailableProcessors();
        if (count($available) < 2) {
            $this->markTestSkipped(
                'Need at least 2 processors for comparison.'
            );
        }

        $levelSets = [];
        foreach ($available as $proc) {
            $r = $this->ensureTiled($proc);
            $levels = array_values(array_filter(
                scandir($r['filesDir']),
                fn($d) => is_dir($r['filesDir'] . '/' . $d)
                    && $d !== '.' && $d !== '..'
            ));
            sort($levels, SORT_NUMERIC);
            $levelSets[$proc] = $levels;
        }

        $reference = reset($levelSets);
        $refProc = key($levelSets);
        foreach ($levelSets as $proc => $levels) {
            $this->assertSame(
                $reference,
                $levels,
                "Levels differ between $refProc and $proc"
            );
        }
    }

    public function testAllProcessorsProduceSameTileFiles(): void
    {
        $available = $this->getAvailableProcessors();
        if (count($available) < 2) {
            $this->markTestSkipped(
                'Need at least 2 processors for comparison.'
            );
        }

        $tileSets = [];
        foreach ($available as $proc) {
            $r = $this->ensureTiled($proc);
            $tileSets[$proc] = $this->getTileFiles($r['filesDir']);
        }

        $reference = reset($tileSets);
        $refProc = key($tileSets);
        foreach ($tileSets as $proc => $tiles) {
            $this->assertSame(
                $reference,
                $tiles,
                "Tile list differs between $refProc and $proc"
            );
        }
    }

    public function testAllProcessorsProduceSameTileDimensions(): void
    {
        $available = $this->getAvailableProcessors();
        if (count($available) < 2) {
            $this->markTestSkipped(
                'Need at least 2 processors for comparison.'
            );
        }

        $dimSets = [];
        foreach ($available as $proc) {
            $r = $this->ensureTiled($proc);
            $dimSets[$proc] = $this->getTileDimensions($r['filesDir']);
        }

        $reference = reset($dimSets);
        $refProc = key($dimSets);
        foreach ($dimSets as $proc => $dims) {
            $this->assertSame(
                $reference,
                $dims,
                "Tile dimensions differ between $refProc and $proc"
            );
        }
    }

    public function testAllProcessorsProduceSameDziMetadata(): void
    {
        $available = $this->getAvailableProcessors();
        if (count($available) < 2) {
            $this->markTestSkipped(
                'Need at least 2 processors for comparison.'
            );
        }

        $dziSets = [];
        foreach ($available as $proc) {
            $r = $this->ensureTiled($proc);
            $dziSets[$proc] = $this->parseDzi($r['dzi']);
        }

        $reference = reset($dziSets);
        $refProc = key($dziSets);
        foreach ($dziSets as $proc => $dzi) {
            $this->assertSame(
                $reference,
                $dzi,
                "DZI metadata differs between $refProc and $proc"
            );
        }
    }
}
