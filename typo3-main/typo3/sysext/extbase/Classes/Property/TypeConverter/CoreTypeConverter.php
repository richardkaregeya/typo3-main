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

namespace TYPO3\CMS\Extbase\Property\TypeConverter;

use TYPO3\CMS\Core\Type\Exception\InvalidValueExceptionInterface;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;

/**
 * Converter which transforms simple types to a core type
 * implementing \TYPO3\CMS\Core\Type\TypeInterface.
 */
class CoreTypeConverter extends AbstractTypeConverter
{
    /**
     * Convert an object from $source to an Enumeration.
     *
     * @param mixed $source
     * @param PropertyMappingConfigurationInterface|null $configuration
     * @return object the target type
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function convertFrom($source, string $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null): object
    {
        try {
            return new $targetType($source);
        } catch (InvalidValueExceptionInterface $exception) {
            return new Error($exception->getMessage(), 1381680012);
        }
    }
}
