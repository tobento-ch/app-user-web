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

use Stringable;

/**
 * VerificatorHashKey
 */
final class VerificatorHashKey implements Stringable
{
    /**
     * Create a new VerificatorHashKey.
     *
     * @param string $key
     */
    public function __construct(
        #[\SensitiveParameter] private string $key,
    ) {}
    
    /**
     * Returns the id.
     *
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }
    
    /**
     * Returns the string representation of the menu.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->key();
    }
}