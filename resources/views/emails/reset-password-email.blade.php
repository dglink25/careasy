<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe - CarEasy</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8fafc;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .header {
            background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .logo {
            font-size: 36px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }
        
        .tagline {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 500;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
        }
        
        .message {
            color: #4b5563;
            font-size: 16px;
            margin-bottom: 15px;
            line-height: 1.8;
        }
        
        .highlight-box {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        
        .highlight-text {
            color: #991b1b;
            font-weight: 600;
            font-size: 15px;
        }
        
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            transition: all 0.3s;
        }
        
        .reset-button:hover {
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }
        
        .timer-icon {
            display: inline-block;
            margin-right: 8px;
        }
        
        .security-section {
            background-color: #eff6ff;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .security-title {
            color: #1e40af;
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .security-icon {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .security-tips {
            list-style: none;
            padding-left: 0;
        }
        
        .security-tips li {
            color: #1e40af;
            margin-bottom: 10px;
            padding-left: 25px;
            position: relative;
        }
        
        .security-tips li:before {
            content: "✓";
            position: absolute;
            left: 0;
            font-weight: bold;
            color: #10b981;
        }
        
        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 30px 0;
        }
        
        .link-fallback {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            word-break: break-all;
        }
        
        .link-fallback-title {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .link-fallback a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 13px;
        }
        
        .footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer-logo {
            font-size: 24px;
            font-weight: 800;
            color: #ef4444;
            margin-bottom: 10px;
        }
        
        .footer-text {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .footer-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        
        .social-links {
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
        }
        
        .warning-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .warning-text {
            color: #92400e;
            font-size: 14px;
        }
        
        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px;
            }
            
            .greeting {
                font-size: 20px;
            }
            
            .reset-button {
                padding: 14px 30px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">CarEasy</div>
            <div class="tagline">Votre partenaire automobile de confiance au Bénin</div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="greeting">Bonjour {{ $userName }} ! 👋</div>
            
            <p class="message">
                Vous recevez cet email car nous avons reçu une <strong>demande de réinitialisation de mot de passe</strong> pour votre compte CarEasy.
            </p>
            
            <p class="message">
                Pour créer un nouveau mot de passe sécurisé, cliquez sur le bouton ci-dessous :
            </p>
            
            <!-- Button -->
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button">
                    🔑 Réinitialiser mon mot de passe
                </a>
            </div>
            
            <!-- Timer Warning -->
            <div class="highlight-box">
                <p class="highlight-text">
                    <span class="timer-icon">⏰</span>
                    Ce lien est valide pendant <strong>60 minutes</strong>.
                </p>
            </div>
            
            <!-- Warning -->
            <div class="warning-box">
                <p class="warning-text">
                    <strong>⚠️ Vous n'avez pas demandé cette réinitialisation ?</strong><br>
                    Aucune action n'est requise. Votre mot de passe actuel reste inchangé et sécurisé.
                </p>
            </div>
            
            <!-- Security Tips -->
            <div class="security-section">
                <div class="security-title">
                    <span class="security-icon">🔒</span>
                    Conseils de sécurité
                </div>
                <ul class="security-tips">
                    <li>Utilisez un mot de passe unique et complexe</li>
                    <li>Combinez lettres majuscules, minuscules, chiffres et symboles</li>
                    <li>Ne partagez jamais votre mot de passe avec qui que ce soit</li>
                    <li>Activez la vérification en deux étapes si disponible</li>
                </ul>
            </div>
            
            <div class="divider"></div>
            
            <!-- Link Fallback -->
            <div class="link-fallback">
                <div class="link-fallback-title">Si le bouton ne fonctionne pas, copiez ce lien :</div>
                <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">CarEasy</div>
            <p class="footer-text">Votre plateforme automobile de confiance au Bénin</p>
            <p class="footer-text">
                Des questions ? Contactez-nous à 
                <a href="mailto:support@careasy.com" class="footer-link">support@careasy.com</a>
            </p>
            
            <div class="social-links">
                <a href="#">Facebook</a> • 
                <a href="#">Twitter</a> • 
                <a href="#">Instagram</a>
            </div>
            
            <p class="footer-text" style="margin-top: 20px; font-size: 12px;">
                © {{ date('Y') }} CarEasy. Tous droits réservés.<br>
                Cotonou, Bénin
            </p>
        </div>
    </div>
</body>
</html>