<?php

declare(strict_types=1);

namespace App\Application\Middleware;

/** Accesso riservato ai proprietari (ruolo owner). */
final class CustomerMiddleware extends JwtAuthMiddleware
{
    protected array $allowedRoles = ['owner'];
}
