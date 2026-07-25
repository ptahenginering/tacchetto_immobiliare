<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Mail\EmailTemplates;
use InvalidArgumentException;

final class EmailTemplatesTest extends TestCase
{
    private EmailTemplates $templates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templates = new EmailTemplates('https://tacchettoimmobiliare.it');
    }

    public function testAllTemplatesRender(): void
    {
        $keys = [
            'magic_link', 'nuovo_lead_admin', 'benvenuto_proprietario', 'nuova_visita',
            'nuovo_feedback', 'nuova_proposta', 'report_settimanale', 'promemoria_appuntamento',
            'step_completato', 'lead_autoresponder',
        ];

        foreach ($keys as $key) {
            $result = $this->templates->render($key, [
                'first_name' => 'Marco',
                'link' => 'https://tacchettoimmobiliare.it/app/access?token=x',
                'lead_name' => 'Giulia Sartori',
                'step_label' => 'APE',
                'when' => '26/07/2026 · 15:00',
            ]);

            self::assertNotSame('', $result['subject'], "Subject vuoto per {$key}");
            self::assertStringContainsString('CASA', $result['html'], "Layout brand assente in {$key}");
            self::assertStringContainsString('Roberto Tacchetto', $result['html']);
        }
    }

    public function testHtmlIsEscaped(): void
    {
        $result = $this->templates->render('nuovo_lead_admin', [
            'lead_name' => '<script>alert(1)</script>',
            'source' => 'sito',
            'request_type' => 'vendere',
        ]);

        self::assertStringNotContainsString('<script>', $result['html']);
        self::assertStringContainsString('&lt;script&gt;', $result['html']);
    }

    public function testProposalEmailNeverContainsAmount(): void
    {
        // Regola §6: mai importi nelle email
        $result = $this->templates->render('nuova_proposta', ['first_name' => 'Marco', 'amount' => '255000']);
        self::assertStringNotContainsString('255000', $result['html']);
        self::assertStringNotContainsString('255.000', $result['html']);
    }

    public function testUnknownTemplateThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->templates->render('template_inesistente', []);
    }
}
