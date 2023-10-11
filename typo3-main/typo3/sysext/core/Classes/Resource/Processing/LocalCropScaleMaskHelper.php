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

namespace TYPO3\CMS\Core\Resource\Processing;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Helper class to locally perform a crop/scale/mask task with the TYPO3 image processing classes.
 */
class LocalCropScaleMaskHelper
{
    /**
     * This method actually does the processing of files locally
     *
     * Takes the original file (for remote storages this will be fetched from the remote server),
     * does the IM magic on the local server by creating a temporary typo3temp/ file,
     * copies the typo3temp/ file to the processing folder of the target storage and
     * removes the typo3temp/ file.
     *
     * The returned array has the following structure:
     *   width => 100
     *   height => 200
     *   filePath => /some/path
     *
     * If filePath isn't set but width and height are the original file is used as ProcessedFile
     * with the returned width and height. This is for example useful for SVG images.
     *
     * @param TaskInterface $task
     * @return array|null
     */
    public function process(TaskInterface $task)
    {
        return $this->processWithLocalFile($task, $task->getSourceFile()->getForLocalProcessing(false));
    }

    /**
     * Does the heavy lifting prescribed in processTask()
     * except that the processing can be performed on any given local image
     */
    public function processWithLocalFile(TaskInterface $task, string $originalFileName): ?array
    {
        $result = null;
        $targetFile = $task->getTargetFile();
        $targetFileExtension = $task->getTargetFileExtension();

        $imageOperations = GeneralUtility::makeInstance(GraphicalFunctions::class);

        $configuration = $targetFile->getProcessingConfiguration();
        $configuration['additionalParameters'] ??= '';

        $options = $this->getConfigurationForImageCropScaleMask($targetFile);

        $croppedImage = null;
        if (!empty($configuration['crop'])) {
            // the result info is an array with 0=width,1=height,2=extension,3=filename
            $result = $imageOperations->crop($originalFileName, $targetFileExtension, $configuration['crop'], $options);
            if ($result !== null) {
                $originalFileName = $croppedImage = $result[3];
            }
        }

        // Normal situation (no masking)
        if (!is_array($configuration['maskImages'] ?? null)) {
            // the result info is an array with 0=width,1=height,2=extension,3=filename
            $result = $imageOperations->imageMagickConvert(
                $originalFileName,
                $targetFileExtension,
                $configuration['width'] ?? '',
                $configuration['height'] ?? '',
                $configuration['additionalParameters'],
                $configuration['frame'] ?? '',
                $options,
                // in case file is in `/typo3temp/` from the crop operation above, it must create a result
                $result !== null
            );
        } else {
            $temporaryFileName = $this->getFilenameForImageCropScaleMask($task);
            $maskImage = $configuration['maskImages']['maskImage'] ?? null;
            $maskBackgroundImage = $configuration['maskImages']['backgroundImage'];
            if ($maskImage instanceof FileInterface && $maskBackgroundImage instanceof FileInterface) {
                // This converts the original image to a temporary PNG file during all steps of the masking process
                $tempFileInfo = $imageOperations->imageMagickConvert(
                    $originalFileName,
                    'png',
                    $configuration['width'] ?? '',
                    $configuration['height'] ?? '',
                    $configuration['additionalParameters'],
                    $configuration['frame'] ?? '',
                    $options
                );
                if (is_array($tempFileInfo)) {
                    // Scaling
                    $command = '-geometry ' . $tempFileInfo[0] . 'x' . $tempFileInfo[1] . '!';
                    $imageOperations->mask(
                        $tempFileInfo[3],
                        $temporaryFileName,
                        $maskImage->getForLocalProcessing(),
                        $maskBackgroundImage->getForLocalProcessing(),
                        $command,
                        $options
                    );
                    $maskBottomImage = $configuration['maskImages']['maskBottomImage'] ?? null;
                    $maskBottomImageMask = $configuration['maskImages']['maskBottomImageMask'] ?? null;
                    if ($maskBottomImage instanceof FileInterface && $maskBottomImageMask instanceof FileInterface) {
                        // Uses the temporary PNG file from the previous step and applies another mask
                        $imageOperations->mask(
                            $temporaryFileName,
                            $temporaryFileName,
                            $maskBottomImage->getForLocalProcessing(),
                            $maskBottomImageMask->getForLocalProcessing(),
                            $command,
                            $options
                        );
                    }
                    $tempFileInfo[3] = $temporaryFileName;
                }
                $result = $tempFileInfo;
            }
        }

        // check if the processing really generated a new file (scaled and/or cropped)
        if ($result !== null) {
            if ($result[3] !== $originalFileName || $originalFileName === $croppedImage) {
                $result = [
                    'width' => $result[0],
                    'height' => $result[1],
                    'filePath' => $result[3],
                ];
            } else {
                // No file was generated
                $result = null;
            }
        }

        // Cleanup temp file if it isn't used as result
        if ($croppedImage && ($result === null || $croppedImage !== $result['filePath'])) {
            GeneralUtility::unlink_tempfile($croppedImage);
        }

        // If noScale option is applied, we need to reset the width and height to ensure the scaled values
        // are used for the generated image tag even if the image itself is not scaled. This is needed, as
        // the result is discarded due to the fact that the original image is used.
        // @see https://forge.typo3.org/issues/100972
        // Note: This should only happen if no image has been generated ($result === null).
        if ($result === null && ($options['noScale'] ?? false)) {
            $configuration = $task->getConfiguration();
            $localProcessedFile = $task->getSourceFile()->getForLocalProcessing(false);
            $imageDimensions = $imageOperations->getImageDimensions($localProcessedFile);
            $imageScaleInfo = $imageOperations->getImageScale(
                $imageDimensions,
                $configuration['width'] ?? '',
                $configuration['height'] ?? '',
                $options
            );
            $targetFile->updateProperties([
                'width' => $imageScaleInfo[0],
                'height' => $imageScaleInfo[1],
            ]);
        }

        return $result;
    }

    protected function getConfigurationForImageCropScaleMask(ProcessedFile $processedFile): array
    {
        $configuration = $processedFile->getProcessingConfiguration();

        $options = [];
        if ($configuration['sample'] ?? false) {
            $options['sample'] = true;
        }
        if ($configuration['maxWidth'] ?? false) {
            $options['maxW'] = $configuration['maxWidth'];
        }
        if ($configuration['maxHeight'] ?? false) {
            $options['maxH'] = $configuration['maxHeight'];
        }
        if ($configuration['minWidth'] ?? false) {
            $options['minW'] = $configuration['minWidth'];
        }
        if ($configuration['minHeight'] ?? false) {
            $options['minH'] = $configuration['minHeight'];
        }
        if (isset($configuration['stripProfile'])) {
            $options['stripProfile'] = (bool)$configuration['stripProfile'];
        }

        if (isset($configuration['noScale'])) {
            $options['noScale'] = (bool)$configuration['noScale'];
        }

        return $options;
    }

    /**
     * Returns the filename for a cropped/scaled/masked file which will be put in typo3temp for the time being.
     */
    protected function getFilenameForImageCropScaleMask(TaskInterface $task): string
    {
        $targetFileExtension = $task->getTargetFileExtension();
        return Environment::getPublicPath() . '/typo3temp/' . $task->getTargetFile()->generateProcessedFileNameWithoutExtension() . '.' . ltrim(trim($targetFileExtension), '.');
    }
}
