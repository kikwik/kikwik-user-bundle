KikwikUserBundle
=================

A super simple user bundle that provide very basic helpers for symfony 6.4 and 7.x user management.


Installation
------------

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require kikwik/user-bundle
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Kikwik\UserBundle\KikwikUserBundle::class => ['all' => true],
];
```

### Step 3: Creating the User

Run `make:user` command:

```console
php bin/console make:user
```

Make your User class extends `Kikwik\UserBundle\Model\BaseUser`:

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kikwik\UserBundle\Model\BaseUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User extends BaseUser implements UserInterface
{
    //...
}
```

Create the `config/packages/kikwik_user.yaml` config file, set the user class and the unique identifier field name of your user

```yaml
kikwik_user:
    user_class: App\Entity\User
    user_identifier_field: username
    user_email_field: email
    password_min_length: 8
    sender_email: '%env(SENDER_EMAIL)%'
    sender_name: '%env(SENDER_NAME)%'
    enable_admin: true  # default is true
```

and define sender vars in your .env file

```dotenv
###> kikwik/user-bundle ###
SENDER_EMAIL=no-reply@example.com
SENDER_NAME="My Company Name"
###< kikwik/user-bundle ###
```


Features
--------

### Disable user access ###

To activate the isEnabled feature set the user_checker option for your firewall in `config/packages/security.yaml`

```yaml
security:
    firewalls:
        main:
            pattern: ^/
            user_checker: Kikwik\UserBundle\Security\UserChecker
```

### Change password ###

To activate the change and forgot password feature add routes in `config/routes/kikwik_user.yaml`:

```yaml
kikwik_user_bundle_password:
    resource: '@KikwikUserBundle/Resources/config/routes.xml'
    prefix: '/password'
```

The forgot password uses symfony/mailer component, so you must configure it in `.env`

```
MAILER_DSN=sendmail+smtp://localhost
```

This will register the following route:

    * kikwik_user_password_change
    * kikwik_user_password_request
    * kikwik_user_password_reset

Copy translations file from `vendor/kikwik/user-bundle/src/Resources/translations/KikwikUserBundle.xx.yaml` 
to `translations/KikwikUserBundle.xx.yaml` and change at least the `request_password.email.sender` value 

```yaml
request_password:
    email:
        sender:  'no-reply@my-domain.ltd'
        subject: 'Istruzioni per reimpostare la password'
        content: |
            <p>
                Ciao {{ username }},<br/>
                Abbiamo ricevuto una richiesta per resettare la tua password,
                <a href="{{ reset_url }}">clicca qui per scegliere una nuova password</a><br/>
                oppure incolla in seguente link nella barra degli indirizzi del browser: <br/>{{ reset_url }}
            </p>
```


Behat
-----

Require behat and dependencies: 

```console
$ composer require friends-of-behat/mink-extension friends-of-behat/mink-browserkit-driver friends-of-behat/symfony-extension doctrine/doctrine-fixtures-bundle robertfausk/behat-panther-extension drevops/behat-screenshot --dev
```

Configure behat extensions in `behat.yml.dist`:

```yaml
default:
    suites:
        default:
            contexts:
                - DrevOps\BehatScreenshotExtension\Context\ScreenshotContext
                - App\Tests\Behat\DefaultContext

    extensions:
        FriendsOfBehat\SymfonyExtension:
            bootstrap: tests/bootstrap.php

        Robertfausk\Behat\PantherExtension: ~ # no configuration here

        Behat\MinkExtension:
            default_session: symfony
            symfony: ~
            show_cmd: firefox %s
            javascript_session: panther
            panther:
                options:
                    browser: 'chrome'

        DrevOps\BehatScreenshotExtension:
            dir: '%paths.base%/var/screenshots'
            fail: true
            fail_prefix: 'failed_'
            purge: true
```

Add these lines to the `.env.test` file:

```dotenv
PANTHER_NO_HEADLESS=0
DATABASE_URL="mysql://user:password@127.0.0.1:3306/local_db_name"  # same string used in .env.dev
MAILER_DSN=null://null
```

Enable the profiler for the test environment in `config/packages/web_profiler.yaml`:

```yaml
when@test:
    framework:
        profiler: { collect: true }
```


In your `templates/security/login.html.twig` template give `name="login-submit"` to the login submit button:

```html
<button class="btn btn-lg btn-primary" type="submit" name="login-submit">
    Sign in
</button>
```

Display flashes in your main template:

```twig
{% for label, messages in app.flashes %}
    {% for message in messages %}
        <div class="alert alert-{{ label }}">
            {{ message|raw }}
        </div>
    {% endfor %}
{% endfor %}
```



Use the `KikwikUserContextTrait` in your behat context and autowire these services in the constructor:
- `ContainerInterface $driverContainer`
- `EntityManagerInterface $entityManager`
- `UserPasswordHasherInterface $passwordHasher`

Eventually override the `getUserClass` and `getUserIdentifierField` trait functions:


```php
declare(strict_types=1);

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Kikwik\UserBundle\Behat\KikwikUserContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * This context class contains the definitions of the steps used by the demo
 * feature file. Learn how to get started with Behat and BDD on Behat's website.
 *
 * @see http://behat.org/en/latest/quick_start.html
 */
final class DefaultContext extends MinkContext implements Context
{
    use KikwikUserContextTrait;

    /** @var KernelInterface */
    private $kernel;

    /** @var Response|null */
    private $response;

    private ContainerInterface $driverContainer;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(KernelInterface $kernel, ContainerInterface $driverContainer, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->kernel = $kernel;
        $this->driverContainer = $driverContainer;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @BeforeScenario
     */
    public function clearData()
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=0');

        $purger = new ORMPurger($this->entityManager);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
        $purger->purge();

        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function getUserClass()
    {
        return 'App\Entity\User';
    }
    
    protected function getUserIdentifierField()
    {
        return 'email';
    }
}
```


Create a feature file to test the reset password in `features/password-request-reset.feature`

Example with `email` as `userIdentifier`:

```yaml
Feature:
    In order to manage private access to site
    As a user
    I want to be able to reset password

    Background:
        Given There is a user "test@example.com" with password "change-me" and "ROLE_USER" roles

    Scenario: Change password should be protected
        When I go to "/password/change"
        Then the response status code should be 200
        And I should not see a "[data-test='change-password-form']" element

    Scenario: Change password
        When I am authenticated as "test@example.com" with password "change-me"
        And I go to "/password/change"
        Then I should see a "[data-test='change-password-form']" element
        When I fill in "change_password_form_newPassword_first" with "myNewPassword"
        And I fill in "change_password_form_newPassword_second" with "myNewPassword"
        And I press "change-password-submit"
        Then I should see a ".alert.alert-success.change_password" element
        When I go to "/logout"
        And I am authenticated as "test@example.com" with password "myNewPassword"
        Then I should not see "Credenziali non valide."

    Scenario: Request password should not be protected
        When I go to "/password/request"
        Then the response status code should be 200
        And I should see a "[data-test='request-password-form']" element

    Scenario: Login page has the forgot password link
        When I go to "/login"
        Then the response status code should be 200
        And I should see a "a[href='/password/request']" element

    Scenario: Request password
      # try a wrog login
        When I go to "/login"
        And I fill in "email" with "test@example.com"
        And I fill in "password" with "mySecretPassword"
        And I press "login-submit"
        Then I should see "Credenziali non valide."
      # request a new password
        When I go to "/password/request"
        Then I should see a "[data-test='request-password-form']" element
        When I fill in "request_password_form_userIdentifier" with "test@example.com"
        And I press "request-password-submit"
        Then I should see an ".alert.alert-success.request_password" element
      # check that email was sent
        And the reset password mail was sent to "test@example.com"
      # reset password
        When I follow the password reset link for user "test@example.com"
        Then I should see a "[data-test='change-password-form']" element
        When I fill in "change_password_form_newPassword_first" with "mySecretPassword"
        And I fill in "change_password_form_newPassword_second" with "mySecretPassword"
        And I press "reset-password-submit"
        Then I should see an ".alert.alert-success.reset_password" element
      # try the login
        When I go to "/login"
        And I fill in "email" with "test@example.com"
        And I fill in "password" with "mySecretPassword"
        And I press "login-submit"
        Then I should not see "Credenziali non valide."
    
    Scenario: Disabled users can't login
      # try login (should work)  
        When I am authenticated as "test@example.com" with password "change-me"
        And user "test@example.com" is disabled
        Then I go to "/logout"
      # try login again (should not work)
        When I go to "/login"
        And I fill in "email" with "test@example.com"
        And I fill in "password" with "change-me"
        And I press "login-submit"
        Then I should see "Credenziali non valide."
```


Example with `username` as `userIdentifier`:


```yaml
Feature:
    In order to manage private access to site
    As a user
    I want to be able to reset password

    Background:
        Given There is a user "testUser" with email "test@example.com" and password "change-me" and "ROLE_USER" roles

    Scenario: Change password should be protected
        When I go to "/password/change"
        Then the response status code should be 200
        And I should not see a "[data-test='change-password-form']" element

    Scenario: Change password
      # auth with old password
        When I am authenticated as "testUser" with password "change-me"
      # change password
        And I go to "/password/change"
        Then I should see a "[data-test='change-password-form']" element
        When I fill in "change_password_form_newPassword_first" with "myNewPassword"
        And I fill in "change_password_form_newPassword_second" with "myNewPassword"
        And I press "change-password-submit"
        Then I should see a ".alert.alert-success.change_password" element
      # logout
        When I go to "/logout"
      # re-auth with new password
        And I am authenticated as "testUser" with password "myNewPassword"
        Then I should not see "Credenziali non valide."

    Scenario: Request password should not be protected
        When I go to "/password/request"
        Then the response status code should be 200
        And I should see a "[data-test='request-password-form']" element

    Scenario: Login page has the forgot password link
        When I go to "/login"
        Then the response status code should be 200
        And I should see a "a[href='/password/request']" element

    Scenario: Request password
      # try a wrog login
        When I go to "/login"
        And I fill in "username" with "testUser"
        And I fill in "password" with "mySecretPassword"
        And I press "login-submit"
        Then I should see "Credenziali non valide."
      # request a new password
        When I go to "/password/request"
        Then I should see a "[data-test='request-password-form']" element
        When I fill in "request_password_form_userIdentifier" with "testUser"
        And I press "request-password-submit"
        Then I should see an ".alert.alert-success.request_password" element
      # check that email was sent
        And the reset password mail was sent to "test@example.com"
      # reset password
        When I follow the password reset link for user "testUser"
        Then I should see a "[data-test='change-password-form']" element
        When I fill in "change_password_form_newPassword_first" with "mySecretPassword"
        And I fill in "change_password_form_newPassword_second" with "mySecretPassword"
        And I press "reset-password-submit"
        Then I should see an ".alert.alert-success.reset_password" element
      # try the login
        When I go to "/login"
        And I fill in "username" with "testUser"
        And I fill in "password" with "mySecretPassword"
        And I press "login-submit"
        Then I should not see "Credenziali non valide."
    
    Scenario: Disabled users can't login
      # try login (should work)  
        When I am authenticated as "testUser" with password "change-me"
        And user "testUser" is disabled
        Then I go to "/logout"
      # try login again (should not work)
        When I go to "/login"
        And I fill in "username" with "test@example.com"
        And I fill in "password" with "change-me"
        And I press "login-submit"
        Then I should see "Credenziali non valide."
```