// careasy-frontend/src/pages/entreprises/MesEntreprises.jsx
import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { entrepriseApi } from '../../api/entrepriseApi';
import theme from '../../config/theme';

export default function MesEntreprises() {
  const [entreprises, setEntreprises] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState('all'); // all, pending, validated, rejected

  useEffect(() => {
    fetchEntreprises();
  }, []);

  const fetchEntreprises = async () => {
    try {
      setLoading(true);
      const data = await entrepriseApi.getMesEntreprises();
      setEntreprises(data);
      setError('');
    } catch (err) {
      setError('Erreur lors du chargement des entreprises');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status) => {
    const badges = {
      pending: { emoji: '🟡', text: 'En attente', color: theme.colors.warning },
      validated: { emoji: '✅', text: 'Validée', color: theme.colors.success },
      rejected: { emoji: '❌', text: 'Rejetée', color: theme.colors.error },
    };
    const badge = badges[status] || badges.pending;
    
    return (
      <span style={{...styles.badge, backgroundColor: badge.color}}>
        {badge.emoji} {badge.text}
      </span>
    );
  };

  const filteredEntreprises = entreprises.filter(e => {
    if (filter === 'all') return true;
    return e.status === filter;
  });

  const stats = {
    total: entreprises.length,
    pending: entreprises.filter(e => e.status === 'pending').length,
    validated: entreprises.filter(e => e.status === 'validated').length,
    rejected: entreprises.filter(e => e.status === 'rejected').length,
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement de vos entreprises...</p>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header */}
        <div style={styles.header}>
          <div>
            <h1 style={styles.title}>Mes Entreprises</h1>
            <p style={styles.subtitle}>
              Gérez vos entreprises et suivez leur statut de validation
            </p>
          </div>
          <Link to="/entreprises/creer" style={styles.createButton}>
            ➕ Créer une entreprise
          </Link>
        </div>

        {/* Statistiques */}
        <div style={styles.statsGrid}>
          <div style={styles.statCard}>
            <div style={styles.statIcon}>🏢</div>
            <div>
              <div style={styles.statNumber}>{stats.total}</div>
              <div style={styles.statLabel}>Total</div>
            </div>
          </div>
          
          <div style={{...styles.statCard, borderColor: theme.colors.warning}}>
            <div style={styles.statIcon}>🟡</div>
            <div>
              <div style={{...styles.statNumber, color: theme.colors.warning}}>{stats.pending}</div>
              <div style={styles.statLabel}>En attente</div>
            </div>
          </div>
          
          <div style={{...styles.statCard, borderColor: theme.colors.success}}>
            <div style={styles.statIcon}>✅</div>
            <div>
              <div style={{...styles.statNumber, color: theme.colors.success}}>{stats.validated}</div>
              <div style={styles.statLabel}>Validées</div>
            </div>
          </div>
          
          <div style={{...styles.statCard, borderColor: theme.colors.error}}>
            <div style={styles.statIcon}>❌</div>
            <div>
              <div style={{...styles.statNumber, color: theme.colors.error}}>{stats.rejected}</div>
              <div style={styles.statLabel}>Rejetées</div>
            </div>
          </div>
        </div>

        {/* Filtres */}
        <div style={styles.filterContainer}>
          <button 
            onClick={() => setFilter('all')}
            style={{
              ...styles.filterButton,
              ...(filter === 'all' ? styles.filterButtonActive : {})
            }}
          >
            Toutes ({stats.total})
          </button>
          <button 
            onClick={() => setFilter('pending')}
            style={{
              ...styles.filterButton,
              ...(filter === 'pending' ? styles.filterButtonActive : {})
            }}
          >
            En attente ({stats.pending})
          </button>
          <button 
            onClick={() => setFilter('validated')}
            style={{
              ...styles.filterButton,
              ...(filter === 'validated' ? styles.filterButtonActive : {})
            }}
          >
            Validées ({stats.validated})
          </button>
          <button 
            onClick={() => setFilter('rejected')}
            style={{
              ...styles.filterButton,
              ...(filter === 'rejected' ? styles.filterButtonActive : {})
            }}
          >
            Rejetées ({stats.rejected})
          </button>
        </div>

        {/* Message d'erreur */}
        {error && (
          <div style={styles.error}>
            ⚠️ {error}
          </div>
        )}

        {/* Liste des entreprises */}
        {filteredEntreprises.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIcon}>🏢</div>
            <h3 style={styles.emptyTitle}>Aucune entreprise trouvée</h3>
            <p style={styles.emptyText}>
              {filter === 'all' 
                ? "Vous n'avez pas encore créé d'entreprise."
                : `Vous n'avez aucune entreprise avec le statut "${filter}".`
              }
            </p>
            {filter === 'all' && (
              <Link to="/entreprises/creer" style={styles.emptyButton}>
                ➕ Créer ma première entreprise
              </Link>
            )}
          </div>
        ) : (
          <div style={styles.grid}>
            {filteredEntreprises.map((entreprise) => (
              <Link 
                key={entreprise.id} 
                to={`/entreprises/${entreprise.id}`}
                style={styles.card}
                className="entreprise-card"
              >
                {/* Logo entreprise */}
                <div style={styles.cardHeader}>
                  {entreprise.logo ? (
                    <img 
                      src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                      alt={entreprise.name}
                      style={styles.logo}
                    />
                  ) : (
                    <div style={styles.logoPlaceholder}>🏢</div>
                  )}
                  {getStatusBadge(entreprise.status)}
                </div>

                {/* Infos entreprise */}
                <div style={styles.cardBody}>
                  <h3 style={styles.cardTitle}>{entreprise.name}</h3>
                  
                  <div style={styles.infoRow}>
                    <span style={styles.infoLabel}>👤 PDG:</span>
                    <span style={styles.infoValue}>{entreprise.pdg_full_name}</span>
                  </div>

                  <div style={styles.infoRow}>
                    <span style={styles.infoLabel}>🏷️ IFU:</span>
                    <span style={styles.infoValue}>{entreprise.ifu_number}</span>
                  </div>

                  {entreprise.domaines && entreprise.domaines.length > 0 && (
                    <div style={styles.domaines}>
                      {entreprise.domaines.slice(0, 3).map((domaine) => (
                        <span key={domaine.id} style={styles.domaineTag}>
                          {domaine.name}
                        </span>
                      ))}
                      {entreprise.domaines.length > 3 && (
                        <span style={styles.domaineTag}>
                          +{entreprise.domaines.length - 3}
                        </span>
                      )}
                    </div>
                  )}
                </div>

                {/* Footer */}
                <div style={styles.cardFooter}>
                  <span style={styles.date}>
                    📅 Créée le {new Date(entreprise.created_at).toLocaleDateString('fr-FR')}
                  </span>
                  <span style={styles.viewLink}>Voir détails →</span>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>

      {/* CSS pour animations */}
      <style>{`
        .entreprise-card {
          transition: all 0.3s ease;
        }
        .entreprise-card:hover {
          transform: translateY(-8px);
          box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: theme.colors.background,
    paddingTop: '2rem',
    paddingBottom: '4rem',
  },
  content: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '0 1rem',
  },
  loadingContainer: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: '60vh',
    gap: '1rem',
  },
  spinner: {
    width: '50px',
    height: '50px',
    border: `4px solid ${theme.colors.primaryLight}`,
    borderTop: `4px solid ${theme.colors.primary}`,
    borderRadius: '50%',
    animation: 'spin 1s linear infinite',
  },
  loadingText: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: '2rem',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  title: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  subtitle: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
  },
  createButton: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '0.875rem 2rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.5rem',
    boxShadow: theme.shadows.md,
    transition: 'all 0.3s',
    border: 'none',
    cursor: 'pointer',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
    gap: '1.5rem',
    marginBottom: '2rem',
  },
  statCard: {
    backgroundColor: theme.colors.secondary,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    border: `2px solid ${theme.colors.primary}`,
    boxShadow: theme.shadows.sm,
  },
  statIcon: {
    fontSize: '2.5rem',
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
    fontWeight: '600',
  },
  filterContainer: {
    display: 'flex',
    gap: '1rem',
    marginBottom: '2rem',
    flexWrap: 'wrap',
  },
  filterButton: {
    backgroundColor: theme.colors.secondary,
    color: theme.colors.text.primary,
    border: `2px solid ${theme.colors.primaryLight}`,
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    cursor: 'pointer',
    fontWeight: '600',
    transition: 'all 0.3s',
  },
  filterButtonActive: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    borderColor: theme.colors.primary,
  },
  error: {
    backgroundColor: '#FEE2E2',
    color: theme.colors.error,
    padding: '1rem',
    borderRadius: theme.borderRadius.md,
    marginBottom: '2rem',
    border: `2px solid ${theme.colors.error}`,
  },
  emptyState: {
    backgroundColor: theme.colors.secondary,
    padding: '4rem 2rem',
    borderRadius: theme.borderRadius.xl,
    textAlign: 'center',
    border: `2px dashed ${theme.colors.primaryLight}`,
  },
  emptyIcon: {
    fontSize: '5rem',
    marginBottom: '1rem',
  },
  emptyTitle: {
    fontSize: '1.75rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.75rem',
  },
  emptyText: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
    marginBottom: '2rem',
  },
  emptyButton: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '1rem 2rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    display: 'inline-block',
    boxShadow: theme.shadows.md,
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(350px, 1fr))',
    gap: '2rem',
  },
  card: {
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    overflow: 'hidden',
    textDecoration: 'none',
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.md,
    display: 'flex',
    flexDirection: 'column',
  },
  cardHeader: {
    padding: '1.5rem',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    borderBottom: `1px solid ${theme.colors.primaryLight}`,
  },
  logo: {
    width: '80px',
    height: '80px',
    borderRadius: theme.borderRadius.md,
    objectFit: 'cover',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  logoPlaceholder: {
    width: '80px',
    height: '80px',
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '2.5rem',
  },
  badge: {
    padding: '0.5rem 1rem',
    borderRadius: theme.borderRadius.full,
    color: theme.colors.text.white,
    fontSize: '0.875rem',
    fontWeight: '600',
    whiteSpace: 'nowrap',
  },
  cardBody: {
    padding: '1.5rem',
    flex: 1,
  },
  cardTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '1rem',
  },
  infoRow: {
    display: 'flex',
    gap: '0.5rem',
    marginBottom: '0.75rem',
    alignItems: 'center',
  },
  infoLabel: {
    color: theme.colors.text.secondary,
    fontSize: '0.95rem',
    fontWeight: '600',
  },
  infoValue: {
    color: theme.colors.text.primary,
    fontSize: '0.95rem',
  },
  domaines: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '0.5rem',
    marginTop: '1rem',
  },
  domaineTag: {
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.primary,
    padding: '0.375rem 0.75rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.8rem',
    fontWeight: '600',
  },
  cardFooter: {
    padding: '1rem 1.5rem',
    backgroundColor: theme.colors.background,
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTop: `1px solid ${theme.colors.primaryLight}`,
  },
  date: {
    color: theme.colors.text.secondary,
    fontSize: '0.875rem',
  },
  viewLink: {
    color: theme.colors.primary,
    fontWeight: '600',
    fontSize: '0.95rem',
  },
};