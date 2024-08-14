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
use Tobento\App\User\UserInterface;
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

class TwoFactorAuthenticationCodeTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }
    
    public function testCodeScreenIsRenderedAndCodeWasSentAndCanBeVerified()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new CustomLoginFeature(),
            new Feature\TwoFactorAuthenticationCode(),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'twofactor.code.show');
        $auth->assertAuthenticated();
        
        // code request/response:
        $http->request(method: 'GET', uri: 'two-factor/code');        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Two-Factor Authentication')
            ->assertBodyContains('Confirm Code')
            ->assertBodyContains('Resend Verification Code');
        
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'two-factor/code/verify',
                body: [
                    'code' => $messages->notification()->token()->id(),
                ],
            );
            
            $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'home');
            
            $this->fakeEvents()->assertDispatched(
                Event\VerifiedTwoFactorCode::class,
                static function(Event\VerifiedTwoFactorCode $event): bool {
                    $user = $event->user();
                    return $user->email() === 'tom@example.com';
                }
            );
            
            $http->followRedirects()->assertStatus(200)->assertBodyContains('Welcome back');
            
            return true;
        });
    }
    
    public function testCodeScreenIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new CustomLoginFeature(),
            new Feature\TwoFactorAuthenticationCode(localizeRoute: true),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'twofactor.code.show');
        $auth->assertAuthenticated();
        
        // code request/response:
        $http->request(method: 'GET', uri: 'de/two-factor/code');
        
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
            ->assertBodyContains('Zwei-Faktor Authentifizierung');
    }

    public function testCodeScreenIsNotRenderedIfUserIsNotAuthenticatedViaLoginformTwofactor()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new Feature\Login(),
            new Feature\TwoFactorAuthenticationCode(),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'home');
        $auth->assertAuthenticated();
        
        // code request/response:
        $http->request(method: 'GET', uri: 'two-factor/code');
        $http->response()->assertStatus(403);
    }
    
    public function testCodeScreenIsRenderedAndCodeIsSentOnceIfExists()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new CustomLoginFeature(),
            new Feature\TwoFactorAuthenticationCode(),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302);
        
        // code request/response:
        $http->request(method: 'GET', uri: 'two-factor/code');
        $http->response()->assertStatus(200);
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class);
        
        // code request/response:
        $http->request(method: 'GET', uri: 'two-factor/code');
        $http->response()->assertStatus(200);
        $this->fakeNotifier()->assertNotSent(Notification\VerificationCode::class);
    }
    
    public function testCodeScreenIsRenderedAndCodeIsResentIfExpired()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new CustomLoginFeature(),
            new Feature\TwoFactorAuthenticationCode(),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302);
        
        // code request/response:
        $http->request(method: 'GET', uri: 'two-factor/code');
        $http->response()->assertStatus(200);
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class);
        
        // code request/response:
        $http->request(method: 'GET', uri: 'two-factor/code');

        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+301 seconds'));
        });
        
        $http->response()->assertStatus(200);
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class);
    }
    
    public function testCodeCanNotBeVerifiedWithInvalidCode()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new CustomLoginFeature(),
            new Feature\TwoFactorAuthenticationCode(),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'twofactor.code.show');
        $auth->assertAuthenticated();
        
        // code request/response:
        $http->request(method: 'GET', uri: 'two-factor/code');        
        $http->response()->assertStatus(200);
        
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'two-factor/code/verify',
                body: [
                    'code' => '123456',
                ],
            );
            
            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute('twofactor.code.show');
            
            $this->fakeEvents()->assertDispatched(Event\VerifyTwoFactorCodeFailed::class);
            $this->fakeEvents()->assertNotDispatched(Event\VerifiedTwoFactorCode::class);
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('The code has been expired or is invalid.');
            
            return true;
        });
    }
    
    public function testCodeCanNotBeVerifiedWithCodeExpired()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new CustomLoginFeature(),
            new Feature\TwoFactorAuthenticationCode(),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'twofactor.code.show');
        $auth->assertAuthenticated();
        
        // code request/response:
        $http->request(method: 'GET', uri: 'two-factor/code');        
        $http->response()->assertStatus(200);
        
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class, function(ChannelMessagesInterface $messages) use ($http): bool {
            $http->request(
                method: 'POST',
                uri: 'two-factor/code/verify',
                body: [
                    'code' => $messages->notification()->token()->id(),
                ],
            );
            
            $this->getApp()->on(ClockInterface::class, function($clock) {
                return new FrozenClock($clock->now()->modify('+301 seconds'));
            });
            
            $http->response()
                ->assertStatus(302)
                ->assertRedirectToRoute('twofactor.code.show');
            
            $this->fakeEvents()->assertDispatched(Event\VerifyTwoFactorCodeFailed::class);
            $this->fakeEvents()->assertNotDispatched(Event\VerifiedTwoFactorCode::class);
            
            $http->followRedirects()
                ->assertStatus(200)
                ->assertBodyContains('The code has been expired or is invalid.');
            
            return true;
        });
    }

    public function testCodeCanBeResent()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new CustomLoginFeature(),
            new Feature\TwoFactorAuthenticationCode(),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'twofactor.code.show');
        $auth->assertAuthenticated();
        
        // code request/response:
        $http->request(method: 'POST', uri: 'two-factor/code/resend');
        
        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('twofactor.code.show');
        
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('A new verification code has been sent.');
    }
    
    public function testCodeCanNotBeResentIfJustPreviouslySent()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new CustomLoginFeature(),
            new Feature\TwoFactorAuthenticationCode(),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $notifier = $this->fakeNotifier();
        $http = $this->fakeHttp();
        
        // login request/response:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        $app->get(PinCodeVerificatorInterface::class)->deleteExpiredCodes();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'twofactor.code.show');
        $auth->assertAuthenticated();
        
        // code request/response:
        $http->request(method: 'POST', uri: 'two-factor/code/resend');
        
        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('twofactor.code.show');
        
        $this->fakeNotifier()->assertSent(Notification\VerificationCode::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('A new verification code has been sent.');
        
        // next request:
        $http->request(method: 'POST', uri: 'two-factor/code/resend');
        
        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('twofactor.code.show');
        
        $this->fakeNotifier()->assertNotSent(Notification\VerificationCode::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have already requested a verification code.');
    }
    
    public function testCodeCanNotSentIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'two-factor/code/resend');

        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
}

class CustomLoginFeature extends Feature\Login
{
    protected function isTwoFactorRequiredFor(UserInterface $user): bool
    {
        return true;
    }
}