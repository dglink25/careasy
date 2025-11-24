import { useState } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import api from '../api/axios';
import Logo from '../components/Logo';
import theme from '../config/theme';

export default function ResetPassword() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const token = searchParams.get('token');
  const emailParam = searchParams.get('email');

  const [formData, setFormData] = useState({
    email: emailParam || '',
    password: '',
    password_confirmation: '',
    token: token || ''
  });

  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [passwordStrength, setPasswordStrength] = useState('');

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({
      ...formData,
      [name]: value
    });

    // Vérifier la force du mot de passe
    if (name === 'password') {
      checkPasswordStrength(value);
    }
  };

  const checkPasswordStrength = (password) => {
    if (password.length === 0) {
      setPasswordStrength('');
    } else if (password.length < 8) {
      setPasswordStrength('faible');
    } else if (password.length < 12) {
      setPasswordStrength('moyen');
    } else {
      setPasswordStrength('fort');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (formData.password !== formData.password_confirmation) {
      setError('Les mots de passe ne correspondent pas');
      return;
    }

    if (formData.password.length < 8) {
      setError('Le mot de passe doit contenir au moins 8 caractères');
      return;
    }

    if (!formData.token) {
      setError('Token de réinitialisation manquant. Veuillez utiliser le lien reçu par email.');
      return;
    }

    setLoading(true);

    try {
      await api.post('/reset-password', formData);
      
      // Message de succès
      alert('✅ Mot de passe réinitialisé avec succès !');
      
      // Rediriger vers la page de connexion
      navigate('/login');
    } catch (err) {
      setError(
        err.response?.data?.message || 
        err.response?.data?.errors?.email?.[0] ||
        err.response?.data?.errors?.password?.[0] ||
        'Une erreur est survenue lors de la réinitialisation'
      );
    } finally {
      setLoading(false);
    }
  };

  const getStrengthColor = () => {
    switch (passwordStrength) {
      case 'faible': return theme.colors.error;
      case 'moyen': return theme.colors.warning;
      case 'fort': return theme.colors.success;
      default: return theme.colors.text.secondary;
    }
  };

  return (
    <div style={styles.container}>
      <div style={styles.card}>
        {/* Logo */}
        <div style={styles.logoContainer}>
          <Logo size="lg" showText={true} />
        </div>

        <h2 style={styles.title}>Nouveau mot de passe</h2>
        <p style={styles.subtitle}>
          Créez un nouveau mot de passe sécurisé pour protéger votre compte CarEasy
        </p>

        {error && (
          <div style={styles.error}>
            ⚠️ {error}
          </div>
        )}

        <form onSubmit={handleSubmit} style={styles.form}>
          <div style={styles.formGroup}>
            <label style={styles.label}>Adresse email</label>
            <input
              type="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              required
              style={styles.input}
              placeholder="votre@email.com"
            />
          </div>

          <div style={styles.formGroup}>
            <label style={styles.label}>Nouveau mot de passe</label>
            <input
              type="password"
              name="password"
              value={formData.password}
              onChange={handleChange}
              required
              minLength="8"
              style={styles.input}
              placeholder="••••••••"
            />
            {passwordStrength && (
              <div style={styles.strengthIndicator}>
                <div 
                  style={{
                    ...styles.strengthBar,
                    width: passwordStrength === 'faible' ? '33%' : passwordStrength === 'moyen' ? '66%' : '100%',
                    backgroundColor: getStrengthColor(),
                  }}
                />
              </div>
            )}
            {passwordStrength && (
              <small style={{...styles.strengthText, color: getStrengthColor()}}>
                Force : {passwordStrength}
              </small>
            )}
            <small style={styles.hint}>
              Minimum 8 caractères. Utilisez majuscules, chiffres et symboles pour plus de sécurité.
            </small>
          </div>

          <div style={styles.formGroup}>
            <label style={styles.label}>Confirmer le mot de passe</label>
            <input
              type="password"
              name="password_confirmation"
              value={formData.password_confirmation}
              onChange={handleChange}
              required
              minLength="8"
              style={styles.input}
              placeholder="••••••••"
            />
          </div>

          <button 
            type="submit" 
            disabled={loading}
            style={{...styles.button, opacity: loading ? 0.6 : 1}}
          >
            {loading ? 'Réinitialisation...' : 'Réinitialiser mon mot de passe'}
          </button>
        </form>

        <div style={styles.divider} />

        <div style={styles.footer}>
          <Link to="/login" style={styles.backLink}>
            ← Retour à la connexion
          </Link>
        </div>

        <div style={styles.securityBox}>
          <div style={styles.securityIcon}>🔒</div>
          <div>
            <p style={styles.securityTitle}>Sécurité renforcée</p>
            <p style={styles.securityText}>
              Votre mot de passe est crypté et stocké de manière sécurisée. Personne, pas même l'équipe CarEasy, ne peut le consulter.
            </p>
          </div>
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
    maxWidth: '550px',
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
    marginBottom: '0.5rem',
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
  strengthIndicator: {
    height: '4px',
    backgroundColor: '#E5E7EB',
    borderRadius: '2px',
    overflow: 'hidden',
    marginTop: '0.5rem',
  },
  strengthBar: {
    height: '100%',
    transition: 'all 0.3s',
    borderRadius: '2px',
  },
  strengthText: {
    fontSize: '0.8rem',
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  hint: {
    color: theme.colors.text.secondary,
    fontSize: '0.8rem',
    lineHeight: '1.4',
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
    marginTop: '0.5rem',
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
  securityBox: {
    backgroundColor: '#DBEAFE',
    padding: '1rem',
    borderRadius: theme.borderRadius.md,
    border: '1px solid #3B82F6',
    display: 'flex',
    gap: '1rem',
    alignItems: 'flex-start',
  },
  securityIcon: {
    fontSize: '1.5rem',
    flexShrink: 0,
  },
  securityTitle: {
    fontWeight: '600',
    color: '#1E40AF',
    marginBottom: '0.25rem',
    fontSize: '0.95rem',
  },
  securityText: {
    color: '#1E40AF',
    fontSize: '0.85rem',
    lineHeight: '1.5',
  },
};