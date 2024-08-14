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
 
namespace Tobento\App\User\Web\Feature;

use Tobento\App\AppInterface;
use Tobento\App\User\UserInterface;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\App\User\Authentication\AuthInterface;
use Tobento\App\User\Middleware\Authenticated;
use Tobento\App\User\Middleware\Verified;
use Tobento\App\User\Exception\AuthorizationException;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\App\Validation\Http\ValidationRequest;
use Tobento\Service\Iterable\Iter;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Routing\RouteGroupInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Menu\MenusInterface;
use Tobento\Service\Validation\ValidatorInterface;
use Tobento\Service\Validation\Rule\Passes;
use Tobento\Service\Notifier\ChannelsInterface;
use Tobento\Service\Notifier\Storage;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Clock\ClockInterface;
use function Tobento\App\Translation\{trans};

/**
 * Notifications
 */
class Notifications
{
    /**
     * Create a new Notifications.
     *
     * @param string $view
     * @param string $notifierStorageChannel The notifier storage channel used to retrieve notifications.
     * @param null|string $menu The menu name or null if none.
     * @param string $menuLabel The menu label.
     * @param null|string $menuParent The menu parent or null if none.
     * @param string $unauthenticatedMessage
     * @param null|string $unauthenticatedRedirectRoute
     * @param bool $localizeRoute
     */
    public function __construct(
        protected string $view = 'user/notifications',
        protected string $notifierStorageChannel = 'storage',
        protected null|string $menu = 'main',
        protected string $menuLabel = 'Notifications',
        protected null|string $menuParent = null,
        protected string $unauthenticatedMessage = 'You have insufficient rights to access the requested resource!',
        protected null|string $unauthenticatedRedirectRoute = null,
        protected bool $localizeRoute = false,
    ) {}
    
    /**
     * Boot the page.
     *
     * @param RouterInterface $router
     * @param AuthInterface $auth
     * @param AppInterface $app
     * @return void
     */
    public function __invoke(
        RouterInterface $router,
        AuthInterface $auth,
        AppInterface $app,
    ): void {
        // Routes:
        $route = $router->group('', function(RouteGroupInterface $route) {
            $uri = $this->localizeRoute ? '{?locale}/{notifications}' : 'notifications';
            $route->get($uri, [$this, 'index'])->name('notifications');
            $route->patch($uri.'/dismiss', [$this, 'dismiss'])->name('notifications.dismiss');
            $route->post($uri.'/dismiss', [$this, 'dismissAll'])->name('notifications.dismiss.all');
            
        })->middleware(...$this->configureMiddlewares($app));

        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route, 'notifications');
        }
        
        // Menus:
        if ($this->menu) {
            $app->on(
                MenusInterface::class,
                function(MenusInterface $menus, ChannelsInterface $channels) use ($router, $auth, $app) {
                    if ($this->canViewMenu($auth)) {
                        $count = $this->getUnreadNotificationsCount($channels, $auth->getAuthenticated()->user(), $app);
                        $menus->menu($this->menu)
                            ->link($router->url('notifications'), trans($this->menuLabel))
                            ->parent($this->menuParent)
                            ->id('notifications')
                            ->icon('notification')
                            ->badgeIf(
                                badge: $count > 0,
                                text: (string)$count,
                                attributes: ['title' => 'Unread notifications']
                            );
                    }
                }
            );
        }
    }
    
    /**
     * Display the user's notifications.
     *
     * @param ServerRequestInterface $request
     * @param ResponserInterface $responser
     * @param ChannelsInterface $channels
     * @return ResponseInterface
     */
    public function index(
        ServerRequestInterface $request,
        ResponserInterface $responser,
        ChannelsInterface $channels,
    ): ResponseInterface {
        $user = $request->getAttribute(UserInterface::class);
        $this->isAuthorized($user);
        
        $channel = $channels->get(name: $this->notifierStorageChannel);
        
        if (!$channel instanceof Storage\Channel) {
            throw new InvalidArgumentException('Channel needs to be a storage channel!');
        }
        
        $notifications = $channel->repository()->findAll(where: [
            'recipient_id' => $user->id(),
            'read_at' => ['null'],
        ]);
        
        return $responser->render($this->view, [
            'user' => $user,
            'notifications' => $notifications,
            'hasNotifications' => count(Iter::toArray($notifications)) > 0,
        ]);
    }
    
    /**
     * Dismiss a user's notification.
     *
     * @param ValidationRequest $request
     * @param ResponserInterface $responser
     * @param UserRepositoryInterface $userRepository
     * @param ChannelsInterface $channels
     * @param ClockInterface $clock
     * @param RouterInterface $router
     * @return ResponseInterface
     */
    public function dismiss(
        ValidationRequest $request,
        ResponserInterface $responser,
        UserRepositoryInterface $userRepository,
        ChannelsInterface $channels,
        ClockInterface $clock,
        RouterInterface $router,
    ): ResponseInterface {
        $user = $request->requester()->request()->getAttribute(UserInterface::class);
        $this->isAuthorized($user);
        
        $channel = $channels->get(name: $this->notifierStorageChannel);
        
        if (!$channel instanceof Storage\Channel) {
            throw new InvalidArgumentException('Channel needs to be a storage channel!');
        }
        
        // request validation:
        $validation = $request->validate(
            rules: [
                'id' => [
                    'required|string',
                    new Passes(
                        passes: static function(string $value) use ($channel, $user): bool {
                            $notification = $channel->repository()->findById($value);
                            return ((string)$user->id()) === $notification?->recipientId();
                        },
                    ),
                ],
            ],
            // You may specify an error message flashed to the user.
            errorMessage: 'The notification could not be dismissed.',
        );
        
        // update message as read:
        $channel->repository()->updateById(
            id: $validation->valid()->get('id'),
            attributes: [
                'read_at' => $clock->now(),
            ]
        );
        
        // create and return response:
        $responser->messages()->add(level: 'success', message: 'Notification dismissed successfully.');
        return $responser->redirect($router->url('notifications'));
    }
    
    /**
     * Dismiss all user's notification.
     *
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param UserRepositoryInterface $userRepository
     * @param ChannelsInterface $channels
     * @param ClockInterface $clock
     * @param RouterInterface $router
     * @return ResponseInterface
     */
    public function dismissAll(
        RequesterInterface $requester,
        ResponserInterface $responser,
        UserRepositoryInterface $userRepository,
        ChannelsInterface $channels,
        ClockInterface $clock,
        RouterInterface $router,
    ): ResponseInterface {
        $user = $requester->request()->getAttribute(UserInterface::class);
        $this->isAuthorized($user);
        
        $channel = $channels->get(name: $this->notifierStorageChannel);
        
        if (!$channel instanceof Storage\Channel) {
            throw new InvalidArgumentException('Channel needs to be a storage channel!');
        }
        
        $channel->repository()->update(
            where: [
                'recipient_id' => $user->id(),
                'read_at' => ['null'],
            ],
            attributes: [
                'read_at' => $clock->now(),
            ]
        );
        
        // create and return response:
        $responser->messages()->add(level: 'success', message: 'Notifications dismissed successfully.');
        return $responser->redirect($router->url('notifications'));
    }
    
    /**
     * Returns the user's unread notifications count for the menu badge.
     *
     * @param ChannelsInterface $channels
     * @param UserInterface $user
     * @param AppInterface $app
     * @return int
     */
    protected function getUnreadNotificationsCount(
        ChannelsInterface $channels,
        UserInterface $user,
        AppInterface $app,
    ): int {
        $channel = $channels->get(name: $this->notifierStorageChannel);
        
        if (!$channel instanceof Storage\Channel) {
            return 0;
        }
        
        return $channel->repository()->count(where: [
            'recipient_id' => $user->id(),
            'read_at' => ['null'],
        ]);
    }

    /**
     * Determines if the user is authorized to access the notifications.
     *
     * @param mixed $user
     * @return void
     * @throws AuthorizationException
     */
    protected function isAuthorized(mixed $user): void
    {
        if (
            !$user instanceof UserInterface
            || !$user->isAuthenticated()
        ) {
            throw new AuthorizationException();
        }
    }
    
    /**
     * Determines if the user is authorized to view the notifications menu.
     *
     * @param AuthInterface $auth
     * @return bool Returns true if authorized, otherwise false.
     */
    protected function canViewMenu(AuthInterface $auth): bool
    {
        return $auth->hasAuthenticated();
    }
    
    /**
     * Configure middlewares for the route(s).
     *
     * @param AppInterface $app
     * @return array
     */
    protected function configureMiddlewares(AppInterface $app): array
    {
        return [
            // The Authenticated::class middleware protects routes from unauthenticated users:
            [
                Authenticated::class,

                // you may specify a custom message to show to the user:
                'message' => $this->unauthenticatedMessage,

                // you may specify a message level:
                //'messageLevel' => 'notice',

                // you may specify a route name for redirection:
                'redirectRoute' => $this->unauthenticatedRedirectRoute,
            ],
            // The Verified::class middleware protects routes from unverified users:
            /*[
                Verified::class,
    
                // you may specify a custom message to show to the user:
                'message' => 'You have insufficient rights to access the requested resource!',

                // you may specify a message level:
                'messageLevel' => 'notice',

                // you may specify a route name for redirection:
                'redirectRoute' => 'verification.account',
            ],*/
        ];
    }
}