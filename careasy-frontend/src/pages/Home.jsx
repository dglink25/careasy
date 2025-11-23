import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function Home() {
  const { user } = useAuth();

  return (
    <div style={styles.container}>
      <div style={styles.hero}>
        <h1 style={styles.title}>
          Bienvenue sur CareEasy
        </h1>
        <p style={styles.subtitle}>
          Votre solution complète de gestion
        </p>
        
        {!user && (
          <div style={styles.buttons}>
            <Link to="/register" style={styles.primaryButton}>
              Commencer gratuitement
            </Link>
            <Link to="/login" style={styles.secondaryButton}>
              Se connecter
            </Link>
          </div>
        )}

        {user && (
          <Link to="/dashboard" style={styles.primaryButton}>
            Accéder au Dashboard
          </Link>
        )}
      </div>

      <div style={styles.features}>
        <h2 style={styles.featuresTitle}>Fonctionnalités</h2>
        
        <div style={styles.grid}>
          <div style={styles.featureCard}>
            <div style={styles.icon}>🚀</div>
            <h3 style={styles.featureTitle}>Rapide</h3>
            <p style={styles.featureText}>
              Interface moderne et réactive pour une expérience optimale
            </p>
          </div>

          <div style={styles.featureCard}>
            <div style={styles.icon}>🔒</div>
            <h3 style={styles.featureTitle}>Sécurisé</h3>
            <p style={styles.featureText}>
              Authentification robuste avec Laravel Sanctum
            </p>
          </div>

          <div style={styles.featureCard}>
            <div style={styles.icon}>💼</div>
            <h3 style={styles.featureTitle}>Professionnel</h3>
            <p style={styles.featureText}>
              Conçu pour répondre aux besoins des entreprises
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
    backgroundColor: '#f9fafb',
  },
  hero: {
    textAlign: 'center',
    padding: '4rem 1rem',
    backgroundColor: 'white',
  },
  title: {
    fontSize: '3rem',
    fontWeight: 'bold',
    color: '#1f2937',
    marginBottom: '1rem',
  },
  subtitle: {
    fontSize: '1.5rem',
    color: '#6b7280',
    marginBottom: '2rem',
  },
  buttons: {
    display: 'flex',
    gap: '1rem',
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  primaryButton: {
    backgroundColor: '#2563eb',
    color: 'white',
    padding: '1rem 2rem',
    borderRadius: '0.5rem',
    textDecoration: 'none',
    fontWeight: '500',
    fontSize: '1.125rem',
    display: 'inline-block',
  },
  secondaryButton: {
    backgroundColor: 'white',
    color: '#2563eb',
    padding: '1rem 2rem',
    borderRadius: '0.5rem',
    textDecoration: 'none',
    fontWeight: '500',
    fontSize: '1.125rem',
    border: '2px solid #2563eb',
    display: 'inline-block',
  },
  features: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '4rem 1rem',
  },
  featuresTitle: {
    fontSize: '2rem',
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: '3rem',
    color: '#1f2937',
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
    gap: '2rem',
  },
  featureCard: {
    backgroundColor: 'white',
    padding: '2rem',
    borderRadius: '0.5rem',
    boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
    textAlign: 'center',
  },
  icon: {
    fontSize: '3rem',
    marginBottom: '1rem',
  },
  featureTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    marginBottom: '1rem',
    color: '#1f2937',
  },
  featureText: {
    color: '#6b7280',
    lineHeight: '1.6',
  },
};