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
 * Event after a user has verified a channel successfully.
 */
final class VerifiedChannel
{
    /**
     * Create a new VerifiedChannel.
     *
     * @param UserInterface $user
     * @param string $channel
     */
    public function __construct(
        private UserInterface $user,
        private string $channel,
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
     * Returns the channel.
     *
     * @return string
     */
    public function channel(): string
    {
        return $this->channel;
    }
}