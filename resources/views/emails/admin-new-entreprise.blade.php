{{-- resources/views/emails/admin-new-entreprise.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nouvelle demande entreprise — CarEasy Admin</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:#f1f5f9; color:#334155; }
    .wrap { max-width:620px; margin:0 auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.08); }
    .header { background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); padding:36px 32px; text-align:center; }
    .header-icon { font-size:48px; margin-bottom:8px; }
    .header h1 { color:#fff; font-size:26px; font-weight:800; }
    .header p { color:rgba(255,255,255,.85); font-size:14px; margin-top:6px; }
    .body { padding:36px 32px; }
    .alert-badge { display:inline-flex; align-items:center; gap:8px; background:#fef3c7; color:#92400e; border:1.5px solid #fbbf24; padding:10px 18px; border-radius:999px; font-weight:700; font-size:13px; margin-bottom:24px; }
    h2 { font-size:22px; font-weight:700; color:#1e293b; margin-bottom:16px; }
    .info-card { background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px; }
    .info-row { display:flex; justify-content:space-between; align-items:flex-start; padding:10px 0; border-bottom:1px solid #e2e8f0; }
    .info-row:last-child { border-bottom:none; padding-bottom:0; }
    .info-label { font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
    .info-value { font-size:14px; color:#1e293b; font-weight:600; text-align:right; max-width:60%; }
    .status-badge { display:inline-flex; align-items:center; gap:6px; background:#fef3c7; color:#92400e; padding:4px 14px; border-radius:999px; font-size:12px; font-weight:700; }
    .cta { text-align:center; margin:28px 0; }
    .btn { display:inline-block; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff !important; text-decoration:none; padding:16px 40px; border-radius:10px; font-weight:700; font-size:16px; box-shadow:0 4px 14px rgba(245,158,11,.35); }
    .note { background:#fffbeb; border-left:4px solid #f59e0b; border-radius:8px; padding:16px; margin-top:20px; font-size:13px; color:#78350f; }
    .footer { background:#f8fafc; padding:24px 32px; text-align:center; border-top:1px solid #e2e8f0; }
    .footer-logo { font-size:20px; font-weight:800; color:#f59e0b; }
    .footer p { font-size:12px; color:#94a3b8; margin-top:6px; }
  </style>
</head>
<body>
<div class="wrap">
  <!-- Header -->
  <div class="header">
    <div class="header-icon">🔔</div>
    <h1>Nouvelle demande d'entreprise</h1>
    <p>Action requise — Validation en attente</p>
  </div>

  <div class="body">
    <div class="alert-badge">⚠️ Validation requise</div>

    <h2>Bonjour Administrateur,</h2>
    <p style="color:#64748b;margin-bottom:24px;line-height:1.7">
      Un prestataire vient de soumettre une nouvelle demande d'enregistrement d'entreprise sur la plateforme CarEasy.
      Votre validation est requise pour activer cette entreprise.
    </p>

    <!-- Infos entreprise -->
    <div class="info-card">
      <div style="font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">🏢 Informations Entreprise</div>
      <div class="info-row">
        <span class="info-label">Nom</span>
        <span class="info-value">{{ $entreprise->name }}</span>
      </div>
      @if($entreprise->ifu_number)
      <div class="info-row">
        <span class="info-label">IFU</span>
        <span class="info-value">{{ $entreprise->ifu_number }}</span>
      </div>
      @endif
      @if($entreprise->rccm_number)
      <div class="info-row">
        <span class="info-label">RCCM</span>
        <span class="info-value">{{ $entreprise->rccm_number }}</span>
      </div>
      @endif
      @if($entreprise->siege)
      <div class="info-row">
        <span class="info-label">Siège</span>
        <span class="info-value">{{ $entreprise->siege }}</span>
      </div>
      @endif
      <div class="info-row">
        <span class="info-label">Statut</span>
        <span class="status-badge">⏳ En attente</span>
      </div>
      <div class="info-row">
        <span class="info-label">Soumis le</span>
        <span class="info-value">{{ $entreprise->created_at->format('d/m/Y à H:i') }}</span>
      </div>
    </div>

    <!-- Infos prestataire -->
    <div class="info-card">
      <div style="font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">👤 Prestataire</div>
      <div class="info-row">
        <span class="info-label">Nom</span>
        <span class="info-value">{{ $prestataire->name }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Email</span>
        <span class="info-value">{{ $prestataire->email }}</span>
      </div>
      @if($prestataire->phone)
      <div class="info-row">
        <span class="info-label">Téléphone</span>
        <span class="info-value">{{ $prestataire->phone }}</span>
      </div>
      @endif
      <div class="info-row">
        <span class="info-label">Inscrit le</span>
        <span class="info-value">{{ $prestataire->created_at->format('d/m/Y') }}</span>
      </div>
    </div>

    <!-- CTA -->
    <div class="cta">
      <a href="{{ $adminUrl }}" class="btn">🔍 Examiner la demande</a>
    </div>

    <div class="note">
      <strong>Rappel :</strong> Vérifiez les documents IFU, RCCM et le certificat avant de valider.
      En cas de validation, la période d'essai gratuite de 30 jours sera automatiquement activée.
    </div>
  </div>

  <div class="footer">
    <div class="footer-logo">CarEasy Admin</div>
    <p>Notification automatique — Ne pas répondre à cet email<br>
    © {{ date('Y') }} CarEasy — Bénin</p>
  </div>
</div>
</body>
</html>