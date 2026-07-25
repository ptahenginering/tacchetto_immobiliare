<?php

declare(strict_types=1);

use App\Application\Actions\Admin\Leads\ConvertLeadAction;
use App\Application\Actions\Admin\Leads\GetLeadAction;
use App\Application\Actions\Admin\Leads\ListLeadsAction;
use App\Application\Actions\Admin\Leads\UpdateLeadAction;
use App\Application\Actions\Auth\AdminLoginAction;
use App\Application\Actions\Auth\RefreshTokenAction;
use App\Application\Middleware\AdminMiddleware;
use Slim\Routing\RouteCollectorProxy;
use App\Application\Actions\Customer\RequestAccessAction;
use App\Application\Actions\Customer\VerifyAccessAction;
use App\Application\Actions\PublicSite\CreateLeadAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Definizione route RT CASA LIVE.
 * Tre gruppi: pubbliche, /customer (JWT owner), /admin (JWT admin/agent).
 */
return function (App $app): void {

    // --- Pubbliche ---
    $app->get('/health', function (Request $request, Response $response): Response {
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'service' => 'rt-casa-live-api',
            'time' => date('c'),
        ]));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    // --- Autenticazione ---
    $app->post('/admin/login', AdminLoginAction::class);
    $app->post('/auth/refresh', RefreshTokenAction::class);

    // --- Accesso proprietari (magic link, no password) ---
    $app->post('/customer/request-access', RequestAccessAction::class);
    $app->post('/customer/verify', VerifyAccessAction::class);

    // --- Endpoint pubblici sito vetrina ---
    $app->post('/public/leads', CreateLeadAction::class);

    // --- Gestionale (JWT admin/agent) ---
    $app->group('/admin', function (RouteCollectorProxy $group): void {
        // Lead
        $group->get('/leads', ListLeadsAction::class);
        $group->get('/leads/{id:[0-9]+}', GetLeadAction::class);
        $group->put('/leads/{id:[0-9]+}', UpdateLeadAction::class);
        $group->post('/leads/{id:[0-9]+}/convert', ConvertLeadAction::class);
    })->add(AdminMiddleware::class);
};
