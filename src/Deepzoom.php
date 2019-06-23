<?php
namespace DanielKm\Deepzoom;

/**
 * Deepzoom big images into tiles supported by "OpenSeadragon", "OpenLayers" and
 * many other viewers.
 *
 * The process is a mix of the Laravel plugin [Deepzoom](https://github.com/jeremytubbs/deepzoom)
 * of Jeremy Tubbs, the standalone open zoom builder [Deepzoom.php](https://github.com/nfabre/deepzoom.php)
 * of Nicolas Fabre, the [blog](http://omarriott.com/aux/leaflet-js-non-geographical-imagery/)
 * of Olivier Mariott, and the Zoomify converter (see the integrated library).
 * See respective copyright and license (MIT and GNU/GPL) in the above pages.
 */

/**
 * Provides an interface to perform tiling of images.
 *
 * @package DanielKm\Deepzoom
 */
class Deepzoom
{
    /**
     * The processor to use.
     *
     * @var string
     */
    protected $processor;

    /**
     * The path to command line ImageMagick convert when the processor is "cli".
     *
     * @var string
     */
    protected $convertPath;

    /**
     * The strategy to use by php to process a command ("exec" or "proc_open").
     *
     * @var string
     */
    protected $executeStrategy;

    /**
     * The path to the image.
     *
     * @var string
     */
    protected $filepath;

    /**
     * The path to the destination dir.
     *
     * @var string
     */
    protected $destinationDir;

    /**
     * If an existing destination should be removed.
     *
     * @var int
     */
    protected $destinationRemove = false;

    /**
     * The file system mode of the directories.
     *
     * @var int
     */
    protected $dirMode = 0755;

    /**
     * The size of tiles.
     *
     * @var int
     */
    protected $tileSize = 256;

    /**
     * The overlap of tiles.
     *
     * @var int
     */
    protected $tileOverlap = 1;

    /**
     * The format of tiles.
     *
     * @var string
     */
    protected $tileFormat = 'jpg';

    /**
     * The quality of the tile.
     *
     * @var int
     */
    protected $tileQuality = 85;

    /**
     * Various metadata of the source and tiles.
     *
     * @array
     */
    protected $data = [];

    /**
     * Constructor.
     *
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config = null)
    {
        if (is_null($config)) {
            $config = [];
        }

        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Check the processor.
        // Automatic.
        if (empty($this->processor)) {
            if (extension_loaded('imagick')) {
                $this->processor = 'Imagick';
            } elseif (extension_loaded('gd')) {
                $this->processor = 'GD';
            } elseif (!empty($this->convertPath)) {
                $this->processor = 'ImageMagick';
            } else {
                $this->convertPath = $this->getConvertPath();
                if (!empty($this->convertPath)) {
                    $this->processor = 'ImageMagick';
                } else {
                    throw new \Exception('Convert path is not available.');
                }
            }
        }
        // Imagick.
        elseif ($this->processor == 'Imagick') {
            if (!extension_loaded('imagick')) {
                throw new \Exception('Imagick library is not available.');
            }
        }
        // GD.
        elseif ($this->processor == 'GD') {
            if (!extension_loaded('gd')) {
                throw new \Exception('GD library is not available.');
            }
        }
        // CLI.
        elseif ($this->processor == 'ImageMagick') {
            if (empty($this->convertPath)) {
                $this->convertPath = $this->getConvertPath();
                if (empty($this->convertPath)) {
                    throw new \Exception('Convert path is not available.');
                }
            }
        }
        // Error.
        else {
            throw new \Exception('No graphic library available.');
        }
    }

    /**
     * Deepzoom the specified image and it them in the destination dir.
     *
     * Check to be sure the file hasn't been converted already.
     *
     * @param string $filepath The path to the image.
     * @param string $destinationDir The directory where to store the tiles.
     * @return bool
     */
    public function process($filepath, $destinationDir = '')
    {
        $this->filepath = realpath($filepath);
        $this->destinationDir = $destinationDir;

        $this->getImageMetadata();
        $result = $this->createDataContainer();
        if (!$result) {
            throw new \Exception('Output directory already exists.');
        }
        $this->processImage();
        $result = $this->saveXMLOutput();
        return $result;
    }

    /**
     * Given an image name, load it and extract metadata.
     */
    protected function getImageMetadata()
    {
        list($this->data['width'], $this->data['height'], $this->data['format']) = getimagesize($this->filepath);
    }

    /**
     * Create a container (a folder) for tiles and tile metadata if not set.
     */
    protected function createDataContainer()
    {
        if ($this->destinationDir) {
            $locationDir = $this->destinationDir;
            $filename = basename($this->filepath);
        }
        // Determine the path to the directory from the filepath.
        else {
            list($root) = $this->getRootAndDotExtension($this->filepath);
            $locationDir = dirname($root);
            $filename = basename($root);
        }

        $this->data['baseDir'] = $locationDir;
        $basepath = $locationDir . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME);
        $this->data['dzi'] = $basepath . '.dzi';
        $this->data['tileDir'] = $basepath . '_files';

        // If the paths already exist, an image is being re-processed, clean up
        // for it.
        if ($this->destinationRemove) {
            if (file_exists($this->data['dzi'])) {
                unlink($this->data['dzi']);
            }
            if (is_dir($this->data['tileDir'])) {
                $this->rmDir($this->data['tileDir']);
            }
        } elseif (file_exists($this->data['dzi']) || is_dir($this->data['tileDir'])) {
            return false;
        }

        mkdir($this->data['tileDir'], $this->dirMode, true);

        return  true;
    }

    /**
     * Create a container for the next group of tiles within the data container.
     */
    protected function createTileContainer($tileContainerName)
    {
        $tileContainerPath = $this->data['tileDir'] . DIRECTORY_SEPARATOR . $tileContainerName;
        if (!is_dir($tileContainerPath)) {
            mkdir($tileContainerPath, $this->dirMode);
        }
    }

    /**
     * Starting with the original image, start processing each level.
     */
    protected function processImage()
    {
        switch ($this->processor) {
            case 'Imagick':
                $image = new \Imagick();
                $image->readImage($this->filepath);
                // Keep icc profiles, but remove metadata and convert colorspace
                // to optimize for web delivery.
                $profiles = $image->getImageProfiles('icc', true);
                $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
                $image->stripImage();
                if (!empty($profiles['icc'])) {
                    $image->profileImage('icc', $profiles['icc']);
                }
                break;

            case 'GD':
                $image = $this->openImage();
                if (empty($image)) {
                    throw new \Exception('GD cannot manage the image.');
                }
                $this->data['image'] = $image;
                switch (strtolower(pathinfo($this->filepath, PATHINFO_EXTENSION))) {
                    case 'jpg':
                    case 'jpe':
                    case 'jpeg':
                        $inputPel = new \lsolesen\pel\PelJpeg($this->filepath);
                        $this->data['icc'] = $inputPel->getIcc();
                        break;
                }
                break;

            case 'ImageMagick':
                $image = $this->filepath;
                break;
        }

        $width = $this->data['width'];
        $height = $this->data['height'];

        $maxDimension = max([$width, $height]);
        $numLevels = $this->getNumLevels($maxDimension);

        foreach (range($numLevels - 1, 0) as $level) {
            $this->createTileContainer($level);
            $scale = $this->getScaleForLevel($numLevels, $level);
            $dimension = $this->getDimensionForScale($width, $height, $scale);
            $this->createLevelTiles($image, $level, $dimension['width'], $dimension['height']);
        }

        switch ($this->processor) {
            case 'Imagick':
                $image->destroy();
                break;

            case 'GD':
                imagedestroy($image);
                break;

            case 'ImageMagick':
                // Nothing to do.
                break;
        }
    }

    /**
     * Get the number of levels in the pyramid.
     *
     * @param $maxDimension
     * @return int
     */
    protected function getNumLevels($maxDimension)
    {
        return (integer) ceil(log($maxDimension, 2)) + 1;
    }

    /**
     * Get the number of tiles according to the tile size.
     *
     * @param $width
     * @param $height
     * @return array
     */
    protected function getNumTiles($width, $height)
    {
        $columns = (int) ceil(floatval($width) / $this->tileSize);
        $rows = (int) ceil(floatval($height) / $this->tileSize);
        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Get the scale for the specified level, according to the number of levels.
     *
     * @param $numberLevels
     * @param $level
     * @return number
     */
    protected function getScaleForLevel($numberLevels, $level)
    {
        $maxLevel = $numberLevels - 1;
        return pow(0.5, $maxLevel - $level);
    }

    /**
     * Get the dimension for the specified size and scale.
     *
     * @param $width
     * @param $height
     * @param $scale
     * @return array
     */
    protected function getDimensionForScale($width, $height, $scale)
    {
        $width = (integer) ceil($width * $scale);
        $height = (integer) ceil($height * $scale);
        return ['width' => $width, 'height' => $height];
    }

    /**
     * Process a level of tiles.
     *
     * @param $image
     * @param $level
     * @param $width
     * @param $height
     */
    protected function createLevelTiles($image, $level, $width, $height)
    {
        // Create new image at scaled dimensions.
        switch ($this->processor) {
            case 'Imagick':
                $image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, false);
                break;

            case 'GD':
                $imageLevel = $this->imageResize($width, $height);
                break;

            case 'ImageMagick':
                $resize = [];
                $resize['width'] = $width;
                $resize['height'] = $height;
                break;
        }

        $basepath = $this->data['tileDir'] . DIRECTORY_SEPARATOR . $level;

        // Get column and row count for level.
        $tiles = $this->getNumTiles($width, $height);

        foreach (range(0, $tiles['columns'] - 1) as $column) {
            foreach (range(0, $tiles['rows'] - 1) as $row) {
                $filename = $column . '_' . $row . '.' . $this->tileFormat;
                $filepath = $basepath . DIRECTORY_SEPARATOR . $filename;
                $bounds = $this->getTileBounds($level, $column, $row, $width, $height);

                switch ($this->processor) {
                    case 'Imagick':
                        $tileImage = clone $image;
                        $tileImage->setImagePage(0, 0, 0, 0);
                        $tileImage->cropImage($bounds['width'], $bounds['height'], $bounds['x'], $bounds['y']);
                        $tileImage->setImageFormat($this->tileFormat);
                        if ($this->tileFormat == 'jpg') {
                            $tileImage->setImageCompression(\Imagick::COMPRESSION_JPEG);
                        }
                        $tileImage->setImageCompressionQuality($this->tileQuality);
                        $tileImage->writeImage($filepath);
                        $tileImage->destroy();
                        break;

                    case 'GD':
                        $ul_x = $bounds['x'];
                        $ul_y = $bounds['y'];
                        $lr_x = $bounds['x'] + $bounds['width'];
                        $lr_y = $bounds['y'] + $bounds['height'];
                        $tileImage = $this->imageCrop($imageLevel, $ul_x, $ul_y, $lr_x, $lr_y);
                        touch($filepath);
                        imagejpeg($tileImage, $filepath, $this->tileQuality);
                        if (!empty($this->data['icc'])) {
                            $outputPel = new \lsolesen\pel\PelJpeg($filepath);
                            $outputPel->setIcc($this->data['icc']);
                            $outputPel->saveFile($filepath);
                        }
                        imagedestroy($tileImage);
                        break;

                    case 'ImageMagick':
                        $this->imageResizeCrop($image, $filepath, $resize, $bounds);
                        break;
                }
            }
        }

        switch ($this->processor) {
            case 'Imagick':
                // Nothing to do.
                break;

            case 'GD':
                imagedestroy($imageLevel);
                break;

            case 'ImageMagick':
                // Nothing to do.
                break;
        }
    }

    /**
     * Get the tile bounds position.
     *
     * @param $column
     * @param $row
     * @return array
     */
    protected function getTileBoundsPosition($column, $row)
    {
        $offsetX = $column == 0 ? 0 : $this->tileOverlap;
        $offsetY = $row == 0 ? 0 : $this->tileOverlap;
        $x = ($column * $this->tileSize) - $offsetX;
        $y = ($row * $this->tileSize) - $offsetY;

        return ['x' => $x, 'y' => $y];
    }

    /**
     * Get the tile bounds.
     *
     * @param $level
     * @param $column
     * @param $row
     * @param $w
     * @param $h
     * @return array
     */
    protected function getTileBounds($level, $column, $row, $w, $h)
    {
        $position = $this->getTileBoundsPosition($column, $row);

        $width = $this->tileSize + ($column == 0 ? 1 : 2) * $this->tileOverlap;
        $height = $this->tileSize + ($row == 0 ? 1 : 2) * $this->tileOverlap;
        $newWidth = min($width, $w - $position['x']);
        $newHeight = min($height, $h - $position['y']);

        return array_merge($position, ['width' => $newWidth, 'height' => $newHeight]);
    }

    /**
     * Save xml metadata about the tiles.
     *
     * @return bool
     */
    protected function saveXMLOutput()
    {
        $xmlFile = fopen($this->data['dzi'], 'w');
        if ($xmlFile === false) {
            return false;
        }
        fwrite($xmlFile, $this->getXMLOutput());
        return fclose($xmlFile);
    }

    /**
     * Create xml metadata about the tiles
     *
     * @return string
     */
    protected function getXMLOutput()
    {
        $xmlOutput = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>
<Image xmlns="http://schemas.microsoft.com/deepzoom/2008" Format="%s" Overlap="%s" TileSize="%s">
    <Size Height="%s" Width="%s" />
</Image>',
            $this->tileFormat,
            $this->tileOverlap,
            $this->tileSize,
            $this->data['height'],
            $this->data['width']
        ) .PHP_EOL;
        return $xmlOutput;
    }

    /**
     * Remove a dir from filesystem.
     *
     * @param string $dirpath
     * @return bool
     */
    protected function rmDir($dirPath)
    {
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $path = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->rmDir($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dirPath);
    }

    /**
     * Load the image data.
     *
     * @return resource Identifier of the image.
     */
    protected function openImage()
    {
        return $this->getImageFromFile($this->filepath);
    }

    /**
     * Helper to get an image of different type (jpg, png, gif…) from file.
     *
     * @param string $filepath
     * @return resource Identifier of the image.
     */
    protected function getImageFromFile($filepath)
    {
        switch (strtolower(pathinfo($filepath, PATHINFO_EXTENSION))) {
            case 'bmp':
                return imagecreatefrombmp($filepath);
            case 'gd':
                return imagecreatefromgd($filepath);
            case 'gd2':
                return imagecreatefromgd2($filepath);
            case 'gif':
                return imagecreatefromgif($filepath);
            case 'jpg':
            case 'jpe':
            case 'jpeg':
                return imagecreatefromjpeg($filepath);
            case 'png':
                return imagecreatefrompng($filepath);
            case 'wbmp':
                return imagecreatefromwbmp($filepath);
            case 'webp':
                return imagecreatefromwebp($filepath);
            case 'xbm':
                return imagecreatefromxbm($filepath);
            case 'xpm':
                return imagecreatefromxpm($filepath);
        }
    }

    /**
     * Crop an image to a size.
     *
     * @return resource Identifier of the image.
     */
    protected function imageCrop($image, $left, $upper, $right, $lower)
    {
        $w = abs($right - $left);
        $h = abs($lower - $upper);
        $crop = imagecreatetruecolor($w, $h);
        imagecopy($crop, $image, 0, 0, $left, $upper, $w, $h);
        return $crop;
    }

    /**
     * Resize the GD main image.
     *
     * @param int $width
     * @param int $height
     * @return resource
     */
    protected function imageResize($width, $height)
    {
        $tempImage = imagecreatetruecolor($width, $height);
        $result = imagecopyresampled(
            $tempImage,
            $this->data['image'],
            0,
            0,
            0,
            0,
            $width,
            $height,
            $this->data['width'],
            $this->data['height']
        );

        if ($result === false) {
            imagedestroy($tempImage);
            throw new \Exception('Cannot resize image with GD.');
        }

        return $tempImage;
    }

    /**
     * Resize and crop an image via convert.
     *
     * @internal For resize, the size is forced (option "!").
     *
     * @param string $source
     * @param string $destination
     * @param array $resize Array with width and height.
     * @param array $crop Array with width, height, upper left x and y.
     * @return bool
     */
    protected function imageResizeCrop($source, $destination, $resize = [], $crop = [])
    {
        $params = [];
        // Clean the canvas.
        $params[] = '+repage';
        $params[] = '-flatten';
        if ($resize) {
            $params[] = '-thumbnail ' . escapeshellarg(sprintf('%sx%s!', $resize['width'], $resize['height']));
        }
        if ($crop) {
            $params[] = '-crop ' . escapeshellarg(sprintf('%dx%d+%d+%d', $crop['width'], $crop['height'], $crop['x'], $crop['y']));
        }
        $params[] = '-quality ' . $this->tileQuality;

        $result = $this->convert($source, $destination, $params);
        return $result;
    }

    /**
     * Helper to process a convert command.
     *
     * @param string $source
     * @param string $destination
     * @param array $params
     * @return bool
     */
    protected function convert($source, $destination, $params)
    {
        $command = sprintf(
            '%s %s %s %s',
            $this->convertPath,
            escapeshellarg($source . '[0]'),
            implode(' ', $params),
            escapeshellarg($destination)
        );
        $result = $this->execute($command);
        return $result !== false;
    }

    /**
     * Helper to get the command line to convert.
     *
     * @return string|null
     */
    public function getConvertPath()
    {
        $command = 'whereis -b convert';
        $result = $this->execute($command);
        if (empty($result)) {
            return;
        }
        strtok($result, ' ');
        return strtok(' ');
    }

    /**
     * Execute a command.
     *
     * Expects arguments to be properly escaped.
     *
     * @see \Omeka\Stdlib\Cli
     *
     * @param string $command An executable command
     * @return string|false The command's standard output or false on error
     */
    protected function execute($command)
    {
        switch ($this->executeStrategy) {
            case 'proc_open':
                $output = $this->procOpen($command);
                break;

            case 'exec':
            default:
                $output = $this->exec($command);
                break;
        }

        return $output;
    }

    /**
     * Execute command using PHP's exec function.
     *
     * @see http://php.net/manual/en/function.exec.php
     * @param string $command
     * @return string|false
     */
    protected function exec($command)
    {
        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);
        if (0 !== $exitCode) {
            return false;
        }
        return implode(PHP_EOL, $output);
    }

    /**
     * Execute command using PHP's proc_open function.
     *
     * For servers that allow proc_open. Logs standard error.
     *
     * @see http://php.net/manual/en/function.proc-open.php
     * @param string $command
     * @return string|false
     */
    protected static function procOpen($command)
    {
        // Using proc_open() instead of exec() solves a problem where exec('convert')
        // fails with a "Permission Denied" error because the current working
        // directory cannot be set properly via exec().  Note that exec() works
        // fine when executing in the web environment but fails in CLI.
        $descriptorSpec = [
            0 => ['pipe', 'r'], //STDIN
            1 => ['pipe', 'w'], //STDOUT
            2 => ['pipe', 'w'], //STDERR
        ];
        $pipes = [];
        $proc = proc_open($command, $descriptorSpec, $pipes, getcwd());
        if (!is_resource($proc)) {
            return false;
        }
        $output = stream_get_contents($pipes[1]);
        // $errors = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        $exitCode = proc_close($proc);
        if (0 !== $exitCode) {
            return false;
        }
        return trim($output);
    }
}
