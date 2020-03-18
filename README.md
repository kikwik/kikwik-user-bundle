KikwikUserBundle
=================

A super simple user bundle that provide very basic helpers for symfony 4 user management.

WARNING: this bundle is still under development! do not use it!


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
    user_identifier_field: email
    user_email_field: email
```

Enable timestampable and blameable doctrine extension in `config/packages/stof_doctrine_extensions.yaml`

```yaml
stof_doctrine_extensions:
    default_locale: en_US
    orm:
        default:
            timestampable: true
            blameable: true
```

Features
------------

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

To activate the change and forgot password feature add routes in `config/routes.yaml`:

```yaml
kikwik_user_bundle_password:
    resource: '@KikwikUserBundle/Resources/config/routes.xml'
    prefix: '/password'
```

The forgot password uses symfony/mailer component, so you must configure it in `.env`

```
MAILER_DSN=smtp://user:pass@smtp.example.com 
```

This will register the following route:

    * kikwik_user_password_change
    * kikwik_user_password_request
    * kikwik_user_password_reset



### Sonata admin ###

If Sonata Admin is installed you can use some helpers (change password handler and getRoleHierarchy) from `SonataUserAdminTrait`

```php
use Kikwik\UserBundle\Traits\SonataUserAdminTrait;

final class UserAdmin extends AbstractAdmin
{
    use SonataUserAdminTrait;

    //...
}
```

Update sonata config files `config/packages/sonata_admin.yaml` to use some templates:

```yaml
sonata_admin:
    templates:
        user_block: '@KikwikUser/SonataAdmin/Core/user_block.html.twig'
```