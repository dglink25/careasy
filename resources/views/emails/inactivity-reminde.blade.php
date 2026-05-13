<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vous nous manquez — CarEasy</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
  <tr>
    <td align="center">
      <table width="520" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:18px;overflow:hidden;
                    box-shadow:0 4px 24px rgba(0,0,0,0.09);">

        {{-- ── HEADER ──────────────────────────────────────────────── --}}
        <tr>
          <td style="background:linear-gradient(135deg,#E63946,#FF6B6B);
                     padding:36px 40px;text-align:center;">
            <div style="font-size:38px;font-weight:900;color:#fff;
                        letter-spacing:2px;">CarEasy</div>
            <div style="color:rgba(255,255,255,0.88);font-size:14px;
                        margin-top:8px;">
              Votre plateforme automobile au Bénin
            </div>
          </td>
        </tr>

        {{-- ── BODY ────────────────────────────────────────────────── --}}
        <tr>
          <td style="padding:16px 40px 32px;">

            <h2 style="margin:0 0 16px;font-size:22px;color:#2D3436;
                       font-weight:800;text-align:center;">
              {{ $firstName }}, vous nous manquez !
            </h2>

            <p style="font-size:15px;color:#636e72;line-height:1.7;margin:0 0 20px;">
              Bonjour <strong>{{ $userName }}</strong>,
            </p>

            <p style="font-size:15px;color:#636e72;line-height:1.7;margin:0 0 24px;">
              Nous avons remarqué que vous n'avez pas visité votre compte
              <strong style="color:#E63946;">CarEasy</strong> depuis
              <strong>{{ $inactiveDays }} jours</strong>.
              Votre compte est toujours actif et prêt à vous servir !
            </p>

            {{-- ── SERVICES CARD ────────────────────────────────────── --}}
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#FFF5F5;border-radius:12px;
                          border-left:4px solid #E63946;margin:0 0 28px;">
              <tr>
                <td style="padding:20px 24px;">
                  <p style="margin:0 0 12px;font-size:14px;font-weight:700;
                             color:#E63946;">
                     Ce qui vous attend sur CarEasy
                  </p>
                  <table cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding:4px 0;font-size:14px;color:#2D3436;">
                        &nbsp;Garages &amp; mécaniciens certifiés
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;font-size:14px;color:#2D3436;">
                         &nbsp;Vulcanisateurs près de chez vous
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;font-size:14px;color:#2D3436;">
                         &nbsp;Lavage &amp; entretien automobile
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;font-size:14px;color:#2D3436;">
                         &nbsp;Prise de rendez-vous en ligne
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:4px 0;font-size:14px;color:#2D3436;">
                         &nbsp;Prestataires notés &amp; vérifiés
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            {{-- ── CTA BUTTON ───────────────────────────────────────── --}}
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center" style="padding:0 0 24px;">
                  <a href="{{ $loginUrl }}"
                     style="display:inline-block;background:linear-gradient(135deg,#E63946,#FF6B6B);
                            color:#fff;text-decoration:none;padding:16px 48px;
                            border-radius:50px;font-size:16px;font-weight:700;
                            letter-spacing:0.5px;box-shadow:0 4px 15px rgba(230,57,70,0.35);">
                     &nbsp;Me reconnecter maintenant
                  </a>
                </td>
              </tr>
            </table>

            {{-- ── RAPPEL NUMBER ────────────────────────────────────── --}}
            @if($reminderNumber > 1)
            <p style="font-size:12px;color:#b2bec3;text-align:center;margin:0 0 16px;">
              Rappel {{ $reminderNumber }} / 3
            </p>
            @endif

            <p style="font-size:13px;color:#b2bec3;line-height:1.6;margin:0;">
              Si vous souhaitez supprimer votre compte ou avez besoin d'aide,
              contactez-nous à
              <a href="mailto:careasy26@gmail.com"
                 style="color:#E63946;text-decoration:none;">careasy26@gmail.com</a>.
            </p>

          </td>
        </tr>

        {{-- ── FOOTER ──────────────────────────────────────────────── --}}
        <tr>
          <td style="background:#f8f9fa;padding:20px 40px;text-align:center;
                     border-top:1px solid #f0f0f0;">
            <p style="font-size:12px;color:#b2bec3;margin:0 0 6px;">
              Vous recevez ce message car votre compte CarEasy est inactif.
            </p>
            <p style="font-size:12px;color:#b2bec3;margin:0;">
              © {{ date('Y') }} CarEasy · Bénin 🇧🇯 · Tous droits réservés
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>