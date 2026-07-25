<?php

declare(strict_types=1);

use App\Application\Actions\Admin\Ai\GenerateAiAction;
use App\Application\Actions\Admin\Ai\ScheduledPostsAction;
use App\Application\Actions\Admin\Leads\ConvertLeadAction;
use App\Application\Actions\Admin\Leads\GetLeadAction;
use App\Application\Actions\Admin\Leads\ListLeadsAction;
use App\Application\Actions\Admin\Leads\UpdateLeadAction;
use App\Application\Actions\Admin\Appointments\DeleteAppointmentAction;
use App\Application\Actions\Admin\Appointments\ListAppointmentsAction;
use App\Application\Actions\Admin\Appointments\SaveAppointmentAction;
use App\Application\Actions\Admin\Marketing\DeleteMarketingAction;
use App\Application\Actions\Admin\Marketing\ListMarketingAction;
use App\Application\Actions\Admin\Marketing\SaveMarketingAction;
use App\Application\Actions\Admin\PracticeSteps\UpdatePracticeStepAction;
use App\Application\Actions\Admin\Stats\StatsAction;
use App\Application\Actions\Admin\System\SystemAction;
use App\Application\Actions\Admin\Users\StaffUsersAction;
use App\Application\Actions\Admin\Properties\DeletePropertyAction;
use App\Application\Actions\Admin\Proposals\DeleteProposalAction;
use App\Application\Actions\Admin\Proposals\ListProposalsAction;
use App\Application\Actions\Admin\Proposals\SaveProposalAction;
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
use App\Application\Actions\Customer\ChatAction;
use App\Application\Actions\Customer\CustomerFeedAction;
use App\Application\Actions\Customer\GetMyPropertyAction;
use App\Application\Actions\Customer\GetPropertyKpiAction;
use App\Application\Actions\Customer\RequestAccessAction;
use App\Application\Actions\Customer\VerifyAccessAction;
use App\Application\Actions\PublicSite\CreateLeadAction;
use App\Application\Middleware\CustomerMiddleware;
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

    // --- Area cliente (JWT owner) ---
    $app->group('/customer', function (RouteCollectorProxy $group): void {
        $group->get('/property', GetMyPropertyAction::class);
        $group->get('/property/kpi', GetPropertyKpiAction::class);
        $group->get('/visits', [CustomerFeedAction::class, 'visits']);
        $group->get('/proposals', [CustomerFeedAction::class, 'proposals']);
        $group->get('/marketing', [CustomerFeedAction::class, 'marketing']);
        $group->get('/practice-steps', [CustomerFeedAction::class, 'practiceSteps']);
        $group->get('/timeline', [CustomerFeedAction::class, 'timeline']);
        $group->post('/chat', ChatAction::class);
    })->add(CustomerMiddleware::class);

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

        // Proposte
        $group->get('/proposals', ListProposalsAction::class);
        $group->post('/proposals', SaveProposalAction::class);
        $group->put('/proposals/{id:[0-9]+}', SaveProposalAction::class);
        $group->delete('/proposals/{id:[0-9]+}', DeleteProposalAction::class);

        // Marketing
        $group->get('/marketing', ListMarketingAction::class);
        $group->post('/marketing', SaveMarketingAction::class);
        $group->put('/marketing/{id:[0-9]+}', SaveMarketingAction::class);
        $group->delete('/marketing/{id:[0-9]+}', DeleteMarketingAction::class);

        // Step burocrazia
        $group->put('/practice-steps/{id:[0-9]+}', UpdatePracticeStepAction::class);

        // AI marketing + post programmati
        $group->post('/ai/generate', GenerateAiAction::class);
        $group->put('/ai/generations/{id:[0-9]+}', [GenerateAiAction::class, 'accept']);
        $group->get('/scheduled-posts', [ScheduledPostsAction::class, 'list']);
        $group->post('/scheduled-posts', [ScheduledPostsAction::class, 'save']);
        $group->put('/scheduled-posts/{id:[0-9]+}', [ScheduledPostsAction::class, 'save']);
        $group->delete('/scheduled-posts/{id:[0-9]+}', [ScheduledPostsAction::class, 'delete']);

        // Statistiche
        $group->get('/stats/overview', [StatsAction::class, 'overview']);
        $group->get('/stats/leads-by-source', [StatsAction::class, 'leadsBySource']);
        $group->get('/stats/performance', [StatsAction::class, 'performance']);
        $group->get('/stats/property/{id:[0-9]+}', [StatsAction::class, 'propertyFunnel']);

        // Sistema, impostazioni, utenti
        $group->get('/system/health', [SystemAction::class, 'health']);
        $group->post('/system/test-email', [SystemAction::class, 'testEmail']);
        $group->get('/settings', [SystemAction::class, 'getSettings']);
        $group->put('/settings', [SystemAction::class, 'updateSettings']);
        $group->get('/users', [StaffUsersAction::class, 'list']);
        $group->post('/users', [StaffUsersAction::class, 'save']);
        $group->put('/users/{id:[0-9]+}', [StaffUsersAction::class, 'save']);
    })->add(AdminMiddleware::class);
};
