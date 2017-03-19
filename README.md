Deepzoom (php library)
======================

[Deepzoom] is a stand-alone library to generate tiles of big images in order to
zoom them instantly. The format of tiles is [DZI] and they can be used with
[OpenSeadragon], [OpenLayers] and various viewers.

It is integrated in the module [IIIF Server] of the open source digital library
[Omeka S] to create images compliant with the specifications of the [International Image Interoperability Framework].

This library is available as a packagist [package].


Usage
-----

### Direct use without the factory

```php
    // Setup the Deepzoom library.
    $deepzoom = new \DanielKm\Deepzoom\Deepzoom($config);

    // Process a source file and save tiles in a destination folder.
    $result = $deepzoom->process($source, $destination);
```

### Direct invocation with the factory

```php
    // Setup the Deepzoom library.
    $factory = new \DanielKm\Deepzoom\DeepzoomFactory;
    $deepzoom = $factory($config);

    // Process a source file and save tiles in a destination folder.
    $result = $deepzoom->process($source, $destination);
```


Supported image libraries
-------------------------

The format of the image source can be anything that is managed by the image
library:

- PHP Extension [GD] (>=2.0)
- PHP extension [Imagick] (>=6.5.6)
- Command line `convert` [ImageMagick] (>=6.0)

The PHP library `exif` should be installed (generally enabled by default).


History
-------

The source is a mix of the Laravel plugin [deepzoom] of Jeremy Tubbs, the
standalone open zoom builder [deepzoom.php] of Nicolas Fabre, the [blog] of
Olivier Mariott, and the similar [Zoomify library].

Some code is shared with the [Zoomify Library].


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See the online [issues] page on GitHub.


License
-------

This library is licensed under the [MIT] license.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.


Copyright
---------

* Copyright 2015-2016 Jeremy Tubbs
* Copyright 2017 Daniel Berthereau (Daniel.github@Berthereau.net)


[Deepzoom]: https://github.com/Daniel-KM/LibraryDeepzoom
[DZI]: https://en.wikipedia.org/wiki/Deep_Zoom
[OpenSeadragon]: https://openseadragon.github.io
[OpenLayers]: https://openlayers.org/
[International Image Interoperability Framework]: http://iiif.io
[IIIF Server]: https://github.com/Daniel-KM/Omeka-S-module-IiifServer
[Omeka S]: https://omeka.org/s
[package]: https://packagist.org/packages/daniel-km/deepzoom
[GD]: https://secure.php.net/manual/en/book.image.php
[Imagick]: https://php.net/manual/en/book.imagick.php
[ImageMagick]: https://www.imagemagick.org/
[deepzoom]: https://github.com/jeremytubbs/deepzoom
[deepzoom.php]: https://github.com/nfabre/deepzoom.php
[blog]: http://omarriott.com/aux/leaflet-js-non-geographical-imagery/
[Zoomify library]: https://github.com/Daniel-KM/LibraryZoomify
[issues]: https://github.com/Daniel-KM/LibraryDeepzoom/issues
[MIT]: https://www.gnu.org/licenses/gpl-3.0.html
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
