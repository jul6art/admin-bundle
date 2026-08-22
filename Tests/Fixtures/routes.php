<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes the shell's templates link to.
 *
 * They exist so a rendering test fails on a *template* defect rather than on `path()` refusing an
 * unknown route — which is the same red for two very different reasons. None of them resolves to a
 * controller: nothing here is ever requested.
 */
return static function (RoutingConfigurator $routes): void {
    foreach ([
        'admin_dashboard' => '/admin',
        'admin_security_login' => '/login',
        'admin_security_logout' => '/logout',
        'admin_security_register' => '/register',
        'admin_account_appearance_edit' => '/admin/account/appearance',
        'admin_widget_index' => '/admin/widgets',
        'admin_widget_show' => '/admin/widgets/{id}',
        'admin_report_index' => '/admin/reports',
    ] as $name => $path) {
        $routes->add($name, $path);
    }
};
