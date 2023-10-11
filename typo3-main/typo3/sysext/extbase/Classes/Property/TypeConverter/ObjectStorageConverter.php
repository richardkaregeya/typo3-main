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

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Utility\TypeHandlingUtility;

/**
 * Converter which transforms simple types to an ObjectStorage.
 */
class ObjectStorageConverter extends AbstractTypeConverter
{
    /**
     * Actually convert from $source to $targetType, taking into account the fully
     * built $convertedChildProperties and $configuration.
     *
     * @param mixed $source
     */
    public function convertFrom($source, string $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null): ObjectStorage
    {
        $objectStorage = new ObjectStorage();
        foreach ($convertedChildProperties as $subProperty) {
            $objectStorage->attach($subProperty);
        }
        return $objectStorage;
    }

    /**
     * Returns the source, if it is an array, otherwise an empty array.
     *
     * @param mixed $source
     */
    public function getSourceChildPropertiesToBeConverted($source): array
    {
        if (is_array($source)) {
            return $source;
        }
        return [];
    }

    /**
     * Return the type of a given sub-property inside the $targetType
     *
     * @param string $targetType
     */
    public function getTypeOfChildProperty($targetType, string $propertyName, PropertyMappingConfigurationInterface $configuration): string
    {
        $parsedTargetType = TypeHandlingUtility::parseType($targetType);
        return $parsedTargetType['elementType'];
    }
}
