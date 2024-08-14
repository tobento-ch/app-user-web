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

use Tobento\App\User\UserInterface;
use Exception;

/**
 * Event after a user two-factor authentication code verification attempt failed.
 */
final class VerifyTwoFactorCodeFailed
{
    /**
     * Create a new VerifyTwoFactorCodeFailed.
     *
     * @param UserInterface $user
     * @param Exception $exception
     */
    public function __construct(
        private UserInterface $user,
        private Exception $exception,
    ) {}
    
    /**
     * Returns the user.
     *
     * @return UserInterface
     */
    public function user(): UserInterface
    {
        return $this->user;
    }
    
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