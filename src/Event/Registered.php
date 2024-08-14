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

/**
 * Event after a user has registered successfully.
 */
final class Registered
{
    /**
     * Create a new Registered.
     *
     * @param UserInterface $user
     */
    public function __construct(
        private UserInterface $user,
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
}