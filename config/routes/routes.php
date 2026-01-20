<?php

use App\Controller\DashboardController;
use App\Controller\Transactions;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('dashboard', '/')
        ->controller([DashboardController::class, 'index'])
        ->methods(['GET']);
        
    $routes->add('transaction_form', '/transaction_form')
        ->controller([Transactions::class, 'index'])
        ->methods(['GET']);
};