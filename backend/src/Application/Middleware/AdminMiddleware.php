<?php

declare(strict_types=1);

namespace App\Application\Middleware;

/** Accesso riservato allo staff agenzia (admin e agent). */
final class AdminMiddleware extends JwtAuthMiddleware
{
    protected array $allowedRoles = ['admin', 'agent'];
}
