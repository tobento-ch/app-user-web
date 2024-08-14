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
 
namespace Tobento\App\User\Web\Notification;

use Tobento\App\Mail\TemplatedMessage;
use Tobento\App\User\Web\TokenInterface;
use Tobento\Service\Notifier\AbstractNotification;
use Tobento\Service\Notifier\RecipientInterface;
use Tobento\Service\Notifier\Message;
use Tobento\Service\Translation\TranslatorInterface;

class ResetPassword extends AbstractNotification implements Message\ToMail, Message\ToSms
{
    /**
     * Create an new ResetPassword.
     *
     * @param TokenInterface $token
     * @param string $url
     */
    public function __construct(
        protected TokenInterface $token,
        protected string $url,
    ) {}
    
    /**
     * Returns the token.
     *
     * @return TokenInterface
     */
    public function token(): TokenInterface
    {
        return $this->token;
    }
    
    /**
     * Returns the url.
     *
     * @return string
     */
    public function url(): string
    {
        return $this->url;
    }
    
    /**
     * Returns the mail message.
     *
     * @param RecipientInterface $recipient
     * @param string $channel The channel name.
     * @param TranslatorInterface $translator
     * @return TemplatedMessage
     */
    public function toMail(RecipientInterface $recipient, string $channel, TranslatorInterface $translator): TemplatedMessage
    {
        $seconds = $this->token()->expiresAt()->getTimestamp() - time();
        
        return (new TemplatedMessage())
            ->subject($translator->trans('Reset password'))
            ->txt($translator->trans('You are receiving this email because we received a password reset request for your account.'))
            ->button(
                url: $this->url(),
                label: $translator->trans('Reset password'),
            )
            ->txt($translator->trans(
                'This password reset link will expire in :minutes minutes.',
                [':minutes' => floor($seconds/60)]
            ));
    }
    
    /**
     * Returns the sms message.
     *
     * @param RecipientInterface $recipient
     * @param string $channel The channel name.
     * @param TranslatorInterface $translator
     * @return Message\SmsInterface
     */
    public function toSms(RecipientInterface $recipient, string $channel, TranslatorInterface $translator): Message\SmsInterface
    {
        return new Message\Sms(
            subject: $translator->trans('Reset password').' '.$this->url(),
        );
    }
}