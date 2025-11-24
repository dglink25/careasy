import { useAuth } from '../contexts/AuthContext';
import theme from '../config/theme';

export default function Dashboard() {
  const { user } = useAuth();

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header */}
        <div style={styles.header}>
          <h1 style={styles.title}>Tableau de Bord</h1>
          <p style={styles.welcomeText}>
            Bienvenue sur votre espace personnel CarEasy
          </p>
        </div>
        
        {/* User Card */}
        <div style={styles.userCard}>
          <div style={styles.userAvatar}>
            {user?.name?.charAt(0).toUpperCase()}
          </div>
          <div style={styles.userInfo}>
            <h2 style={styles.userName}>👋 Bonjour, {user?.name} !</h2>
            <p style={styles.userEmail}>
              📧 <strong>{user?.email}</strong>
            </p>
            <span style={styles.badge}>✅ Compte actif</span>
          </div>
        </div>

        {/* Stats Grid */}
        <div style={styles.grid}>
          <div style={styles.statCard}>
            <div style={styles.statIcon}>🚗</div>
            <div style={styles.statContent}>
              <h3 style={styles.statNumber}>15+</h3>
              <p style={styles.statLabel}>Catégories de services</p>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIcon}>📅</div>
            <div style={styles.statContent}>
              <h3 style={styles.statNumber}>0</h3>
              <p style={styles.statLabel}>Rendez-vous actifs</p>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIcon}>💬</div>
            <div style={styles.statContent}>
              <h3 style={styles.statNumber}>0</h3>
              <p style={styles.statLabel}>Messages non lus</p>
            </div>
          </div>

          <div style={styles.statCard}>
            <div style={styles.statIcon}>⭐</div>
            <div style={styles.statContent}>
              <h3 style={styles.statNumber}>0</h3>
              <p style={styles.statLabel}>Prestataires favoris</p>
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div style={styles.actionsSection}>
          <h2 style={styles.sectionTitle}>Actions rapides</h2>
          <div style={styles.actionsGrid}>
            <button style={styles.actionCard}>
              <span style={styles.actionIcon}>🔍</span>
              <span style={styles.actionText}>Rechercher un service</span>
            </button>
            
            <button style={styles.actionCard}>
              <span style={styles.actionIcon}>🤖</span>
              <span style={styles.actionText}>Diagnostic IA</span>
            </button>
            
            <button style={styles.actionCard}>
              <span style={styles.actionIcon}>📅</span>
              <span style={styles.actionText}>Prendre RDV</span>
            </button>
            
            <button style={styles.actionCard}>
              <span style={styles.actionIcon}>💬</span>
              <span style={styles.actionText}>Mes messages</span>
            </button>
          </div>
        </div>

        {/* Info Box */}
        <div style={styles.infoBox}>
          <div style={styles.infoIcon}>💡</div>
          <div>
            <h3 style={styles.infoTitle}>Nouveau sur CarEasy ?</h3>
            <p style={styles.infoText}>
              Explorez nos 15+ catégories de services automobiles : mécaniciens, vulcanisateurs, 
              peintres, auto-écoles, assurances et bien plus encore !
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
    backgroundColor: theme.colors.background,
    padding: '2rem 1rem',
  },
  content: {
    maxWidth: '1200px',
    margin: '0 auto',
  },
  header: {
    marginBottom: '2rem',
  },
  title: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  welcomeText: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
  },
  userCard: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.xl,
    boxShadow: theme.shadows.md,
    marginBottom: '2rem',
    display: 'flex',
    alignItems: 'center',
    gap: '1.5rem',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  userAvatar: {
    width: '80px',
    height: '80px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '2rem',
    fontWeight: 'bold',
    flexShrink: 0,
  },
  userInfo: {
    flex: 1,
  },
  userName: {
    fontSize: '1.75rem',
    fontWeight: 'bold',
    marginBottom: '0.5rem',
    color: theme.colors.text.primary,
  },
  userEmail: {
    marginBottom: '0.75rem',
    color: theme.colors.text.secondary,
  },
  badge: {
    backgroundColor: theme.colors.success,
    color: theme.colors.text.white,
    padding: '0.375rem 1rem',
    borderRadius: theme.borderRadius.full,
    fontSize: '0.875rem',
    fontWeight: '600',
    display: 'inline-block',
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '1.5rem',
    marginBottom: '2rem',
  },
  statCard: {
    backgroundColor: theme.colors.secondary,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    boxShadow: theme.shadows.md,
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    border: `1px solid ${theme.colors.primaryLight}`,
    transition: 'all 0.3s',
  },
  statIcon: {
    fontSize: '2.5rem',
  },
  statContent: {
    flex: 1,
  },
  statNumber: {
    fontSize: '2rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '0.25rem',
  },
  statLabel: {
    fontSize: '0.875rem',
    color: theme.colors.text.secondary,
  },
  actionsSection: {
    marginBottom: '2rem',
  },
  sectionTitle: {
    fontSize: '1.75rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '1.5rem',
  },
  actionsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: '1rem',
  },
  actionCard: {
    backgroundColor: theme.colors.secondary,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: `2px solid ${theme.colors.primary}`,
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    gap: '0.75rem',
    cursor: 'pointer',
    transition: 'all 0.3s',
    boxShadow: theme.shadows.sm,
  },
  actionIcon: {
    fontSize: '2.5rem',
  },
  actionText: {
    fontWeight: '600',
    color: theme.colors.primary,
    textAlign: 'center',
  },
  infoBox: {
    backgroundColor: theme.colors.primaryLight,
    border: `2px solid ${theme.colors.primary}`,
    borderRadius: theme.borderRadius.lg,
    padding: '1.5rem',
    display: 'flex',
    gap: '1rem',
    alignItems: 'flex-start',
  },
  infoIcon: {
    fontSize: '2rem',
    flexShrink: 0,
  },
  infoTitle: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '0.5rem',
  },
  infoText: {
    color: theme.colors.text.primary,
    lineHeight: '1.6',
  },
};