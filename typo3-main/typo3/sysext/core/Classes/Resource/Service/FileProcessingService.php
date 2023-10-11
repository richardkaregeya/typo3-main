<?php

declare(strict_types=1);

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

namespace TYPO3\CMS\Core\Resource\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\Event\BeforeFileProcessingEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\LocalPreviewHelper;
use TYPO3\CMS\Core\Resource\Processing\ProcessorInterface;
use TYPO3\CMS\Core\Resource\Processing\ProcessorRegistry;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * This is a general service for creating Processed Files a.k.a. processing a File object with a given configuration.
 *
 * This is how it works:
 *   -> File->process(string $taskType, array $configuration)
 *      -> ResourceStorage->processFile(File $file, $taskType, array $configuration)
 *         -> FileProcessingService->processFile(File $file, $taskType, array $configuration)
 *
 * This class then transforms the information of a Task through a Processor into a ProcessedFile object.
 * For this, the DB is checked if there is a ProcessedFile which has been processed or does not need
 * to be processed. If processing is required, a valid Processor is searched for to process the
 * Task object (which is retrieved from ProcessedFile->getTask()).
 */
class FileProcessingService
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly ProcessedFileRepository $processedFileRepository,
        protected readonly ProcessorRegistry $processorRegistry,
    ) {
    }

    public function processFile(File|FileReference $fileObject, string $taskType, DriverInterface $driver, array $configuration): ProcessedFile
    {
        // Processing always works on the original file
        if ($fileObject instanceof FileReference) {
            $fileObject = $fileObject->getOriginalFile();
        }

        // @todo: this part needs to be moved into a DTO object where it is sanitized
        // Enforce default configuration for preview processing here,
        // to be sure we find already processed files below,
        // which we wouldn't if we would change the configuration later, as configuration is part of the lookup.
        if ($taskType === ProcessedFile::CONTEXT_IMAGEPREVIEW) {
            $configuration = LocalPreviewHelper::preProcessConfiguration($configuration);
        }
        // Ensure that the processing configuration which is part of the hash sum is properly cast, so
        // unnecessary duplicate images are not produced, see #80942
        foreach ($configuration as &$value) {
            if (MathUtility::canBeInterpretedAsInteger($value)) {
                $value = (int)$value;
            }
        }

        $processedFile = $this->processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration($fileObject, $taskType, $configuration);

        // Pre-process the file
        $event = $this->eventDispatcher->dispatch(
            new BeforeFileProcessingEvent($driver, $processedFile, $fileObject, $taskType, $configuration)
        );
        $processedFile = $event->getProcessedFile();

        // Only handle the file if it is not processed yet
        // (maybe modified or already processed by an event)
        // or (in case of preview images) already in the DB/in the processing folder
        if (!$processedFile->isProcessed()) {
            // We only have to trigger the file processing if the file either is new, does not exist or the
            // original file has changed since the last processing run (the last case has to trigger a reprocessing
            // even if the original file was used until now)
            if ($processedFile->isNew() || (!$processedFile->usesOriginalFile() && !$processedFile->exists()) || $processedFile->isOutdated()) {
                $task = $processedFile->getTask();
                $processor = $this->getProcessorByTask($task);
                $processor->processTask($task);

                if ($task->isExecuted() && $task->isSuccessful() && $processedFile->isProcessed()) {
                    $this->processedFileRepository->add($processedFile);
                }
            }
        }

        // Post-process (enrich) the file
        $event = $this->eventDispatcher->dispatch(
            new AfterFileProcessingEvent($driver, $processedFile, $fileObject, $taskType, $configuration)
        );

        return $event->getProcessedFile();
    }

    protected function getProcessorByTask(TaskInterface $task): ProcessorInterface
    {
        return $this->processorRegistry->getProcessorByTask($task);
    }
}
