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
use Tobento\App\User\PasswordHasherInterface;
use Tobento\App\User\Web\Event;
use Tobento\App\User\Exception\AuthorizationException;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\App\Notifier\AvailableChannelsInterface;
use Tobento\App\Validation\Http\ValidationRequest;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Routing\RouteGroupInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Menu\MenusInterface;
use Tobento\Service\Validation\Rule\Passes;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use function Tobento\App\Translation\{trans};

/**
 * Profile
 */
class Profile
{
    /**
     * Create a new Profile.
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
        protected string $view = 'user/profile/edit',
        protected null|string $menu = 'main',
        protected string $menuLabel = 'Profile',
        protected null|string $menuParent = null,
        protected string $unauthenticatedMessage = 'You have insufficient rights to access the requested resource!',
        protected null|string $unauthenticatedRedirectRoute = null,
        protected bool $channelVerifications = true,
        protected string $successDeleteRedirectRoute = 'home',
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
            $route->get($uri, [$this, 'edit'])->name('profile.edit');
            $route->patch($uri, [$this, 'update'])->name('profile.update');
            $route->delete($uri, [$this, 'delete'])->name('profile.delete');
            $route->patch($uri.'/password', [$this, 'passwordUpdate'])->name('profile.password.update');
            
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
                        ->link($router->url('profile.edit'), trans($this->menuLabel))
                        ->parent($this->menuParent)
                        ->id('profile.edit')
                        ->icon('user-edit');
                }
            });
        }
    }
    
    /**
     * Display the user's profile form.
     *
     * @param ServerRequestInterface $request
     * @param ResponserInterface $responser
     * @param AvailableChannelsInterface $channels
     * @return ResponseInterface
     */
    public function edit(
        ServerRequestInterface $request,
        ResponserInterface $responser,
        AvailableChannelsInterface $channels,
    ): ResponseInterface {
        $user = $request->getAttribute(UserInterface::class);
        $this->isAuthorized($user);

        return $responser->render($this->view, [
            'user' => $user,
            'channels' => $this->configureAvailableChannels($channels, $user),
        ]);
    }
    
    /**
     * Update the user's profile information.
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
            rules: $this->validationUpdateRules($user),
            // You may specify an error message flashed to the user.
            errorMessage: 'The profile information could not be updated.',
        );
        
        // update user:
        $updatedUser = $userRepository->updateWithAddress(
            id: $user->id(),
            user: $validation->valid()->all(),
            address: $validation->valid()->get('address', []),
        );
        
        // update verified channels:
        $updatedUser = $this->updateVerifiedChannels($userRepository, $user, $updatedUser);
        
        // dispatch event:
        $eventDispatcher?->dispatch(new Event\UpdatedProfile(user: $updatedUser, oldUser: $user));
        
        // create and return response:
        $responser->messages()->add(level: 'success', message: 'Profile updated successfully.');
        return $responser->redirect($router->url('profile.edit'));
    }
    
    /**
     * Update the user's password.
     *
     * @param ValidationRequest $request
     * @param ResponserInterface $responser
     * @param UserRepositoryInterface $userRepository
     * @param RouterInterface $router
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function passwordUpdate(
        ValidationRequest $request,
        ResponserInterface $responser,
        UserRepositoryInterface $userRepository,
        PasswordHasherInterface $passwordHasher,
        RouterInterface $router,
        null|EventDispatcherInterface $eventDispatcher = null,
    ): ResponseInterface {
        $user = $request->requester()->request()->getAttribute(UserInterface::class);
        $this->isAuthorized($user);
        
        // request validation:
        $validation = $request->validate(
            rules: $this->validationUpdatePasswordRules($user),
            // You may specify an error message flashed to the user.
            errorMessage: 'The password could not be updated.',
        );
        
        // password hashing:
        $hashedPassword = $passwordHasher->hash(plainPassword: $validation->valid()->get('password'));
        $validation->valid()->set('password', $hashedPassword);
        
        // update user:
        $updatedUser = $userRepository->updateById(
            id: $user->id(),
            attributes: $validation->valid()->all(),
        );
        
        // dispatch event:
        $eventDispatcher?->dispatch(new Event\UpdatedProfile(user: $updatedUser, oldUser: $user));
        
        // create and return response:
        $responser->messages()->add(level: 'success', message: 'Password updated successfully.');
        return $responser->redirect($router->url('profile.edit'));
    }
    
    /**
     * Delete the user's account.
     *
     * @param ValidationRequest $request
     * @param ResponserInterface $responser
     * @param AuthInterface $auth
     * @param UserRepositoryInterface $userRepository
     * @param RouterInterface $router
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function delete(
        ValidationRequest $request,
        ResponserInterface $responser,
        AuthInterface $auth,
        UserRepositoryInterface $userRepository,
        RouterInterface $router,
        null|EventDispatcherInterface $eventDispatcher = null,
    ): ResponseInterface {
        $user = $request->requester()->request()->getAttribute(UserInterface::class);
        $this->isAuthorized($user);
        
        // request validation:
        $request->validate(
            rules: $this->validationDeleteRules($user),
            // You may specify an error message flashed to the user.
            errorMessage: 'The account could not be deleted.',
        );
        
        // auth:
        $auth->close();
        
        if ($auth->getUnauthenticated()) {
            $eventDispatcher?->dispatch(new Event\Logout(
                unauthenticated: $auth->getUnauthenticated(),
            ));
        }
        
        // delete user with addresses:
        $userRepository->deleteWithAddresses(id: $user->id());
        
        // dispatch event:
        $eventDispatcher?->dispatch(new Event\DeletedAccount(user: $user));
        
        // create and return response:
        $responser->messages()->add(level: 'success', message: 'Account deleted successfully.');
        return $responser->redirect($router->url($this->successDeleteRedirectRoute));
    }

    /**
     * Determines if the user is authorized to access the profile.
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
     * Determines if the user is authorized to view the profile menu.
     *
     * @param AuthInterface $auth
     * @return bool Returns true if authorized, otherwise false.
     */
    protected function canViewMenu(AuthInterface $auth): bool
    {
        return $auth->hasAuthenticated();
        
        //return $auth->getAuthenticated()?->user()?->can('profile.edit');
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
        // $router->getRoute(name: 'profile.edit')->middleware();
        // $router->getRoute(name: 'profile.update')->middleware();
        // $router->getRoute(name: 'profile.delete')->middleware();
        // $router->getRoute(name: 'profile.password.update')->middleware();
    }
    
    /**
     * Configure the available verification channels to display.
     *
     * @param AvailableChannelsInterface $channels
     * @param UserInterface $user
     * @return AvailableChannelsInterface
     */
    protected function configureAvailableChannels(
        AvailableChannelsInterface $channels,
        UserInterface $user,
    ): AvailableChannelsInterface {
        if (! $this->channelVerifications) {
            // do not display any at all:
            return $channels->only([]);
        }
        
        return $channels->only(['mail', 'sms']);
    }
    
    /**
     * Returns the validation rules for updating the user's profile information.
     *
     * @param UserInterface $user
     * @return array
     */
    protected function validationUpdateRules(UserInterface $user): array
    {
        return [
            'address.name' => 'required|string',
            'email' => [
                'required_without:smartphone',
                'email',
                new Passes(
                    passes: function(string $value, UserRepositoryInterface $repo) use ($user): bool {
                        
                        if ($user->email() === $value) {
                            return true;
                        }
                        
                        return is_null($repo->findByIdentity(email: $value)) ? true : false;
                    },
                    errorMessage: 'E-mail exists already.',
                ),
            ],
            'smartphone' => [
                'required_without:email',
                'digit',
                'minLen:8',
                new Passes(
                    passes: function(string $value, UserRepositoryInterface $repo) use ($user): bool {
                        
                        if ($user->smartphone() === $value) {
                            return true;
                        }
                        
                        return is_null($repo->findByIdentity(smartphone: $value)) ? true : false;
                    },
                    errorMessage: 'Smartphone exists already.',
                ),
            ],
        ];
    }
    
    /**
     * Returns the validation rules for updating the user's password.
     *
     * @param UserInterface $user
     * @return array
     */
    protected function validationUpdatePasswordRules(UserInterface $user): array
    {
        return [
            'current_password' => [
                'required|string',
                new Passes(
                    passes: function(string $value, PasswordHasherInterface $hasher) use ($user): bool {
                        return $hasher->verify(
                            hashedPassword: $user->password(),
                            plainPassword: $value
                        );
                    },
                    errorMessage: 'Invalid password.',
                ),
            ],
            'password' => [
                'required|string|minLen:8',
                ['same:password_confirmation', 'error' => 'The password confirmation does not match.'],
            ],
            'password_confirmation' => 'required|string',
        ];
    }
    
    /**
     * Returns the validation rules for deleting the user's account.
     *
     * @param UserInterface $user
     * @return array
     */
    protected function validationDeleteRules(UserInterface $user): array
    {
        return [
            'delete_password' => [
                'required|string',
                new Passes(
                    passes: function(string $value, PasswordHasherInterface $hasher) use ($user): bool {
                        return $hasher->verify(
                            hashedPassword: $user->password(),
                            plainPassword: $value
                        );
                    },
                    errorMessage: 'Invalid password.',
                ),
            ],
        ];
    }
    
    /**
     * Update the verified channels if user has changed the email or smartphone e.g.
     *
     * @param UserRepositoryInterface $userRepository
     * @param UserInterface $oldUser
     * @param UserInterface $newUser
     * @return UserInterface The updated user
     */
    protected function updateVerifiedChannels(
        UserRepositoryInterface $userRepository,
        UserInterface $oldUser,
        UserInterface $newUser,
    ) {
        if ($oldUser->email() !== $newUser->email()) {
            $newUser = $userRepository->removeVerified(id: $newUser->id(), channel: 'email');
        }
        
        if ($oldUser->smartphone() !== $newUser->smartphone()) {
            $newUser = $userRepository->removeVerified(id: $newUser->id(), channel: 'smartphone');
        }
        
        return $newUser;
    }
}