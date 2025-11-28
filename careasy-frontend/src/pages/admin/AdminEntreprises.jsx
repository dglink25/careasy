// careasy-frontend/src/pages/admin/AdminEntreprises.jsx
import { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { adminApi } from '../../api/adminApi';
import theme from '../../config/theme';

export default function AdminEntreprises() {
  const [searchParams, setSearchParams] = useSearchParams();
  const statusParam = searchParams.get('status');

  const [entreprises, setEntreprises] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState(statusParam || 'all');
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    fetchEntreprises();
  }, []);

  useEffect(() => {
    if (statusParam) {
      setFilter(statusParam);
    }
  }, [statusParam]);

  const fetchEntreprises = async () => {
    try {
      setLoading(true);
      const data = await adminApi.getEntreprises();
      setEntreprises(data.data);
      setError('');
    } catch (err) {
      setError('Erreur lors du chargement des entreprises');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (newFilter) => {
    setFilter(newFilter);
    if (newFilter === 'all') {
      setSearchParams({});
    } else {
      setSearchParams({ status: newFilter });
    }
  };

  const getStatusBadge = (status) => {
    const badges = {
      pending: { emoji: '🟡', text: 'En attente', color: theme.colors.warning, bg: '#FEF3C7' },
      validated: { emoji: '✅', text: 'Validée', color: theme.colors.success, bg: '#D1FAE5' },
      rejected: { emoji: '❌', text: 'Rejetée', color: theme.colors.error, bg: '#FEE2E2' },
    };
    const badge = badges[status] || badges.pending;
    
    return (
      <span style={{...styles.badge, backgroundColor: badge.bg, color: badge.color}}>
        {badge.emoji} {badge.text}
      </span>
    );
  };

  // Filtrer par statut et recherche
  const filteredEntreprises = entreprises
    .filter(e => filter === 'all' || e.status === filter)
    .filter(e => 
      e.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      e.pdg_full_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      e.prestataire?.name.toLowerCase().includes(searchTerm.toLowerCase())
    );

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
          <p style={styles.loadingText}>Chargement des entreprises...</p>
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
            <Link to="/admin/dashboard" style={styles.backButton}>
              ← Retour au dashboard
            </Link>
            <h1 style={styles.title}>Gestion des Entreprises</h1>
            <p style={styles.subtitle}>
              Validez ou rejetez les demandes d'inscription des prestataires
            </p>
          </div>
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

        {/* Filtres et recherche */}
        <div style={styles.filtersSection}>
          <div style={styles.filterButtons}>
            <button 
              onClick={() => handleFilterChange('all')}
              style={{
                ...styles.filterButton,
                ...(filter === 'all' ? styles.filterButtonActive : {})
              }}
            >
              Toutes ({stats.total})
            </button>
            <button 
              onClick={() => handleFilterChange('pending')}
              style={{
                ...styles.filterButton,
                ...(filter === 'pending' ? styles.filterButtonActive : {})
              }}
            >
              🟡 En attente ({stats.pending})
            </button>
            <button 
              onClick={() => handleFilterChange('validated')}
              style={{
                ...styles.filterButton,
                ...(filter === 'validated' ? styles.filterButtonActive : {})
              }}
            >
              ✅ Validées ({stats.validated})
            </button>
            <button 
              onClick={() => handleFilterChange('rejected')}
              style={{
                ...styles.filterButton,
                ...(filter === 'rejected' ? styles.filterButtonActive : {})
              }}
            >
              ❌ Rejetées ({stats.rejected})
            </button>
          </div>

          <input
            type="text"
            placeholder="🔍 Rechercher par nom, prestataire..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            style={styles.searchInput}
          />
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
              {searchTerm 
                ? `Aucun résultat pour "${searchTerm}"`
                : filter === 'all'
                  ? "Aucune entreprise enregistrée"
                  : `Aucune entreprise avec le statut "${filter}"`
              }
            </p>
          </div>
        ) : (
          <div style={styles.tableContainer}>
            <table style={styles.table}>
              <thead>
                <tr style={styles.tableHeader}>
                  <th style={styles.th}>Entreprise</th>
                  <th style={styles.th}>Prestataire</th>
                  <th style={styles.th}>IFU</th>
                  <th style={styles.th}>Date</th>
                  <th style={styles.th}>Statut</th>
                  <th style={styles.th}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredEntreprises.map((entreprise) => (
                  <tr key={entreprise.id} style={styles.tableRow} className="table-row">
                    <td style={styles.td}>
                      <div style={styles.entrepriseCell}>
                        {entreprise.logo ? (
                          <img 
                            src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                            alt={entreprise.name}
                            style={styles.tableLogo}
                          />
                        ) : (
                          <div style={styles.tableLogoPlaceholder}>🏢</div>
                        )}
                        <div>
                          <div style={styles.entrepriseName}>{entreprise.name}</div>
                          <div style={styles.entrepriseInfo}>
                            👤 {entreprise.pdg_full_name}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td style={styles.td}>
                      {entreprise.prestataire?.name || '-'}
                    </td>
                    <td style={styles.td}>
                      <code style={styles.code}>{entreprise.ifu_number}</code>
                    </td>
                    <td style={styles.td}>
                      {new Date(entreprise.created_at).toLocaleDateString('fr-FR')}
                    </td>
                    <td style={styles.td}>
                      {getStatusBadge(entreprise.status)}
                    </td>
                    <td style={styles.td}>
                      <Link 
                        to={`/admin/entreprises/${entreprise.id}`}
                        style={styles.viewButton}
                      >
                        {entreprise.status === 'pending' ? 'Examiner' : 'Voir détails'} →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Info box */}
        <div style={styles.infoBox}>
          <div style={styles.infoIcon}>💡</div>
          <div>
            <h3 style={styles.infoTitle}>Conseils de validation</h3>
            <p style={styles.infoText}>
              • Vérifiez la cohérence des informations (nom, IFU, RCCM)<br/>
              • Assurez-vous que les domaines d'activité correspondent<br/>
              • En cas de doute, contactez le prestataire avant de rejeter<br/>
              • Fournissez des commentaires constructifs en cas de rejet
            </p>
          </div>
        </div>
      </div>

      {/* CSS Animations */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        .table-row {
          transition: background-color 0.2s;
        }
        .table-row:hover {
          background-color: ${theme.colors.background};
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
    maxWidth: '1400px',
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
    marginBottom: '2rem',
  },
  backButton: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    marginBottom: '1rem',
    display: 'inline-block',
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
  filtersSection: {
    backgroundColor: theme.colors.secondary,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    marginBottom: '2rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  filterButtons: {
    display: 'flex',
    gap: '1rem',
    flexWrap: 'wrap',
  },
  filterButton: {
    backgroundColor: theme.colors.background,
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
  searchInput: {
    width: '100%',
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    outline: 'none',
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
  },
  tableContainer: {
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    overflow: 'hidden',
    overflowX: 'auto',
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
  },
  tableHeader: {
    backgroundColor: theme.colors.background,
  },
  th: {
    padding: '1rem',
    textAlign: 'left',
    fontWeight: '600',
    color: theme.colors.text.primary,
    borderBottom: `2px solid ${theme.colors.primaryLight}`,
    whiteSpace: 'nowrap',
  },
  tableRow: {
    borderBottom: `1px solid ${theme.colors.primaryLight}`,
  },
  td: {
    padding: '1rem',
    color: theme.colors.text.secondary,
  },
  entrepriseCell: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  tableLogo: {
    width: '50px',
    height: '50px',
    borderRadius: theme.borderRadius.md,
    objectFit: 'cover',
    flexShrink: 0,
  },
  tableLogoPlaceholder: {
    width: '50px',
    height: '50px',
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '1.5rem',
    flexShrink: 0,
  },
  entrepriseName: {
    fontWeight: '600',
    color: theme.colors.text.primary,
    marginBottom: '0.25rem',
  },
  entrepriseInfo: {
    fontSize: '0.85rem',
    color: theme.colors.text.secondary,
  },
  code: {
    backgroundColor: theme.colors.background,
    padding: '0.25rem 0.5rem',
    borderRadius: theme.borderRadius.sm,
    fontSize: '0.875rem',
    fontFamily: 'monospace',
  },
  badge: {
    padding: '0.375rem 0.75rem',
    borderRadius: theme.borderRadius.full,
    fontSize: '0.875rem',
    fontWeight: '600',
    whiteSpace: 'nowrap',
  },
  viewButton: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    whiteSpace: 'nowrap',
  },
  infoBox: {
    backgroundColor: '#DBEAFE',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: '2px solid #3B82F6',
    display: 'flex',
    gap: '1rem',
    marginTop: '2rem',
  },
  infoIcon: {
    fontSize: '2rem',
    flexShrink: 0,
  },
  infoTitle: {
    fontWeight: 'bold',
    color: '#1E40AF',
    marginBottom: '0.5rem',
  },
  infoText: {
    color: '#1E40AF',
    fontSize: '0.95rem',
    lineHeight: '1.8',
  },
};