# App User Web

The User Web provides authentication features such as:

* login/logout
* two factor login
* remember me
* forgot password
* channel verification (email and smartphone)
* simple profile page where the user may update their profile data
* user notifications page
* multi-language support

## Table of Contents

- [Getting Started](#getting-started)
    - [Requirements](#requirements)
- [Documentation](#documentation)
    - [App](#app)
    - [User Web Boot](#user-web-boot)
        - [User Web Config](#user-web-config)
    - [Features](#features)
        - [Home Feature](#home-feature)
        - [Login Feature](#login-feature)
            - [Supporting Remember Me](#supporting-remember-me)
        - [Two-Factor Authentication Code Feature](#two-factor-authentication-code-feature)
            - [Determine When Two Factor Auth Is Required](#determine-when-two-factor-auth-is-required)
            - [User Permissions Strategies For Two Factor Auth](#user-permissions-strategies-for-two-factor-auth)
        - [Logout Feature](#logout-feature)
        - [Forgot Password Feature](#forgot-password-feature)
            - [Customize Reset Password Notification](#customize-reset-password-notification)
        - [Register Feature](#register-feature)
            - [Customize Registration Fields](#customize-registration-fields)
            - [Customize The Role For Registered Users](#customize-the-role-for-registered-users)
            - [Auto Login After Registration](#auto-login-after-registration)
            - [Account Verification](#account-verification)
            - [Account Verification For Specific User Roles Only](#account-verification-for-specific-user-roles-only)
            - [Terms and Conditions Agreement](#terms-and-conditions-agreement)
            - [Spam Protection For Registering](#spam-protection-for-registering)
        - [Notifications Feature](#notifications-feature)
            - [Creating And Sending Notifications](#creating-and-sending-notifications)
            - [Customize Unread Notifications Count](#customize-unread-notifications-count)
            - [Clear Read Notifications](#clear-read-notifications)
        - [Profile Feature](#profile-feature)
            - [Customize Information Fields](#customize-information-fields)
            - [Customize Available Verification Channels To Display](#customize-available-verification-channels-to-display)
        - [Profile Settings Feature](#profile-settings-feature)
            - [Customize Available Notification Channels](#customize-available-notification-channels)
            - [Customize Settings Fields](#customize-settings-fields)
        - [Verification Feature](#verification-feature)
            - [Protect Routes From Unverified User](#protect-routes-from-unverified-user)
            - [Customize Available Channels To Verify](#customize-available-channels-to-verify)
    - [Deleting Expired Tokens](#deleting-expired-tokens)
    - [Events](#events)
    - [Learn More](#learn-more)
        - [List Available Routes](#list-available-routes)
        - [Newsletter Subscription](#newsletter-subscription)
        - [Customize Verification Code Notification](#customize-verification-code-notification)
        - [Localization](#localization)
        - [Translations](#translations)
- [Credits](#credits)
___

# Getting Started

Add the latest version of the app user web project running this command.

```
composer require tobento/app-user-web
```

## Requirements

- PHP 8.0 or greater

# Documentation

## App

Check out the [**App Skeleton**](https://github.com/tobento-ch/app-skeleton) if you are using the skeleton.

You may also check out the [**App**](https://github.com/tobento-ch/app) to learn more about the app in general.

## User Web Boot

The user web boot does the following:

* installs and loads user_web config
* installs view and translation files

```php
use Tobento\App\AppFactory;

// Create the app
$app = (new AppFactory())->createApp();

// Add directories:
$app->dirs()
    ->dir(realpath(__DIR__.'/../'), 'root')
    ->dir(realpath(__DIR__.'/../app/'), 'app')
    ->dir($app->dir('app').'config', 'config', group: 'config')
    ->dir($app->dir('root').'public', 'public')
    ->dir($app->dir('root').'vendor', 'vendor');

// Adding boots
$app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);

// Run the app
$app->run();
```

### User Web Config

The configuration for the user web is located in the ```app/config/user_web.php``` file at the default App Skeleton config location.

## Features

Simply, configure any features you want to support in the ```app/config/user_web.php``` feature section.

### Home Feature

The Home Feature provides a simple home page. In case, you are not using this feature you need to adjust the "home" route in other features or add another route with the name ```home```.

**Config**

In the [config file](#user-web-config) you can configure the home feature:

```php
'features' => [
    new Feature\Home(),
    
    // Or:
    new Feature\Home(
        // The view to render:
        view: 'user/home',

        // A menu name to show the home link or null if none.
        menu: 'main',
        menuLabel: 'Home',
        // A menu parent name (e.g. 'user') or null if none.
        menuParent: null,

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

### Login Feature

The Login Feature provides a simple login page where a user can login by his email, smartphone or username and the password.

**Config**

In the [config file](#user-web-config) you can configure the login feature:

```php
use Tobento\App\RateLimiter\Symfony\Registry\SlidingWindow;

'features' => [
    new Feature\Login(),
    
    // Or:
    new Feature\Login(
        // The view to render:
        view: 'user/login',

        // A menu name to show the login link or null if none.
        menu: 'header',
        menuLabel: 'Log in',
        // A menu parent name (e.g. 'user') or null if none.
        menuParent: null,
        
        // Specify a rate limiter:
        rateLimiter: new SlidingWindow(limit: 10, interval: '5 Minutes'),
        // see: https://github.com/tobento-ch/app-rate-limiter#available-rate-limiter-registries

        // Specify the identity attributes to be checked on login.
        identifyBy: ['email', 'username', 'smartphone', 'password'],
        // You may set a user verifier(s), see: https://github.com/tobento-ch/app-user#user-verifier
        /*userVerifier: function() {
            return new \Tobento\App\User\Authenticator\UserRoleVerifier('editor', 'author');
        },*/

        // The period of time from the present after which the auth token MUST be considered expired.
        expiresAfter: 1500, // int|\DateInterval

        // If you want to support remember. If set and the user wants to be remembered,
        // this value replaces the expiresAfter parameter.
        remember: new \DateInterval('P6M'), // null|int|\DateInterval

        // The message and redirect route if a user is authenticated.
        authenticatedMessage: 'You are logged in!',
        authenticatedRedirectRoute: 'home', // or null (no redirection)

        // The message shown if a login attempt fails.
        failedMessage: 'Invalid user or password.',

        // The redirect route after a successful login.
        successRoute: 'home',
            
        // The message shown after a user successfully log in.
        successMessage: 'Welcome back :greeting.', // or null

        // If set, it shows the forgot password link. Make sure the Feature\ForgotPassword is set too.
        forgotPasswordRoute: 'forgot-password.identity', // or null
        
        // The two factor authentication route.
        twoFactorRoute: 'twofactor.code.show',

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

#### Supporting Remember Me

You may support remember me functionality by the following steps:

**1. Enable remember me**

In the config, specify a value for the ```remember``` parameter:

```php
'features' => [
    new Feature\Login(
        // If you want to support remember. If set and the user wants to be remembered,
        // this value replaces the expiresAfter parameter.
        remember: new \DateInterval('P6M'), //null|int|\DateInterval
    ),
],
```

The auth token will expire after 6 months unless the user logs out!

**2. Use Suitable Token Storage**

As remember me tokens are often long-lived, make sure you use the [Repository Storage](https://github.com/tobento-ch/app-user#repository-storage) to store tokens, which is configured as default.

**3. Add RememeredToken Middleware (optional)**

In the ```app/config/user.php``` add the ```RememberedToken::class``` middleware after the ```User::class``` middleware and specify the period of time from the present after which the token is considered as remembered.

```php
use Tobento\App\User;

'middlewares' => [
    // You may uncomment it and set it on each route individually
    // using the User\Middleware\AuthenticationWith::class!
    User\Middleware\Authentication::class,
    User\Middleware\User::class,

    [User\Web\Middleware\RememberedToken::class, 'isRememberedAfter' => 1500],
    
    // or with date interval:
    [User\Web\Middleware\RememberedToken::class, 'isRememberedAfter' => new \DateInterval('PT2H')],
],
```

After the token is considered as remembered, a new token will be created setting the parameter ```authenticatedVia``` as ```remembered```. In addition, on every request it will verify the token with the [token verifiers](https://github.com/tobento-ch/app-user#token-verifier) defined in the middleware such as checking the password hash.

Once the middleware is added, you may force users to re-authenticate before accessing certain resources if the token is considered as remembered by using the [Authenticated Middleware](https://github.com/tobento-ch/app-user#authenticated-middleware):

```php
use Tobento\App\User\Middleware\Authenticated;

$app->route('GET', 'account-info', function() {
    return 'account';
})->middleware([
    Authenticated::class,
    'exceptVia' => 'remembered',
    'redirectRoute' => 'login',
]);
```

### Two-Factor Authentication Code Feature

The Two-Factor Authentication Code Feature provides a simple way for two-factor authentication using verification codes.

**Config**

In the [config file](#user-web-config) you can configure the feature:

```php
'features' => [
    new Feature\TwoFactorAuthenticationCode(),
    
    // Or:
    new Feature\TwoFactorAuthenticationCode(
        // The view to render:
        view: 'user/twofactor-code',

        // The period of time from the present after which the verification code MUST be considered expired.
        codeExpiresAfter: 300, // int|\DateInterval

        // The seconds after a new code can be reissued.
        canReissueCodeAfter: 60,

        // The message and redirect route if a user is unauthenticated.
        unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
        unauthenticatedRedirectRoute: 'home', // or null (no redirection)

        // The redirect route after a successful code verification.
        successRoute: 'home',

        // The message shown after a successful code verification.
        successMessage: 'Welcome back :greeting.', // or null

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

#### Determine When Two Factor Auth Is Required

To enable two-factor authentication you will need to determine when two-factor authentication is required by extending the ```Login::class``` and customizing the ```isTwoFactorRequiredFor``` method:

```php
use Tobento\App\User\Web\Feature\Login;
use Tobento\App\User\UserInterface;

class CustomLoginFeature extends Login
{
    /**
     * Returns true if the user is required to perform two factor authentication, otherwise false.
     *
     * @param UserInterface $user
     * @return bool
     */
    protected function isTwoFactorRequiredFor(UserInterface $user): bool
    {
        // Your conditions here:
        if (in_array($user->getRoleKey(), ['business'])) {
            return true;
        }
        
        return false;
    }
}
```

In the ```config/user_web.php``` replace the default login feature with your customized:

```php
'features' => [
    new CustomLoginFeature(
        //...
    ),
],
```

Once this is set up, on successful login, any user with the role business will be redirected to the two-factor authentication page where he can confirm the sent code.

However, the user is not obliged to confirm the code, he could just leave the two-factor authentication page and will be logged in as normal. It is up to you how to handle this. You can use any of the [User Permissions Strategies](#user-permissions-strategies-for-two-factor-auth) or you may create a middleware to force him to confirm the code before he can access any other routes for instance.

#### User Permissions Strategies For Two Factor Auth

**Using The Authenticated Middleware**

The simplest way is just to protect routes from users which are not authenticated via two-factor authentification by using the ```Authenticated::class``` middleware and defining the ```via``` parameter with ```twofactor-code```:

```php
use Tobento\App\User\Middleware\Authenticated;

$app->route('GET', 'account-info', function() {
    return 'account';
})->middleware([
    Authenticated::class,
    'via' => 'twofactor-code',
    'redirectRoute' => 'home',
]);
```

You may check out the [Authenticated Middleware](https://github.com/tobento-ch/app-user#authenticated-middleware) section for more detail.

**Using A Custom Token Authenticator To Change The Users Role**

You may change the users role when he has just logged in and is (required) to perform two-factor authentification.

First, create a custom [token authenticator](https://github.com/tobento-ch/app-user#token-authenticator) and use the ```authenticatedVia``` token method to check for the ```loginform-twofactor``` value which is set by the Login feature when two-factor authentication is required. Once the user has confirmed the code the value of the ```authenticatedVia``` token method will be set to ```twofactor-code``` and the users original role will be used again:

```php
use Tobento\App\User\Authentication\Token\TokenInterface;
use Tobento\App\User\Authenticator\TokenAuthenticator;
use Tobento\App\User\Authenticator\TokenVerifierInterface;
use Tobento\App\User\Exception\AuthenticationException;
use Tobento\App\User\UserInterface;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\Service\Acl\AclInterface;

class CustomTokenAuthenticator extends TokenAuthenticator
{
    public function __construct(
        protected AclInterface $acl,
        protected UserRepositoryInterface $userRepository,
        protected null|TokenVerifierInterface $tokenVerifier = null,
    ) {}
    
    /**
     * Authenticate token.
     *
     * @param TokenInterface $token
     * @return UserInterface
     * @throws AuthenticationException If authentication fails.
     */
    public function authenticate(TokenInterface $token): UserInterface
    {
        $user = parent::authenticate($token);
        
        if ($token->authenticatedVia() === 'loginform-twofactor') {
            
            $role = $this->acl->getRole('registered');
            
            if (is_null($role)) {
                throw new AuthenticationException('Registered role not set up');
            }
            
            $user->setRole($role);
            $user->setRoleKey($role->key());
            $user->setPermissions([]); // clear user specific permissions too.
        }
        
        return $user;
    }
}
```

Next, in the ```config/user.php``` file implement your created custom token authenticator:

```php
use Tobento\App\User\Authenticator;

'interfaces' => [
    // ...
    
    Authenticator\TokenAuthenticatorInterface::class => CustomTokenAuthenticator::class,
    
    // ...
],
```

Finally, just use the [Verify Permission Middleware](https://github.com/tobento-ch/app-user#verify-permission-middleware) or the [Verify Role Middleware](https://github.com/tobento-ch/app-user#verify-role-middleware) to protect any routes from unauthorized users.

### Logout Feature

The Logout Feature provides a simple logout functionality.

**Config**

In the [config file](#user-web-config) you can configure the logout feature:

```php
'features' => [
    new Feature\Logout(),
    
    // Or:
    new Feature\Logout(
        // A menu name to show the logout link or null if none.
        menu: 'header',
        menuLabel: 'Log out',
        // A menu parent name (e.g. 'user') or null if none.
        menuParent: null,

        // The redirect route after a successful logout.
        redirectRoute: 'home',

        // The message and redirect route if a user is unauthenticated.
        unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
        unauthenticatedRedirectRoute: 'home', // or null (no redirection)

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

### Forgot Password Feature

The Forgot Password Feature provides a simple way for users to reset their forgotten passwords.

**Config**

In the [config file](#user-web-config) you can configure the forgot password feature:

```php
'features' => [
    new Feature\ForgotPassword(),
    
    // Or:
    new Feature\ForgotPassword(
        viewIdentity: 'user/forgot-password/identity',
        viewReset: 'user/forgot-password/reset',

        // Specify the identity attributes to be checked on identity.
        identifyBy: ['email', 'username', 'smartphone'],
        // You may set a user verifier(s), see: https://github.com/tobento-ch/app-user#user-verifier
        /*userVerifier: function() {
            return new \Tobento\App\User\Authenticator\UserRoleVerifier('editor', 'author');
        },*/
        
        // The period of time from the present after which the verification token MUST be considered expired.
        tokenExpiresAfter: 300, // int|\DateInterval
        
        // The seconds after a new token can be reissued.
        canReissueTokenAfter: 60,
            
        // The message shown if an identity attempt fails.
        identityFailedMessage: 'Invalid name or user.',

        // The message and redirect route if a user is authenticated.
        authenticatedMessage: 'You are logged in!',
        authenticatedRedirectRoute: 'home', // or null (no redirection)

        // The redirect route after a successful password reset.
        successRedirectRoute: 'home',
        
        // The message shown after a successful password reset.
        successMessage: 'Your password has been reset!', // or null

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

#### Customize Reset Password Notification

You may customize the reset password notification in two ways:

**By adding a custom notification**

See [Custom Notifications](https://github.com/tobento-ch/app-notifier#custom-notifications)

**By customizing the feature**

Extend the ```Tobento\App\User\Web\Feature\ForgotPassword::class``` and customize the ```sendLinkNotification``` method. Within this method, you may send the notification using any [notification class](https://github.com/tobento-ch/service-notifier#notifications) of your own creation:

```php
use Tobento\App\User\Web\Feature\ForgotPassword;
use Tobento\App\User\Web\TokenInterface;
use Tobento\App\User\Web\Notification;
use Tobento\App\User\UserInterface;
use Tobento\Service\Notifier\NotifierInterface;
use Tobento\Service\Notifier\UserRecipient;
use Tobento\Service\Routing\RouterInterface;

class CustomForgotPasswordFeature extends ForgotPassword
{
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
}
```

Finally, in the config replace the default Forgot Password feature with your customized:

```php
'features' => [
    new CustomForgotPasswordFeature(),
],
```

### Register Feature

The Register Feature provides a simple way for users to register.

**Config**

In the [config file](#user-web-config) you can configure the register feature:

```php
'features' => [
    new Feature\Register(),
    
    // Or:
    new Feature\Register(
        // The view to render:
        view: 'user/register',

        // A menu name to show the register link or null if none.
        menu: 'header',
        menuLabel: 'Register',
        // A menu parent name (e.g. 'user') or null if none.
        menuParent: null,

        // The message and redirect route if a user is authenticated.
        authenticatedMessage: 'You are logged in!',
        authenticatedRedirectRoute: 'home',

        // The default role key for the registered user.
        roleKey: 'registered',

        // The redirect route after a successful registration.
        successRedirectRoute: 'login',
        // You may redirect to the verification account page
        // see: https://github.com/tobento-ch/app-user-web#account-verification

        // If true, user has the option to subscribe to the newsletter.
        newsletter: false,

        // If a terms route is specified, users need to agree terms and conditions.
        termsRoute: null,
        /*termsRoute: static function (RouterInterface $router): string {
            return (string)$router->url('blog.show', ['key' => 'terms']);
        },*/

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

#### Customize Registration Fields

You may customize the registration fields by the following steps:

**1.A Customize the view**

In the ```views/user/``` directory create a new file ```custom-register.php``` where you write your custom view code.

**1.B Or customize the view using a theme (recommended way)**

In your theme create a new file ```my-view-theme/user/register.php``` where you write your custom view code.

Check out the [App View - Themes](https://github.com/tobento-ch/app-view#themes) section to learn more about it.

**2. Customize the validation rules**

Customize the registration rules corresponding to the customized view (step 1) by extending the ```Register::class``` and customizing the ```validationRules``` method:

```php
use Tobento\App\User\Web\Feature\Register;

class CustomRegisterFeature extends Register
{
    protected function validationRules(): array
    {
        return [
            'user_type' => 'string',
            'address.fistname' => 'required|string',
            'address.lastname' => 'required|string',
            // ...
        ];
    }
}
```

Finally, in the config replace the default register feature with your customized:

```php
'features' => [
    new CustomRegisterFeature(
        // specify your custom view if (step 1.A):
        view: 'user/custom-register',
        
        // No need to change the default view for (step 1.B)
        view: 'user/register',
        //...
    ),
],
```

#### Customize The Role For Registered Users

You may customize the role for registered users by extending the ```Register::class``` and customizing the ```determineRoleKey``` method:

```php
use Tobento\App\User\Web\Feature\Register;
use Tobento\Service\Acl\AclInterface;
use Tobento\Service\Validation\ValidationInterface;

class CustomRegisterFeature extends Register
{
    protected function determineRoleKey(AclInterface $acl, ValidationInterface $validation): string
    {
        return match ($validation->valid()->get('user_type', '')) {
            'business' => $acl->hasRole('business') ? 'business' : $this->roleKey,
            default => $this->roleKey,
        };
    }
}
```

In the config replace the default register feature with your customized:

```php
'features' => [
    new CustomRegisterFeature(
        //...
    ),
],
```

Make sure you have [added the roles](https://github.com/tobento-ch/app-user#adding-roles), otherwise the ```guest``` role key would be used as the fallback.

#### Auto Login After Registration

By default, after successful registration users get not authenticated (logged in).

If you want them to get auto logged in just add the ```AutoLoginAfterRegistration::class``` listener in the ```config/event.php``` file:

```php
use Tobento\App\User\Web\Listener\AutoLoginAfterRegistration;

'listeners' => [
    \Tobento\App\User\Web\Event\Registered::class => [
        AutoLoginAfterRegistration::class,
        
        // Or you may set the expires after:
        [AutoLoginAfterRegistration::class, ['expiresAfter' => 1500]], // in seconds
        [AutoLoginAfterRegistration::class, ['expiresAfter' => new \DateInterval('PT1H')]],
    ],
],
```

In the ```config/user_web.php``` file you may redirect users to the profile edit page or any other page you desire:

```php
'features' => [
    new Feature\Register(
        // redirect users to the profile page
        // after successful registration:
        successRedirectRoute: 'profile.edit',
    ),
],
```

#### Account Verification

After users have successfully registered, you may require them to verify at least one channel such as their email address before using the application or individual routes. You can achieve this by the following steps:

**1. Auto login users after successful registration**

In the ```config/event.php``` file add the ```AutoLoginAfterRegistration::class``` listener:

```php
'listeners' => [
    \Tobento\App\User\Web\Event\Registered::class => [
        \Tobento\App\User\Web\Listener\AutoLoginAfterRegistration::class,
    ],
],
```

Because only authenticated users are allowed to verify its account!

**2. Redirect users to the verification page after successful registration**

In the ```config/user_web.php``` file:

```php
'features' => [
    new Feature\Register(
        // redirect users to the verification account page
        // after successful registration:
        successRedirectRoute: 'verification.account',
    ),
    
    // make sure the verification feature is set:
    Feature\Verification::class,
],
```

**3. Protect routes from unverified users**

Use the [Verified Middleware](https://github.com/tobento-ch/app-user#verified-middleware) to protect any routes from unverified users.

**4. Protect the profile feature from unverified users**

Extend the ```Profile::class``` and customize the ```configureMiddlewares``` method:

```php
use Tobento\App\User\Web\Feature\Profile;
use Tobento\App\AppInterface;
use Tobento\App\User\Middleware\Authenticated;
use Tobento\App\User\Middleware\Verified;

class CustomProfileFeature extends Profile
{
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
            [
                Verified::class,

                // you may specify a custom message to show to the user:
                'message' => 'You have insufficient rights to access the requested resource!',

                // you may specify a message level:
                'messageLevel' => 'notice',

                // you may specify a route name for redirection:
                'redirectRoute' => 'verification.account',
            ],
        ];
    }
}
```

In the config replace the default profile feature with your customized:

```php
'features' => [
    new CustomProfileFeature(
        //...
    ),
],
```

**5. Protect the profile settings feature from unverified users**

Same as step 4. just with the ```Tobento\App\User\Web\Feature\ProfileSettings::class```.

#### Account Verification For Specific User Roles Only

Instead of [Account Verification](#account-verification) for all users, you may do it only for specific user roles. You can achieve this by the following steps:

**1. Customize The Register Feature**

Extend the ```Register::class``` and customize the ```configureSuccessRedirectRoute``` method:

```php
use Tobento\App\User\Web\Feature\Register;
use Tobento\App\User\UserInterface;

class CustomRegisterFeature extends Register
{
    protected function configureSuccessRedirectRoute(UserInterface $user): string
    {
        if (in_array($user->getRoleKey(), ['business'])) {
            return 'verification.account';
        }
        
        return 'login';
    }
}
```

**2. Customize The Role For Registered Users (optional)**

Check out the [Customize The Role For Registered Users](#customize-the-role-for-registered-users) section.

**3. Auto Login Users After Registration**

You will need to auto login the users which need to verify its account, as only authenticated users are allowed to verify its account:

In the ```config/event.php``` file add the ```CustomAutoLoginAfterRegistration::class``` listener:

```php
use Tobento\App\User\Web\Listener\AutoLoginAfterRegistration;

'listeners' => [
    \Tobento\App\User\Web\Event\Registered::class => [
        [AutoLoginAfterRegistration::class, ['userRoles' => ['business']]],
    ],
],
```

**4. Protect the profile and profile settings feature from unverified users as well as any other routes**

See [Account Verification](#account-verification) step 3, 4 and 5.

#### Terms and Conditions Agreement

You may users to agree your terms and conditions before they can register:

In the ```config/user_web.php``` file:

```php
'features' => [
    new Feature\Register(
        // If a terms route is specified, users need to agree terms and conditions.
        termsRoute: 'your.terms.route.name',
        
        // Or you may use router directly:
        termsRoute: static function (RouterInterface $router): string {
            return (string)$router->url('blog.show', ['key' => 'terms']);
        },
    ),
],
```

Make sure you have registered your terms route somewhere in your application.

#### Spam Protection For Registering

The registration form is protected against spam by default using the [App Spam](https://github.com/tobento-ch/app-spam) bundle. It uses the ```default``` spam detector as the defined named ```register``` detector does not exist. In order to use a custom detector, you will just need to define it on the ```app/config/spam.php``` file:

```php
use Tobento\App\Spam\Factory;

'detectors' => [
    'register' => new Factory\Composite(
        new Factory\Honeypot(inputName: 'hp'),
        new Factory\MinTimePassed(inputName: 'mtp', milliseconds: 1000),
    ),
]
```

### Notifications Feature

The Notifications Feature provides a simple way for users to view their notifications.

**Config**

In the [config file](#user-web-config) you can configure the notifications feature:

```php
'features' => [
    new Feature\Notifications(),
    
    // Or:
    new Feature\Notifications(
        // The view to render:
        view: 'user/notifications',

        // The notifier storage channel used to retrieve notifications.
        notifierStorageChannel: 'storage',

        // A menu name to show the notifications link or null if none.
        menu: 'main',
        menuLabel: 'Notifications',
        // A menu parent name (e.g. 'user') or null if none.
        menuParent: null,

        // The message and redirect route if a user is unauthenticated.            
        unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
        unauthenticatedRedirectRoute: 'login', // or null (no redirection)

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

#### Creating And Sending Notifications

To send notifications to be display on the notifications page you will need to send to the storage channel configured:

```php
use Tobento\Service\Notifier\NotifierInterface;
use Tobento\Service\Notifier\Notification;
use Tobento\Service\Notifier\Recipient;

class SomeService
{
    public function send(NotifierInterface $notifier): void
    {
        // Create a Notification that has to be sent:
        // using the "email" and "sms" channel
        $notification = new Notification(
            subject: 'New Invoice',
            content: 'You got a new invoice for 15 EUR.',
            channels: ['mail', 'sms', 'storage'],
        );
        
        // with specific storage message. Will be displayed on the notifications page:
        $notification->addMessage('storage', new Message\Storage([
            'message' => 'You received a new order.',
            'action_text' => 'View Order',
            'action_route' => 'orders.view',
            'action_route_parameters' => ['id' => 55],
        ]));

        // The receiver of the notification:
        $recipient = new Recipient(
            email: 'mail@example.com',
            phone: '15556666666',
            id: 'unique-user-id',
        );

        // Send the notification to the recipient:
        $notifier->send($notification, $recipient);
    }
}
```

**Format Notifications**

Check out the [Storage Notification Formatters](https://github.com/tobento-ch/app-notifier#storage-notification-formatters) section to learn more about formatting the displayed notifications.

#### Customize Unread Notifications Count

You may customize the unread notifications count logic for the menu badge by extending the ```Notifications::class``` and customizing the ```getUnreadNotificationsCount``` method. 

**Example caching the count**

Install the [App Cache](https://github.com/tobento-ch/app-cache) bundle to support caching.

```php
use Tobento\App\AppInterface;
use Tobento\App\User\Web\Feature\Notifications;
use Tobento\Service\Notifier\ChannelsInterface;
use Tobento\Service\Notifier\Storage;
use Psr\SimpleCache\CacheInterface;

class CustomNotificationsFeature extends Notifications
{
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
        
        $key = sprintf('unread_notifications_count:%s', (string)$user->id());
        
        $cache = $app->get(CacheInterface::class);
        
        if ($cache->has($key)) {
            return $cache->get($key);
        }
        
        $count = $channel->repository()->count(where: [
            'recipient_id' => $user->id(),
            'read_at' => ['null'],
        ]);
        
        $cache->set($key, $count, 60);
        
        return $count;
    }
}
```

In the config replace the default notifications feature with your customized:

```php
'features' => [
    new CustomNotificationsFeature(
        //...
    ),
],
```

#### Clear Read Notifications

If you have installed the [App Console](https://github.com/tobento-ch/app-console) you may easily delete read notifications running the following command:

```
php ap notifications:clear --read-only --channel=storage --older-than-days=10
```

You may check out the [App Notifier - Clear Notifications Command](https://github.com/tobento-ch/app-notifier#clear-notifications-command) section for more information about the ```notifications:clear``` command.

If you would like to automate this process, consider installing the [App Schedule](https://github.com/tobento-ch/app-schedule) bundle and using a command task:

```php
use Tobento\Service\Schedule\Task;
use Butschster\CronExpression\Generator;

$schedule->task(
    (new Task\CommandTask(
        command: 'notifications:clear --read-only',
    ))
    // schedule task:
    ->cron(Generator::create()->weekly())
);
```

### Profile Feature

The Profile Feature provides a simple way for users to update their profile data, delete their account and to verify their channels.

**Config**

In the [config file](#user-web-config) you can configure the profile feature:

```php
'features' => [
    new Feature\Profile(),
    
    // Or:
    new Feature\Profile(
        // The view to render:
        view: 'user/profile/edit',

        // A menu name to show the profile link or null if none.
        menu: 'main',
        menuLabel: 'Profile',
        // A menu parent name (e.g. 'user') or null if none.
        menuParent: null,

        // The message and redirect route if a user is unauthenticated.            
        unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
        unauthenticatedRedirectRoute: 'login', // or null (no redirection)

        // If true, it displays the channel verification section to verify channels.
        // Make sure the Verification Feature is enabled.
        channelVerifications: true,

        // The redirect route after a successfully account deletion.
        successDeleteRedirectRoute: 'home',

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

#### Customize Information Fields

You may customize the information fields by the following steps:

**1.A Customize the view**

In the ```views/user/``` directory create a new file ```profile/custom-edit.php``` where you write your custom view code.

**1.B Or customize the view using a theme (recommended way)**

In your theme create a new file ```my-view-theme/user/profile/edit.php``` where you write your custom view code.

Check out the [App View - Themes](https://github.com/tobento-ch/app-view#themes) section to learn more about it.

**2. Customize the validation rules**

Customize the settings rules corresponding to the customized view (step 1) by extending the ```Profile::class``` and customizing the ```validationUpdateRules``` method:

```php
use Tobento\App\User\Web\Feature\Profile;
use Tobento\App\User\UserInterface;

class CustomProfileFeature extends Profile
{
    /**
     * Returns the validation rules for updating the user's profile information.
     *
     * @param UserInterface $user
     * @return array
     */
    protected function validationUpdateRules(UserInterface $user): array
    {
        // your rules:
        $rules = [
            'user_type' => 'string',
            'address.fistname' => 'required|string',
            'address.lastname' => 'required|string',
        ];
        
        // your may merge the default rules.
        return array_merge($rules, parent::validationUpdateRules($user));
    }
}
```

Finally, in the config replace the default profile settings feature with your customized:

```php
'features' => [
    new CustomProfileFeature(
        // specify your custom view if (step 1.A):
        view: 'user/profile/custom-edit',
        
        // No need to change the default view for (step 1.B)
        view: 'user/profile/edit',
        //...
    ),
],
```

#### Customize Available Verification Channels To Display

You may customize the available verification channels by extending the ```Profile::class``` and customizing the ```configureAvailableChannels``` method:

```php
use Tobento\App\User\Web\Feature\Profile;
use Tobento\App\User\UserInterface;
use Tobento\App\Notifier\AvailableChannelsInterface;

class CustomProfileFeature extends Profile
{
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
}
```

In the config replace the default profile feature with your customized:

```php
'features' => [
    new CustomProfileFeature(
        //...
    ),
],
```

If you allow other channels than ```mail``` and ```sms```, you will need to [customize the verification feature](#customize-available-channels-to-verify).

### Profile Settings Feature

The Profile Settings Feature provides a simple way for users to update their profile settings such as his preferred locale and notification channels.

**Config**

In the [config file](#user-web-config) you can configure the profile feature:

```php
'features' => [
    new Feature\ProfileSettings(),
    
    // Or:
    new Feature\ProfileSettings(
        // The view to render:
        view: 'user/profile/settings',

        // A menu name to show the profile settings link or null if none.
        menu: 'main',
        menuLabel: 'Profile Settings',
        // A menu parent name (e.g. 'user') or null if none.
        menuParent: null,

        // The message and redirect route if a user is unauthenticated.            
        unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
        unauthenticatedRedirectRoute: 'login', // or null (no redirection)

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

#### Customize Settings Fields

You may customize the settings fields by the following steps:

**1.A Customize the view**

In the ```views/user/``` directory create a new file ```profile/custom-settings.php``` where you write your custom view code.

**1.B Or customize the view using a theme (recommended way)**

In your theme create a new file ```my-view-theme/user/profile/settings.php``` where you write your custom view code.

Check out the [App View - Themes](https://github.com/tobento-ch/app-view#themes) section to learn more about it.

**2. Customize the validation rules**

Customize the settings rules corresponding to the customized view (step 1) by extending the ```ProfileSettings::class``` and customizing the ```validationRules``` method:

```php
use Tobento\App\User\Web\Feature\ProfileSettings;
use Tobento\App\User\UserInterface;

class CustomProfileSettingsFeature extends ProfileSettings
{
    /**
     * Returns the validation rules for updating the user's profile settings.
     *
     * @param UserInterface $user
     * @return array
     */
    protected function validationRules(UserInterface $user): array
    {
        // your rules:
        $rules = [
            'settings.something' => 'required|string',
        ];
        
        // your may merge the default rules.
        return array_merge($rules, parent::validationRules($user));
    }
}
```

Finally, in the config replace the default profile settings feature with your customized:

```php
'features' => [
    new CustomProfileSettingsFeature(
        // specify your custom view if (step 1.A):
        view: 'user/profile/custom-settings',
        
        // No need to change the default view for (step 1.B)
        view: 'user/profile/settings',
        //...
    ),
],
```

#### Customize Available Notification Channels

You may customize the available notification channels by extending the ```ProfileSettings::class``` and customizing the ```configureAvailableChannels``` method:

```php
use Tobento\App\User\Web\Feature\ProfileSettings;
use Tobento\App\User\UserInterface;
use Tobento\App\Notifier\AvailableChannelsInterface;

class CustomProfileSettingsFeature extends ProfileSettings
{
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
        
        // Or you may return no channels at all:
        return $channels->only([]);
    }
}
```

In the config replace the default profile settings feature with your customized:

```php
'features' => [
    new CustomProfileSettingsFeature(
        //...
    ),
],
```

### Verification Feature

The Verification Feature provides a simple way for users to verify their email and smartphone.

**Config**

In the [config file](#user-web-config) you can configure the verification feature:

```php
'features' => [
    new Feature\Verification(),
    
    // Or:
    new Feature\Verification(
        // The view to render:
        viewAccount: 'user/verification/account',
        viewChannel: 'user/verification/channel',

        // The period of time from the present after which the verification code MUST be considered expired.
        codeExpiresAfter: 300, // int|\DateInterval

        // The seconds after a new code can be reissued.
        canReissueCodeAfter: 60,
            
        // The message and redirect route if a user is unauthenticated.            
        unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
        unauthenticatedRedirectRoute: 'login', // or null (no redirection)

        // The redirect route after a verified channel.
        verifiedRedirectRoute: 'home',

        // If true, routes are being localized.
        localizeRoute: false,
    ),
],
```

#### Protect Routes From Unverified User

Use the [Verified Middleware](https://github.com/tobento-ch/app-user#verified-middleware) to protect any routes from unverified users.

#### Customize Available Channels To Verify

You may customize the available channels which can be verified by extending the ```Verification::class``` and customizing the ```configureAvailableChannels```, ```getNotifierChannelFor``` and ```canVerifyChannel``` methods.

Do not forget to configure the notifier channels in the ```app/config/notifier.php``` config file!

```php
use Tobento\App\User\Web\Feature\Verification;
use Tobento\App\User\UserInterface;
use Tobento\App\Notifier\AvailableChannelsInterface;

class CustomVerificationFeature extends Verification
{
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
        //return $channels->only(['mail', 'sms']); // default
        
        return $channels->only(['mail', 'sms', 'chat/slack']);
    }
    
    /**
     * Returns the notifier channel for the given verification channel.
     *
     * @param string $channel
     * @return null|string
     */
    protected function getNotifierChannelFor(string $channel): null|string
    {
        return match ($channel) {
            'email' => 'mail',
            'smartphone' => 'sms',
            'slack' => 'chat/slack',
            default => null,
        };
    }
    
    /**
     * Determine if the channel can be verified.
     *
     * @param string $channel
     * @param AvailableChannelsInterface $channels
     * @param null|UserInterface $user
     * @return bool
     */
    protected function canVerifyChannel(string $channel, AvailableChannelsInterface $channels, null|UserInterface $user): bool
    {
        if (is_null($user) || !$user->isAuthenticated()) {
            return false;
        }
        
        if (! $channels->has((string)$this->getNotifierChannelFor($channel))) {
            return false;
        }
        
        return match ($channel) {
            'email' => !empty($user->email()) && ! $user->isVerified([$channel]),
            'smartphone' => !empty($user->smartphone()) && ! $user->isVerified([$channel]),
            'slack' => !empty($user->setting('slack')) && ! $user->isVerified([$channel]),
            default => false,
        };
    }
}
```

In the config replace the default profile feature with your customized:

```php
'features' => [
    new CustomVerificationFeature(
        //...
    ),
],
```

## Deleting Expired Tokens

**Verificator Tokens**

The following features use the token or pin code verificator creating tokens which will still be present within your token repository even if expired.

* [Forgot Password Feature](#forgot-password-feature)
* [Two-Factor Authentication Code Feature](#two-factor-authentication-code-feature)
* [Verification Feature](#verification-feature)

If you have installed the [App Console](https://github.com/tobento-ch/app-console) you may easily delete these records running the following command:

```
php ap user-web:clear-tokens
```

If you would like to automate this process, consider installing the [App Schedule](https://github.com/tobento-ch/app-schedule) bundle and using a command task:

```php
use Tobento\Service\Schedule\Task;
use Butschster\CronExpression\Generator;

$schedule->task(
    (new Task\CommandTask(
        command: 'user-web:clear-tokens',
    ))
    // schedule task:
    ->cron(Generator::create()->weekly())
);
```

**Auth Tokens**

```
php ap auth:purge-tokens
```

Or automate this process using a command schedule task:

```php
use Tobento\Service\Schedule\Task;
use Butschster\CronExpression\Generator;

$schedule->task(
    (new Task\CommandTask(
        command: 'auth:purge-tokens',
    ))
    // schedule task:
    ->cron(Generator::create()->weekly())
);
```

Visit [User - Console](https://github.com/tobento-ch/app-user#console) for more detail.

## Events

**Available Events**

```php
use Tobento\App\User\Web\Event;
```

| Event | Description |
| --- | --- |
| ```Event\DeletedAccount::class``` | The event will dispatch **after** a user has deleted his account. |
| ```Event\Login::class``` | The event will dispatch **after** a user has logged in. |
| ```Event\LoginFailed::class``` | The event will dispatch **after** a user login attempt failed. |
| ```Event\LoginAttemptsExceeded::class``` | The event will dispatch **after** a user has exceeded the maximal number of login attempts. |
| ```Event\Logout::class``` | The event will dispatch **after** a user is logged out. |
| ```Event\PasswordReset::class``` | The event will dispatch **after** a user has reset his password. |
| ```Event\PasswordResetFailed::class``` | The event will dispatch **after** a user password reset attempt failed. |
| ```Event\Registered::class``` | The event will dispatch **after** a user has registered. |
| ```Event\RegisterFailed::class``` | The event will dispatch **after** a user register attempt failed. |
| ```Event\UpdatedProfile::class``` | The event will dispatch **after** a user has updated his profile. |
| ```Event\VerifiedChannel::class``` | The event will dispatch **after** a user has verified a channel. |
| ```Event\VerifyChannelFailed::class``` | The event will dispatch **after** a user channel verification attempt failed. |
| ```Event\VerifiedTwoFactorCode::class``` | The event will dispatch **after** a user has verified two-factor authentication code successfully. |
| ```Event\VerifyTwoFactorCodeFailed::class``` | The event will dispatch **after** a user two-factor authentication code verification attempt failed. |

## Learn More

### Login With Smartphone

By default, login with smarthone is enabled. Make sure you have configured the **sms** channel in the ```app/config/notifier.php``` file for sending sms to verify its account for instance.

### List Available Routes

Use the [Route List Command](https://github.com/tobento-ch/app-http#route-list-command) to get an overview of the available routes.

### Newsletter Subscription

You may use the provided [Events](#events) to subscribe/unsubscribe registered users to an newsletter provider.

**Example of Listener:**

```php
use Tobento\App\User\Web\Event;

class UserNewsletterSubscriber
{
    public function subscribe(Event\Registered $event): void
    {
        if ($event->user()->newsletter()) {
            // subscribe...
        }
    }

    public function resubscribe(Event\UpdatedProfile $event): void
    {
        if ($event->user()->email() !== $event->oldUser()->email()) {
            // unsubscribe user with the old email address...
            // subscribe user with the new email address...
        }
    }
    
    public function subscribeOrUnsubscribe(Event\UpdatedProfileSettings $event): void
    {
        if ($event->user()->newsletter()) {
            // subscribe...
        } else {
            // unsubscribe...
        }
    }
    
    public function unsubscribe(Event\DeletedAccount $event): void
    {
        // just unsubscribe...
    }
}
```

In the ```config/event.php``` file add the the listener:

```php
'listeners' => [
    // Specify listeners without event:
    'auto' => [
        UserNewsletterSubscriber::class,
    ],
],
```

You may check out the [App Event - Add Listeners](https://github.com/tobento-ch/app-event#add-listeners) section to learn more about it.

### Customize Verification Code Notification

You may customize the verification code notification in two ways:

**By adding a custom notification**

See [Custom Notifications](https://github.com/tobento-ch/app-notifier#custom-notifications)

**By customizing the pin code verificator**

Extend the ```Tobento\App\User\Web\PinCodeVerificator::class``` and customize the ```createNotification``` method. Within this method, you may send the notification using any notification class of your own creation:

```php
use Tobento\App\User\Web\Notification;
use Tobento\App\User\Web\PinCodeVerificator;
use Tobento\App\User\Web\TokenInterface;
use Tobento\App\User\UserInterface;
use Tobento\Service\Notifier\NotifierInterface;
use Tobento\Service\Routing\RouterInterface;

class CustomPinCodeVerificator extends PinCodeVerificator
{
    protected function createNotification(
        TokenInterface $token,
        UserInterface $user,
        array $channels,
    ): NotificationInterface {
        return (new Notification\VerificationCode(token: $token))->channels($channels);
    }
}
```

Finally, in the [config file](#user-web-config) replace the default implementation with your custom:

```php
use Tobento\App\User\Web;

'interfaces' => [
    Web\PinCodeVerificatorInterface::class => CustomPinCodeVerificator::class,
],
```

### Localization

If you enable feature routes being localized, you can define the languages you support in the ```app/config/language.php```.

In the [config file](#user-web-config):

```php
'features' => [
    new Feature\Home(
        localizeRoute: true,
    ),
    new Feature\Register(
        localizeRoute: true,
    ),
],
```

Check out the [App Language](https://github.com/tobento-ch/app-language) to learn more about the languages.

### Translations

By default, ```en``` and ```de``` translation are available. If you want to support more locales, check out the [App Translation](https://github.com/tobento-ch/app-translation) to learn more about it.

# Credits

- [Tobias Strub](https://www.tobento.ch)
- [All Contributors](../../contributors)