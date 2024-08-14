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
 * Event after a user has logged in successfully.
 */
final class Login
{
    /**
     * Create a new Login.
     *
     * @param AuthenticatedInterface $authenticated
     * @param bool $remember
     */
    public function __construct(
        private AuthenticatedInterface $authenticated,
        private bool $remember = false,
    ) {}
    
    /**
     * Returns the authenticated.
     *
     * @return AuthenticatedInterface
     */
    public function authenticated(): AuthenticatedInterface
    {
        return $this->authenticated;
    }
    
    /**
     * Returns the remember.
     *
     * @return bool
     */
    public function remember(): bool
    {
        return $this->remember;
    }
}