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

class VerificationCode extends AbstractNotification implements Message\ToMail, Message\ToSms
{
    /**
     * Create an new VerificationCode.
     *
     * @param TokenInterface $token
     */
    public function __construct(
        protected TokenInterface $token,
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
     * Returns the mail message.
     *
     * @param RecipientInterface $recipient
     * @param string $channel The channel name.
     * @param TranslatorInterface $translator
     * @return TemplatedMessage
     */
    public function toMail(RecipientInterface $recipient, string $channel, TranslatorInterface $translator): TemplatedMessage
    {
        /*
        // example custom message for twofactor:
        if ($this->token()->type() === 'twofactor') {
            // return created message.
        }
        */
        
        $seconds = $this->token()->expiresAt()->getTimestamp() - time();

        return (new TemplatedMessage())
            ->subject($translator->trans('Verification code'))
            ->txt($translator->trans(':code is your verification code.', [':code' => $this->token()->id()]))
            ->txt($translator->trans(
                'This verification code will expire in :minutes minutes.',
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
            subject: $translator->trans(':code is your verification code.', [':code' => $this->token()->id()]),
        );
    }
}