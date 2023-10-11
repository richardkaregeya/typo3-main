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

namespace TYPO3\CMS\Core\Database\Schema\Parser\AST;

/**
 * Syntax node to structure an index definition.
 *
 * @internal
 */
final class CreateIndexDefinitionItem extends AbstractCreateDefinitionItem
{
    // Use a special index type (MySQL: BTREE | HASH)
    public string $indexType = '';
    // @var IndexColumnName[]
    public array $columnNames = [];
    // Index options KEY_BLOCK_SIZE, USING, WITH PARSER or COMMENT
    public array $options = [];

    public function __construct(
        public readonly ?Identifier $indexName = null,
        public readonly bool $isPrimary = false,
        public readonly bool $isUnique = false,
        public readonly bool $isSpatial = false,
        public readonly bool $isFulltext = false
    ) {
    }
}
