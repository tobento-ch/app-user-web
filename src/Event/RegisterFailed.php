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

namespace Tobento\App\User\Web\Event;

use Exception;

/**
 * Event after a user register attempt failed.
 */
final class RegisterFailed
{
    /**
     * Create a new RegisterFailed.
     *
     * @param Exception $exception
     */
    public function __construct(
        private Exception $exception,
    ) {}
    
    /**
     * Returns the exception.
     *
     * @return Exception
     */
    public function exception(): Exception
    {
        return $this->exception;
    }
}