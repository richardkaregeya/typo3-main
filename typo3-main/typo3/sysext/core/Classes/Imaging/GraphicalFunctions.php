<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Core\Imaging;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Standard graphical functions
 *
 * Class contains a bunch of cool functions for manipulating graphics with GDlib/Freetype and ImageMagick.
 * VERY OFTEN used with gifbuilder that uses this class and provides a TypoScript API to using these functions
 */
class GraphicalFunctions
{
    /**
     * If set, the frame pointer is appended to the filenames.
     *
     * @var bool
     */
    public $addFrameSelection = true;

    /**
     * defines the RGB colorspace to use
     *
     * @var string
     */
    protected $colorspace = 'RGB';

    /**
     * colorspace names allowed
     *
     * @var array
     */
    protected $allowedColorSpaceNames = [
        'CMY',
        'CMYK',
        'Gray',
        'HCL',
        'HSB',
        'HSL',
        'HWB',
        'Lab',
        'LCH',
        'LMS',
        'Log',
        'Luv',
        'OHTA',
        'Rec601Luma',
        'Rec601YCbCr',
        'Rec709Luma',
        'Rec709YCbCr',
        'RGB',
        'sRGB',
        'Transparent',
        'XYZ',
        'YCbCr',
        'YCC',
        'YIQ',
        'YCbCr',
        'YUV',
    ];

    /**
     * Allowed file extensions perceived as images by TYPO3.
     * List should be set to 'gif,png,jpeg,jpg' if IM is not available.
     */
    protected array $imageFileExt = ['gif', 'jpg', 'jpeg', 'png', 'tif', 'bmp', 'tga', 'pcx', 'ai', 'pdf', 'webp'];

    /**
     * Web image extensions (can be shown by a webbrowser)
     */
    protected array $webImageExt = ['gif', 'jpg', 'jpeg', 'png'];

    /**
     * @var array
     */
    public $cmds = [
        'jpg' => '',
        'jpeg' => '',
        'gif' => '',
        'png' => '',
    ];

    /**
     * Whether ImageMagick/GraphicsMagick is enabled or not
     */
    protected bool $processorEnabled;

    /**
     * @var bool
     */
    protected $mayScaleUp = true;

    /**
     * Filename prefix for images scaled in imageMagickConvert()
     *
     * @var string
     */
    public $filenamePrefix = '';

    /**
     * Forcing the output filename of imageMagickConvert() to this value. However after calling imageMagickConvert() it will be set blank again.
     *
     * @var string
     */
    public $imageMagickConvert_forceFileNameBody = '';

    /**
     * This flag should always be FALSE. If set TRUE, imageMagickConvert will always write a new file to the tempdir! Used for debugging.
     *
     * @var bool
     */
    public $dontCheckForExistingTempFile = false;

    /**
     * For debugging only.
     * Filenames will not be based on mtime and only filename (not path) will be used.
     * This key is also included in the hash of the filename...
     *
     * @var string
     */
    public $alternativeOutputKey = '';

    /**
     * All ImageMagick commands executed is stored in this array for tracking. Used by the Install Tools Image section
     *
     * @var array
     */
    public $IM_commands = [];

    /**
     * ImageMagick scaling command; "-auto-orient -geometry" or "-auto-orient -sample". Used in makeText() and imageMagickConvert()
     *
     * @var string
     */
    public $scalecmd = '-auto-orient -geometry';

    /**
     * Used by v5_blur() to simulate 10 continuous steps of blurring
     *
     * @var string
     */
    protected $im5fx_blurSteps = '1x2,2x2,3x2,4x3,5x3,5x4,6x4,7x5,8x5,9x5';

    /**
     * Used by v5_sharpen() to simulate 10 continuous steps of sharpening.
     *
     * @var string
     */
    protected $im5fx_sharpenSteps = '1x2,2x2,3x2,2x3,3x3,4x3,3x4,4x4,4x5,5x5';

    /**
     * This is the limit for the number of pixels in an image before it will be rendered as JPG instead of GIF/PNG
     *
     * @var int
     */
    protected $pixelLimitGif = 10000;

    /**
     * @var int
     */
    protected int $jpegQuality = 85;

    /**
     * Reads configuration information from $GLOBALS['TYPO3_CONF_VARS']['GFX']
     * and sets some values in internal variables.
     */
    public function __construct()
    {
        $gfxConf = $GLOBALS['TYPO3_CONF_VARS']['GFX'];
        $this->colorspace = $this->getColorspaceFromConfiguration();

        $this->processorEnabled = (bool)$gfxConf['processor_enabled'];
        // Setting default JPG parameters:
        $this->jpegQuality = MathUtility::forceIntegerInRange($gfxConf['jpg_quality'], 10, 100, 85);
        $this->addFrameSelection = (bool)$gfxConf['processor_allowFrameSelection'];
        $this->imageFileExt = GeneralUtility::trimExplode(',', $gfxConf['imagefile_ext']);

        // Boolean. This is necessary if using ImageMagick 5+.
        // Effects in Imagemagick 5+ tends to render very slowly!!
        // - therefore must be disabled in order not to perform sharpen, blurring and such.
        $this->cmds['jpg'] = $this->cmds['jpeg'] = '-colorspace ' . $this->colorspace . ' -quality ' . $this->jpegQuality;

        // ... but if 'processor_effects' is set, enable effects
        if ($gfxConf['processor_effects']) {
            $this->cmds['jpg'] .= $this->v5_sharpen(10);
            $this->cmds['jpeg'] .= $this->v5_sharpen(10);
        }
        // Secures that images are not scaled up.
        $this->mayScaleUp = (bool)$gfxConf['processor_allowUpscaling'];
    }

    /**
     * Returns the IM command for sharpening with ImageMagick 5
     * Uses $this->im5fx_sharpenSteps for translation of the factor to an actual command.
     *
     * @param int $factor The sharpening factor, 0-100 (effectively in 10 steps)
     * @return string The sharpening command, eg. " -sharpen 3x4
     * @see makeText()
     * @see IMparams()
     * @see v5_blur()
     */
    public function v5_sharpen($factor)
    {
        $factor = MathUtility::forceIntegerInRange((int)ceil($factor / 10), 0, 10);
        $sharpenArr = explode(',', ',' . $this->im5fx_sharpenSteps);
        $sharpenF = trim($sharpenArr[$factor]);
        if ($sharpenF) {
            return ' -sharpen ' . $sharpenF;
        }
        return '';
    }

    /**
     * Returns the IM command for blurring with ImageMagick 5.
     * Uses $this->im5fx_blurSteps for translation of the factor to an actual command.
     *
     * @param int $factor The blurring factor, 0-100 (effectively in 10 steps)
     * @return string The blurring command, e.g. " -blur 3x4"
     * @see makeText()
     * @see IMparams()
     * @see v5_sharpen()
     */
    public function v5_blur($factor)
    {
        $factor = MathUtility::forceIntegerInRange((int)ceil($factor / 10), 0, 10);
        $blurArr = explode(',', ',' . $this->im5fx_blurSteps);
        $blurF = trim($blurArr[$factor]);
        if ($blurF) {
            return ' -blur ' . $blurF;
        }
        return '';
    }

    /**
     * Returns a random filename prefixed with "temp_" and then 32 char md5 hash (without extension).
     * Used by functions in this class to create truly temporary files for the on-the-fly processing. These files will most likely be deleted right away.
     *
     * @return string
     */
    public function randomName()
    {
        GeneralUtility::mkdir_deep(Environment::getVarPath() . '/transient/');
        return Environment::getVarPath() . '/transient/' . md5(StringUtility::getUniqueId());
    }

    /***********************************
     *
     * Scaling, Dimensions of images
     *
     ***********************************/
    /**
     * Converts $imagefile to another file in temp-dir of type $newExt (extension).
     *
     * @param string $imagefile The absolute image filepath
     * @param string $newExt New extension, eg. "gif", "png", "jpg", "tif". If $newExt is NOT set, the new imagefile will be of the original format. If newExt = 'WEB' then one of the web-formats is applied.
     * @param string $w Width. $w / $h is optional. If only one is given the image is scaled proportionally. If an 'm' exists in the $w or $h and if both are present the $w and $h is regarded as the Maximum w/h and the proportions will be kept
     * @param string $h Height. See $w
     * @param string $params Additional ImageMagick parameters.
     * @param string $frame Refers to which frame-number to select in the image. '' or 0 will select the first frame, 1 will select the next and so on...
     * @param array $options An array with options passed to getImageScale (see this function).
     * @param bool $mustCreate If set, then another image than the input imagefile MUST be returned. Otherwise you can risk that the input image is good enough regarding measures etc and is of course not rendered to a new, temporary file in typo3temp/. But this option will force it to.
     * @return array|null [0]/[1] is w/h, [2] is file extension and [3] is the filename.
     * @see getImageScale()
     * @see \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::getImgResource()
     * @see maskImageOntoImage()
     * @see copyImageOntoImage()
     * @see scale()
     */
    public function imageMagickConvert($imagefile, $newExt = '', $w = '', $h = '', $params = '', $frame = '', $options = [], $mustCreate = false)
    {
        if (!$this->processorEnabled) {
            // Returning file info right away
            return $this->getImageDimensions($imagefile);
        }
        $info = $this->getImageDimensions($imagefile);
        if (!$info) {
            return null;
        }

        $params = $this->modifyImageMagickStripProfileParameters($params, $options);
        $newExt = strtolower(trim($newExt));
        // If no extension is given the original extension is used
        if (!$newExt) {
            $newExt = $info[2];
        }
        if ($newExt === 'web') {
            if (in_array($info[2], $this->webImageExt, true)) {
                $newExt = $info[2];
            } else {
                $newExt = $this->gif_or_jpg($info[2], $info[0], $info[1]);
                if (!$params) {
                    $params = $this->cmds[$newExt];
                }
            }
        }
        if (!in_array($newExt, $this->imageFileExt, true)) {
            return null;
        }

        $data = $this->getImageScale($info, $w, $h, $options);
        $w = $data['origW'];
        $h = $data['origH'];
        // If no conversion should be performed
        // this flag is TRUE if the width / height does NOT dictate
        // the image to be scaled!! (that is if no width / height is
        // given or if the destination w/h matches the original image
        // dimensions or if the option to not scale the image is set)
        $noScale = !$w && !$h || $data[0] == $info[0] && $data[1] == $info[1] || !empty($options['noScale']);
        if ($noScale && !$data['crs'] && !$params && !$frame && $newExt == $info[2] && !$mustCreate) {
            // Set the new width and height before returning,
            // if the noScale option is set
            if (!empty($options['noScale'])) {
                $info[0] = $data[0];
                $info[1] = $data[1];
            }
            $info[3] = $imagefile;
            return $info;
        }
        $info[0] = $data[0];
        $info[1] = $data[1];
        $frame = $this->addFrameSelection ? (int)$frame : 0;
        if (!$params) {
            $params = $this->cmds[$newExt] ?? '';
        }
        // Cropscaling:
        if ($data['crs']) {
            if (!$data['origW']) {
                $data['origW'] = $data[0];
            }
            if (!$data['origH']) {
                $data['origH'] = $data[1];
            }
            $offsetX = (int)(($data[0] - $data['origW']) * ($data['cropH'] + 100) / 200);
            $offsetY = (int)(($data[1] - $data['origH']) * ($data['cropV'] + 100) / 200);
            $params .= ' -crop ' . $data['origW'] . 'x' . $data['origH'] . '+' . $offsetX . '+' . $offsetY . '! +repage';
        }
        // start with the default scale command

        // check if we should use -sample or -geometry
        if ($options['sample'] ?? false) {
            $command = '-auto-orient -sample';
        } else {
            $command = $this->scalecmd;
        }
        $command .= ' ' . $info[0] . 'x' . $info[1] . '! ' . $params . ' ';
        // re-apply colorspace-setting for the resulting image so colors don't appear to dark (sRGB instead of RGB)
        $command .= ' -colorspace ' . $this->colorspace;
        $cropscale = $data['crs'] ? 'crs-V' . $data['cropV'] . 'H' . $data['cropH'] : '';
        if ($this->alternativeOutputKey) {
            $theOutputName = md5($command . $cropscale . PathUtility::basename($imagefile) . $this->alternativeOutputKey . '[' . $frame . ']');
        } else {
            $theOutputName = md5($command . $cropscale . $imagefile . filemtime($imagefile) . '[' . $frame . ']');
        }
        if ($this->imageMagickConvert_forceFileNameBody) {
            $theOutputName = $this->imageMagickConvert_forceFileNameBody;
            $this->imageMagickConvert_forceFileNameBody = '';
        }
        // Making the temporary filename
        GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/typo3temp/assets/images/');
        $output = Environment::getPublicPath() . '/typo3temp/assets/images/' . $this->filenamePrefix . $theOutputName . '.' . $newExt;
        if ($this->dontCheckForExistingTempFile || !file_exists($output)) {
            $this->imageMagickExec($imagefile, $output, $command, $frame);
        }
        if (file_exists($output)) {
            $info[3] = $output;
            $info[2] = $newExt;
            // params might change some image data!
            if ($params) {
                $info = $this->getImageDimensions($info[3]);
            }
            return $info;
        }
        return null;
    }

    /**
     * This only crops the image, but does not take other "options" such as maxWidth etc. not into account. Do not use
     * standalone if you don't know what you are doing.
     *
     * @internal until API is finalized
     */
    public function crop(string $imageFile, string $targetFileExtension, string $cropInformation, array $options): ?array
    {
        // check if it is a json object
        $cropData = json_decode($cropInformation);
        if ($cropData) {
            $offsetLeft = (int)($cropData->x ?? 0);
            $offsetTop = (int)($cropData->y ?? 0);
            $newWidth = (int)($cropData->width ?? 0);
            $newHeight = (int)($cropData->height ?? 0);
        } else {
            [$offsetLeft, $offsetTop, $newWidth, $newHeight] = explode(',', $cropInformation, 4);
        }

        return $this->imageMagickConvert(
            $imageFile,
            $targetFileExtension,
            '',
            '',
            sprintf('-crop %dx%d+%d+%d +repage -quality %d', $newWidth, $newHeight, $offsetLeft, $offsetTop, $this->jpegQuality),
            '',
            isset($options['skipProfile']) ? ['skipProfile' => $options['skipProfile']] : [],
            true
        );
    }

    /**
     * This applies an image onto the $inputFile with an additional backgroundImage for the mask
     * @internal until API is finalized
     */
    public function mask(string $inputFile, string $outputFile, string $maskImage, string $maskBackgroundImage, string $params, array $options)
    {
        $params = $this->modifyImageMagickStripProfileParameters($params, $options);
        $tmpStr = $this->randomName();
        //	m_mask
        $intermediateMaskFile = $tmpStr . '_mask.png';
        $this->imageMagickExec($maskImage, $intermediateMaskFile, $params);
        //	m_bgImg
        $intermediateMaskBackgroundFile = $tmpStr . '_bgImg.miff';
        $this->imageMagickExec($maskBackgroundImage, $intermediateMaskBackgroundFile, $params);
        // The image onto the background
        $this->combineExec($intermediateMaskBackgroundFile, $inputFile, $intermediateMaskFile, $outputFile);
        // Unlink the temp-images...
        @unlink($intermediateMaskFile);
        @unlink($intermediateMaskBackgroundFile);
    }

    /**
     * Gets the input image dimensions.
     *
     * @param string $imageFile The absolute image filepath
     * @return array|null Returns an array where [0]/[1] is w/h, [2] is extension and [3] is the absolute filepath.
     * @see imageMagickConvert()
     * @see \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::getImgResource()
     */
    public function getImageDimensions($imageFile)
    {
        preg_match('/([^\\.]*)$/', $imageFile, $reg);
        if (!file_exists($imageFile)) {
            return null;
        }
        // @todo: check if we actually need this, ass ImageInfo deals with this much more professionally
        if (!in_array(strtolower($reg[0]), $this->imageFileExt, true)) {
            return null;
        }
        $imageInfoObject = GeneralUtility::makeInstance(ImageInfo::class, $imageFile);
        if ($imageInfoObject->isFile() && $imageInfoObject->getWidth()) {
            return [
                $imageInfoObject->getWidth(),
                $imageInfoObject->getHeight(),
                $imageInfoObject->getExtension(),
                $imageFile,
            ];
        }
        return null;
    }

    /**
     * Get numbers for scaling the image based on input.
     *
     * Notes by Benni in 2023 in order to understand this magic:
     * ----------------------------
     * Relevant if an image should be
     * - scaled
     * - cropped
     * - keep the aspect ratio while scaling?
     * - use a target width or height
     * - or rather have a minimum or maximum width and/or height
     *
     * This method does a lot of magic:
     * - $info contains [0] = width and [1] the width of an original image for example.
     * - $w and $h are the width and height that are originally required the image to be like
     * when scaled. They could contain a "c" for cropping information or "m" for "Ensure that even though $w and $h are given, one containing an $m that we keep the aspect ratio."
     * "m" really allows to say $w="50c" that this might in a result with [0]=100 because $w would follow $h in order to keep aspect ratio.
     * Obviously this only works properly if both m and c are working
     * - $options contain "maxW" (never go beyond this width, even if scaling larger as this), same with "maxH" and "minW" and "minH"
     *
     * The return values are a bit tricky to understand, so I added a few tests:
     * - AFAICS "0" and "1" are always used as "these are the target width / height" which my image
     *   should be scaled to, or cropped down to.
     *   Notes: If you hand in $info[0] and $info[1] a "0", you will get "0" as return value back!
     *          but
     * - "crs" if the image should be cropped (which is indicated by one of $w or $h contain the "c" at the end)
     * - "cropH" and "cropV" is also set when one of the incoming $w or $h contains a "c".
     *   Notes: "cropH" and "cropV" are rather cryptic, and can't really be used outside of this context.
     *          They are then "magically calculated" outside of this function
     *          $offsetX = (int)(($data[0] - $data['origW']) * ($data['cropH'] + 100) / 200);
     *          $offsetY = (int)(($data[1] - $data['origH']) * ($data['cropV'] + 100) / 200);
     *
     * - "origW" / "origH" seems to be the values that were handed in as $w and $h, but they might be altered
     *   f.e. "origH" is set when $w is given and $options["maxH"]
     * - When such a rearranging calculation was made ("maxH" reduces the original $w due to constraints),
     *   then the return value "max" is set.
     * - When using the "c" argument, origH and origW seem to contain the values that you would expect when NOT doing a crop scenario
     *   whereas [0] and [1] contain the target width and height that could be larger than originally requested.
     *
     * @todo in this method:
     * - use real values for "cropH" and "cropV"
     * - clean up "max" value to use a better naming, same as "crs"
     * - we might just replace "0" and "1" with "width" and "height" for the sake of having it in place for the future.
     *
     * ----------------------------
     * @param array $info Current image information: Width, Height etc.
     * @param string $w "required" width
     * @param string $h "required" height
     * @param array $options Options: Keys are like "maxW", "maxH", "minW", "minH"
     * @return array keys "0" and "1" explain the target width and height that should be adopted, but can be null
     * @internal
     * @see imageMagickConvert()
     */
    public function getImageScale($info, $w, $h, $options)
    {
        $out = [];
        $max = str_contains($w . $h, 'm') ? 1 : 0;
        if (str_contains($w . $h, 'c')) {
            $out['cropH'] = (int)substr((string)strstr((string)$w, 'c'), 1);
            $out['cropV'] = (int)substr((string)strstr((string)$h, 'c'), 1);
            $crs = true;
        } else {
            $crs = false;
        }
        $out['crs'] = $crs;
        $w = (int)$w;
        $h = (int)$h;
        // If there are max-values...
        if (!empty($options['maxW'])) {
            // If width is given...
            if ($w) {
                if ($w > $options['maxW']) {
                    $w = $options['maxW'];
                    // Height should follow
                    $max = 1;
                }
            } else {
                if ($info[0] > $options['maxW']) {
                    $w = $options['maxW'];
                    // Height should follow
                    $max = 1;
                }
            }
        }
        if (!empty($options['maxH'])) {
            // If height is given...
            if ($h) {
                if ($h > $options['maxH']) {
                    $h = $options['maxH'];
                    // Height should follow
                    $max = 1;
                }
            } else {
                // Changed [0] to [1] 290801
                if ($info[1] > $options['maxH']) {
                    $h = $options['maxH'];
                    // Height should follow
                    $max = 1;
                }
            }
        }
        $out['origW'] = $w;
        $out['origH'] = $h;
        $out['max'] = $max;
        if (!$this->mayScaleUp) {
            if ($w > $info[0]) {
                $w = $info[0];
            }
            if ($h > $info[1]) {
                $h = $info[1];
            }
        }
        // If scaling should be performed. Check that input "info" array will not cause division-by-zero
        if (($w || $h) && $info[0] && $info[1]) {
            if ($w && !$h) {
                $info[1] = (int)ceil($info[1] * ($w / $info[0]));
                $info[0] = $w;
            }
            if (!$w && $h) {
                $info[0] = (int)ceil($info[0] * ($h / $info[1]));
                $info[1] = $h;
            }
            if ($w && $h) {
                if ($max) {
                    $ratio = $info[0] / $info[1];
                    if ($h * $ratio > $w) {
                        $h = (int)round($w / $ratio);
                    } else {
                        $w = (int)round($h * $ratio);
                    }
                }
                if ($crs) {
                    $ratio = $info[0] / $info[1];
                    if ($h * $ratio < $w) {
                        $h = (int)round($w / $ratio);
                    } else {
                        $w = (int)round($h * $ratio);
                    }
                }
                $info[0] = $w;
                $info[1] = $h;
            }
        }
        $out[0] = $info[0];
        $out[1] = $info[1];
        // Set minimum-measures!
        if (isset($options['minW']) && $out[0] < $options['minW']) {
            if (($max || $crs) && $out[0]) {
                $out[1] = (int)round($out[1] * $options['minW'] / $out[0]);
            }
            $out[0] = $options['minW'];
        }
        if (isset($options['minH']) && $out[1] < $options['minH']) {
            if (($max || $crs) && $out[1]) {
                $out[0] = (int)round($out[0] * $options['minH'] / $out[1]);
            }
            $out[1] = $options['minH'];
        }
        return $out;
    }

    /***********************************
     *
     * ImageMagick API functions
     *
     ***********************************/
    /**
     * Call the identify command
     *
     * @param string $imagefile The relative to public web path image filepath
     * @return array|null Returns an array where [0]/[1] is w/h, [2] is extension, [3] is the filename and [4] the real image type identified by ImageMagick.
     */
    public function imageMagickIdentify($imagefile)
    {
        if (!$this->processorEnabled) {
            return null;
        }

        $result = $this->executeIdentifyCommandForImageFile($imagefile);
        if ($result) {
            [$width, $height, $fileExtension, $fileType] = explode(' ', $result);
            if ((int)$width && (int)$height) {
                return [$width, $height, strtolower($fileExtension), $imagefile, strtolower($fileType)];
            }
        }
        return null;
    }

    /**
     * Internal function to execute an IM command fetching information on an image
     *
     * @param string $imageFile the absolute path to the image
     * @return string|null the raw result of the identify command.
     */
    protected function executeIdentifyCommandForImageFile(string $imageFile): ?string
    {
        $frame = $this->addFrameSelection ? 0 : null;
        $cmd = CommandUtility::imageMagickCommand(
            'identify',
            '-format "%w %h %e %m" ' . ImageMagickFile::fromFilePath($imageFile, $frame)
        );
        $returnVal = [];
        CommandUtility::exec($cmd, $returnVal);
        $result = array_pop($returnVal);
        $this->IM_commands[] = ['identify', $cmd, $result];
        return $result;
    }

    /**
     * Executes an ImageMagick "convert" on two filenames, $input and $output using $params before them.
     * Can be used for many things, mostly scaling and effects.
     *
     * @param string $input The relative to public web path image filepath, input file (read from)
     * @param string $output The relative to public web path image filepath, output filename (written to)
     * @param string $params ImageMagick parameters
     * @param int $frame Optional, refers to which frame-number to select in the image. '' or 0
     * @return string The result of a call to PHP function "exec()
     */
    public function imageMagickExec($input, $output, $params, $frame = 0)
    {
        if (!$this->processorEnabled) {
            return '';
        }
        // If addFrameSelection is set in the Install Tool, a frame number is added to
        // select a specific page of the image (by default this will be the first page)
        $frame = $this->addFrameSelection ? (int)$frame : null;
        $cmd = CommandUtility::imageMagickCommand(
            'convert',
            $params
                . ' ' . ImageMagickFile::fromFilePath($input, $frame)
                . ' ' . CommandUtility::escapeShellArgument($output)
        );
        $this->IM_commands[] = [$output, $cmd];
        $ret = CommandUtility::exec($cmd);
        // Change the permissions of the file
        GeneralUtility::fixPermissions($output);
        return $ret;
    }

    /**
     * Executes an ImageMagick "combine" (or composite in newer times) on four filenames - $input, $overlay and $mask as input files and $output as the output filename (written to)
     * Can be used for many things, mostly scaling and effects.
     *
     * @param string $input The relative to public web path image filepath, bottom file
     * @param string $overlay The relative to public web path image filepath, overlay file (top)
     * @param string $mask The relative to public web path image filepath, the mask file (grayscale)
     * @param string $output The relative to public web path image filepath, output filename (written to)
     * @return string
     */
    public function combineExec($input, $overlay, $mask, $output)
    {
        if (!$this->processorEnabled) {
            return '';
        }
        $theMask = $this->randomName() . '.png';
        // +matte = no alpha layer in output
        $this->imageMagickExec($mask, $theMask, '-colorspace GRAY +matte');

        $parameters = '-compose over'
            . ' -quality ' . $this->jpegQuality
            . ' +matte '
            . ImageMagickFile::fromFilePath($input) . ' '
            . ImageMagickFile::fromFilePath($overlay) . ' '
            . ImageMagickFile::fromFilePath($theMask) . ' '
            . CommandUtility::escapeShellArgument($output);
        $cmd = CommandUtility::imageMagickCommand('combine', $parameters);
        $this->IM_commands[] = [$output, $cmd];
        $ret = CommandUtility::exec($cmd);
        // Change the permissions of the file
        GeneralUtility::fixPermissions($output);
        if (is_file($theMask)) {
            @unlink($theMask);
        }
        return $ret;
    }

    /**
     * Modifies the parameters for ImageMagick for stripping of profile information.
     * Strips profile information of image to save some space ideally
     *
     * @param string $parameters The parameters to be modified (if required)
     */
    protected function modifyImageMagickStripProfileParameters(string $parameters, array $options): string
    {
        if (isset($options['stripProfile'])) {
            if ($options['stripProfile'] && $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileCommand'] !== '') {
                $parameters = $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileCommand'] . ' ' . $parameters;
            } else {
                $parameters .= '###SkipStripProfile###';
            }
        }
        return $parameters;
    }

    /***********************************
     *
     * Various IO functions
     *
     ***********************************/

    /**
     * Returns an image extension for an output image based on the number of pixels of the output and the file extension of the original file.
     * For example: If the number of pixels exceeds $this->pixelLimitGif (normally 10000) then it will be a "jpg" string in return.
     *
     * @param string $type The file extension, lowercase.
     * @param int $w The width of the output image.
     * @param int $h The height of the output image.
     * @return string The filename, either "jpg" or "png"
     */
    public function gif_or_jpg($type, $w, $h)
    {
        if ($type === 'ai' || $w * $h < $this->pixelLimitGif) {
            return 'png';
        }
        return 'jpg';
    }

    /**
     * @internal Only used for ext:install, not part of TYPO3 Core API.
     */
    public function setImageFileExt(array $imageFileExt): void
    {
        $this->imageFileExt = $imageFileExt;
    }

    /**
     * @internal Not part of TYPO3 Core API.
     */
    public function getImageFileExt(): array
    {
        return $this->imageFileExt;
    }

    /**
     * Returns the recommended colorspace for a processor or the one set
     * in the configuration
     */
    protected function getColorspaceFromConfiguration(): string
    {
        $gfxConf = $GLOBALS['TYPO3_CONF_VARS']['GFX'];

        if ($gfxConf['processor'] === 'ImageMagick' && $gfxConf['processor_colorspace'] === '') {
            return 'sRGB';
        }

        if ($gfxConf['processor'] === 'GraphicsMagick' && $gfxConf['processor_colorspace'] === '') {
            return 'RGB';
        }

        return $gfxConf['processor_colorspace'];
    }
}
