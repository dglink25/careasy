// careasy-frontend/src/pages/admin/AdminDashboard.jsx
import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { adminApi } from '../../api/adminApi';
import { useAuth } from '../../contexts/AuthContext';
import theme from '../../config/theme';

export default function AdminDashboard() {
  const { user } = useAuth();
  const [stats, setStats] = useState({
    total: 0,
    pending: 0,
    validated: 0,
    rejected: 0,
  });
  const [recentEntreprises, setRecentEntreprises] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchStats();
  }, []);

  const fetchStats = async () => {
    try {
      setLoading(true);
      const data = await adminApi.getEntreprises();
      
      const stats = {
        total: data.data.length,
        pending: data.data.filter(e => e.status === 'pending').length,
        validated: data.data.filter(e => e.status === 'validated').length,
        rejected: data.data.filter(e => e.status === 'rejected').length,
      };
      
      setStats(stats);
      
      // Prendre les 5 dernières demandes en attente
      const recent = data.data
        .filter(e => e.status === 'pending')
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 5);
      
      setRecentEntreprises(recent);
    } catch (err) {
      console.error('Erreur chargement stats:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement du dashboard...</p>
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
            <h1 style={styles.title}>Dashboard Administrateur</h1>
            <p style={styles.subtitle}>
              Bienvenue {user?.name}  - Gérez les demandes d'entreprises
            </p>
          </div>
        </div>

        {/* Alerte demandes en attente */}
        {stats.pending > 0 && (
          <div style={styles.alertBox}>
            <div style={styles.alertIcon}></div>
            <div>
              <div style={styles.alertTitle}>
                {stats.pending} demande{stats.pending > 1 ? 's' : ''} en attente
              </div>
              <p style={styles.alertText}>
                Des entreprises attendent votre validation
              </p>
            </div>
            <Link to="/admin/entreprises?status=pending" style={styles.alertButton}>
              Voir les demandes
            </Link>
          </div>
        )}

        {/* Statistiques */}
        <div style={styles.statsGrid}>
          <Link to="/admin/entreprises" style={styles.statCard} className="stat-card">
            <div style={styles.statIcon}></div>
            <div>
              <div style={styles.statNumber}>{stats.total}</div>
              <div style={styles.statLabel}>Total entreprises</div>
            </div>
          </Link>
          
          <Link 
            to="/admin/entreprises?status=pending" 
            style={{...styles.statCard, borderColor: theme.colors.warning}}
            className="stat-card"
          >
            <div style={styles.statIcon}></div>
            <div>
              <div style={{...styles.statNumber, color: theme.colors.warning}}>
                {stats.pending}
              </div>
              <div style={styles.statLabel}>En attente</div>
            </div>
          </Link>
          
          <Link 
            to="/admin/entreprises?status=validated" 
            style={{...styles.statCard, borderColor: theme.colors.success}}
            className="stat-card"
          >
            <div style={styles.statIcon}></div>
            <div>
              <div style={{...styles.statNumber, color: theme.colors.success}}>
                {stats.validated}
              </div>
              <div style={styles.statLabel}>Validées</div>
            </div>
          </Link>
          
          <Link 
            to="/admin/entreprises?status=rejected" 
            style={{...styles.statCard, borderColor: theme.colors.error}}
            className="stat-card"
          >
            <div style={styles.statIcon}></div>
            <div>
              <div style={{...styles.statNumber, color: theme.colors.error}}>
                {stats.rejected}
              </div>
              <div style={styles.statLabel}>Rejetées</div>
            </div>
          </Link>
        </div>

        {/* Actions rapides */}
        <div style={styles.section}>
          <h2 style={styles.sectionTitle}>⚡ Actions rapides</h2>
          <div style={styles.actionsGrid}>
            <Link to="/admin/entreprises?status=pending" style={styles.actionCard}>
              <div style={styles.actionIcon}></div>
              <div style={styles.actionTitle}>Demandes en attente</div>
              <div style={styles.actionDesc}>Valider ou rejeter les nouvelles entreprises</div>
            </Link>
            
            <Link to="/admin/entreprises" style={styles.actionCard}>
              <div style={styles.actionIcon}></div>
              <div style={styles.actionTitle}>Toutes les entreprises</div>
              <div style={styles.actionDesc}>Consulter l'historique complet</div>
            </Link>
            
            <Link to="/admin/stats" style={styles.actionCard}>
              <div style={styles.actionIcon}></div>
              <div style={styles.actionTitle}>Statistiques</div>
              <div style={styles.actionDesc}>Analyser les données (à venir)</div>
            </Link>
            
            <Link to="/admin/settings" style={styles.actionCard}>
              <div style={styles.actionIcon}>⚙️</div>
              <div style={styles.actionTitle}>Paramètres</div>
              <div style={styles.actionDesc}>Configurer la plateforme (à venir)</div>
            </Link>
          </div>
        </div>

        {/* Demandes récentes */}
        {recentEntreprises.length > 0 && (
          <div style={styles.section}>
            <div style={styles.sectionHeader}>
              <h2 style={styles.sectionTitle}> Demandes récentes</h2>
              <Link to="/admin/entreprises?status=pending" style={styles.viewAllLink}>
                Voir tout →
              </Link>
            </div>
            
            <div style={styles.tableContainer}>
              <table style={styles.table}>
                <thead>
                  <tr style={styles.tableHeader}>
                    <th style={styles.th}>Entreprise</th>
                    <th style={styles.th}>Prestataire</th>
                    <th style={styles.th}>Date</th>
                    <th style={styles.th}>Statut</th>
                    <th style={styles.th}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {recentEntreprises.map((entreprise) => (
                    <tr key={entreprise.id} style={styles.tableRow}>
                      <td style={styles.td}>
                        <div style={styles.entrepriseCell}>
                          {entreprise.logo ? (
                            <img 
                              src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                              alt={entreprise.name}
                              style={styles.tableLogo}
                            />
                          ) : (
                            <div style={styles.tableLogoPlaceholder}></div>
                          )}
                          <span style={styles.entrepriseName}>{entreprise.name}</span>
                        </div>
                      </td>
                      <td style={styles.td}>{entreprise.prestataire?.name || '-'}</td>
                      <td style={styles.td}>
                        {new Date(entreprise.created_at).toLocaleDateString('fr-FR')}
                      </td>
                      <td style={styles.td}>
                        <span style={styles.statusBadge}> En attente</span>
                      </td>
                      <td style={styles.td}>
                        <Link 
                          to={`/admin/entreprises/${entreprise.id}`}
                          style={styles.viewButton}
                        >
                          Examiner →
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Info box */}
        <div style={styles.infoBox}>
          <div style={styles.infoIcon}></div>
          <div>
            <h3 style={styles.infoTitle}>Responsabilités de l'administrateur</h3>
            <p style={styles.infoText}>
              • Examinez attentivement chaque demande d'entreprise<br/>
              • Vérifiez les documents légaux (IFU, RCCM, certificat)<br/>
              • Fournissez des commentaires clairs en cas de rejet<br/>
              • Les prestataires seront notifiés par email de votre décision
            </p>
          </div>
        </div>
      </div>

      {/* CSS Animations */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        .stat-card {
          transition: all 0.3s ease;
        }
        .stat-card:hover {
          transform: translateY(-5px);
          box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
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
    marginBottom: '2rem',
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
  alertBox: {
    backgroundColor: '#FEF3C7',
    border: `2px solid ${theme.colors.warning}`,
    borderRadius: theme.borderRadius.lg,
    padding: '1.5rem',
    marginBottom: '2rem',
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    flexWrap: 'wrap',
  },
  alertIcon: {
    fontSize: '2rem',
    flexShrink: 0,
  },
  alertTitle: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    color: theme.colors.warning,
    marginBottom: '0.25rem',
  },
  alertText: {
    color: theme.colors.text.secondary,
  },
  alertButton: {
    backgroundColor: theme.colors.warning,
    color: '#fff',
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    textDecoration: 'none',
    fontWeight: '600',
    marginLeft: 'auto',
    whiteSpace: 'nowrap',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
    gap: '1.5rem',
    marginBottom: '3rem',
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
    textDecoration: 'none',
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
  section: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    marginBottom: '2rem',
  },
  sectionHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '1.5rem',
  },
  sectionTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
  },
  viewAllLink: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
  },
  actionsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '1.5rem',
  },
  actionCard: {
    backgroundColor: theme.colors.background,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: `2px solid ${theme.colors.primaryLight}`,
    textDecoration: 'none',
    transition: 'all 0.3s',
  },
  actionIcon: {
    fontSize: '2.5rem',
    marginBottom: '0.75rem',
  },
  actionTitle: {
    fontSize: '1.125rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  actionDesc: {
    color: theme.colors.text.secondary,
    fontSize: '0.95rem',
  },
  tableContainer: {
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
    width: '40px',
    height: '40px',
    borderRadius: theme.borderRadius.md,
    objectFit: 'cover',
  },
  tableLogoPlaceholder: {
    width: '40px',
    height: '40px',
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '1.25rem',
  },
  entrepriseName: {
    fontWeight: '600',
    color: theme.colors.text.primary,
  },
  statusBadge: {
    padding: '0.375rem 0.75rem',
    borderRadius: theme.borderRadius.full,
    backgroundColor: '#FEF3C7',
    color: theme.colors.warning,
    fontSize: '0.875rem',
    fontWeight: '600',
  },
  viewButton: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
  },
  infoBox: {
    backgroundColor: '#DBEAFE',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: '2px solid #3B82F6',
    display: 'flex',
    gap: '1rem',
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