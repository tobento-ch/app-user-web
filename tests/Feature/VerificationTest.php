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

namespace Tobento\App\User\Web\Test\Feature;

use PHPUnit\Framework\TestCase;
use Tobento\App\Testing\Database\RefreshDatabases;
use Tobento\App\AppInterface;
use Tobento\App\User\Web\Event;
use Tobento\App\User\Web\Feature;
use Tobento\App\User\Web\Notification;
use Tobento\App\User\Web\PinCodeVerificatorInterface;
use Tobento\App\Notifier\AvailableChannelsInterface;
use Tobento\App\Notifier\AvailableChannels;
use Tobento\App\Seeding\User\UserFactory;
use Tobento\Service\Notifier\ChannelMessagesInterface;
use Tobento\Service\Language\LanguageFactory;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Language\Languages;
use Tobento\Service\Clock\FrozenClock;
use Psr\Clock\ClockInterface;

class VerificationTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }

    public function testVerificationScreenIsRendered()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification');
        
        $app = $this->bootingApp();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Account Verification')
            ->assertBodyContains('Channel')
            ->assertBodyContains('Actions')
            ->assertBodyContains('verify');
    }
    
    public function testVerificationScreenIsNotRenderedIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
    
    public function testVerificationScreenIsRenderedIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            Feature\Profile::class,
            new Feature\Verification(
                localizeRoute: true,
            ),
        ]);
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'de/verifizierung');
        
        $app = $this->getApp();
        $app->on(LanguagesInterface::class, function() {
            $languageFactory = new LanguageFactory();
            return new Languages(
                $languageFactory->createLanguage(locale: 'en', default: true),
                $languageFactory->createLanguage(locale: 'de', slug: 'de'),
            );
        });
        
        $app = $this->bootingApp();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Kontoverifizierung')
            ->assertBodyContains('Kanal')
            ->assertBodyContains('Aktionen');
    }

    public function testChannelScreenIsNotRenderedIfInvalidChannel()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification/foo');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()
            ->assertStatus(403);
    }
    
    public function testChannelScreenIsNotRenderedIfChannelAlreadyVerified()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification/email');
        
        $app = $this->bootingApp();
        $user = UserFactory::new(['verified' => ['email' => '2025-12-26 12:00']])->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()
            ->assertStatus(403);
    }
    
    public function testChannelScreenIsNotRenderedIfIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification/email');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
    
    public function testChannelScreenIsRenderedAndCodeWasSentAndCanBeVerified()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification/email');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Channel Verification')
            ->assertBodyContains('Confirm Code')
            ->assertBodyContains('Resend Verification Code');
        
        $notifier->assertSent(Notification\VerificationCode::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'verification/email/verify',
                body: [
                    'code' => $messages->notification()->token()->id(),
                ],
            );

            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute(name: 'home');
            
            $this->fakeEvents()->assertDispatched(
                Event\VerifiedChannel::class,
                static function(Event\VerifiedChannel $event): bool {
                    $user = $event->user();
                    return $user->email() === 'tom@example.com'
                        && $event->channel() === 'email';
                }
            );
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('Channel verified successfully.');
            
            return true;
        });
    }
    
    public function testChannelScreenIsRenderedAndCodeIsSentOnceIfExists()
    {
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification/email');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(200);
        $notifier->assertSent(Notification\VerificationCode::class);
        
        $http->request(method: 'GET', uri: 'verification/email');
        $http->response()->assertStatus(200);
        
        $this->fakeNotifier()->assertNotSent(Notification\VerificationCode::class);
    }
    
    public function testChannelScreenIsRenderedAndCodeIsResentIfExpired()
    {
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification/email');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(200);
        $notifier->assertSent(Notification\VerificationCode::class);
        
        $http->request(method: 'GET', uri: 'verification/email');
        
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+301 seconds'));
        });
        
        $http->response()->assertStatus(200);
        
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class);
    }
    
    public function testChannelCanNotBeVerifiedWithInvalidCode()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification/email');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(200);
        
        $notifier->assertSent(Notification\VerificationCode::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'verification/email/verify',
                body: [
                    'code' => '123456',
                ],
            );

            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute('verification.channel', ['channel' => 'email']);
            
            $this->fakeEvents()->assertDispatched(Event\VerifyChannelFailed::class);
            $this->fakeEvents()->assertNotDispatched(Event\VerifiedChannel::class);
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('The code has been expired or is invalid.');
            
            return true;
        });
    }
    
    public function testChannelCanNotBeVerifiedWithCodeExpired()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'verification/email');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(200);
        
        $notifier->assertSent(Notification\VerificationCode::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'verification/email/verify',
                body: [
                    'code' => $messages->notification()->token()->id(),
                ],
            );
            
            $this->getApp()->on(ClockInterface::class, function($clock) {
                return new FrozenClock($clock->now()->modify('+301 seconds'));
            });

            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute('verification.channel', ['channel' => 'email']);
            
            $this->fakeEvents()->assertDispatched(Event\VerifyChannelFailed::class);
            $this->fakeEvents()->assertNotDispatched(Event\VerifiedChannel::class);
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('The code has been expired or is invalid.');
            
            return true;
        });
    }
    
    public function testChannelCanNotBeVerifiedIfNoCodeAtAll()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'verification/email/verify',
            body: [
                'code' => '123456',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);

        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('verification.channel', ['channel' => 'email']);
        
        $notifier->assertNotSent(Notification\VerificationCode::class);
        $events->assertNotDispatched(Event\VerifiedChannel::class);

        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The code has been expired or is invalid.');
    }
    
    public function testChannelCanNotBeVerifiedIfInvalidChannel()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'verification/foo/verify',
            body: [
                'code' => '123456',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);

        $http->response()
            ->assertStatus(403);
    }
    
    public function testChannelCanNotBeVerifiedIfChannelAlreadyVerified()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'verification/email/verify',
            body: [
                'code' => '123456',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new(['verified' => ['email' => '2025-12-26 12:00']])->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);

        $http->response()
            ->assertStatus(403);
    }
    
    public function testChannelCanNotBeVerifiedWithoutEmail()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'verification/email/verify',
            body: [
                'code' => '123456',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('')->createOne();
        $auth->authenticatedAs($user);

        $http->response()
            ->assertStatus(403);
    }
    
    public function testChannelCanNotBeVerifiedWithoutSmartphone()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'verification/smartphone/verify',
            body: [
                'code' => '123456',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withSmartphone('')->createOne();
        $auth->authenticatedAs($user);

        $http->response()
            ->assertStatus(403);
    }
    
    public function testChannelCanNotBeVerifiedIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'verification/email/verify',
            body: [
                'code' => '123456',
            ],
        );

        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('login');
    }
    
    public function testNotificationIsOnlySentWithTheChannelToVerify()
    {
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'verification/email/resend');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('verification.channel', ['channel' => 'email']);
        
        $notifier->assertSent(Notification\VerificationCode::class, static function(ChannelMessagesInterface $messages): bool {
            return $messages->successful()->channelNames() === ['mail'];
        });
    }

    public function testCodeCanBeResent()
    {
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'verification/email/resend');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('verification.channel', ['channel' => 'email']);
        
        $notifier->assertSent(Notification\VerificationCode::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('A new verification code has been sent.');
    }
    
    public function testCodeCanNotBeResentIfJustPreviouslySent()
    {
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'verification/email/resend');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302);
        
        // next request:
        $http->request(method: 'POST', uri: 'verification/email/resend');
        
        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('verification.channel', ['channel' => 'email']);
        
        $this->fakeNotifier()->assertNotSent(Notification\VerificationCode::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have already requested a verification code.');
    }
    
    public function testCodeCanNotSentIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'verification/email/resend');

        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
    
    public function testCodeCanNotSentIfInvalidChannel()
    {
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'verification/foo/resend');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(403);
    }
}