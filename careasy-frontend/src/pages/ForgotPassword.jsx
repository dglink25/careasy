import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/axios';
import Logo from '../components/Logo';
import theme from '../config/theme';

export default function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      const response = await api.post('/forgot-password', { email });
      setSuccess(response.data.status || 'Un email de réinitialisation a été envoyé avec succès !');
      setEmail('');
    } catch (err) {
      setError(
        err.response?.data?.message || 
        err.response?.data?.errors?.email?.[0] ||
        'Une erreur est survenue. Vérifiez votre adresse email.'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={styles.container}>
      <div style={styles.card}>
        {/* Logo */}
        <div style={styles.logoContainer}>
          <Logo size="lg" showText={true} />
        </div>

        <h2 style={styles.title}>Mot de passe oublié ?</h2>
        <p style={styles.subtitle}>
          Pas de souci ! Entrez votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe.
        </p>

        {error && (
          <div style={styles.error}>
            ⚠️ {error}
          </div>
        )}

        {success && (
          <div style={styles.success}>
            <div style={styles.successIcon}>✅</div>
            <div>
              <p style={styles.successTitle}>Email envoyé !</p>
              <p style={styles.successText}>{success}</p>
              <p style={styles.successHint}>Vérifiez votre boîte de réception et vos spams.</p>
            </div>
          </div>
        )}

        <form onSubmit={handleSubmit} style={styles.form}>
          <div style={styles.formGroup}>
            <label style={styles.label}>Adresse email</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              style={styles.input}
              placeholder="votre@email.com"
            />
          </div>

          <button 
            type="submit" 
            disabled={loading}
            style={{...styles.button, opacity: loading ? 0.6 : 1}}
          >
            {loading ? 'Envoi en cours...' : 'Envoyer le lien de réinitialisation'}
          </button>
        </form>

        <div style={styles.divider} />

        <div style={styles.footer}>
          <Link to="/login" style={styles.backLink}>
            ← Retour à la connexion
          </Link>
        </div>

        <div style={styles.helpBox}>
          <p style={styles.helpTitle}>💡 Besoin d'aide ?</p>
          <p style={styles.helpText}>
            Si vous ne recevez pas l'email dans les 5 minutes, vérifiez vos spams ou contactez le support.
          </p>
        </div>
      </div>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.background,
    padding: '2rem 1rem',
  },
  card: {
    backgroundColor: theme.colors.secondary,
    padding: '2.5rem',
    borderRadius: theme.borderRadius.xl,
    boxShadow: theme.shadows.xl,
    width: '100%',
    maxWidth: '500px',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  logoContainer: {
    display: 'flex',
    justifyContent: 'center',
    marginBottom: '1.5rem',
  },
  title: {
    fontSize: '2rem',
    fontWeight: 'bold',
    marginBottom: '0.75rem',
    textAlign: 'center',
    color: theme.colors.text.primary,
  },
  subtitle: {
    color: theme.colors.text.secondary,
    textAlign: 'center',
    marginBottom: '1.5rem',
    fontSize: '0.95rem',
    lineHeight: '1.5',
  },
  error: {
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.error,
    padding: '0.875rem',
    borderRadius: theme.borderRadius.md,
    marginBottom: '1rem',
    border: `1px solid ${theme.colors.error}`,
    fontSize: '0.95rem',
  },
  success: {
    backgroundColor: '#D1FAE5',
    border: `2px solid ${theme.colors.success}`,
    borderRadius: theme.borderRadius.md,
    padding: '1rem',
    marginBottom: '1.5rem',
    display: 'flex',
    gap: '1rem',
    alignItems: 'flex-start',
  },
  successIcon: {
    fontSize: '1.5rem',
    flexShrink: 0,
  },
  successTitle: {
    fontWeight: 'bold',
    color: theme.colors.success,
    marginBottom: '0.25rem',
  },
  successText: {
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
    fontSize: '0.95rem',
  },
  successHint: {
    color: theme.colors.text.secondary,
    fontSize: '0.85rem',
    fontStyle: 'italic',
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.25rem',
  },
  formGroup: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  label: {
    fontWeight: '600',
    color: theme.colors.text.primary,
    fontSize: '0.95rem',
  },
  input: {
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    transition: 'all 0.3s',
    outline: 'none',
  },
  button: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '0.875rem',
    border: 'none',
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.3s',
    boxShadow: theme.shadows.md,
  },
  divider: {
    height: '1px',
    backgroundColor: theme.colors.primaryLight,
    margin: '1.5rem 0',
  },
  footer: {
    textAlign: 'center',
    marginBottom: '1rem',
  },
  backLink: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '0.95rem',
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.25rem',
  },
  helpBox: {
    backgroundColor: theme.colors.background,
    padding: '1rem',
    borderRadius: theme.borderRadius.md,
    border: `1px solid ${theme.colors.primaryLight}`,
  },
  helpTitle: {
    fontWeight: '600',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
    fontSize: '0.95rem',
  },
  helpText: {
    color: theme.colors.text.secondary,
    fontSize: '0.85rem',
    lineHeight: '1.5',
  },
};