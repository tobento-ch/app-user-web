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
use Tobento\App\RateLimiter\Middleware\RateLimitRequests;
use Tobento\App\RateLimiter\Symfony\Registry\SlidingWindow;
use Tobento\App\Spam\Middleware\ProtectAgainstSpam;
use Tobento\App\User\UserInterface;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\App\User\PasswordHasherInterface;
use Tobento\App\User\Authentication\AuthInterface;
use Tobento\App\User\Middleware\Unauthenticated;
use Tobento\App\User\Web\Event;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\Service\Acl\AclInterface;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Routing\RouteGroupInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Menu\MenusInterface;
use Tobento\App\Validation\Http\ValidationRequest;
use Tobento\Service\Validation\ValidatorInterface;
use Tobento\Service\Validation\ValidationInterface;
use Tobento\Service\Validation\Rule\Passes;
use Tobento\App\Validation\Exception\ValidationException;
use Tobento\Service\Language\LanguagesInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Closure;
use function Tobento\App\Translation\{trans};

/**
 * Register
 */
class Register
{
    /**
     * Create a new Register.
     *
     * @param string $view
     * @param null|string $menu The menu name or null if no menu.
     * @param string $menuLabel The menu label.
     * @param null|string $menuParent The menu parent or null if none.
     * @param string $authenticatedMessage
     * @param null|string $authenticatedRedirectRoute
     * @param string $roleKey The default role key for the user.
     * @param string $successRedirectRoute
     * @param bool $newsletter
     * @param null|string|Closure $termsRoute General terms and conditions route. If set it must be confirmed.
     * @param bool $localizeRoute
     */
    public function __construct(
        protected string $view = 'user/register',
        protected null|string $menu = 'header',
        protected string $menuLabel = 'Register',
        protected null|string $menuParent = null,
        protected string $authenticatedMessage = 'You are logged in!',
        protected null|string $authenticatedRedirectRoute = null,
        protected string $roleKey = 'registered',
        protected string $successRedirectRoute = 'home',
        protected bool $newsletter = false,
        protected null|string|Closure $termsRoute = null,
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
            $uri = $this->localizeRoute ? '{?locale}/{register}' : 'register';
            $route->get($uri, [$this, 'show'])->name('register');
            $route->post($uri, [$this, 'store'])->name('register.store');
        })->middleware(...$this->configureMiddlewares($app));

        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route, 'register');
        }
        
        $this->configureRoutes($router, $app);
        
        // Menus:
        if ($this->menu) {
            $app->on(MenusInterface::class, function(MenusInterface $menus) use ($router, $auth) {
                if (!$auth->hasAuthenticated()) {
                    $menus->menu($this->menu)
                        ->link($router->url('register'), trans($this->menuLabel))
                        ->parent($this->menuParent)
                        ->id('register')
                        ->icon('register');
                }
            });
        }
    }
    
    /**
     * Display the user's registration form.
     *
     * @param ResponserInterface $responser
     * @param RouterInterface $router
     * @return ResponseInterface
     */
    public function show(
        ResponserInterface $responser,
        RouterInterface $router,
    ): ResponseInterface {

        if ($this->termsRoute instanceof Closure) {
            $termsUrl = call_user_func_array($this->termsRoute, [$router]);
        } elseif (is_string($this->termsRoute)) {
            $termsUrl = (string)$router->url($this->termsRoute);
        } else {
            $termsUrl = '#';
        }
        
        return $responser->render(
            view: $this->view,
            data: [
                'newsletter' => $this->newsletter,
                'terms' => !is_null($this->termsRoute) ? true : false,
                'termsUrl' => $termsUrl,
            ]
        );
    }
    
    /**
     * Handle an incoming registration request.
     *
     * @param ValidationRequest $request
     * @param ResponserInterface $responser
     * @param AclInterface $acl
     * @param UserRepositoryInterface $userRepository
     * @param PasswordHasherInterface $passwordHasher
     * @param LanguagesInterface $languages
     * @param RouterInterface $router
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function store(
        ValidationRequest $request,
        ResponserInterface $responser,
        AclInterface $acl,
        UserRepositoryInterface $userRepository,
        PasswordHasherInterface $passwordHasher,
        LanguagesInterface $languages,
        RouterInterface $router,
        null|EventDispatcherInterface $eventDispatcher = null,
    ): ResponseInterface {
        // request validation:
        $validation = $request->validate(
            rules: $this->validationRules(),
            throwExceptionOnFailure: false,
        );
        
        if (! $validation->isValid()) {            
            $exception = new ValidationException(
                validation: $validation,
                redirectUri: (string)$router->url('register'),
                // You may specify an error message flashed to the user.
                // message: 'You have some errors check out the fields for its error message',
            );

            $eventDispatcher?->dispatch(new Event\RegisterFailed(exception: $exception));
            
            throw $exception;
        }
        
        // password hashing:
        $hashedPassword = $passwordHasher->hash(plainPassword: $validation->valid()->get('password'));
        $validation->valid()->set('password', $hashedPassword);
        
        // role assignment:
        $roleKey = $this->determineRoleKey($acl, $validation);
        $validation->valid()->set('role_key', $roleKey);
        
        // locale assignment:
        $validation->valid()->set('locale', $languages->current()->locale());
        
        // create user:
        $createdUser = $userRepository->createWithAddress(
            user: $validation->valid()->except(['password_confirmation'])->all(),
            address: $validation->valid()->get('address', []),
        );

        // dispatch event:
        $eventDispatcher?->dispatch(new Event\Registered(user: $createdUser));
        
        // create and return response:
        return $responser->redirect($router->url($this->configureSuccessRedirectRoute($createdUser)));
    }

    /**
     * Configure the success redirect route.
     *
     * @param UserInterface $user
     * @return string
     */
    protected function configureSuccessRedirectRoute(UserInterface $user): string
    {
        return $this->successRedirectRoute;
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
            // The Unauthenticated::class middleware protects routes from authenticated users:
            [
                Unauthenticated::class,

                // you may specify a custom message to show to the user:
                'message' => $this->authenticatedMessage,

                // you may specify a message level:
                //'messageLevel' => 'notice',

                // you may specify a route name for redirection:
                'redirectRoute' => $this->authenticatedRedirectRoute,
            ],
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
        $router->getRoute(name: 'register.store')->middleware([
            RateLimitRequests::class,
            'registry' => new SlidingWindow(limit: 10, interval: '5 Minutes', id: 'register'),
            'redirectRoute' => 'register',
            'message' => 'Too many attempts. Please retry after :seconds seconds.',
            //'messageLevel' => 'error',
        ], [
            ProtectAgainstSpam::class,
            'detector' => 'register',
        ]);
    }

    /**
     * Returns the role key verified based on the user type.
     *
     * @param AclInterface $acl
     * @param ValidationInterface $validation
     * @return string The verified role key.
     */
    protected function determineRoleKey(AclInterface $acl, ValidationInterface $validation): string
    {
        return match ($validation->valid()->get('user_type', '')) {
            'business' => $acl->hasRole('business') ? 'business' : $this->roleKey,
            default => $this->roleKey,
        };
    }
    
    /**
     * Returns the validation rules.
     *
     * @return array
     */
    protected function validationRules(): array
    {
        $rules = [
            'user_type' => 'string',
            'address.name' => 'required|string',
            'email' => [
                'required_without:smartphone',
                'email',
                new Passes(
                    passes: function(mixed $value, UserRepositoryInterface $repo): bool {
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
                    passes: function(mixed $value, UserRepositoryInterface $repo): bool {
                        return is_null($repo->findByIdentity(smartphone: $value)) ? true : false;
                    },
                    errorMessage: 'Smartphone exists already.',
                ),
            ],
            'password' => [
                'required|string|minLen:8',
                ['same:password_confirmation', 'error' => 'The password confirmation does not match.'],
            ],
            'password_confirmation' => 'required|string',
            'newsletter' => 'bool',
        ];
        
        if (!is_null($this->termsRoute)) {
            $rules['terms'] = 'required';
        }
        
        return $rules;
    }
}