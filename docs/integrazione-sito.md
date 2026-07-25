# Integrazione form sito vetrina → RT CASA LIVE

Il form contatti del sito vetrina (oggi un `mailto:`) va sostituito con una
chiamata all'endpoint pubblico del backend:

```
POST https://tacchettoimmobiliare.it/api/public/leads
Content-Type: application/json
```

## Campi accettati

| Campo | Obbligatorio | Note |
|-------|--------------|------|
| `first_name` | ✅ | max 100 caratteri |
| `last_name` | ✅ | max 100 caratteri |
| `email` | ⚠️ | serve email **o** telefono |
| `phone` | ⚠️ | serve email **o** telefono |
| `request_type` | no | `vendere` \| `ereditato` \| `altro` (default `vendere`) |
| `message` | no | max 3000 caratteri |
| `source` | no | `sito` \| `qr` \| `social` \| `referral` \| `altro` (default `sito`) |
| `website` | **honeypot** | campo nascosto: deve restare VUOTO. Se un bot lo compila la richiesta viene scartata in silenzio |

Risposte: `201` `{ok:true}` · `422` errori di validazione con dettaglio campi · `429` rate limit (max 5 invii/ora per IP).

## Snippet da incollare nel sito

HTML del form (aggiungere il campo honeypot invisibile):

```html
<form id="contact-form">
  <input name="first_name" placeholder="Nome" required />
  <input name="last_name" placeholder="Cognome" required />
  <input name="email" type="email" placeholder="Email" />
  <input name="phone" type="tel" placeholder="Telefono" />
  <select name="request_type">
    <option value="vendere">Voglio vendere casa</option>
    <option value="ereditato">Ho ereditato un immobile</option>
    <option value="altro">Altro</option>
  </select>
  <textarea name="message" placeholder="Il tuo messaggio"></textarea>
  <!-- honeypot: NON rimuovere, deve restare invisibile e vuoto -->
  <input name="website" type="text" tabindex="-1" autocomplete="off"
         style="position:absolute;left:-9999px" aria-hidden="true" />
  <button type="submit">Invia richiesta</button>
  <p id="form-feedback" role="status"></p>
</form>
```

JavaScript:

```html
<script>
document.getElementById('contact-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const feedback = document.getElementById('form-feedback');
  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  feedback.textContent = 'Invio in corso…';

  try {
    const res = await fetch('https://tacchettoimmobiliare.it/api/public/leads', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.fromEntries(new FormData(form))),
    });
    const data = await res.json();

    if (res.ok) {
      feedback.textContent = data.message || 'Grazie! Ti ricontatteremo al più presto.';
      form.reset();
    } else if (res.status === 422) {
      feedback.textContent = 'Controlla i campi: ' +
        Object.values(data.error.fields || {}).join(' ');
    } else {
      feedback.textContent = data.error?.message || 'Si è verificato un errore. Riprova.';
    }
  } catch {
    feedback.textContent = 'Connessione non riuscita. Riprova oppure chiama +39 345 7771822.';
  } finally {
    btn.disabled = false;
  }
});
</script>
```

## Note

- CORS: i domini del sito vetrina sono già ammessi via env `CORS_ALLOWED_ORIGINS`.
- Ogni lead genera una email di notifica a Roberto (`nuovo_lead_admin`).
- Autoresponder al lead attivabile con env `LEAD_AUTORESPONDER=true`.
- Per i QR code nei cartelli vetrina usare l'URL del sito con `?src=qr` e passare `source: 'qr'` nel payload.
