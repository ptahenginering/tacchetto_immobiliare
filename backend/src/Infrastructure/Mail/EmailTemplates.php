<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use InvalidArgumentException;

/**
 * Template email HTML brandizzati RT CASA LIVE (navy/oro/avorio, responsive).
 * Regola: mai dati sensibili di terzi né importi nelle email — si invita
 * ad aprire l'app (deep link alla pagina giusta).
 */
final class EmailTemplates
{
    public function __construct(private readonly string $appUrl)
    {
    }

    /**
     * @param array<string, mixed> $vars
     * @return array{subject: string, html: string}
     */
    public function render(string $templateKey, array $vars): array
    {
        $name = $this->e((string) ($vars['first_name'] ?? ''));
        $app = rtrim($this->appUrl, '/');

        switch ($templateKey) {
            case 'magic_link':
                $link = (string) ($vars['link'] ?? $app . '/app/');
                $minutes = (int) ($vars['minutes'] ?? 30);
                return [
                    'subject' => 'Il tuo accesso a RT CASA LIVE',
                    'html' => $this->layout(
                        'Il tuo accesso',
                        "<p>Ciao {$name},</p>
                         <p>ecco il tuo link personale per entrare nella tua area riservata RT CASA LIVE
                         e seguire in tempo reale la vendita della tua casa.</p>"
                        . $this->button('Entra nella tua area', $link)
                        . "<p style=\"font-size:13px;color:#5C6B80\">Il link è valido per {$minutes} minuti e può essere usato una sola volta.
                           Se non hai richiesto tu l'accesso, ignora questa email.</p>"
                    ),
                ];

            case 'nuovo_lead_admin':
                $leadName = $this->e((string) ($vars['lead_name'] ?? ''));
                $source = $this->e((string) ($vars['source'] ?? 'sito'));
                $requestType = $this->e((string) ($vars['request_type'] ?? ''));
                return [
                    'subject' => "Nuovo contatto dal sito: {$leadName}",
                    'html' => $this->layout(
                        'Nuovo contatto',
                        "<p>È arrivata una nuova richiesta.</p>
                         <table cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;margin:16px 0;font-size:15px\">
                           <tr><td style=\"padding:6px 0;color:#5C6B80\">Nome</td><td style=\"padding:6px 0\"><strong>{$leadName}</strong></td></tr>
                           <tr><td style=\"padding:6px 0;color:#5C6B80\">Tipo richiesta</td><td style=\"padding:6px 0\">{$requestType}</td></tr>
                           <tr><td style=\"padding:6px 0;color:#5C6B80\">Fonte</td><td style=\"padding:6px 0\">{$source}</td></tr>
                         </table>"
                        . $this->button('Apri il gestionale', $app . '/admin/')
                    ),
                ];

            case 'benvenuto_proprietario':
                $link = (string) ($vars['link'] ?? $app . '/app/');
                return [
                    'subject' => 'Benvenuto in RT CASA LIVE — la tua casa, sotto controllo',
                    'html' => $this->layout(
                        'Benvenuto',
                        "<p>Ciao {$name},</p>
                         <p>da oggi hai un posto tutto tuo dove seguire, passo dopo passo, la vendita della tua casa:
                         visite, riscontri, proposte, promozione e pratiche. <strong>Sempre informato, sempre sereno.</strong></p>"
                        . $this->button('Entra nella tua area', $link)
                        . "<p style=\"font-size:13px;color:#5C6B80\">Il link è personale: non condividerlo. Potrai richiederne uno nuovo in ogni momento con la tua email.</p>"
                    ),
                ];

            case 'nuova_visita':
                return [
                    'subject' => 'Nuova visita per la tua casa',
                    'html' => $this->layout(
                        'Nuova visita',
                        "<p>Ciao {$name},</p>
                         <p>c'è stata una nuova visita al tuo immobile. Trovi i dettagli nella tua area riservata.</p>"
                        . $this->button('Vedi le visite', $app . '/app/visite')
                    ),
                ];

            case 'nuovo_feedback':
                return [
                    'subject' => 'Nuovo riscontro da una visita',
                    'html' => $this->layout(
                        'Nuovo riscontro',
                        "<p>Ciao {$name},</p>
                         <p>abbiamo raccolto il parere di chi ha visitato la tua casa. Leggilo nella tua area riservata:
                         la trasparenza è la base del nostro lavoro.</p>"
                        . $this->button('Leggi il riscontro', $app . '/app/visite')
                    ),
                ];

            case 'nuova_proposta':
                return [
                    'subject' => 'Hai ricevuto una proposta per la tua casa',
                    'html' => $this->layout(
                        'Nuova proposta',
                        "<p>Ciao {$name},</p>
                         <p>è arrivata una proposta per il tuo immobile. Ogni proposta viene discussa personalmente con Roberto:
                         intanto puoi vederla nella tua area riservata.</p>"
                        . $this->button('Vedi la proposta', $app . '/app/proposte')
                    ),
                ];

            case 'report_settimanale':
                $visits = (int) ($vars['visits_7d'] ?? 0);
                $appointments = (int) ($vars['appointments_next'] ?? 0);
                $proposals = (int) ($vars['proposals_active'] ?? 0);
                return [
                    'subject' => 'La tua settimana RT CASA LIVE',
                    'html' => $this->layout(
                        'Il punto della settimana',
                        "<p>Ciao {$name},</p>
                         <p>ecco come sta andando la vendita della tua casa:</p>
                         <table cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;margin:16px 0\">
                           <tr>
                             " . $this->kpiCell((string) $visits, 'Visite negli ultimi 7 giorni') . "
                             " . $this->kpiCell((string) $appointments, 'Appuntamenti in programma') . "
                             " . $this->kpiCell((string) $proposals, 'Proposte in corso') . "
                           </tr>
                         </table>
                         <p>Tutti i dettagli, i riscontri e le attività di promozione ti aspettano nella tua area.</p>"
                        . $this->button('Apri RT CASA LIVE', $app . '/app/')
                    ),
                ];

            case 'step_completato':
                $stepLabel = $this->e((string) ($vars['step_label'] ?? 'Uno step della pratica'));
                return [
                    'subject' => 'Un passo avanti per la tua pratica',
                    'html' => $this->layout(
                        'Pratica aggiornata',
                        "<p>Ciao {$name},</p>
                         <p>abbiamo completato: <strong>{$stepLabel}</strong>.</p>
                         <p>Ci occupiamo noi di ogni passaggio — puoi seguire l'avanzamento completo nella tua area.</p>"
                        . $this->button('Vedi le pratiche', $app . '/app/pratiche')
                    ),
                ];

            case 'lead_autoresponder':
                return [
                    'subject' => 'Abbiamo ricevuto la tua richiesta',
                    'html' => $this->layout(
                        'Richiesta ricevuta',
                        "<p>Ciao {$name},</p>
                         <p>grazie per averci scritto. Roberto ti ricontatterà personalmente al più presto
                         per capire insieme come valorizzare al meglio il tuo immobile.</p>
                         <p style=\"font-size:13px;color:#5C6B80\">Se hai urgenza puoi chiamare direttamente:
                         <a href=\"tel:+393457771822\" style=\"color:#C29B52\">+39 345 7771822</a></p>"
                    ),
                ];

            case 'promemoria_appuntamento':
                $when = $this->e((string) ($vars['when'] ?? ''));
                $type = $this->e((string) ($vars['type'] ?? 'appuntamento'));
                $address = $this->e((string) ($vars['address'] ?? ''));
                $addressRow = $address !== ''
                    ? "<tr><td style=\"padding:6px 0;color:#5C6B80\">Dove</td><td style=\"padding:6px 0\">{$address}</td></tr>"
                    : '';
                return [
                    'subject' => 'Promemoria: appuntamento di domani',
                    'html' => $this->layout(
                        'Promemoria appuntamento',
                        "<p>Ciao {$name},</p>
                         <p>ti ricordiamo l'appuntamento di domani:</p>
                         <table cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;margin:16px 0;font-size:15px\">
                           <tr><td style=\"padding:6px 0;color:#5C6B80\">Tipo</td><td style=\"padding:6px 0\"><strong>{$type}</strong></td></tr>
                           <tr><td style=\"padding:6px 0;color:#5C6B80\">Quando</td><td style=\"padding:6px 0\"><strong>{$when}</strong></td></tr>
                           {$addressRow}
                         </table>
                         <p style=\"font-size:13px;color:#5C6B80\">Per qualsiasi necessità: Roberto Tacchetto — <a href=\"tel:+393457771822\" style=\"color:#C29B52\">+39 345 7771822</a></p>"
                    ),
                ];

            case 'scheda_immobile':
                $propertyTitle = $this->e((string) ($vars['property_title'] ?? 'immobile'));
                $customMessage = trim((string) ($vars['custom_message'] ?? ''));
                $messageBlock = $customMessage !== ''
                    ? '<p style="border-left:3px solid #C29B52;padding-left:14px;color:#16273F">' . nl2br($this->e($customMessage)) . '</p>'
                    : '';
                $greeting = $name !== '' ? "Gentile {$name}," : 'Gentile cliente,';
                return [
                    'subject' => "Scheda immobile: {$propertyTitle}",
                    'html' => $this->layout(
                        'Scheda immobile',
                        "<p>{$greeting}</p>
                         <p>in allegato trovi la scheda di presentazione dell'immobile
                         <strong>{$propertyTitle}</strong> con foto, caratteristiche e descrizione completa.</p>
                         {$messageBlock}
                         <p>Per fissare una visita o per qualsiasi domanda sono a tua disposizione.</p>
                         <p style=\"font-size:13px;color:#5C6B80\">Roberto Tacchetto — Real Estate Advisor ·
                         <a href=\"tel:+393457771822\" style=\"color:#C29B52\">+39 345 7771822</a></p>"
                    ),
                ];

            default:
                throw new InvalidArgumentException("Template email sconosciuto: {$templateKey}");
        }
    }

    private function layout(string $title, string $content): string
    {
        $title = $this->e($title);
        return <<<HTML
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#F6F2EA;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#16273F">
  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:#F6F2EA;padding:24px 0">
    <tr><td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;background:#FBF9F4;border-radius:14px;overflow:hidden;box-shadow:0 24px 60px rgba(14,27,46,.18)">
        <tr>
          <td style="background:#0E1B2E;padding:28px 32px;text-align:center">
            <div style="display:inline-block;width:52px;height:52px;line-height:52px;border-radius:12px;background:#16273F;border:1px solid rgba(194,155,82,.5)">
              <span style="font-family:Georgia,'Times New Roman',serif;font-size:24px"><span style="color:#F6F2EA">R</span><span style="color:#C29B52;margin-left:-6px">T</span></span>
            </div>
            <div style="margin-top:10px;color:#F6F2EA;font-size:13px;letter-spacing:.28em;text-transform:uppercase">CASA <span style="color:#C29B52">LIVE</span></div>
          </td>
        </tr>
        <tr>
          <td style="padding:32px">
            <div style="text-align:center;color:#C29B52;font-size:11px;letter-spacing:.22em;text-transform:uppercase;margin-bottom:18px">&mdash;&nbsp; {$title} &nbsp;&mdash;</div>
            <div style="font-size:15px;line-height:1.65">{$content}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px;border-top:1px solid rgba(194,155,82,.3);text-align:center;font-size:12px;color:#5C6B80">
            <strong style="color:#16273F">Roberto Tacchetto</strong> — Real Estate Advisor<br>
            <a href="tel:+393457771822" style="color:#C29B52;text-decoration:none">+39 345 7771822</a> &middot;
            <a href="mailto:info@rtimmobiliare.it" style="color:#C29B52;text-decoration:none">info@rtimmobiliare.it</a><br>
            <span style="letter-spacing:.14em;text-transform:uppercase;font-size:10px">Trasparenza &middot; Controllo &middot; Risultati</span>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    private function button(string $label, string $url): string
    {
        $label = $this->e($label);
        $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto">
  <tr><td style="border-radius:999px;background:linear-gradient(135deg,#D3AE66,#B98F45);background-color:#C29B52">
    <a href="{$url}" style="display:inline-block;padding:13px 34px;color:#0E1B2E;font-weight:600;text-decoration:none;border-radius:999px">{$label}</a>
  </td></tr>
</table>
HTML;
    }

    private function kpiCell(string $value, string $label): string
    {
        $value = $this->e($value);
        $label = $this->e($label);
        return <<<HTML
<td align="center" style="padding:12px 6px;background:#FFFFFF;border:1px solid rgba(194,155,82,.3);border-radius:14px">
  <div style="font-family:Georgia,serif;font-size:28px;color:#16273F">{$value}</div>
  <div style="font-size:11px;color:#5C6B80;margin-top:4px">{$label}</div>
</td>
HTML;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
