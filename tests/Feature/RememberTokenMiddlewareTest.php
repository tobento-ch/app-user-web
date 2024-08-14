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
use Tobento\App\User;
use Tobento\App\User\Web\Feature;
use Tobento\App\User\Web\Middleware\RememberedToken;
use Tobento\App\Seeding\User\UserFactory;
use Tobento\Service\Clock\FrozenClock;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Psr\Clock\ClockInterface;

class RememberTokenMiddlewareTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        
        $app->on(RouterInterface::class, function(RouterInterface $router) {
            $router->get('orders', function (ResponserInterface $responser) {
                return $responser->render('home');
            })
            ->name('orders')
            ->middleware([
                User\Middleware\Authenticated::class,
                'exceptVia' => 'remembered',
            ]);
            
            $router->get('orders/{id}', function (ResponserInterface $responser) {
                return $responser->render('home');
            })
            ->name('orders.view')
            ->middleware([
                User\Middleware\Authenticated::class,
                'exceptVia' => 'remembered',
                'redirectRoute' => 'login',
            ]);
        });
        
        return $app;
    }
    
    public function testPageCannotBeAccessedAnymoreIfTokenIsConsideredAsRemembered()
    {
        $config = $this->fakeConfig();
        $config->with('user.middlewares', [
            User\Middleware\Authentication::class,
            User\Middleware\User::class,
            [RememberedToken::class, 'isRememberedAfter' => 30],
        ]);
        $config->with('user_web.features', [
            new Feature\Home(),
            new Feature\Login(
                remember: new \DateInterval('P6M')
            ),
        ]);
        $serverParams = ['REMOTE_ADDR' => 'addr', 'HTTP_USER_AGENT' => 'user-agent'];
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'login', body: [
            'user' => 'tom@example.com', 'password' => '123456', 'remember' => '1',
        ], server: $serverParams);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        
        $http->response()->assertStatus(302);
        $auth->assertAuthenticated();
        $payload = $auth->getAuthenticated()?->token()?->payload();
        $this->assertTrue($payload['remember'] ?? false);

        // next request: we should be able to access the orders view page
        // as we are not yet determined as remembered by the middleware:
        $http->request(method: 'GET', uri: 'orders', server: $serverParams);
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request: after the specified isRememberedAfter, we should not
        // be able to access the page:
        $http->request(method: 'GET', uri: 'orders', server: $serverParams);
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+31 seconds'));
        });
        $http->response()->assertStatus(403);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request: test if we are still logged in
        $http->request(method: 'GET', uri: '', server: $serverParams);
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+31 seconds'));
        });
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
    }
    
    public function testPageCannotBeAccessedAnymoreIfTokenIsConsideredAsRememberedRedirectingToLoginPage()
    {
        $config = $this->fakeConfig();
        $config->with('user.middlewares', [
            User\Middleware\Authentication::class,
            User\Middleware\User::class,
            [RememberedToken::class, 'isRememberedAfter' => 30],
        ]);
        $config->with('user_web.features', [
            new Feature\Home(),
            new Feature\Login(
                remember: new \DateInterval('P6M')
            ),
        ]);
        $serverParams = ['REMOTE_ADDR' => 'addr', 'HTTP_USER_AGENT' => 'user-agent'];
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'login', body: [
            'user' => 'tom@example.com', 'password' => '123456', 'remember' => '1',
        ], server: $serverParams);

        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        
        $http->response()->assertStatus(302);
        $auth->assertAuthenticated();
        $payload = $auth->getAuthenticated()?->token()?->payload();
        $this->assertTrue($payload['remember'] ?? false);

        // next request: we should be able to access the orders view page
        // as we are not yet determined as remembered by the middleware:
        $http->request(method: 'GET', uri: 'orders/12', server: $serverParams);
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request: after the specified isRememberedAfter, we should not
        // be able to access the page:
        $http->request(method: 'GET', uri: 'orders/12', server: $serverParams);
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+31 seconds'));
        });
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $http->followRedirects()->assertStatus(200)->assertBodyContains('Login');
        $this->fakeAuth()->assertAuthenticated();
        
        // next request: test if we are still logged in
        $http->request(method: 'GET', uri: '', server: $serverParams);
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+31 seconds'));
        });
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
    }
    
    public function testIfLogOutYouAreNotRememberedAnymoreAsTokenGetsDeleted()
    {
        $config = $this->fakeConfig();
        $config->with('user.middlewares', [
            User\Middleware\Authentication::class,
            User\Middleware\User::class,
            [RememberedToken::class, 'isRememberedAfter' => 30],
        ]);
        $config->with('user_web.features', [
            new Feature\Home(),
            new Feature\Logout(),
            new Feature\Login(
                remember: new \DateInterval('P6M')
            ),
        ]);
        $serverParams = ['REMOTE_ADDR' => 'addr', 'HTTP_USER_AGENT' => 'user-agent'];
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'login', body: [
            'user' => 'tom@example.com', 'password' => '123456', 'remember' => '1',
        ], server: $serverParams);

        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        
        $http->response()->assertStatus(302);
        $auth->assertAuthenticated();
        $payload = $auth->getAuthenticated()?->token()?->payload();
        $this->assertTrue($payload['remember'] ?? false);

        // next request: we are still log in after some time
        $http->request(method: 'GET', uri: '', server: $serverParams);
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request: log out
        $http->request(method: 'POST', uri: 'logout', server: $serverParams);
        $http->followRedirects()->assertStatus(200);
        $this->fakeAuth()->assertNotAuthenticated();
        
        // next request: we are still log out
        $http->request(method: 'GET', uri: '', server: $serverParams);
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertNotAuthenticated();
    }
    
    public function testUnauthenticatesUserIfRememberMeTokenVerifierFails()
    {
        $config = $this->fakeConfig();
        $config->with('user.middlewares', [
            User\Middleware\Authentication::class,
            User\Middleware\User::class,
            [RememberedToken::class, 'isRememberedAfter' => 30],
        ]);
        $config->with('user_web.features', [
            new Feature\Home(),
            new Feature\Login(
                remember: new \DateInterval('P6M')
            ),
        ]);
        $serverParams = ['REMOTE_ADDR' => 'addr', 'HTTP_USER_AGENT' => 'user-agent'];
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'login', body: [
            'user' => 'tom@example.com', 'password' => '123456', 'remember' => '1',
        ], server: $serverParams);

        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        
        $http->response()->assertStatus(302);
        $auth->assertAuthenticated();

        // next request: we are still log in after some time
        $http->request(method: 'GET', uri: '', server: $serverParams);
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+31 seconds'));
        });
        
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request: we should be unauthenticated as token verifier fails
        // because 'REMOTE_ADDR' is not matching.
        $http->request(method: 'GET', uri: '', server: ['REMOTE_ADDR' => 'invalid-addr', 'HTTP_USER_AGENT' => 'user-agent']);
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertNotAuthenticated();
    }
}