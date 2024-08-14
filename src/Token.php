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

use DateTimeImmutable;

/**
 * Token
 */
class Token implements TokenInterface
{
    /**
     * Create a new Token.
     *
     * @param string $id
     * @param string $type
     * @param string|int $userId
     * @param array $payload
     * @param DateTimeImmutable $issuedAt
     * @param DateTimeImmutable $expiresAt
     */
    public function __construct(
        protected string $id,
        protected string $type,
        protected string|int $userId,
        protected array $payload,
        protected DateTimeImmutable $issuedAt,
        protected DateTimeImmutable $expiresAt,
    ) {}
    
    /**
     * Returns the id.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }
    
    /**
     * Returns a new instance with the specified id.
     *
     * @param string $id
     * @return static
     */
    public function withId(string $id): static
    {
        $new = clone $this;
        $new->id = $id;
        return $new;
    }

    /**
     * Returns the type.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }
    
    /**
     * Returns the user id.
     *
     * @return string|int
     */
    public function userId(): string|int
    {
        return $this->userId;
    }

    /**
     * Returns the payload.
     *
     * @return array
     */
    public function payload(): array
    {
        return $this->payload;
    }
    
    /**
     * Returns the point in time the token has been issued.
     *
     * @return DateTimeImmutable
     */
    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }
    
    /**
     * Returns the point in time after which the token MUST be considered expired.
     *
     * @return DateTimeImmutable
     */
    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
    
    /**
     * Serializes the object to a value that can be serialized natively by json_encode().
     *
     * @return array
     */    
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type(),
            'userId' => $this->userId(),
            'payload' => $this->payload(),
            'issuedAt' => $this->issuedAt()->getTimestamp(),
            'expiresAt' => $this->expiresAt()->getTimestamp(),
        ];
    }
    
    /**
     * Returns a new instance created from the specified JSON string.
     *
     * @param string $json
     * @return static
     * @throws \Throwable
     */
    public static function fromJsonString(string $json): static
    {
        $data = json_decode($json, true);
        
        $data['issuedAt'] = (new DateTimeImmutable())->setTimestamp($data['issuedAt']);
        $data['expiresAt'] = (new DateTimeImmutable())->setTimestamp($data['expiresAt']);
        
        return new static(...$data);
    }    
}