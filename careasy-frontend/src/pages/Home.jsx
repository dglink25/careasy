import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Logo from '../components/Logo';
import theme from '../config/theme';

export default function Home() {
  const { user } = useAuth();

  return (
    <div style={styles.container}>
      {/* Hero Section */}
      <div style={styles.hero}>
        <div style={styles.logoContainer}>
          <Logo size="lg" showText={false} />
        </div>
        
        <h1 style={styles.title}>
          Bienvenue sur <span style={styles.brandName}>CarEasy</span>
        </h1>
        
        <p style={styles.subtitle}>
          Votre assistant intelligent pour tous vos besoins automobiles au Bénin
        </p>
        
        <p style={styles.description}>
          Trouvez rapidement des mécaniciens, garagistes, vulcanisateurs et bien plus encore. 
          Prenez rendez-vous en ligne et bénéficiez d'un diagnostic IA gratuit !
        </p>
        
        {!user ? (
          <div style={styles.buttons}>
            <Link to="/register" style={styles.primaryButton}>
              Commencer maintenant
            </Link>
            <Link to="/login" style={styles.secondaryButton}>
              Se connecter
            </Link>
          </div>
        ) : (
          <Link to="/dashboard" style={styles.primaryButton}>
            Accéder au Dashboard
          </Link>
        )}
      </div>

      {/* Features Section */}
      <div style={styles.features}>
        <h2 style={styles.featuresTitle}>
          Pourquoi choisir CarEasy ?
        </h2>
        
        <div style={styles.grid}>
          <div style={styles.featureCard}>
            <div style={styles.icon}>🚗</div>
            <h3 style={styles.featureTitle}>15+ Catégories</h3>
            <p style={styles.featureText}>
              Mécaniciens, vulcanisateurs, peintres, auto-écoles, assurances et plus encore
            </p>
          </div>

          <div style={styles.featureCard}>
            <div style={styles.icon}>🤖</div>
            <h3 style={styles.featureTitle}>Diagnostic IA</h3>
            <p style={styles.featureText}>
              Intelligence artificielle pour diagnostiquer vos pannes et recommander le bon prestataire
            </p>
          </div>

          <div style={styles.featureCard}>
            <div style={styles.icon}>📍</div>
            <h3 style={styles.featureTitle}>Géolocalisation</h3>
            <p style={styles.featureText}>
              Trouvez les prestataires les plus proches de vous en temps réel
            </p>
          </div>

          <div style={styles.featureCard}>
            <div style={styles.icon}>💬</div>
            <h3 style={styles.featureTitle}>Chat Direct</h3>
            <p style={styles.featureText}>
              Communiquez instantanément avec les prestataires
            </p>
          </div>

          <div style={styles.featureCard}>
            <div style={styles.icon}>📅</div>
            <h3 style={styles.featureTitle}>Rendez-vous</h3>
            <p style={styles.featureText}>
              Prenez et gérez vos rendez-vous en ligne facilement
            </p>
          </div>

          <div style={styles.featureCard}>
            <div style={styles.icon}>⭐</div>
            <h3 style={styles.featureTitle}>Avis Certifiés</h3>
            <p style={styles.featureText}>
              Consultez les évaluations et choisissez en toute confiance
            </p>
          </div>
        </div>
      </div>

      {/* CTA Section */}
      <div style={styles.cta}>
        <h2 style={styles.ctaTitle}>Prêt à simplifier votre expérience automobile ?</h2>
        {!user && (
          <Link to="/register" style={styles.ctaButton}>
            Créer un compte gratuitement
          </Link>
        )}
      </div>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: theme.colors.background,
  },
  hero: {
    textAlign: 'center',
    padding: '4rem 1rem',
    backgroundColor: theme.colors.secondary,
    borderBottom: `4px solid ${theme.colors.primary}`,
  },
  logoContainer: {
    display: 'flex',
    justifyContent: 'center',
    marginBottom: '1.5rem',
  },
  title: {
    fontSize: '3rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '1rem',
  },
  brandName: {
    color: theme.colors.primary,
  },
  subtitle: {
    fontSize: '1.5rem',
    color: theme.colors.text.secondary,
    marginBottom: '1rem',
    maxWidth: '800px',
    margin: '0 auto 1rem',
  },
  description: {
    fontSize: '1.125rem',
    color: theme.colors.text.secondary,
    marginBottom: '2rem',
    maxWidth: '700px',
    margin: '0 auto 2rem',
    lineHeight: '1.6',
  },
  buttons: {
    display: 'flex',
    gap: '1rem',
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '1rem 2.5rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.125rem',
    display: 'inline-block',
    boxShadow: theme.shadows.md,
    transition: 'all 0.3s',
  },
  secondaryButton: {
    backgroundColor: 'transparent',
    color: theme.colors.primary,
    padding: '1rem 2.5rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.125rem',
    border: `2px solid ${theme.colors.primary}`,
    display: 'inline-block',
    transition: 'all 0.3s',
  },
  features: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '4rem 1rem',
  },
  featuresTitle: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: '3rem',
    color: theme.colors.text.primary,
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
    gap: '2rem',
  },
  featureCard: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.lg,
    boxShadow: theme.shadows.md,
    textAlign: 'center',
    border: `1px solid ${theme.colors.primaryLight}`,
    transition: 'all 0.3s',
  },
  icon: {
    fontSize: '3rem',
    marginBottom: '1rem',
  },
  featureTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    marginBottom: '1rem',
    color: theme.colors.primary,
  },
  featureText: {
    color: theme.colors.text.secondary,
    lineHeight: '1.6',
  },
  cta: {
    backgroundColor: theme.colors.primary,
    padding: '4rem 1rem',
    textAlign: 'center',
  },
  ctaTitle: {
    fontSize: '2rem',
    fontWeight: 'bold',
    color: theme.colors.text.white,
    marginBottom: '2rem',
  },
  ctaButton: {
    backgroundColor: theme.colors.secondary,
    color: theme.colors.primary,
    padding: '1rem 2.5rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.125rem',
    display: 'inline-block',
    boxShadow: theme.shadows.lg,
  },
};