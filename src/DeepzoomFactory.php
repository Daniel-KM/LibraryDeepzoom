<?php
namespace DanielKm\Deepzoom;

/**
 * Class DeepzoomFactory
 * @package DanielKm\Deepzoom
 */
class DeepzoomFactory
{
    /**
     * Initialize the Deepzoom library.
     *
     * @param array $config
     * @return Deepzoom
     */
    public function __invoke(array $config = null)
    {
        if (is_null($config)) {
            $config = [];
        }

        // Check the autoload.
        if (!class_exists('DanielKm\Deepzoom\Deepzoom', false)) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'Deepzoom.php';
        }
        return new Deepzoom($config);
    }
}
