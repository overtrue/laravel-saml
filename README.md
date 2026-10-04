Laravel SAML
---

SAML toolkit for Laravel based on [OneLogin's SAML PHP Toolkit](https://github.com/onelogin/php-saml).

[![Sponsor me](https://github.com/overtrue/overtrue/blob/master/sponsor-me-button-s.svg?raw=true)](https://github.com/sponsors/overtrue)

## Requirements

- PHP 8.3 or later
- Laravel 13

Version 2.x supports Laravel 13 only. Applications on Laravel 9–12 should remain on the 1.x release line until they upgrade Laravel.

## Installation

```bash
composer require overtrue/laravel-saml:^2.0
```

## Upgrading from 1.x

Upgrade your application to PHP 8.3+ and Laravel 13 first, then run:

```bash
composer require overtrue/laravel-saml:^2.0 --with-all-dependencies
```

This major release raises the supported platform baseline. Existing SAML APIs and configuration keys are unchanged; keep your IdP certificates, strict validation, signing requirements, and application-specific ACS/SLS routes and middleware intact. Test login, assertion validation, and logout against your IdP after upgrading.

The release also fixes explicitly supplied IdP settings being ignored and preserves the original PHP error when SAML response or logout processing fails.

For package development, the test suite now uses Orchestra Testbench 11 and PHPUnit 12.5. CI runs on PHP 8.3, 8.4, and 8.5, including the lowest dependency versions allowed by Composer's security checks.

## Configuration

```bash
php artisan vendor:publish --tag=saml-config
```

This command will add the file `config/saml.php`. This config is handled almost directly by [OneLogin](https://github.com/onelogin/php-saml) so you may get further references there, but will cover here what's really necessary. There are some other config about routes you may want to check, they are pretty straightforward.

## Usage

If your application is only used to log in to one specified IdP, you just need to configure `idp` section in `config/saml.php`.

### idp configuration resolver

In order to support multiple IdP, you need to configure the following method to get the configuration of the IdP.

```php
Saml::configureIdpUsing(function($idpName): array {
    return [...]; 
});
```

You need to return the configuration array for IdP, see the `idp` section in `config/saml.php` for the structure.

### Entrypoints controller

You can create a controller to perform SAML integration:

```php
$ php artisan make:controller SamlController
```

Then we prepare the following 5 necessary methods.

```php
<?php

namespace App\Http\Controllers;

use Overtrue\LaravelSaml\Saml;

class SamlController extends Controller
{
    public function login() {}
    public function acs() {}
    public function logout() {}
    public function sls() {}
    public function metadata() {}
}
```

### Entrypoints Routes

Then configure the routes at `routes/web.php`:

| Method | URI                      | Name 				|
| -------|--------------------------|------------------ |
| GET    | `{routesPrefix}/login`     | saml.login 		|
| POST   | `{routesPrefix}/acs`       | saml.acs 			|
| GET    | `{routesPrefix}/logout`    | saml.logout 		|
| GET    | `{routesPrefix}/sls`       | saml.sls 			|
| GET    | `{routesPrefix}/metadata`  | saml.metadata 	|

You are free to use your preferred routing prefix, for example, we use `saml` as the routing prefix:

```php
use App\Http\Controllers\SamlController;

Route::get('saml/login', [SamlController::class, 'login'])->name('saml.login');
Route::get('saml/logout', [SamlController::class, 'logout'])->name('saml.logout');
Route::post('saml/acs', [SamlController::class, 'acs'])->name('saml.acs');
Route::get('saml/sls', [SamlController::class, 'sls'])->name('saml.sls');
Route::get('saml/metadata', [SamlController::class, 'metadata'])->name('saml.metadata');
```

#### Redirect to IdP login service

Initiates the SSO process, creates an AuthnRequest, returns a laravel redirect response.

```php
    //<...>
    public function login(Request $request)
    {
        // Use the default idp in the configuration
        return Saml::redirect(); 
        
        // Or specify the idp name
        return Saml::idp($request->get('idp'))->redirect();
    }
```

#### Assertion Consumer Service (ACS)

This method is used to handle the IdP authorization callback, `SamlAuth::getAuthenticatedUser` will validation the request and return a `Overtrue\LaravelSaml\SamlUser` object.

```php
//<...>
    public function acs(Request $request)
    {
        // Overtrue\LaravelSaml\SamlUser
        $samlUser = Saml::getAuthenticatedUser();
        // Or specify the idp name
        //$samlUser = Saml::idp($request->get('idp'))->getAuthenticatedUser(); 
        
        $samlUserId = $samlUser->getNameId();
        
        // SamlUser to app User
        // $user = User::FirstOrCreate(['email' => $samlUser->getNameId()]);
        Auth::set($user);
        
        return redirect('/home')
    }
```

#### Redirect to IdP logout service

Create a redirect response to IdP logout service.

```php
    //<...>
    public function logout(Request $request)
    {
        // Use the default IdP in the configuration
        return Saml::redirectToLogout(); 
        
        // Or specify the IdP name
        return Saml::idp($request->get('idp'))->redirectToLogout();
    }
```

The IdP will return the Logout Response through the user's client to the Single Logout Service of the SP (route `saml/sls`).

#### Single Logout Service (SLS)

This code handles the Logout Request and the Logout Responses.

```php
    //<...>
    public function sls(Request $request)
    {
        $auth = Saml::handleLogoutRequest();
        // Or specify the IdP name
        //$auth = Saml::idp($request->get('idp'))->handleLogoutRequest();
    
        Auth::logout();
        
        return redirect('/home')
    }
```

#### Metadata

This code will provide the XML metadata file of our SP, based on the info that we provided in the settings files.

```php
    //<...>
    public function metadata(Request $request)
    {
        if ($request->has('download')) {
            return Saml::getMetadataXMLAsStreamResponse();
            // or specify a filename to the xml file:
            // return Saml::getMetadataXMLAsStreamResponse('sp-metadata.xml');
        }
        
        return Saml::getMetadataXML();
    }
```

### More

For more information on configuration and usage please see the source code or read [onelogin/php-saml](https://github.com/onelogin/php-saml).


## :heart: Sponsor me 

[![Sponsor me](https://github.com/overtrue/overtrue/blob/master/sponsor-me.svg?raw=true)](https://github.com/sponsors/overtrue)

如果你喜欢我的项目并想支持它，[点击这里 :heart:](https://github.com/sponsors/overtrue)


## Project supported by JetBrains

Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/overtrue)

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/overtrue/laravel-package/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/overtrue/laravel-package/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT
