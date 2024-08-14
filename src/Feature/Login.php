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
use Tobento\App\User\Authentication\AuthInterface;
use Tobento\App\User\Authentication\Authenticated;
use Tobento\App\User\Authentication\Token\TokenStorageInterface;
use Tobento\App\User\Authenticator\IdentityAuthenticator;
use Tobento\App\User\Authenticator\UserVerifierInterface;
use Tobento\App\User\Middleware\Unauthenticated;
use Tobento\App\User\Web\Event;
use Tobento\App\User\Exception\AuthenticationException;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\App\RateLimiter\RateLimiterCreatorInterface;
use Tobento\App\RateLimiter\RegistryInterface;
use Tobento\App\RateLimiter\Symfony\Registry\SlidingWindow;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Routing\RouteGroupInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Menu\MenusInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Clock\ClockInterface;
use DateTimeInterface;
use DateTimeImmutable;
use DateInterval;
use Closure;
use function Tobento\App\Translation\{trans};

/**
 * Login
 */
class Login
{
    /**
     * Create a new Login.
     *
     * @param string $view
     * @param null|string $menu The menu name or null if none.
     * @param string $menuLabel The menu label.
     * @param null|string $menuParent The menu parent or null if none.
     * @param null|RegistryInterface $rateLimiter
     * @param array $identifyBy
     * @param null|Closure $userVerifier
     * @param int|DateInterval $expiresAfter
     *   The period of time from the present after which the item MUST be considered
     *   expired. An integer parameter is understood to be the time in seconds until expiration.
     * @param null|int|DateInterval $remember Null if no remember at all, otherwise
     *   the period of time from the present after which the item MUST be considered
     *   expired. An integer parameter is understood to be the time in seconds until expiration.
     * @param string $authenticatedMessage
     * @param null|string $authenticatedRedirectRoute
     * @param string $failedMessage
     * @param string $successRoute
     * @param null|string $successMessage
     * @param null|string $forgotPasswordRoute
     * @param string $twoFactorRoute
     * @param bool $localizeRoute
     */
    public function __construct(
        protected string $view = 'user/login',
        protected null|string $menu = 'header',
        protected string $menuLabel = 'Log in',
        protected null|string $menuParent = null,
        protected null|RegistryInterface $rateLimiter = null,
        protected array $identifyBy = ['email', 'username', 'smartphone', 'password'],
        protected null|Closure $userVerifier = null,
        protected int|DateInterval $expiresAfter = 1500,
        protected null|int|DateInterval $remember = null,
        protected string $authenticatedMessage = 'You are logged in!',
        protected null|string $authenticatedRedirectRoute = null,
        protected string $failedMessage = 'Invalid user or password.',
        protected string $successRoute = 'home',
        protected null|string $successMessage = 'Welcome back :greeting.',
        protected null|string $forgotPasswordRoute = null,
        protected string $twoFactorRoute = 'twofactor.code.show',
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
            $uri = $this->localizeRoute ? '{?locale}/{login}' : 'login';
            $route->get($uri, [$this, 'show'])->name('login');
            $route->post($uri, [$this, 'store'])->name('login.store');
        })->middleware(...$this->configureMiddlewares($app));
        
        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route, 'login');
        }
        
        $this->configureRoutes($router, $app);
        
        // Menus:
        if ($this->menu) {
            $app->on(MenusInterface::class, function(MenusInterface $menus) use ($router, $auth) {
                if (!$auth->hasAuthenticated()) {
                    $menus->menu($this->menu)
                        ->link($router->url('login'), trans($this->menuLabel))
                        ->parent($this->menuParent)
                        ->id('login')
                        ->icon('login');
                }
            });
        }
    }
    
    /**
     * Display the user's login form.
     *
     * @param ResponserInterface $responser
     * @return ResponseInterface
     */
    public function show(
        ResponserInterface $responser,
    ): ResponseInterface {
        return $responser->render(
            view: $this->view,
            data: [
                'remember' => $this->remember,
                'forgotPasswordRoute' => $this->forgotPasswordRoute
            ]
        );
    }
    
    /**
     * Handle an incoming login request.
     *
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param RateLimiterCreatorInterface $rateLimiterCreator
     * @param AuthInterface $auth,
     * @param IdentityAuthenticator $authenticator
     * @param RouterInterface $router
     * @param ClockInterface $clock
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function store(
        RequesterInterface $requester,
        ResponserInterface $responser,
        RateLimiterCreatorInterface $rateLimiterCreator,
        AuthInterface $auth,
        IdentityAuthenticator $authenticator,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        ClockInterface $clock,
        null|EventDispatcherInterface $eventDispatcher,
    ): ResponseInterface {
        // Rate Limiting:
        $limiter = $rateLimiterCreator->createFromRegistry(
            id: 'login:'.$requester->input()->get('user', ''),
            registry: $this->rateLimiter ?: new SlidingWindow(limit: 10, interval: '5 Minutes'),
        );
        
        if ($limiter->hit()->isAttemptsExceeded()) {
            $responser->messages()->add(
                level: 'error',
                message: 'Too many login attempts. Please retry after :seconds seconds.',
                parameters: [':seconds' => $limiter->availableIn()],
                key: 'user',
            );
            
            $eventDispatcher?->dispatch(
                new Event\LoginAttemptsExceeded(rateLimiter: $limiter, request: $requester->request())
            );
            
            return $responser->redirect($router->url('login'));
        }
        
        // You may specify the identity attributes to be checked.
        // At least one attribute is required.
        $authenticator->identifyBy($this->identifyBy);
        
        // you may verify user attributes:
        if (!is_null($userVerifier = $this->getUserVerifier())) {
            $authenticator = $authenticator->withUserVerifier($userVerifier);
        }
        
        // You may change the request input names:
        $authenticator->userInputName('user');
        $authenticator->passwordInputName('password');
        
        // You may change the request method,
        // only 'POST' (default), 'GET' and 'PUT':
        $authenticator->requestMethod('POST');
        
        // try to authenticate user:
        try {
            $user = $authenticator->authenticate($requester->request());
        } catch (AuthenticationException $e) {
            $eventDispatcher?->dispatch(new Event\LoginFailed(exception: $e, authenticator: $authenticator));
            
            $responser->messages()->add(level: 'error', message: $this->failedMessage);
            
            return $responser
                ->withInput($requester->input()->except(['password'])->all())
                ->redirect($router->url('login'));
        }
        
        // User authenticated successfully.
        
        $wantsToBeRemembered = $requester->input()->has('remember');
        
        if (! $wantsToBeRemembered) {
            $this->remember = null;
        }
        
        $isTwoFactor = $this->isTwoFactorRequiredFor(user: $user);

        // create token and start auth:
        $token = $tokenStorage->createToken(
            // Set the payload:
            payload: $this->configureTokenPayload(user: $user, request: $requester->request()),

            // Set the name of which the user was authenticated via:
            authenticatedVia: $isTwoFactor ? 'loginform-twofactor' : 'loginform',
            
            // Set the name of which the user was authenticated by (authenticator name) or null if none:
            authenticatedBy: $authenticator::class,
            
            // Set the point in time the token has been issued or null (now):
            issuedAt: $clock->now(),
            
            // Set the point in time after which the token MUST be considered expired or null:
            // The time might depend on the token storage e.g. session expiration!
            expiresAt: $this->getExpiresAt($clock->now(), $user),
        );
        
        $authenticated = new Authenticated(token: $token, user: $user);
        
        $eventDispatcher?->dispatch(new Event\Login(
            authenticated: $authenticated,
            remember: is_null($this->remember) ? false : true,
        ));
        
        $auth->start($authenticated);
        
        $limiter->reset();
        
        if ($isTwoFactor) {
            return $responser->redirect($router->url($this->twoFactorRoute));
        }
        
        if ($this->successMessage) {
            $responser->messages()->add(
                level: 'success',
                message: $this->successMessage,
                parameters: [':greeting' => $authenticated->user()->greeting()]
            );
        }
            
        return $responser->redirect($router->url($this->successRoute));
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
                
                // allow access only to user authenticated via:
                'via' => 'remembered',

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
        // $router->getRoute(name: 'login')->middleware();
        // $router->getRoute(name: 'login.store')->middleware();
    }
    
    /**
     * Configure token payload.
     *
     * @param UserInterface $user
     * @param ServerRequestInterface $request
     * @return array The configured token payload.
     */
    protected function configureTokenPayload(
        UserInterface $user,
        ServerRequestInterface $request,
    ): array {
        return [
            'userId' => $user->id(),
            'passwordHash' => $user->password(),            
            'remoteAddress' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
            'userAgent' => $request->getServerParams()['HTTP_USER_AGENT'] ?? null,
            
            // used for RememberToken middleware e.g.
            'remember' => is_null($this->remember) ? false : true,
        ];
    }
    
    /**
     * Returns true if the user is required to perform two factor authentication, otherwise false.
     *
     * @param UserInterface $user
     * @return bool
     */
    protected function isTwoFactorRequiredFor(UserInterface $user): bool
    {
        return false;
    }
    
    /**
     * Returns the user verifier used for the authenticator.
     *
     * @return null|UserVerifierInterface
     */
    protected function getUserVerifier(): null|UserVerifierInterface
    {
        if (!is_null($this->userVerifier)) {
            return call_user_func($this->userVerifier);
        }
        
        return null;
    }
    
    /**
     * Returns the point in time after which the token MUST be considered expired.
     *
     * @param DateTimeImmutable $now
     * @param UserInterface $user
     * @return DateTimeInterface
     */
    protected function getExpiresAt(DateTimeImmutable $now, UserInterface $user): DateTimeInterface
    {
        $expiresAfter = $this->remember ?: $this->expiresAfter;
        
        if (is_int($expiresAfter)) {
            $modified = $now->modify('+'.$expiresAfter.' seconds');
            return $modified === false ? $now : $modified;
        }
        
        return $now->add($expiresAfter);
    }
}