<?php

declare(strict_types=1);

namespace DanielKm\Deepzoom\Test;

use DanielKm\Deepzoom\Deepzoom;

trait TestHelpersTrait
{
    /**
     * @var string[]
     */
    protected array $tempDirs = [];

    protected function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'deepzoom_test_' . uniqid();
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    protected function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path)
                ? $this->removeTempDir($path)
                : unlink($path);
        }
        rmdir($dir);
    }

    protected function cleanupTempDirs(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeTempDir($dir);
        }
        $this->tempDirs = [];
    }

    protected function fixtureImagePath(): string
    {
        return __DIR__ . '/fixtures/test-image.jpg';
    }

    /**
     * Path to a small JPEG with EXIF orientation 6 (90° CW).
     *
     * Raw pixels are 100x60; after auto-orient: 60x100.
     */
    protected function fixtureExif6ImagePath(): string
    {
        return __DIR__ . '/fixtures/test-image-exif6.jpg';
    }

    /**
     * Skip test if the processor is not available.
     */
    protected function skipIfProcessorUnavailable(string $processor): void
    {
        try {
            new Deepzoom($this->getProcessorConfig($processor));
        } catch (\Exception $e) {
            $this->markTestSkipped(
                "Processor $processor is not available: "
                . $e->getMessage()
            );
        }
    }

    /**
     * Return the config array for a given processor.
     */
    protected function getProcessorConfig(string $processor): array
    {
        return ['processor' => $processor];
    }

    /**
     * Assert that a file is a valid JPEG image.
     */
    protected function assertFileIsValidJpeg(string $path): void
    {
        $this->assertFileExists($path);
        $info = @getimagesize($path);
        $this->assertNotFalse($info, "Not a valid image: $path");
        $this->assertSame(
            IMAGETYPE_JPEG,
            $info[2],
            "File is not JPEG: $path"
        );
    }

    /**
     * Return sorted relative paths of tile files in a _files directory.
     */
    protected function getTileFiles(string $filesDir): array
    {
        $result = [];
        $levels = array_diff(scandir($filesDir), ['.', '..']);
        sort($levels, SORT_NUMERIC);
        foreach ($levels as $level) {
            $levelDir = $filesDir . DIRECTORY_SEPARATOR . $level;
            if (!is_dir($levelDir)) {
                continue;
            }
            $tiles = array_diff(scandir($levelDir), ['.', '..']);
            sort($tiles);
            foreach ($tiles as $tile) {
                $result[] = $level . '/' . $tile;
            }
        }
        return $result;
    }

    /**
     * Return tile dimensions: [relative_path => [width, height]].
     */
    protected function getTileDimensions(string $filesDir): array
    {
        $result = [];
        foreach ($this->getTileFiles($filesDir) as $relPath) {
            $absPath = $filesDir . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $info = @getimagesize($absPath);
            if ($info !== false) {
                $result[$relPath] = [$info[0], $info[1]];
            }
        }
        return $result;
    }

    /**
     * Parse a .dzi XML file and return metadata.
     *
     * @return array{format: string, overlap: int, tileSize: int,
     *     width: int, height: int}
     */
    protected function parseDzi(string $path): array
    {
        $this->assertFileExists($path);
        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml, "Cannot parse DZI: $path");
        $attrs = $xml->attributes();
        $size = $xml->Size->attributes();
        return [
            'format' => (string) $attrs['Format'],
            'overlap' => (int) $attrs['Overlap'],
            'tileSize' => (int) $attrs['TileSize'],
            'width' => (int) $size['Width'],
            'height' => (int) $size['Height'],
        ];
    }

    /**
     * List all available processors (those that can be instantiated).
     *
     * @return string[]
     */
    protected function getAvailableProcessors(): array
    {
        $all = ['GD', 'Imagick', 'ImageMagick', 'Vips', 'PhpVips'];
        $available = [];
        foreach ($all as $proc) {
            try {
                new Deepzoom($this->getProcessorConfig($proc));
                $available[] = $proc;
            } catch (\Exception $e) {
                // Not available.
            }
        }
        return $available;
    }
}
