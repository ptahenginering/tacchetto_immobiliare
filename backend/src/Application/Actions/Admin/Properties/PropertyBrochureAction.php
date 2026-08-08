<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Properties;

use App\Application\Actions\Action;
use App\Domain\Mail\MailerInterface;
use App\Infrastructure\Logger;
use App\Infrastructure\Upload\ImageUploadService;
use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Scheda di presentazione immobile in PDF (brand RT, foto incluse).
 *
 *   POST /api/admin/properties/{id}/brochure/pdf  — genera e restituisce il PDF
 *   POST /api/admin/properties/{id}/brochure/send — invia il PDF via email a un acquirente
 *
 * Body comune: {presentation_text?} — testo generato con l'AI (kind "scheda");
 * in mancanza si usa la descrizione dell'immobile.
 */
final class PropertyBrochureAction extends Action
{
    private const MAX_PHOTOS = 6;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ImageUploadService $uploads,
        private readonly MailerInterface $mailer,
        private readonly Logger $logger,
    ) {
    }

    public function pdf(Request $request, Response $response, array $args): Response
    {
        $property = $this->loadProperty((int) ($args['id'] ?? 0));
        if ($property === null) {
            return $this->error($response, 'not_found', 'Immobile non trovato.', 404);
        }

        $data = $this->body($request);
        $pdf = $this->buildPdf($property, $this->str($data, 'presentation_text', 20000));

        $response->getBody()->write($pdf);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $this->filename($property) . '"');
    }

    public function send(Request $request, Response $response, array $args): Response
    {
        $property = $this->loadProperty((int) ($args['id'] ?? 0));
        if ($property === null) {
            return $this->error($response, 'not_found', 'Immobile non trovato.', 404);
        }

        $data = $this->body($request);
        $email = mb_strtolower($this->str($data, 'email') ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error($response, 'validation', 'Email destinatario non valida.', 422);
        }

        $pdf = $this->buildPdf($property, $this->str($data, 'presentation_text', 20000));

        $sent = $this->mailer->send(
            $email,
            'scheda_immobile',
            [
                'first_name' => $this->str($data, 'recipient_name', 100) ?? '',
                'property_title' => $property['title'],
                'custom_message' => $this->str($data, 'message', 2000) ?? '',
            ],
            'property',
            (int) $property['id'],
            [['name' => $this->filename($property), 'content' => $pdf]]
        );

        if (!$sent) {
            return $this->error($response, 'email_error', 'Invio email non riuscito: controlla la configurazione email del server.', 502);
        }

        $this->logger->info('Scheda immobile inviata', ['property_id' => $property['id'], 'to' => $email]);

        return $this->json($response, ['ok' => true, 'message' => "Scheda inviata a {$email}."]);
    }

    /** @return array<string, mixed>|null */
    private function loadProperty(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id = :id AND agency_id = 1');
        $stmt->execute(['id' => $id]);
        $property = $stmt->fetch();
        if (!$property) {
            return null;
        }

        $imgs = $this->pdo->prepare(
            'SELECT url FROM property_images WHERE property_id = :id ORDER BY sort_order, id LIMIT ' . self::MAX_PHOTOS
        );
        $imgs->execute(['id' => $id]);
        $property['image_paths'] = array_column($imgs->fetchAll(), 'url');

        return $property;
    }

    /** @param array<string, mixed> $property */
    private function filename(array $property): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $property['title']), '-'));
        return 'scheda-' . ($slug !== '' ? $slug : 'immobile') . '.pdf';
    }

    /** @param array<string, mixed> $property */
    private function buildPdf(array $property, ?string $presentationText): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->buildHtml($property, $presentationText));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Incorpora l'immagine come data URI (webp convertito in JPEG per dompdf).
     */
    private function imageDataUri(string $relativePath): ?string
    {
        $full = $this->uploads->resolve($relativePath);
        if ($full === null || !is_file($full)) {
            return null;
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if ($ext === 'webp') {
            $src = @imagecreatefromwebp($full);
            if ($src === false) {
                return null;
            }
            ob_start();
            imagejpeg($src, null, 82);
            $jpeg = (string) ob_get_clean();
            imagedestroy($src);
            return 'data:image/jpeg;base64,' . base64_encode($jpeg);
        }

        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($full));
    }

    /** @param array<string, mixed> $property */
    private function buildHtml(array $property, ?string $presentationText): string
    {
        $e = fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $title = $e($property['title']);
        $zone = $e(implode(', ', array_filter([$property['address'] ?? null, $property['city'] ?? null, $property['province'] ?? null])));
        $price = $property['price']
            ? '€ ' . number_format((float) $property['price'], 0, ',', '.')
            : 'Su richiesta';

        // Testo: AI (paragrafi + bullet "- ") o descrizione interna
        $text = trim($presentationText ?? '') !== '' ? (string) $presentationText : (string) ($property['description'] ?? '');
        $textHtml = '';
        foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $block) {
            $lines = array_filter(array_map('trim', explode("\n", $block)));
            $bullets = array_filter($lines, fn (string $l) => str_starts_with($l, '- ') || str_starts_with($l, '• '));
            if ($bullets !== [] && count($bullets) === count($lines)) {
                $items = implode('', array_map(fn (string $l) => '<li>' . $e(ltrim($l, '-• ')) . '</li>', $lines));
                $textHtml .= "<ul>{$items}</ul>";
            } else {
                $textHtml .= '<p>' . nl2br($e($block)) . '</p>';
            }
        }

        // Foto: la prima grande (cover), le altre in griglia
        $uris = array_values(array_filter(array_map(
            fn (string $p) => $this->imageDataUri($p),
            (array) $property['image_paths']
        )));
        $coverHtml = isset($uris[0]) ? '<img class="cover" src="' . $uris[0] . '" alt="">' : '';
        $gridHtml = '';
        foreach (array_slice($uris, 1) as $uri) {
            $gridHtml .= '<div class="ph"><img src="' . $uri . '" alt=""></div>';
        }
        if ($gridHtml !== '') {
            $gridHtml = '<div class="photos">' . $gridHtml . '</div>';
        }

        $rows = [
            'Tipologia' => ucfirst((string) $property['type']),
            'Superficie' => $property['surface_sqm'] ? $property['surface_sqm'] . ' mq' : null,
            'Locali' => $property['rooms'] ?: null,
            'Zona' => $zone !== '' ? $zone : null,
        ];
        $dataRows = '';
        foreach ($rows as $label => $value) {
            if ($value !== null && $value !== '') {
                $dataRows .= '<td class="cell"><span class="lbl">' . $label . '</span><span class="val">' . $e($value) . '</span></td>';
            }
        }

        return <<<HTML
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DejaVu Sans', sans-serif; color: #16273F; font-size: 11pt; }
  .band { background: #0E1B2E; color: #F6F2EA; padding: 22pt 34pt 18pt; }
  .band .rt { color: #C29B52; font-size: 9pt; letter-spacing: 3pt; text-transform: uppercase; }
  .band h1 { font-size: 21pt; font-weight: normal; margin-top: 6pt; }
  .band .zone { color: #DCC28C; font-size: 10pt; margin-top: 4pt; }
  .cover { width: 100%; height: 230pt; object-fit: cover; }
  .content { padding: 20pt 34pt; }
  .datatable { width: 100%; border-collapse: collapse; margin-bottom: 16pt; }
  .cell { border: 1pt solid #E4DDCE; padding: 8pt 10pt; text-align: center; }
  .lbl { display: block; color: #5C6B80; font-size: 8pt; text-transform: uppercase; letter-spacing: 1pt; }
  .val { display: block; font-size: 11pt; margin-top: 3pt; }
  .price { background: #F6F2EA; border: 1pt solid #C29B52; padding: 10pt 14pt; margin-bottom: 16pt; text-align: center; }
  .price .lbl { color: #5C6B80; }
  .price .val { color: #0E1B2E; font-size: 16pt; font-weight: bold; }
  p { margin-bottom: 8pt; line-height: 1.5; }
  ul { margin: 4pt 0 10pt 16pt; line-height: 1.55; }
  .photos { width: 100%; margin-top: 6pt; }
  .ph { display: inline-block; width: 48.5%; margin: 0 0.5% 6pt 0; }
  .ph img { width: 100%; height: 130pt; object-fit: cover; }
  .footer { background: #0E1B2E; color: #F6F2EA; padding: 14pt 34pt; font-size: 9pt; position: fixed; bottom: 0; left: 0; right: 0; }
  .footer .gold { color: #C29B52; }
  .content { padding-bottom: 70pt; }
</style>
</head>
<body>
  <div class="band">
    <div class="rt">RT &mdash; Roberto Tacchetto &middot; Real Estate Advisor</div>
    <h1>{$title}</h1>
    <div class="zone">{$zone}</div>
  </div>
  {$coverHtml}
  <div class="content">
    <table class="datatable"><tr>{$dataRows}</tr></table>
    <div class="price"><span class="lbl">Prezzo</span><span class="val">{$price}</span></div>
    {$textHtml}
    {$gridHtml}
  </div>
  <div class="footer">
    <span class="gold">Roberto Tacchetto</span> &middot; Real Estate Advisor &middot; Treviso e provincia
    &nbsp;&nbsp; Tel. +39 345 7771822 &nbsp;&nbsp; tacchettoimmobiliare.it
  </div>
</body>
</html>
HTML;
    }
}
