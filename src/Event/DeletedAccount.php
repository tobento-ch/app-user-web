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
 * Event after a user has successfully deleted his account.
 */
final class DeletedAccount
{
    /**
     * Create a new DeletedAccount.
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