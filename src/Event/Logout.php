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

use Tobento\App\User\Authentication\AuthenticatedInterface;

/**
 * Event after a user is logged out successfully.
 */
final class Logout
{
    /**
     * Create a new Logout.
     *
     * @param AuthenticatedInterface $unauthenticated
     */
    public function __construct(
        private AuthenticatedInterface $unauthenticated,
    ) {}
    
    /**
     * Returns the unauthenticated.
     *
     * @return AuthenticatedInterface
     */
    public function unauthenticated(): AuthenticatedInterface
    {
        return $this->unauthenticated;
    }
}