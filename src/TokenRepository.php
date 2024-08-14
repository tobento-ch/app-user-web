<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

declare(strict_types=1);
 
namespace Tobento\App\User\Web;

use Tobento\Service\Repository\Storage\StorageRepository;
use Tobento\Service\Repository\Storage\Column\ColumnsInterface;
use Tobento\Service\Repository\Storage\Column\ColumnInterface;
use Tobento\Service\Repository\Storage\Column;

/**
 * TokenRepository
 */
class TokenRepository extends StorageRepository
{
    /**
     * Returns the configured columns.
     *
     * @return iterable<ColumnInterface>|ColumnsInterface
     */
    protected function configureColumns(): iterable|ColumnsInterface
    {
        return [
            Column\Text::new('id'),
            Column\Text::new('type')->type(length: 100),
            Column\Integer::new('user_id'),
            Column\Json::new('payload'),
            Column\Datetime::new('issued_at', type: 'timestamp'),
            Column\Datetime::new('expires_at', type: 'timestamp'),
        ];
    }
}