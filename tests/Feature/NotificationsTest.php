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
use Tobento\App\Seeding\User\UserFactory;
use Tobento\Service\Language\LanguageFactory;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Language\Languages;
use Tobento\Service\Notifier\ChannelsInterface;
use Tobento\Service\Routing\RouterInterface;
use Symfony\Component\DomCrawler\Crawler;
use Tobento\Service\Clock\FrozenClock;
use Psr\Clock\ClockInterface;

class NotificationsTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        
        $app->on(RouterInterface::class, function(RouterInterface $router) {
            $router->get('orders/{id}', function () {
                return 'order';
            })->name('orders.view');
        });
        
        return $app;
    }

    public function testNotificationsScreenIsRenderedWithoutNotifications()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'notifications');
        
        $app = $this->bootingApp();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Notifications')
            ->assertBodyContains('You have no notifications.');
    }
    
    public function testNotificationsScreenIsRenderedWithNotification()
    {
        $config = $this->fakeConfig();
        $config->with('http.url', '');
        $config->with('notifier.formatters', [
            \Tobento\App\Notifier\Storage\GeneralNotificationFormatter::class,
        ]);
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'notifications');
        
        $app = $this->getApp();
        $app->on(ChannelsInterface::class, function(ChannelsInterface $channels) {
            $channel = $channels->get(name: 'storage');
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 1,
                'recipient_type' => 'user',
                'data' => [
                    'message' => 'Order Message',
                    'subject' => 'Subject',
                    'action_text' => 'View Order',
                    'action_route' => 'orders.view',
                    'action_route_parameters' => ['id' => 5],
                ],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
        });
        
        $app->booting();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Sunday, 12. May 2024, 00:00')
            ->assertBodyContains('Order Message')
            ->assertNodeExists('a[href="orders/5"]', fn (Crawler $n): bool => $n->text() === 'View Order');
    }
    
    public function testNotificationsScreenShowsUnreadNotificationsOnly()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'notifications');
        
        $app = $this->getApp();
        $app->on(ChannelsInterface::class, function(ChannelsInterface $channels) {
            $channel = $channels->get(name: 'storage');
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 1,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 1,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => '12-05-2024',
                'created_at' => '12-05-2024',
            ]);
        });
        
        $app->booting();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Sunday, 12. May 2024, 00:00')
            ->assertBodyContains('Order Message')
            // menu badge
            ->assertNodeExists('span[title="Unread notifications"]', fn (Crawler $n): bool => $n->text() === '1')
            // one more because of header row
            ->assertNodeExists('.table-row', fn (Crawler $n): bool => $n->count() === 2);
    }
    
    public function testNotificationsScreenShowsNotificationsBelongingToUsersOnly()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'notifications');
        
        $app = $this->getApp();
        $app->on(ChannelsInterface::class, function(ChannelsInterface $channels) {
            $channel = $channels->get(name: 'storage');
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 1,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 3,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
        });
        
        $app->booting();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Order Message')
            // menu badge
            ->assertNodeExists('span[title="Unread notifications"]', fn (Crawler $n): bool => $n->text() === '1')
            // one more because of header row
            ->assertNodeExists('.table-row', fn (Crawler $n): bool => $n->count() === 2);
    }
    
    public function testNotificationsMenuIsNotShownIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: '');
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyNotContains('Notifications');
    }
    
    public function testNotificationsScreenIsRenderedIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            new Feature\Notifications(
                localizeRoute: true,
            ),
        ]);
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'de/benachrichtigungen');
        
        $app = $this->getApp();
        $app->on(LanguagesInterface::class, function() {
            $languageFactory = new LanguageFactory();
            return new Languages(
                $languageFactory->createLanguage(locale: 'en', default: true),
                $languageFactory->createLanguage(locale: 'de', slug: 'de'),
            );
        });
        
        $app->booting();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Benachrichtigungen');
    }
    
    public function testNotificationsScreenIsNotRenderedIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'notifications');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }    
    
    public function testNotificationCanBeDismissed()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'notifications/dismiss',
            body: [
                'id' => '1',
            ],
        );
        
        $app = $this->getApp();
        $app->on(ClockInterface::class, function($clock) {
            return new FrozenClock(new \DateTimeImmutable('12-05-2025'));
        });
        $app->on(ChannelsInterface::class, function(ChannelsInterface $channels) {
            $channel = $channels->get(name: 'storage');
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 1,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
        });
        
        $app->booting();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'notifications');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Notification dismissed successfully.')
            ->assertBodyContains('You have no notifications.');
        
        $repo = $app->get(ChannelsInterface::class)->get(name: 'storage')->repository();
        $this->assertSame('2025-05-12 00:00:00', $repo->findById(1)->get('read_at'));
    }
    
    public function testNotificationCanNotBeDismissedIfNotBelongsToUser()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri('notifications');
        $http->request(
            method: 'PATCH',
            uri: 'notifications/dismiss',
            body: [
                'id' => '1',
            ],
        );
        
        $app = $this->getApp();
        $app->on(ChannelsInterface::class, function(ChannelsInterface $channels) {
            $channel = $channels->get(name: 'storage');
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 3,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
        });
        
        $app->booting();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'notifications');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The notification could not be dismissed.');
    }
    
    public function testNotificationCanNotBeDismissedIfNotExist()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->previousUri('notifications');
        $http->request(
            method: 'PATCH',
            uri: 'notifications/dismiss',
            body: [
                'id' => '1',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'notifications');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The notification could not be dismissed.');
    }
    
    public function testNotificationCanNotBeDismissedIfNotAuthenticated()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'notifications/dismiss',
            body: [
                'id' => '1',
            ],
        );
        
        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('login');
    }
    
    public function testNotificationsCanBeDismissed()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'notifications/dismiss',
        );
        
        $app = $this->getApp();
        $app->on(ClockInterface::class, function($clock) {
            return new FrozenClock(new \DateTimeImmutable('12-05-2025'));
        });
        $app->on(ChannelsInterface::class, function(ChannelsInterface $channels) {
            $channel = $channels->get(name: 'storage');
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 1,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
        });
        
        $app->booting();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'notifications');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Notifications dismissed successfully.')
            ->assertBodyContains('You have no notifications.');
        
        $repo = $app->get(ChannelsInterface::class)->get(name: 'storage')->repository();
        $this->assertSame('2025-05-12 00:00:00', $repo->findById(1)->get('read_at'));
    }
    
    public function testNotificationsDismissesNotificationsBelongingToUserOnly()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'notifications/dismiss',
        );
        
        $app = $this->getApp();
        $app->on(ClockInterface::class, function($clock) {
            return new FrozenClock(new \DateTimeImmutable('12-05-2025'));
        });
        $app->on(ChannelsInterface::class, function(ChannelsInterface $channels) {
            $channel = $channels->get(name: 'storage');
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 1,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
            $channel->repository()->create([
                'name' => 'Notif',
                'recipient_id' => 3,
                'recipient_type' => 'user',
                'data' => ['message' => 'Order Message'],
                'read_at' => null,
                'created_at' => '12-05-2024',
            ]);
        });
        
        $app->booting();
        $auth->authenticatedAs(UserFactory::new()->createOne());
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'notifications');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Notifications dismissed successfully.')
            ->assertBodyContains('You have no notifications.');
        
        $repo = $app->get(ChannelsInterface::class)->get(name: 'storage')->repository();
        $this->assertSame('2025-05-12 00:00:00', $repo->findById(1)->get('read_at'));
        $this->assertSame('', $repo->findById(2)->get('read_at'));
    }
    
    public function testNotificationsCanNotBeDismissedIfNotAuthenticated()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'notifications/dismiss',
        );
        
        $http->response()
            ->assertStatus(302)
            ->assertRedirectToRoute('login');
    }
}