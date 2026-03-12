{{-- resources/views/emails/entreprise-validation.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $isApproved ? 'Entreprise validée' : 'Demande refusée' }} — CarEasy</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:#f1f5f9; color:#334155; }
    .wrap { max-width:620px; margin:0 auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.08); }
    .header-approved { background:linear-gradient(135deg,#10b981 0%,#059669 100%); padding:40px 32px; text-align:center; }
    .header-rejected { background:linear-gradient(135deg,#ef4444 0%,#b91c1c 100%); padding:40px 32px; text-align:center; }
    .header-icon { font-size:56px; margin-bottom:12px; }
    .header h1 { color:#fff; font-size:28px; font-weight:800; }
    .header p { color:rgba(255,255,255,.85); font-size:15px; margin-top:8px; }
    .body { padding:40px 32px; }
    h2 { font-size:22px; font-weight:700; color:#1e293b; margin-bottom:12px; }
    .lead { color:#64748b; font-size:16px; line-height:1.7; margin-bottom:28px; }
    .highlight-approved { background:#d1fae5; border-left:4px solid #10b981; border-radius:8px; padding:20px; margin:20px 0; }
    .highlight-rejected { background:#fee2e2; border-left:4px solid #ef4444; border-radius:8px; padding:20px; margin:20px 0; }
    .highlight-title { font-weight:700; font-size:15px; margin-bottom:8px; }
    .highlight-approved .highlight-title { color:#065f46; }
    .highlight-rejected .highlight-title { color:#991b1b; }
    .highlight-text { font-size:14px; line-height:1.6; }
    .highlight-approved .highlight-text { color:#064e3b; }
    .highlight-rejected .highlight-text { color:#7f1d1d; }
    .features { list-style:none; padding:0; margin:16px 0; }
    .features li { display:flex; align-items:center; gap:10px; padding:8px 0; font-size:14px; color:#1e293b; border-bottom:1px solid #e2e8f0; }
    .features li:last-child { border-bottom:none; }
    .features li::before { content:"✓"; color:#10b981; font-weight:700; font-size:16px; flex-shrink:0; }
    .cta { text-align:center; margin:32px 0; }
    .btn-approved { display:inline-block; background:linear-gradient(135deg,#10b981,#059669); color:#fff !important; text-decoration:none; padding:16px 40px; border-radius:10px; font-weight:700; font-size:16px; box-shadow:0 4px 14px rgba(16,185,129,.35); }
    .btn-rejected  { display:inline-block; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff !important; text-decoration:none; padding:16px 40px; border-radius:10px; font-weight:700; font-size:16px; box-shadow:0 4px 14px rgba(245,158,11,.35); }
    .admin-note { background:#fffbeb; border:1.5px solid #fbbf24; border-radius:10px; padding:18px; margin:20px 0; }
    .admin-note-title { font-weight:700; color:#92400e; font-size:13px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
    .admin-note-text { color:#78350f; font-size:14px; line-height:1.6; }
    .divider { height:1px; background:#e2e8f0; margin:24px 0; }
    .footer { background:#f8fafc; padding:24px 32px; text-align:center; border-top:1px solid #e2e8f0; }
    .footer-logo { font-size:22px; font-weight:800; color:{{ $isApproved ? '#10b981' : '#ef4444' }}; }
    .footer p { font-size:12px; color:#94a3b8; margin-top:6px; line-height:1.6; }
  </style>
</head>
<body>
<div class="wrap">
  <!-- Header -->
  <div class="{{ $isApproved ? 'header-approved' : 'header-rejected' }}">
    <div class="header-icon">{{ $isApproved ? '🎉' : '⚠️' }}</div>
    <h1>{{ $isApproved ? 'Entreprise validée !' : 'Demande refusée' }}</h1>
    <p>{{ $isApproved ? 'Votre activité peut démarrer sur CarEasy' : 'Votre demande nécessite des corrections' }}</p>
  </div>

  <div class="body">
    <h2>Bonjour {{ $userName }},</h2>

    @if($isApproved)
      <p class="lead">
        Excellente nouvelle ! Votre entreprise <strong>« {{ $entreprise->name }} »</strong> a été
        <strong style="color:#10b981">approuvée</strong> par notre équipe.
        Vous pouvez désormais profiter de toutes les fonctionnalités de la plateforme.
      </p>

      <div class="highlight-approved">
        <div class="highlight-title">🚀 Période d'essai gratuite activée</div>
        <div class="highlight-text">
          Votre essai gratuit de <strong>30 jours</strong> démarre maintenant.<br>
          Date de fin : <strong>{{ $entreprise->trial_ends_at?->format('d/m/Y') ?? 'N/A' }}</strong>
        </div>
      </div>

      <p style="font-weight:700;color:#1e293b;margin-bottom:12px">Ce que vous pouvez faire maintenant :</p>
      <ul class="features">
        <li>Créer jusqu'à {{ $entreprise->max_services_allowed }} services</li>
        <li>Configurer votre profil et boutique</li>
        <li>Recevoir des rendez-vous de clients</li>
        <li>Accéder à la messagerie intégrée</li>
        <li>Gérer vos rendez-vous depuis le tableau de bord</li>
      </ul>

    @else
      <p class="lead">
        Nous avons examiné votre demande d'enregistrement de l'entreprise
        <strong>« {{ $entreprise->name }} »</strong> et nous ne pouvons pas la valider
        pour le moment.
      </p>

      <div class="highlight-rejected">
        <div class="highlight-title">❌ Demande non approuvée</div>
        <div class="highlight-text">
          Votre dossier ne répond pas encore à nos critères de validation.
          Vous pouvez corriger les informations et soumettre une nouvelle demande.
        </div>
      </div>
    @endif

    @if($adminNote)
      <div class="admin-note">
        <div class="admin-note-title">📝 Note de l'administrateur</div>
        <div class="admin-note-text">{{ $adminNote }}</div>
      </div>
    @endif

    <!-- CTA -->
    <div class="cta">
      <a href="{{ $dashboardUrl }}" class="{{ $isApproved ? 'btn-approved' : 'btn-rejected' }}">
        {{ $isApproved ? '🏢 Accéder à mon espace' : '🔄 Corriger et re-soumettre' }}
      </a>
    </div>

    <div class="divider"></div>

    <p style="font-size:13px;color:#94a3b8;text-align:center">
      Des questions ? Contactez-nous à
      <a href="mailto:support@careasy.com" style="color:#3b82f6">support@careasy.com</a>
    </p>
  </div>

  <div class="footer">
    <div class="footer-logo">CarEasy</div>
    <p>Votre plateforme de services de confiance au Bénin<br>
    © {{ date('Y') }} CarEasy. Tous droits réservés.</p>
  </div>
</div>
</body>
</html>