<?php

namespace Janmensik\Jmlib;

/*
    Thumbnail class for image resizing and caching.
    Original author: Thomas Schedler (  http://www.thomas-schedler.de )

    Config params:
    - cache: directory for cached thumbnails
    - types: supported image types

    Source parameters:
    - url: URL of remote image to fetch
    - url_cache: time in seconds to cache remote image
    - file: path to local image file
    - default: default image file if original is missing
    - baseimgurl: base URL for image src attribute

    Caching and naming parameters:
    - name: custom name for cached thumbnail
    - cache_forced: if true, forces re-download of remote image

    Resizing parameters:
    - extrapolate: whether to allow upscaling
    - crop: whether to crop image to fit dimensions
    - width: desired width
    - height: desired height
    - longside: size of the longer side
    - shortside: size of the shorter side
    - fitin: whether to fit image within dimensions without cropping

    Image output parameters:
    - type: output image type
    - frame: path to frame image
    - sharpen: whether to apply unsharp mask
    - quality: JPEG quality

*/

class Thumbnail {
    private $DEFAULT = array(
        'cache_dir' => './cache/', // cache directory - ex cache
        'cache_lifetime' => 24 * 60 * 60, // 24 hours - ex url_cache
        'types' => array('.gif', '.jpg', '.png'),
        'quality_jpeg' => 85,
        'max_ram_image_size' => 20000000, // 20 million pixels
    );
    private $DEBUG = [];

    private $buildParams = [];

    /**
     * Start building a thumbnail request.
     * @param string $source File path or URL
     * @return self New instance with source set
     */
    public function from(string $source): self {
        $clone = clone $this;
        $clone->buildParams = [];

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $clone->DEFAULT['caller_dir'] = dirname($trace[0]['file']);
        $clone->DEBUG['caller_dir'] = $clone->DEFAULT['caller_dir'];
        echo ("Path: " . $clone->DEFAULT['caller_dir'] . "\n");

        if (preg_match('/^https?:\/\//', $source)) {
            $clone->buildParams['url'] = $source;
        } else {
            if (file_exists($source)) {
                $clone->buildParams['file'] = $source;
            } else {
                $path = $clone->DEFAULT['caller_dir'] . DIRECTORY_SEPARATOR . $source;

                if (file_exists($path)) {
                    $clone->buildParams['file'] = $path;
                } else {
                    $clone->buildParams['file'] = $source;
                }
            }
        }
        return $clone;
    }

    public function resize(int $width, int $height): self {
        $this->buildParams['width'] = $width;
        $this->buildParams['height'] = $height;
        return $this;
    }

    public function width(int $width): self {
        $this->buildParams['width'] = $width;
        return $this;
    }

    public function height(int $height): self {
        $this->buildParams['height'] = $height;
        return $this;
    }

    public function longSide(int $size): self {
        $this->buildParams['longside'] = $size;
        unset($this->buildParams['shortside']);
        return $this;
    }

    public function shortSide(int $size): self {
        $this->buildParams['shortside'] = $size;
        unset($this->buildParams['longside']);
        return $this;
    }

    public function crop(?int $width = null, ?int $height = null): self {
        if ($width !== null) {
            $this->buildParams['width'] = $width;
        }
        if ($height !== null) {
            $this->buildParams['height'] = $height;
        }
        $this->buildParams['crop'] = true;
        $this->buildParams['fitin'] = false;
        unset($this->buildParams['longside'], $this->buildParams['shortside']);
        return $this;
    }

    public function fit(?int $width = null, ?int $height = null): self {
        if ($width !== null) {
            $this->buildParams['width'] = $width;
        }
        if ($height !== null) {
            $this->buildParams['height'] = $height;
        }
        $this->buildParams['crop'] = false;
        $this->buildParams['fitin'] = true;
        unset($this->buildParams['longside'], $this->buildParams['shortside']);
        return $this;
    }

    public function extrapolate(): self {
        $this->buildParams['extrapolate'] = true;
        return $this;
    }

    public function sharpen(): self {
        $this->buildParams['sharpen'] = true;
        return $this;
    }

    public function name(string $name): self {
        $this->buildParams['name'] = $name;
        return $this;
    }

    public function generate(): ?string {
        return $this->thumb($this->buildParams);
    }

    public function thumb($params) {

        echo ("Cache path: " . ($this->DEFAULT['caller_dir'] . DIRECTORY_SEPARATOR . $this->DEFAULT['cache_dir']) . "\n");
        # cache dir control
        if ($real = realpath($this->DEFAULT['caller_dir'] . DIRECTORY_SEPARATOR . $this->DEFAULT['cache_dir'])) {
            $this->DEFAULT['cache_path'] = $real . DIRECTORY_SEPARATOR;
            $this->DEBUG['cache_path'] = $this->DEFAULT['cache_path'];
        }

        # added by Jan Mensik - support url
        if (!empty($params['url'])) {
            $remoteLoad = $this->downloadRemoteFile($params);
            if (!$remoteLoad) {
                return;
            } elseif ($remoteLoad === 2) {
                $def_no_cache = true;
                # downloaded remote file into $params['file']
            } else {
                $def_no_cache = false;
            }
        }

        echo ("Params file: " . ($params['file'] ?? 'n/a') . "\n");
        echo ("File: " . (file_exists($params['file']) ? 'yes' : 'no') . "\n");
        echo ("mTime: " . (filemtime($params['file']) ?? 'n/a') . "\n");


        # changed by Jan Mensik: no image = no error report
        if (empty($params['file']) || !file_exists($params['file'])) {
            # default image?
            if (!empty($params['default']) && file_exists($params['default'])) {
                $params['file'] = $params['default'];
            } else {
                return;
            }
        }

        # .........................................................................
        # load source image info
        $temp = getimagesize($params['file']);

        $_SRC['file']  = $params['file'];
        $_SRC['width'] = $temp[0];
        $_SRC['height'] = $temp[1];
        $_SRC['type'] = $temp[2]; // 1=GIF, 2=JPG, 3=PNG, SWF=4
        $_SRC['string'] = $temp[3];
        $_SRC['filename'] = basename($params['file']);
        $_SRC['modified'] = filemtime($params['file']);

        echo ("SRC data: ");
        print_r($_SRC);
        echo ("\n");

        # .........................................................................
        # Set default parameters - logic for resizing
        if (empty($params['extrapolate'])) {
            $params['extrapolate'] = true;
        }
        if (empty($params['crop'])) {
            $params['crop'] = true;
        }
        if (empty($params['width']) && empty($params['height']) && empty($params['longside']) && empty($params['shortside'])) {
            $params['width'] = $_SRC['width'];
        }

        if (empty($params['fitin'])) {
            $params['fitin'] = false;
        }

        # Check RAM limit
        if ($_SRC['width'] * $_SRC['height'] > $this->DEFAULT['max_ram_image_size']) {
            return;
        }

        $_SRC['hash'] = md5($_SRC['file'] . $_SRC['modified'] . implode('', $params));

        $_DST['offset_w'] = 0;
        $_DST['offset_h'] = 0;



        # .........................................................................
        # Basic destination image size calculation
        if (is_numeric($params['width'])) {
            $_DST['width'] = $params['width'];
        } else {
            $_DST['width'] = round($params['height'] / ($_SRC['height'] / $_SRC['width']));
        }

        if (is_numeric($params['height'])) {
            $_DST['height'] = $params['height'];
        } else {
            $_DST['height'] = round($params['width'] / ($_SRC['width'] / $_SRC['height']));
        }

        # .........................................................................
        # Destination image size calculation based on longside/shortside (if set)
        if (!empty($params['longside']) && is_numeric($params['longside'])) {
            if ($_SRC['width'] < $_SRC['height']) {
                $_DST['height'] = $params['longside'];
                $_DST['width'] = round($params['longside'] / ($_SRC['height'] / $_SRC['width']));
            } else {
                $_DST['width'] = $params['longside'];
                $_DST['height'] = round($params['longside'] / ($_SRC['width'] / $_SRC['height']));
            }
        } elseif (!empty($params['shortside']) && is_numeric($params['shortside'])) {
            if ($_SRC['width'] < $_SRC['height']) {
                $_DST['width'] = $params['shortside'];
                $_DST['height'] = round($params['shortside'] / ($_SRC['width'] / $_SRC['height']));
            } else {
                $_DST['height'] = $params['shortside'];
                $_DST['width'] = round($params['shortside'] / ($_SRC['height'] / $_SRC['width']));
            }
        }

        # .........................................................................
        # Destination image size calculation: Fit-in calculation (if fitin is true)
        if ($params['fitin'] == 'true' && $params['crop'] != 'true') {
            # zjistim si pomery stran
            $width_ratio = $_SRC['width'] / $_DST['width'];
            $height_ratio = $_SRC['height'] / $_DST['height'];

            # logika rika: vezmu vetsi pomer a vydelim jim puvodni rozmery
            $width_ratio > $height_ratio ? $ratio = $width_ratio : $ratio = $height_ratio;

            $_DST['width'] = round($_SRC['width'] / $ratio);
            $_DST['height'] = round($_SRC['height'] / $ratio);
        }

        # .........................................................................
        # Destination image size calculation: Cropping calculation (if crop is true) and not fitin
        if ($params['crop'] == 'true' && $params['fitin'] != 'true') {
            $width_ratio = $_SRC['width'] / $_DST['width'];
            $height_ratio = $_SRC['height'] / $_DST['height'];

            # Trimming in width
            if ($width_ratio > $height_ratio) {
                $_DST['offset_w'] = round(($_SRC['width'] - $_DST['width'] * $height_ratio) / 2);
                $_SRC['width'] = round($_DST['width'] * $height_ratio);
            }
            # Trimming in height
            elseif ($width_ratio < $height_ratio) {
                $_DST['offset_h'] = round(($_SRC['height'] - $_DST['height'] * $width_ratio) / 2);
                $_SRC['height'] = round($_DST['height'] * $width_ratio);
            }
        }

        # .........................................................................
        # Prevent upscaling if extrapolate is false
        if ($params['extrapolate'] == 'false' && $_DST['height'] > $_SRC['height'] && $_DST['width'] > $_SRC['width']) {
            $_DST['width'] = $_SRC['width'];
            $_DST['height'] = $_SRC['height'];
        }

        # .........................................................................
        # Prepare destination image file name and URL

        if (!empty($params['type'])) {
            $_DST['type'] = $params['type'];
        } else {
            $_DST['type'] = $_SRC['type'];
        }

        if (!empty($params['name'])) {
            $_DST['file'] = $this->DEFAULT['cache_path'] . $params['name'] . $this->DEFAULT['types'][$_DST['type']];
        } else {
            $_DST['file'] = $this->DEFAULT['cache_path'] . $_SRC['hash'] . $this->DEFAULT['types'][$_DST['type']];
        }

        if (!empty($params['baseimgurl'])) {
            $_DST['imgurl'] = addslashes($params['baseimgurl']) . substr($_DST['file'], 1);
        } else {
            $_DST['imgurl'] = $_DST['file'];
        }

        $output = $_DST['imgurl'];

        echo ("Destination file: " . $_DST['file'] . "\n");


        # .........................................................................
        # Check if thumbnail already exists in cache and return it
        if (file_exists($_DST['file']) && !$def_no_cache) {
            return  $output;
        }

        # .........................................................................
        # Source image load up based on type (GIF, JPG, PNG)
        switch ($_SRC['type']) {
            case 1:
                $_SRC['image'] = imagecreatefromgif($_SRC['file']);
                break;
            case 2:
                $_SRC['image'] = imagecreatefromjpeg($_SRC['file']);
                break;
            case 3:
                $_SRC['image'] = imagecreatefrompng($_SRC['file']);
                break;
        }

        # .........................................................................
        # If the image is very large, first scale it down linearly to four times the target size and override $_SRC.
        if ($_DST['width'] * 4 < $_SRC['width'] and $_DST['height'] * 4 < $_SRC['height']) {
            $_TMP['width'] = round($_DST['width'] * 4);
            $_TMP['height'] = round($_DST['height'] * 4);

            $_TMP['image'] = imagecreatetruecolor($_TMP['width'], $_TMP['height']);
            imagecopyresized($_TMP['image'], $_SRC['image'], 0, 0, $_DST['offset_w'], $_DST['offset_h'], $_TMP['width'], $_TMP['height'], $_SRC['width'], $_SRC['height']);
            $_SRC['image'] = $_TMP['image'];
            $_SRC['width'] = $_TMP['width'];
            $_SRC['height'] = $_TMP['height'];

            $_DST['offset_w'] = 0;
            $_DST['offset_h'] = 0;
            unset($_TMP['image']);
        }

        # .........................................................................
        # Destination image creation and resampling
        $_DST['image'] = imagecreatetruecolor($_DST['width'], $_DST['height']);
        imagecopyresampled($_DST['image'], $_SRC['image'], 0, 0, $_DST['offset_w'], $_DST['offset_h'], $_DST['width'], $_DST['height'], $_SRC['width'], $_SRC['height']);
        if (!empty($params['sharpen'])) {
            $_DST['image'] = $this->unsharpMask($_DST['image'], 80, .5, 3);
        }

        switch ($_DST['type']) {
            case 1:
                imagetruecolortopalette($_DST['image'], false, 256);
                imagegif($_DST['image'], $_DST['file']);
                break;
            case 2:
                Imageinterlace($_DST['image'], 1);
                if (empty($params['quality'])) $params['quality'] = $this->DEFAULT['quality_jpeg'];
                imagejpeg($_DST['image'], $_DST['file'], $params['quality']);
                break;
            case 3:
                imagepng($_DST['image'], $_DST['file']);
                break;
        }

        return  $output;
    }

    private function downloadRemoteFile(array $params): int|bool {
        # cache
        if (empty($params['cache_lifetime'])) {
            $params['cache_lifetime'] = $this->DEFAULT['cache_lifetime'];
        }

        # abych mel korektni priponu
        unset($back);
        if (preg_match('/^.+\.(jpg|gif|png|jpeg)$/i', $params['url'], $back))
            $pripona = $back[1];
        else
            $pripona = 'url';

        # vytvorim nazev souboru
        $filename = $this->DEFAULT['cache_dir'] . 'remote-image.' . md5($params['url']) . '.' . $pripona;
        echo ('Filename: ' . $filename . "\n");

        $fmt_local = false;
        if (file_exists($filename)) {
            $fmt_local = filemtime($filename);
        }

        # mam remote-image a cache_lifetime zatim nevyprsel
        if ($fmt_local > time() - $params['cache_lifetime']) {
            $params['file'] = $filename;
        }
        # mam remote-image a vzdaleny soubor neni mladsi nez moje kopie (pokud nemam zakazano pomoci cache_forced)
        elseif (empty($params['cache_forced']) && file_exists($filename) && $fmt_local >= filemtime_remote($params['url']) && filemtime_remote($params['url']) > 0) {
            $params['file'] = $filename;
            touch($params['file']);
        }
        # need to download
        else {
            $def_no_cache = true;
            # zkusim nacist z url
            $file_data = file($params['url']);
            if (is_array($file_data))
                $input = @implode('', $file_data);
            if ($input) {
                # ulozim do cache adresare
                $fp = fopen($filename, 'w');
                fwrite($fp, $input);
                fclose($fp);

                # zapisu nazev souboru do $params['file']
                $params['file'] = $filename;
            }
        }
        if (!$params['name']) {
            $params['name'] = 'remote-cache.' . md5($params['url'] . implode('', $params));
        }

        return ($def_no_cache ? 2 : true);
    }

    private function unsharpMask($img, $amount, $radius, $threshold) {
        // Attempt to calibrate the parameters to Photoshop:
        if ($amount > 500) $amount = 500;
        $amount = $amount * 0.016;
        if ($radius > 50) $radius = 50;
        $radius = $radius * 2;
        if ($threshold > 255) $threshold = 255;

        $radius = abs(round($radius));     // Only integers make sense.
        if ($radius == 0) {
            return $img;
            // break;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $imgCanvas = $img;
        $imgCanvas2 = $img;
        $imgBlur = imagecreatetruecolor($w, $h);

        // Gaussian blur matrix:
        //	1	2	1
        //	2	4	2
        //	1	2	1

        // Move copies of the image around one pixel at the time and merge them with weight
        // according to the matrix. The same matrix is simply repeated for higher radii.
        for ($i = 0; $i < $radius; $i++) {
            imagecopy($imgBlur, $imgCanvas, 0, 0, 1, 1, $w - 1, $h - 1); // up left
            imagecopymerge($imgBlur, $imgCanvas, 1, 1, 0, 0, $w, $h, 50); // down right
            imagecopymerge($imgBlur, $imgCanvas, 0, 1, 1, 0, $w - 1, $h, 33); // down left
            imagecopymerge($imgBlur, $imgCanvas, 1, 0, 0, 1, $w, $h - 1, 25); // up right
            imagecopymerge($imgBlur, $imgCanvas, 0, 0, 1, 0, $w - 1, $h, 33); // left
            imagecopymerge($imgBlur, $imgCanvas, 1, 0, 0, 0, $w, $h, 25); // right
            imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 20); // up
            imagecopymerge($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 16); // down
            imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 0, $w, $h, 50); // center
        }
        $imgCanvas = $imgBlur;

        // Calculate the difference between the blurred pixels and the original
        // and set the pixels
        for ($x = 0; $x < $w; $x++) { // each row
            for ($y = 0; $y < $h; $y++) { // each pixel
                $rgbOrig = ImageColorAt($imgCanvas2, $x, $y);
                $rOrig = (($rgbOrig >> 16) & 0xFF);
                $gOrig = (($rgbOrig >> 8) & 0xFF);
                $bOrig = ($rgbOrig & 0xFF);
                $rgbBlur = ImageColorAt($imgCanvas, $x, $y);
                $rBlur = (($rgbBlur >> 16) & 0xFF);
                $gBlur = (($rgbBlur >> 8) & 0xFF);
                $bBlur = ($rgbBlur & 0xFF);

                // When the masked pixels differ less from the original
                // than the threshold specifies, they are set to their original value.
                $rNew = (abs($rOrig - $rBlur) >= $threshold) ? round(max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))) : round($rOrig);
                $gNew = (abs($gOrig - $gBlur) >= $threshold) ? round(max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))) : round($gOrig);
                $bNew = (abs($bOrig - $bBlur) >= $threshold) ? round(max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))) : round($bOrig);

                if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
                    $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew);
                    ImageSetPixel($img, $x, $y, $pixCol);
                }
            }
        }
        return $img;
    }
}
