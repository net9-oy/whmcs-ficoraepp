<?php
declare(strict_types=1);

namespace Net9\FicoraEPP;

use FastRoute\RouteCollector;
use WHMCS\Route\ProviderTrait;

class RouteProvider
{
    use ProviderTrait;

    public function register(RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, [
            '/module/ficoraepp' => [
                [
                    'path' => '/addds',
                    'method' => 'POST',
                    'name' => 'module-ficoraepp-addds',
                    'handle' => [Api::class, 'addDs'],
                ],
                [
                    'path' => '/removeds',
                    'method' => 'POST',
                    'name' => 'module-ficoraepp-removeds',
                    'handle' => [Api::class, 'removeDs'],
                ],
                [
                    'path' => '/dnssec/{id:\\d+}',
                    'method' => 'GET',
                    'name' => 'module-ficoraepp-dnssec',
                    'handle' => [Api::class, 'dnsSec'],
                ],
            ]
        ]);
    }
}