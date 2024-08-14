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
 * Event after a user has updated his profile successfully.
 */
final class UpdatedProfile
{
    /**
     * Create a new UpdatedProfile.
     *
     * @param UserInterface $user
     * @param UserInterface $oldUser
     */
    public function __construct(
        private UserInterface $user,
        private UserInterface $oldUser,
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
     * Returns the old user.
     *
     * @return UserInterface
     */
    public function oldUser(): UserInterface
    {
        return $this->oldUser;
    }    
}