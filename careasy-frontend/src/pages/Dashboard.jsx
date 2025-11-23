import { useAuth } from '../contexts/AuthContext';

export default function Dashboard() {
  const { user } = useAuth();

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        <h1 style={styles.title}>
          Tableau de Bord
        </h1>
        
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Bienvenue {user?.name} !</h2>
          <p style={styles.cardText}>
            Email : <strong>{user?.email}</strong>
          </p>
          <p style={styles.cardText}>
            Statut : <span style={styles.badge}>Connecté</span>
          </p>
        </div>

        <div style={styles.grid}>
          <div style={styles.statCard}>
            <h3 style={styles.statTitle}>Projets</h3>
            <p style={styles.statNumber}>12</p>
          </div>
          
          <div style={styles.statCard}>
            <h3 style={styles.statTitle}>Tâches</h3>
            <p style={styles.statNumber}>48</p>
          </div>
          
          <div style={styles.statCard}>
            <h3 style={styles.statTitle}>Messages</h3>
            <p style={styles.statNumber}>7</p>
          </div>
        </div>

        <div style={styles.infoBox}>
          <p>
            ℹ️ C'est votre espace personnel. Vous pouvez maintenant 
            ajouter vos fonctionnalités ici !
          </p>
        </div>
      </div>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f3f4f6',
    padding: '2rem 1rem',
  },
  content: {
    maxWidth: '1200px',
    margin: '0 auto',
  },
  title: {
    fontSize: '2.25rem',
    fontWeight: 'bold',
    color: '#1f2937',
    marginBottom: '2rem',
  },
  card: {
    backgroundColor: 'white',
    padding: '2rem',
    borderRadius: '0.5rem',
    boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
    marginBottom: '2rem',
  },
  cardTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    marginBottom: '1rem',
    color: '#1f2937',
  },
  cardText: {
    marginBottom: '0.5rem',
    color: '#4b5563',
  },
  badge: {
    backgroundColor: '#10b981',
    color: 'white',
    padding: '0.25rem 0.75rem',
    borderRadius: '9999px',
    fontSize: '0.875rem',
    fontWeight: '500',
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '1.5rem',
    marginBottom: '2rem',
  },
  statCard: {
    backgroundColor: 'white',
    padding: '1.5rem',
    borderRadius: '0.5rem',
    boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
    textAlign: 'center',
  },
  statTitle: {
    fontSize: '1rem',
    color: '#6b7280',
    marginBottom: '0.5rem',
  },
  statNumber: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    color: '#2563eb',
  },
  infoBox: {
    backgroundColor: '#dbeafe',
    border: '1px solid #3b82f6',
    borderRadius: '0.5rem',
    padding: '1rem',
    color: '#1e40af',
  },
};