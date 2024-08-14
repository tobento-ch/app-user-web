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
use Tobento\App\User\Web\Feature;
use Tobento\App\User\Web\Event;
use Tobento\App\User\Web\Notification;
use Tobento\App\AppInterface;
use Tobento\App\Seeding\User\UserFactory;
use Tobento\App\RateLimiter\Middleware\RateLimitRequests;
use Tobento\App\RateLimiter\Symfony\Registry\SlidingWindow;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Notifier\ChannelMessagesInterface;
use Tobento\Service\Language\LanguageFactory;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Language\Languages;
use Tobento\Service\Clock\FrozenClock;
use Psr\Clock\ClockInterface;

class ForgotPasswordTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }

    public function testForgotPasswordScreenIsRendered()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'forgot-password');
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Name')
            ->assertBodyContains('User');
    }
    
    public function testForgotPasswordLinkCanBeRequested()
    {
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        
        $http->request(
            method: 'POST',
            uri: 'forgot-password',
            body: [
                'address' => ['name' => 'tom'],
                'user' => 'tom@example.com',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withAddress(['name' => 'tom'])->createOne();
        
        $this->assertSame('', (string) $http->response()->response()->getBody());
        $http->response()->assertStatus(302);
        
        $notifier->assertSent(Notification\ResetPassword::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request('GET', $messages->notification()->url());
            $http->response()
                ->assertStatus(200)
                ->assertBodyContains('User')
                ->assertBodyContains('Password');
            
            return true;
        });
    }
    
    public function testPasswordCanBeResetWithValidToken()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->request(
            method: 'POST',
            uri: 'forgot-password',
            body: [
                'address' => ['name' => 'tom'],
                'user' => 'tom@example.com',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withAddress(['name' => 'tom'])->createOne();
        
        $http->response()->assertStatus(302);
        
        $notifier->assertSent(Notification\ResetPassword::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'forgot-password/reset',
                body: [
                    'token' => $messages->notification()->token()->id(),
                    'user' => 'tom@example.com',
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ],
            );

            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute(name: 'home');
            
            $this->fakeEvents()->assertDispatched(Event\PasswordReset::class, static function(Event\PasswordReset $event): bool {
                $user = $event->user();
                return $user->email() === 'tom@example.com';
            });
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('Your password has been reset!');
            
            // check if token is being deleted on success:
            $http->request(
                method: 'POST',
                uri: 'forgot-password/reset',
                body: [
                    'token' => $messages->notification()->token()->id(),
                    'user' => 'tom@example.com',
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ],
            );
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('The token has been expired or is invalid.');
            
            return true;
        });
    }
    
    public function testPasswordCanNotBeResetWithInvalidToken()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->request(
            method: 'POST',
            uri: 'forgot-password',
            body: [
                'address' => ['name' => 'tom'],
                'user' => 'tom@example.com',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withAddress(['name' => 'tom'])->createOne();
        
        $http->response()->assertStatus(302);
        
        $notifier->assertSent(Notification\ResetPassword::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'forgot-password/reset',
                body: [
                    'token' => 'invalid-token',
                    'user' => 'tom@example.com',
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ],
            );

            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute(name: 'forgot-password.identity');
            
            return true;
        });
    }
    
    public function testPasswordCanNotBeResetWithInvalidUser()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->request(
            method: 'POST',
            uri: 'forgot-password',
            body: [
                'address' => ['name' => 'tom'],
                'user' => 'tom@example.com',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withAddress(['name' => 'tom'])->createOne();
        
        $http->response()->assertStatus(302);
        
        $notifier->assertSent(Notification\ResetPassword::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'forgot-password/reset',
                body: [
                    'token' => $messages->notification()->token()->id(),
                    'user' => 'invalid-user',
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ],
            );
            
            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute(
                    name: 'forgot-password.reset',
                    parameters: ['token' => $messages->notification()->token()->id()]
                );
            
                $this->fakeEvents()->assertDispatched(
                    Event\PasswordResetFailed::class,
                    static function(Event\PasswordResetFailed $event): bool {
                        return $event->step() === 'reset';
                    }
                );
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('Invalid user.');
            
            return true;
        });
    }
    
    public function testPasswordCanNotBeResetWithExpiredToken()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->request(
            method: 'POST',
            uri: 'forgot-password',
            body: [
                'address' => ['name' => 'tom'],
                'user' => 'tom@example.com',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withAddress(['name' => 'tom'])->createOne();
        
        $http->response()->assertStatus(302);
        
        $notifier->assertSent(Notification\ResetPassword::class, function(ChannelMessagesInterface $messages) use ($http): bool {
                        
            $http->request(
                method: 'POST',
                uri: 'forgot-password/reset',
                body: [
                    'token' => $messages->notification()->token()->id(),
                    'user' => 'tom@example.com',
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ],
            );
            
            $this->getApp()->on(ClockInterface::class, function($clock) {
                return new FrozenClock($clock->now()->modify('+301 seconds'));
            });
            
            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute(name: 'forgot-password.identity');
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('The token has been expired or is invalid.');
            
            return true;
        });
    }    
    
    public function testForgotPasswordLinkRequestIsThrottled()
    {
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->request(method: 'POST', uri: 'forgot-password', body: [
            'address' => ['name' => 'tom'],
            'user' => 'tom@example.com',
        ]);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withAddress(['name' => 'tom'])->createOne();
        
        $http->response()->assertStatus(302);
        $notifier->assertSent(Notification\ResetPassword::class);
        
        $http->request(method: 'POST', uri: 'forgot-password', body: [
            'address' => ['name' => 'tom'],
            'user' => 'tom@example.com',
        ]);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have already requested a reset password link.');
        $this->fakeNotifier()->assertNotSent(Notification\ResetPassword::class);
        
        $http->request(method: 'POST', uri: 'forgot-password', body: [
            'address' => ['name' => 'tom'],
            'user' => 'tom@example.com',
        ]);
        
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+61 seconds'));
        });
        
        $http->response()->assertStatus(302);
        $this->fakeNotifier()->assertSent(Notification\ResetPassword::class);
    }
    
    public function testForgotPasswordLinkRequestResetFailedEventIsDispatched()
    {
        $events = $this->fakeEvents();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->request(method: 'POST', uri: 'forgot-password', body: [
            'address' => ['name' => 'tom'],
            'user' => 'tom@example.com',
        ]);
        
        $http->response()->assertStatus(302);
        $this->fakeEvents()->assertDispatched(
            Event\PasswordResetFailed::class,
            static function(Event\PasswordResetFailed $event): bool {
                return $event->step() === 'identity'
                    && !is_null($event->authenticator());
            }
        );
    }
    
    public function testForgotPasswordLinkRequestIsRateLimited()
    {
        $this->fakeConfig()->with('middleware.replace', [
            RateLimitRequests::class => [
                RateLimitRequests::class,
                'registry' => new SlidingWindow(limit: 1, interval: '1 Seconds')
            ],
        ]);
        
        $request = [
            'method' => 'POST',
            'uri' => 'forgot-password',
            'server' => ['REMOTE_ADDR' => '127.0.0.1'],
            'body' => ['address' => ['name' => 'invalid'], 'user' => 'tom@example.com'],
        ];
        
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        $http->request(...$request);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withAddress(['name' => 'tom'])->createOne();

        $http->response()->assertStatus(302);
        $notifier->assertNotSent(Notification\ResetPassword::class);
        
        $http->request(...$request);
        
        $http->followRedirects()
            ->assertStatus(429);
        $this->fakeNotifier()->assertNotSent(Notification\ResetPassword::class);
    }
    
    public function testForgotPasswordScreenIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\ForgotPassword(
                localizeRoute: true,
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'de/forgot-password');
        
        $app = $this->getApp();
        $app->on(LanguagesInterface::class, function() {
            $languageFactory = new LanguageFactory();
            return new Languages(
                $languageFactory->createLanguage(locale: 'en', default: true),
                $languageFactory->createLanguage(locale: 'de', slug: 'de'),
            );
        });
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Name')
            ->assertBodyContains('Benutzer');
    }
}