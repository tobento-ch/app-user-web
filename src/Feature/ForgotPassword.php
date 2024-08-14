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
use Tobento\App\User\UserInterface;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\App\User\PasswordHasherInterface;
use Tobento\App\User\Authenticator\IdentityAuthenticator;
use Tobento\App\User\Authenticator\UserVerifierInterface;
use Tobento\App\User\Middleware\Unauthenticated;
use Tobento\App\User\Web\TokenVerificatorInterface;
use Tobento\App\User\Web\TokenInterface;
use Tobento\App\User\Web\Notification;
use Tobento\App\User\Web\Event;
use Tobento\App\User\Exception\AuthenticationException;
use Tobento\App\User\Web\Exception\VerificationTokenException;
use Tobento\App\User\Web\Exception\VerificationTokenUserException;
use Tobento\App\Validation\Http\ValidationRequest;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Routing\RouteGroupInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Notifier\NotifierInterface;
use Tobento\Service\Notifier\NotificationInterface;
use Tobento\Service\Notifier\UserRecipient;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use DateInterval;
use Closure;

/**
 * ForgotPassword
 */
class ForgotPassword
{
    /**
     * Create a new ForgotPassword.
     *
     * @param string $viewIdentity
     * @param string $viewReset
     * @param array $identifyBy
     * @param null|Closure $userVerifier
     * @param int|DateInterval $tokenExpiresAfter
     * @param int $canReissueTokenAfter The seconds a new token can be reissued.
     * @param string $identityFailedMessage
     * @param string $authenticatedMessage
     * @param null|string $authenticatedRedirectRoute
     * @param string $successRedirectRoute The route to redirect after successful reset.
     * @param null|string $successMessage
     * @param bool $localizeRoute
     */
    public function __construct(
        protected string $viewIdentity = 'user/forgot-password/identity',
        protected string $viewReset = 'user/forgot-password/reset',
        protected array $identifyBy = ['email', 'username', 'smartphone'],
        protected null|Closure $userVerifier = null,
        protected int|DateInterval $tokenExpiresAfter = 300,
        protected int $canReissueTokenAfter = 60,
        protected string $identityFailedMessage = 'Invalid name or user.',
        protected string $authenticatedMessage = 'You are logged in!',
        protected null|string $authenticatedRedirectRoute = 'home',
        protected string $successRedirectRoute = 'home',
        protected null|string $successMessage = 'Your password has been reset!',
        protected bool $localizeRoute = false,
    ) {}
    
    /**
     * Boot the page.
     *
     * @param RouterInterface $router
     * @param AppInterface $app
     * @return void
     */
    public function __invoke(
        RouterInterface $router,
        AppInterface $app,
    ): void {
        // Routes:
        $route = $router->group('', function(RouteGroupInterface $route) {
            
            $uri = $this->localizeRoute ? '{?locale}/forgot-password' : 'forgot-password';
            
            // prove identity:
            $route->get($uri, [$this, 'identity'])->name('forgot-password.identity');
            $route->post($uri, [$this, 'identityVerify'])->name('forgot-password.identity.verify');
            
            // reset password:
            $route->get($uri.'/reset/{token}', [$this, 'reset'])->name('forgot-password.reset');
            $route->post($uri.'/reset', [$this, 'resetVerify'])->name('forgot-password.reset.verify');
            
        })->middleware(...$this->configureMiddlewares($app));
                
        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route);
        }
        
        $this->configureRoutes($router, $app);
    }
    
    /**
     * Display the user's identity form.
     *
     * @param ServerRequestInterface $request
     * @param ResponserInterface $responser
     * @return ResponseInterface
     */
    public function identity(
        ServerRequestInterface $request,
        ResponserInterface $responser,
    ): ResponseInterface {
        return $responser->render(view: $this->viewIdentity);
    }
    
    /**
     * Verify the user's identity.
     *
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param IdentityAuthenticator $authenticator
     * @param TokenVerificatorInterface $verificator
     * @param RouterInterface $router
     * @param NotifierInterface $notifier
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function identityVerify(
        RequesterInterface $requester,
        ResponserInterface $responser,
        IdentityAuthenticator $authenticator,
        TokenVerificatorInterface $verificator,
        RouterInterface $router,
        NotifierInterface $notifier,
        null|EventDispatcherInterface $eventDispatcher,
    ): ResponseInterface {
        // You may specify the identity attributes to be checked.
        // At least one attribute is required.
        $authenticator->identifyBy($this->identifyBy);
        
        // you may verify user attributes:
        if (!is_null($userVerifier = $this->getUserVerifier())) {
            $authenticator = $authenticator->withUserVerifier($userVerifier);
        }

        // You may change the request input names:
        $authenticator->userInputName('user');
        
        // You may change the request method,
        // only 'POST' (default), 'GET' and 'PUT':
        $authenticator->requestMethod('POST');

        // try to authenticate user:
        try {
            $user = $authenticator->authenticate($requester->request());
            $this->verifyUserAttributes($user, $requester);
        } catch (AuthenticationException $e) {
            $eventDispatcher?->dispatch(new Event\PasswordResetFailed(
                step: 'identity',
                exception: $e,
                authenticator: $authenticator,
            ));
            
            $responser->messages()->add(level: 'error', message: $this->identityFailedMessage);
            return $responser
                ->withInput($requester->input()->all())
                ->redirect($router->url('forgot-password.identity'));
        }
        
        $payload = $this->configureTokenPayload($user, $requester);
        
        // check token (throttling):
        if ($token = $verificator->findToken(type: 'password:reset', payload: $payload)) {
            $availableInSeconds = $verificator->tokenIssuedLessThan(token: $token, seconds: $this->canReissueTokenAfter);
            
            if ($availableInSeconds) {
                $responser->messages()->add(
                    level: 'error',
                    message: 'You have already requested a reset password link. Please check your E-Mails or SMS messages, or retry after :seconds seconds.',
                    parameters: [':seconds' => $availableInSeconds],
                );
                
                return $responser
                    ->withInput($requester->input()->all())
                    ->redirect($router->url('forgot-password.identity'));                
            }
        }
        
        // create new token:
        $token = $verificator->createToken(
            type: 'password:reset',
            payload: $payload,
            expiresAfter: $this->tokenExpiresAfter
        );
        
        // create and send notification with link:
        $this->sendLinkNotification(
            token: $token,
            user: $user,
            notifier: $notifier,
            router: $router,
        );
        
        // send notification:
        $responser->messages()->add(level: 'success', message: 'A password reset link has been sent. Check your E-Mails or SMS messages.');
        
        // response:
        return $responser->redirect($router->url('forgot-password.identity'));
    }
    
    /**
     * Display the user's reset form.
     *
     * @param string $token
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param UserRepositoryInterface $userRepository
     * @param TokenVerificatorInterface $verificator
     * @param RouterInterface $router
     * @return ResponseInterface
     */
    public function reset(
        string $token,
        RequesterInterface $requester,
        ResponserInterface $responser,
        UserRepositoryInterface $userRepository,
        TokenVerificatorInterface $verificator,
        RouterInterface $router,
    ): ResponseInterface {
        try {
            $token = $verificator->verifyToken(id: $token, type: 'password:reset');
        } catch (VerificationTokenException $e) {
            $responser->messages()->add(level: 'error', message: 'The token has been expired or is invalid.');
            return $responser->redirect($router->url('forgot-password.identity'));
        }
        
        return $responser->render(view: $this->viewReset, data: ['token' => $token]);
    }
    
    /**
     * Verify and reset the user's password.
     *
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param UserRepositoryInterface $userRepository
     * @param TokenVerificatorInterface $verificator
     * @param SessionInterface $session
     * @param RouterInterface $router
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function resetVerify(
        ValidationRequest $request,
        ResponserInterface $responser,
        UserRepositoryInterface $userRepository,
        PasswordHasherInterface $passwordHasher,
        TokenVerificatorInterface $verificator,
        RouterInterface $router,
        null|EventDispatcherInterface $eventDispatcher,
    ): ResponseInterface {
        // request validation:
        $validation = $request->validate(
            rules: $this->validationResetRules(),
            // You may specify an error message flashed to the user.
            //errorMessage: 'You have some errors check out the fields for its error message',
        );
        
        // verify token:
        try {
            $token = $verificator->verifyToken(id: $validation->valid()->get('token'), type: 'password:reset');
            $user = $userRepository->findById($token->userId());
            
            if (is_null($user)) {
                throw new VerificationTokenUserException(message: 'User not found', token: $token);
            }

            $this->verifyTokenPayload($token, $user, $request->requester());
        } catch (VerificationTokenUserException $e) {
            $eventDispatcher?->dispatch(new Event\PasswordResetFailed(step: 'reset', exception: $e));
            
            if (is_null($e->token())) {
                $responser->messages()->add(level: 'error', message: 'The token has been expired or is invalid.');
                return $responser->redirect($router->url('forgot-password.identity'));
            }
            
            $responser->messages()->add(level: 'error', message: 'Invalid user.', key: 'user');
            
            return $responser
                ->withInput($validation->valid()->all())
                ->redirect($router->url('forgot-password.reset', ['token' => $e->token()->id()]));
        } catch (VerificationTokenException $e) {
            $eventDispatcher?->dispatch(new Event\PasswordResetFailed(step: 'reset', exception: $e));
            
            $responser->messages()->add(level: 'error', message: 'The token has been expired or is invalid.');
            return $responser->redirect($router->url('forgot-password.identity'));
        }
                
        // update user:
        $updatedUser = $userRepository->updateById(
            id: $user->id(),
            attributes: [
                'password' => $passwordHasher->hash(plainPassword: $validation->valid()->get('password')),
            ],
        );
        
        // delete token:
        $verificator->deleteToken(id: $validation->valid()->get('token'), type: 'password:reset');
        
        // event:
        $eventDispatcher?->dispatch(new Event\PasswordReset(user: $updatedUser));
        
        // response:
        if ($this->successMessage) {
            $responser->messages()->add(level: 'success', message: $this->successMessage);
        }
        
        return $responser->redirect($router->url($this->successRedirectRoute));
    }
    
    /**
     * Verify the user attributes.
     *
     * @param UserInterface $user
     * @param RequesterInterface $requester
     * @return void
     * @throws AuthenticationException If user verification fails.
     */
    protected function verifyUserAttributes(UserInterface $user, RequesterInterface $requester): void
    {
        $userName = $user->address()->name();
        
        if (empty($userName) || $userName !== $requester->input()->get('address.name')) {
            throw new AuthenticationException('Invalid user attributes');
        }
    }
    
    /**
     * Configure token payload.
     *
     * @param UserInterface $user
     * @param RequesterInterface $requester
     * @return array The configured token payload.
     */
    protected function configureTokenPayload(
        UserInterface $user,
        RequesterInterface $requester,
    ): array {
        return [
            'userID' => $user->id(),
            'user' => $requester->input()->get('user'),
        ];
    }
    
    /**
     * Verify the token payload.
     *
     * @param TokenInterface $token
     * @param UserInterface $user
     * @param RequesterInterface $requester
     * @return void
     * @throws VerificationTokenException If verification fails.
     */
    protected function verifyTokenPayload(
        TokenInterface $token,
        UserInterface $user,
        RequesterInterface $requester
    ): void {        
        $userPayload = $token->payload()['user'] ?? '';
        
        if (empty($userPayload) || $userPayload !== $requester->input()->get('user')) {
            throw new VerificationTokenUserException(message: 'Invalid token user', token: $token);
        }
    }
    
    /**
     * Returns the validation rules for updating the user's password.
     *
     * @return array
     */
    protected function validationResetRules(): array
    {
        return [
            'token' => 'required|string',
            'user' => 'required|string|minLen:3',
            'password' => [
                'required|string|minLen:8',
                ['same:password_confirmation', 'error' => 'The password confirmation does not match.'],
            ],
            'password_confirmation' => 'required|string',
        ];
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
     * Send link notification.
     *
     * @param TokenInterface $token
     * @param UserInterface $user
     * @param NotifierInterface $notifier
     * @param RouterInterface $router
     * @return void
     */
    protected function sendLinkNotification(
        TokenInterface $token,
        UserInterface $user,
        NotifierInterface $notifier,
        RouterInterface $router,
    ): void {
        $notification = new Notification\ResetPassword(
            token: $token,
            url: (string)$router->url('forgot-password.reset', ['token' => $token->id()]),
        );
        
        // The receiver of the notification:
        $recipient = new UserRecipient(user: $user);

        // Send the notification to the recipient:
        $notifier->send($notification, $recipient);
    }
    
    /**
     * Configure middlewares for all routes.
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
     * Configure routes.
     *
     * @param RouterInterface $router
     * @param AppInterface $app
     * @return void
     */
    protected function configureRoutes(RouterInterface $router, AppInterface $app): void
    {
        $router->getRoute(name: 'forgot-password.identity.verify')->middleware([
            RateLimitRequests::class,
            'registry' => new SlidingWindow(limit: 10, interval: '5 Minutes', id: 'forgot-password'),
            'redirectRoute' => 'forgot-password.identity',
            'message' => 'Too many attempts. Please retry after :seconds seconds.',
            //'messageLevel' => 'error',
            //'messageKey' => 'user',
        ]);
    }
}