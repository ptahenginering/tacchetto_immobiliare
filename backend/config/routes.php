<?php

declare(strict_types=1);

use App\Application\Actions\Admin\Leads\ConvertLeadAction;
use App\Application\Actions\Admin\Leads\GetLeadAction;
use App\Application\Actions\Admin\Leads\ListLeadsAction;
use App\Application\Actions\Admin\Leads\UpdateLeadAction;
use App\Application\Actions\Admin\Appointments\DeleteAppointmentAction;
use App\Application\Actions\Admin\Appointments\ListAppointmentsAction;
use App\Application\Actions\Admin\Appointments\SaveAppointmentAction;
use App\Application\Actions\Admin\Properties\DeletePropertyAction;
use App\Application\Actions\Admin\Visits\DeleteVisitAction;
use App\Application\Actions\Admin\Visits\ListVisitsAction;
use App\Application\Actions\Admin\Visits\SaveVisitAction;
use App\Application\Actions\Admin\Properties\GetPropertyAction;
use App\Application\Actions\Admin\Properties\ListPropertiesAction;
use App\Application\Actions\Admin\Properties\ManagePropertyImageAction;
use App\Application\Actions\Admin\Properties\SavePropertyAction;
use App\Application\Actions\Admin\Properties\UploadPropertyImagesAction;
use App\Application\Actions\PublicSite\ServeFileAction;
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

    // --- File caricati (immagini immobili) ---
    $app->get('/files/{path:.+}', ServeFileAction::class);

    // --- Gestionale (JWT admin/agent) ---
    $app->group('/admin', function (RouteCollectorProxy $group): void {
        // Lead
        $group->get('/leads', ListLeadsAction::class);
        $group->get('/leads/{id:[0-9]+}', GetLeadAction::class);
        $group->put('/leads/{id:[0-9]+}', UpdateLeadAction::class);
        $group->post('/leads/{id:[0-9]+}/convert', ConvertLeadAction::class);

        // Immobili
        $group->get('/properties', ListPropertiesAction::class);
        $group->post('/properties', SavePropertyAction::class);
        $group->get('/properties/{id:[0-9]+}', GetPropertyAction::class);
        $group->put('/properties/{id:[0-9]+}', SavePropertyAction::class);
        $group->delete('/properties/{id:[0-9]+}', DeletePropertyAction::class);
        $group->post('/properties/{id:[0-9]+}/images', UploadPropertyImagesAction::class);
        $group->put('/properties/{id:[0-9]+}/images/order', [ManagePropertyImageAction::class, 'reorder']);
        $group->put('/properties/{id:[0-9]+}/images/{imageId:[0-9]+}/cover', [ManagePropertyImageAction::class, 'setCover']);
        $group->delete('/properties/{id:[0-9]+}/images/{imageId:[0-9]+}', [ManagePropertyImageAction::class, 'delete']);

        // Appuntamenti
        $group->get('/appointments', ListAppointmentsAction::class);
        $group->post('/appointments', SaveAppointmentAction::class);
        $group->put('/appointments/{id:[0-9]+}', SaveAppointmentAction::class);
        $group->delete('/appointments/{id:[0-9]+}', DeleteAppointmentAction::class);

        // Visite & feedback
        $group->get('/visits', ListVisitsAction::class);
        $group->post('/visits', SaveVisitAction::class);
        $group->put('/visits/{id:[0-9]+}', SaveVisitAction::class);
        $group->delete('/visits/{id:[0-9]+}', DeleteVisitAction::class);
    })->add(AdminMiddleware::class);
};
