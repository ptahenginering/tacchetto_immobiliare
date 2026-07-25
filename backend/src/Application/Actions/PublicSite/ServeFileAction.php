<?php

declare(strict_types=1);

namespace App\Application\Actions\PublicSite;

use App\Application\Actions\Action;
use App\Infrastructure\Upload\ImageUploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Stream;

/**
 * GET /api/files/{path} — serve i file caricati (immagini immobili).
 * Protetto contro path traversal, solo estensioni immagine.
 */
final class ServeFileAction extends Action
{
    private const CONTENT_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];

    public function __construct(private readonly ImageUploadService $uploads)
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $relative = (string) ($args['path'] ?? '');
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        if (!isset(self::CONTENT_TYPES[$ext])) {
            return $this->error($response, 'not_found', 'File non trovato.', 404);
        }

        $full = $this->uploads->resolve($relative);
        if ($full === null || !is_file($full)) {
            return $this->error($response, 'not_found', 'File non trovato.', 404);
        }

        $fh = fopen($full, 'rb');
        return $response
            ->withHeader('Content-Type', self::CONTENT_TYPES[$ext])
            ->withHeader('Content-Length', (string) filesize($full))
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->withBody(new Stream($fh));
    }
}
