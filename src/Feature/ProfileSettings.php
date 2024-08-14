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
use Tobento\App\User\Web\Event;
use Tobento\App\User\Exception\AuthorizationException;
use Tobento\App\Notifier\AvailableChannelsInterface;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\App\Validation\Http\ValidationRequest;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Routing\RouteGroupInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Menu\MenusInterface;
use Tobento\Service\Validation\ValidatorInterface;
use Tobento\Service\Validation\Rule\Passes;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use function Tobento\App\Translation\{trans};

/**
 * ProfileSettings
 */
class ProfileSettings
{
    /**
     * Create a new ProfileSettings.
     *
     * @param string $view
     * @param null|string $menu The menu name or null if none.
     * @param string $menuLabel The menu label.
     * @param null|string $menuParent The menu parent or null if none.
     * @param string $unauthenticatedMessage
     * @param null|string $unauthenticatedRedirectRoute
     * @param bool $channelVerifications If true, it displays a channel verification section to verify channels.
     * @param string $successDeleteRedirectRoute
     * @param bool $localizeRoute
     */
    public function __construct(
        protected string $view = 'user/profile/settings',
        protected null|string $menu = 'main',
        protected string $menuLabel = 'Profile Settings',
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
            $uri = $this->localizeRoute ? '{?locale}/{profile}' : 'profile';
            $route->get($uri.'/settings', [$this, 'edit'])->name('profile.settings.edit');
            $route->patch($uri.'/settings', [$this, 'update'])->name('profile.settings.update');
            
        })->middleware(...$this->configureMiddlewares($app));

        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route, 'profile');
        }
        
        $this->configureRoutes($router, $app);
        
        // Menus:
        if ($this->menu) {
            $app->on(MenusInterface::class, function(MenusInterface $menus) use ($router, $auth) {
                if ($this->canViewMenu($auth)) {
                    $menus->menu($this->menu)
                        ->link($router->url('profile.settings.edit'), trans($this->menuLabel))
                        ->parent($this->menuParent)
                        ->id('profile.settings.edit')
                        ->icon('settings');
                }
            });
        }
    }
    
    /**
     * Display the user's profile settings form.
     *
     * @param ServerRequestInterface $request
     * @param ResponserInterface $responser
     * @param LanguagesInterface $languages
     * @param AvailableChannelsInterface $channels
     * @return ResponseInterface
     */
    public function edit(
        ServerRequestInterface $request,
        ResponserInterface $responser,
        LanguagesInterface $languages,
        AvailableChannelsInterface $channels,
    ): ResponseInterface {
        $user = $request->getAttribute(UserInterface::class);
        $this->isAuthorized($user);

        return $responser->render($this->view, [
            'user' => $user,
            'languages' => $languages,
            'channels' => $this->configureAvailableChannels($channels, $user),
        ]);
    }
    
    /**
     * Update the user's profile settings.
     *
     * @param ValidationRequest $request
     * @param ResponserInterface $responser
     * @param UserRepositoryInterface $userRepository
     * @param RouterInterface $router
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function update(
        ValidationRequest $request,
        ResponserInterface $responser,
        UserRepositoryInterface $userRepository,
        RouterInterface $router,
        null|EventDispatcherInterface $eventDispatcher = null,
    ): ResponseInterface {
        $user = $request->requester()->request()->getAttribute(UserInterface::class);
        $this->isAuthorized($user);
        
        // request validation:
        $validation = $request->validate(
            rules: $this->validationRules($user),
            redirectRouteName: 'profile.settings.edit',
            // You may specify an error message flashed to the user.
            errorMessage: 'The profile settings could not be updated.',
        );

        // update user:
        $updatedUser = $userRepository->updateWithAddress(
            id: $user->id(),
            user: $validation->valid()->all(),
            address: $validation->valid()->get('address', []),
        );
        
        // dispatch event:
        $eventDispatcher?->dispatch(new Event\UpdatedProfile(user: $updatedUser, oldUser: $user));
        
        // create and return response:
        return $responser->redirect($router->url('profile.settings.edit'));
    }
    
    /**
     * Configure the available channels.
     *
     * @param AvailableChannelsInterface $channels
     * @param UserInterface $user
     * @return AvailableChannelsInterface
     */
    protected function configureAvailableChannels(
        AvailableChannelsInterface $channels,
        UserInterface $user,
    ): AvailableChannelsInterface {
        return $channels
            ->only(['mail', 'sms', 'storage'])
            ->withTitle('storage', 'Account')
            ->sortByTitle();
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
    
    /**
     * Configure routes
     *
     * @param RouterInterface $router
     * @param AppInterface $app
     * @return void
     */
    protected function configureRoutes(RouterInterface $router, AppInterface $app): void
    {
        // $router->getRoute(name: 'profile.settings.edit')->middleware();
        // $router->getRoute(name: 'profile.settings.update')->middleware();
    }
    
    /**
     * Returns the validation rules for updating the user's profile settings.
     *
     * @param UserInterface $user
     * @return array
     */
    protected function validationRules(UserInterface $user): array
    {
        return [
            'locale' => [
                'required|string',
                new Passes(
                    passes: function(mixed $value, LanguagesInterface $languages): bool {
                        return in_array($value, $languages->column('locale')) ? true : false;
                    },
                ),
            ],
            'newsletter' => 'bool',
            'settings.preferred_notification_channels' => [
                new Passes(
                    passes: function(
                        mixed $value,
                        ValidatorInterface $validator,
                        AvailableChannelsInterface $channels
                    ) use ($user): bool {
                        $channels = $this->configureAvailableChannels($channels, $user);
                        return $validator->validating(
                            value: $value,
                            rules: [
                                ['eachIn', $channels->names()],
                            ],
                        )->isValid();
                    },
                ),
            ],
        ];
    }
    
    /**
     * Determines if the user is authorized to access the profile settings.
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
     * Determines if the user is authorized to view the profile settings menu.
     *
     * @param AuthInterface $auth
     * @return bool Returns true if authorized, otherwise false.
     */
    protected function canViewMenu(AuthInterface $auth): bool
    {
        return $auth->hasAuthenticated();
    }
}