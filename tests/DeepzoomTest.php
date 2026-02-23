<?php

declare(strict_types=1);

namespace DanielKm\Deepzoom\Test;

use DanielKm\Deepzoom\Deepzoom;
use DanielKm\Deepzoom\DeepzoomFactory;
use PHPUnit\Framework\TestCase;

class DeepzoomTest extends TestCase
{
    use TestHelpersTrait;

    protected function tearDown(): void
    {
        $this->cleanupTempDirs();
    }

    // ------------------------------------------------------------------
    // Constructor tests
    // ------------------------------------------------------------------

    public function testConstructorAutoDetectsProcessor(): void
    {
        $dz = new Deepzoom();
        $this->assertInstanceOf(Deepzoom::class, $dz);
    }

    /**
     * @dataProvider validProcessorProvider
     */
    public function testConstructorWithValidProcessor(
        string $processor
    ): void {
        $this->skipIfProcessorUnavailable($processor);
        $dz = new Deepzoom($this->getProcessorConfig($processor));
        $this->assertInstanceOf(Deepzoom::class, $dz);
    }

    public static function validProcessorProvider(): array
    {
        return [
            'GD' => ['GD'],
            'Imagick' => ['Imagick'],
            'ImageMagick' => ['ImageMagick'],
            'Vips' => ['Vips'],
            'PhpVips' => ['PhpVips'],
        ];
    }

    public function testConstructorWithInvalidProcessorThrows(): void
    {
        $this->expectException(\Exception::class);
        new Deepzoom(['processor' => 'NonExistent']);
    }

    public function testFactoryReturnsInstance(): void
    {
        $factory = new DeepzoomFactory();
        $dz = $factory();
        $this->assertInstanceOf(Deepzoom::class, $dz);
    }

    // ------------------------------------------------------------------
    // Process error tests
    // ------------------------------------------------------------------

    public function testProcessNonExistentFileThrows(): void
    {
        $dz = new Deepzoom();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source file does not exist');
        $dz->process('/non/existent/file.jpg');
    }

    public function testProcessExistingDestinationThrows(): void
    {
        $dz = new Deepzoom();
        $dest = $this->createTempDir();

        // First run: should succeed.
        $result = $dz->process($this->fixtureImagePath(), $dest);
        $this->assertTrue($result);

        // Second run with same destination: should throw.
        $dz2 = new Deepzoom();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Output directory already exists');
        $dz2->process($this->fixtureImagePath(), $dest);
    }

    public function testProcessWithDestinationRemoveOverwrites(): void
    {
        $dest = $this->createTempDir();

        $dz = new Deepzoom();
        $dz->process($this->fixtureImagePath(), $dest);

        $dz2 = new Deepzoom(['destinationRemove' => true]);
        $result = $dz2->process($this->fixtureImagePath(), $dest);
        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // Smoke tests per processor
    // ------------------------------------------------------------------

    /**
     * @dataProvider validProcessorProvider
     */
    public function testProcessProducesOutputWithProcessor(
        string $processor
    ): void {
        $this->skipIfProcessorUnavailable($processor);

        $dest = $this->createTempDir();
        $dz = new Deepzoom($this->getProcessorConfig($processor));
        $result = $dz->process($this->fixtureImagePath(), $dest);
        $this->assertTrue($result);

        // Check .dzi file exists.
        $dziFiles = glob($dest . '/*.dzi');
        $this->assertNotEmpty($dziFiles, "No .dzi file produced by $processor");

        // Check _files directory exists.
        $filesDirs = glob($dest . '/*_files');
        $this->assertNotEmpty(
            $filesDirs,
            "No _files directory produced by $processor"
        );

        // Validate DZI metadata.
        $dzi = $this->parseDzi($dziFiles[0]);
        $this->assertSame('jpg', $dzi['format']);
        $this->assertSame(256, $dzi['tileSize']);
        $this->assertSame(1, $dzi['overlap']);
        $this->assertSame(1217, $dzi['width']);
        $this->assertSame(797, $dzi['height']);

        // All tiles must be valid JPEG files.
        $tiles = $this->getTileFiles($filesDirs[0]);
        $this->assertNotEmpty($tiles, "No tiles produced by $processor");
        foreach ($tiles as $relPath) {
            $absPath = $filesDirs[0] . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $this->assertFileIsValidJpeg($absPath);
        }
    }
}
