// careasy-frontend/src/pages/admin/AdminDashboard.jsx
import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { adminApi } from '../../api/adminApi';
import { useAuth } from '../../contexts/AuthContext';
import theme from '../../config/theme';

// Import des icônes React Icons
import {
  FiAlertCircle,
  FiCheckCircle,
  FiXCircle,
  FiHome,
  FiClock,
  FiUsers,
  FiBarChart2,
  FiSettings,
  FiEye,
  FiChevronRight,
  FiFileText,
  FiSearch,
  FiFilter,
  FiDownload,
  FiMail,
  FiBell,
  FiHelpCircle,
  FiRefreshCw,
  FiTrendingUp,
  FiCalendar,
  FiBriefcase,
  FiShield,
  FiCheckSquare,
  FiList,
  FiGrid,
  FiMapPin,
  FiPhone,
  FiGlobe,
  FiZap // J'ai vérifié - FiZap existe bien dans react-icons/fi
} from 'react-icons/fi';
import {
  MdBusiness,
  MdPendingActions,
  MdVerified,
  MdCancel,
  MdDashboard,
  MdArrowForward
} from 'react-icons/md';
import { FaRegBuilding, FaChartPie, FaUserCheck } from 'react-icons/fa';
import { HiOutlineDocumentText } from 'react-icons/hi';
import { BsCardChecklist } from 'react-icons/bs';

export default function AdminDashboard() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [stats, setStats] = useState({
    total: 0,
    pending: 0,
    validated: 0,
    rejected: 0,
  });
  const [recentEntreprises, setRecentEntreprises] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

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
      setRefreshing(false);
    }
  };

  const handleRefresh = () => {
    setRefreshing(true);
    fetchStats();
  };

  const handleViewEntreprise = (id) => {
    navigate(`/admin/entreprises/${id}`);
  };

  const handleQuickAction = (action) => {
    switch(action) {
      case 'pending':
        navigate('/admin/entreprises?status=pending');
        break;
      case 'all':
        navigate('/admin/entreprises');
        break;
      case 'stats':
        navigate('/admin/stats');
        break;
      case 'settings':
        navigate('/admin/settings');
        break;
      default:
        break;
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
        {/* Header avec actions */}
        <div style={styles.header}>
          <div>
            <h1 style={styles.title}>
              <MdDashboard style={styles.titleIcon} />
              Dashboard Administrateur
            </h1>
            <p style={styles.subtitle}>
              Bienvenue, <span style={styles.userName}>{user?.name}</span> - Gérez les demandes d'entreprises
            </p>
          </div>
          <div style={styles.headerActions}>
            <button 
              onClick={handleRefresh}
              style={styles.refreshButton}
              disabled={refreshing}
            >
              <FiRefreshCw style={refreshing ? styles.refreshingIcon : styles.refreshIcon} />
              {refreshing ? 'Rafraîchissement...' : 'Rafraîchir'}
            </button>
            <button style={styles.notificationButton}>
              <FiBell style={styles.notificationIcon} />
              {stats.pending > 0 && (
                <span style={styles.notificationBadge}>{stats.pending}</span>
              )}
            </button>
          </div>
        </div>

        {/* Alerte demandes en attente */}
        {stats.pending > 0 && (
          <div style={styles.alertBox}>
            <FiAlertCircle style={styles.alertIcon} />
            <div style={styles.alertContent}>
              <div style={styles.alertTitle}>
                {stats.pending} demande{stats.pending > 1 ? 's' : ''} en attente de validation
              </div>
              <p style={styles.alertText}>
                Des entreprises attendent votre examen. Veuillez les traiter rapidement.
              </p>
            </div>
            <Link to="/admin/entreprises?status=pending" style={styles.alertButton}>
              <MdArrowForward style={styles.alertButtonIcon} />
              Examiner maintenant
            </Link>
          </div>
        )}

        {/* Cartes de statistiques */}
        <div style={styles.statsGrid}>
          <div 
            style={styles.statCard}
            onClick={() => navigate('/admin/entreprises')}
            className="stat-card"
          >
            <div style={styles.statIconContainer}>
              <FaRegBuilding style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.total}</div>
              <div style={styles.statLabel}>Total entreprises</div>
            </div>
            <FiChevronRight style={styles.statArrow} />
          </div>
          
          <div 
            style={{...styles.statCard, ...styles.pendingCard}}
            onClick={() => navigate('/admin/entreprises?status=pending')}
            className="stat-card"
          >
            <div style={{...styles.statIconContainer, backgroundColor: 'rgba(245, 158, 11, 0.1)'}}>
              <MdPendingActions style={{...styles.statIcon, color: theme.colors.warning}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: theme.colors.warning}}>
                {stats.pending}
              </div>
              <div style={styles.statLabel}>En attente</div>
            </div>
            <FiChevronRight style={styles.statArrow} />
          </div>
          
          <div 
            style={{...styles.statCard, ...styles.validatedCard}}
            onClick={() => navigate('/admin/entreprises?status=validated')}
            className="stat-card"
          >
            <div style={{...styles.statIconContainer, backgroundColor: 'rgba(34, 197, 94, 0.1)'}}>
              <MdVerified style={{...styles.statIcon, color: theme.colors.success}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: theme.colors.success}}>
                {stats.validated}
              </div>
              <div style={styles.statLabel}>Validées</div>
            </div>
            <FiChevronRight style={styles.statArrow} />
          </div>
          
          <div 
            style={{...styles.statCard, ...styles.rejectedCard}}
            onClick={() => navigate('/admin/entreprises?status=rejected')}
            className="stat-card"
          >
            <div style={{...styles.statIconContainer, backgroundColor: 'rgba(239, 68, 68, 0.1)'}}>
              <MdCancel style={{...styles.statIcon, color: theme.colors.error}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: theme.colors.error}}>
                {stats.rejected}
              </div>
              <div style={styles.statLabel}>Rejetées</div>
            </div>
            <FiChevronRight style={styles.statArrow} />
          </div>
        </div>

        {/* Actions rapides */}
        <div style={styles.section}>
          <div style={styles.sectionHeader}>
            <h2 style={styles.sectionTitle}>
              <FiZap style={styles.sectionTitleIcon} />
              Actions rapides
            </h2>
            <div style={styles.sectionActions}>
              <button 
                style={styles.viewAllButton}
                onClick={() => navigate('/admin/entreprises')}
              >
                <FiList style={styles.viewAllIcon} />
                Liste complète
              </button>
            </div>
          </div>
          
          <div style={styles.actionsGrid}>
            <div 
              style={styles.actionCard}
              onClick={() => handleQuickAction('pending')}
              className="action-card"
            >
              <div style={{...styles.actionIconContainer, backgroundColor: 'rgba(245, 158, 11, 0.1)'}}>
                <FiClock style={{...styles.actionIcon, color: theme.colors.warning}} />
              </div>
              <div style={styles.actionContent}>
                <div style={styles.actionTitle}>Demandes en attente</div>
                <div style={styles.actionDesc}>Valider ou rejeter les nouvelles entreprises</div>
              </div>
              <FiChevronRight style={styles.actionArrow} />
            </div>
            
            <div 
              style={styles.actionCard}
              onClick={() => handleQuickAction('all')}
              className="action-card"
            >
              <div style={{...styles.actionIconContainer, backgroundColor: 'rgba(59, 130, 246, 0.1)'}}>
                <FiUsers style={{...styles.actionIcon, color: theme.colors.primary}} />
              </div>
              <div style={styles.actionContent}>
                <div style={styles.actionTitle}>Toutes les entreprises</div>
                <div style={styles.actionDesc}>Consulter l'historique complet</div>
              </div>
              <FiChevronRight style={styles.actionArrow} />
            </div>
            
            <div 
              style={styles.actionCard}
              onClick={() => handleQuickAction('stats')}
              className="action-card"
            >
              <div style={{...styles.actionIconContainer, backgroundColor: 'rgba(139, 92, 246, 0.1)'}}>
                <FiBarChart2 style={{...styles.actionIcon, color: '#8b5cf6'}} />
              </div>
              <div style={styles.actionContent}>
                <div style={styles.actionTitle}>Analytiques</div>
                <div style={styles.actionDesc}>Statistiques et rapports détaillés</div>
              </div>
              <FiChevronRight style={styles.actionArrow} />
            </div>
            
            <div 
              style={styles.actionCard}
              onClick={() => handleQuickAction('settings')}
              className="action-card"
            >
              <div style={{...styles.actionIconContainer, backgroundColor: 'rgba(107, 114, 128, 0.1)'}}>
                <FiSettings style={{...styles.actionIcon, color: '#6b7280'}} />
              </div>
              <div style={styles.actionContent}>
                <div style={styles.actionTitle}>Paramètres</div>
                <div style={styles.actionDesc}>Configurer la plateforme</div>
              </div>
              <FiChevronRight style={styles.actionArrow} />
            </div>
          </div>
        </div>

        {/* Demandes récentes */}
        {recentEntreprises.length > 0 && (
          <div style={styles.section}>
            <div style={styles.sectionHeader}>
              <h2 style={styles.sectionTitle}>
                <HiOutlineDocumentText style={styles.sectionTitleIcon} />
                Demandes récentes
                <span style={styles.badge}>{recentEntreprises.length}</span>
              </h2>
              <button 
                style={styles.viewAllButton}
                onClick={() => navigate('/admin/entreprises?status=pending')}
              >
                Voir tout
                <FiChevronRight style={styles.viewAllIcon} />
              </button>
            </div>
            
            <div style={styles.tableContainer}>
              <table style={styles.table}>
                <thead>
                  <tr style={styles.tableHeader}>
                    <th style={styles.th}>
                      <FiBriefcase style={styles.thIcon} />
                      Entreprise
                    </th>
                    <th style={styles.th}>
                      <FaUserCheck style={styles.thIcon} />
                      Prestataire
                    </th>
                    <th style={styles.th}>
                      <FiCalendar style={styles.thIcon} />
                      Date
                    </th>
                    <th style={styles.th}>
                      <FiClock style={styles.thIcon} />
                      Statut
                    </th>
                    <th style={styles.th}>
                      <FiEye style={styles.thIcon} />
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {recentEntreprises.map((entreprise) => (
                    <tr 
                      key={entreprise.id} 
                      style={styles.tableRow}
                      onClick={() => handleViewEntreprise(entreprise.id)}
                      className="table-row"
                    >
                      <td style={styles.td}>
                        <div style={styles.entrepriseCell}>
                          {entreprise.logo ? (
                            <img 
                              src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                              alt={entreprise.name}
                              style={styles.tableLogo}
                              onError={(e) => {
                                e.target.onerror = null;
                                e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHJ4PSI4IiBmaWxsPSIjRUZFQ0VGIi8+PHBhdGggZD0iTTIwIDI0QzIzLjMxMzcgMjQgMjYgMjEuMzEzNyAyNiAxOEMyNiAxNC42ODYzIDIzLjMxMzcgMTIgMjAgMTJDMTYuNjg2MyAxMiAxNCAxNC42ODYzIDE0IDE4QzE0IDIxLjMxMzcgMTYuNjg2MyAyNCAyMCAyNFoiIGZpbGw9IiM5QzVDQUIiLz48cGF0aCBkPSJNMjggMzBIMTJDMTAuODk1NCAzMCAxMCAyOS4xMDQ2IDEwIDI4VjE2QzEwIDE0Ljg5NTQgMTAuODk1NCAxNCAxMiAxNEgyOEMyOS4xMDQ2IDE0IDMwIDE0Ljg5NTQgMzAgMTZWMjhDMzAgMjkuMTA0NiAyOS4xMDQ2IDMwIDI4IDMwWiIgZmlsbD0iIzlDNUNBQiIvPjwvc3ZnPg==';
                              }}
                            />
                          ) : (
                            <div style={styles.tableLogoPlaceholder}>
                              <FaRegBuilding style={styles.defaultLogoIcon} />
                            </div>
                          )}
                          <div style={styles.entrepriseInfo}>
                            <span style={styles.entrepriseName}>{entreprise.name}</span>
                            <span style={styles.entrepriseEmail}>{entreprise.email || 'Non renseigné'}</span>
                          </div>
                        </div>
                      </td>
                      <td style={styles.td}>
                        <div style={styles.prestataireCell}>
                          <div style={styles.prestataireAvatar}>
                            {entreprise.prestataire?.name?.charAt(0) || 'P'}
                          </div>
                          <span style={styles.prestataireName}>
                            {entreprise.prestataire?.name || 'Non assigné'}
                          </span>
                        </div>
                      </td>
                      <td style={styles.td}>
                        <div style={styles.dateCell}>
                          <FiCalendar style={styles.dateIcon} />
                          {new Date(entreprise.created_at).toLocaleDateString('fr-FR', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric'
                          })}
                        </div>
                      </td>
                      <td style={styles.td}>
                        <div style={styles.statusBadge}>
                          <FiClock style={styles.statusIcon} />
                          En attente
                        </div>
                      </td>
                      <td style={styles.td}>
                        <button 
                          style={styles.actionButton}
                          onClick={(e) => {
                            e.stopPropagation();
                            handleViewEntreprise(entreprise.id);
                          }}
                        >
                          <FiEye style={styles.actionButtonIcon} />
                          Voir détails
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Guide et informations */}
        <div style={styles.infoGrid}>
          <div style={styles.infoCard}>
            <div style={styles.infoCardHeader}>
              <FiHelpCircle style={styles.infoCardIcon} />
              <h3 style={styles.infoCardTitle}>Guide de validation</h3>
            </div>
            <ul style={styles.infoList}>
              <li style={styles.infoListItem}>
                <FiCheckSquare style={styles.listIcon} />
                Vérifier les documents légaux (IFU, RCCM)
              </li>
              <li style={styles.infoListItem}>
                <FiCheckSquare style={styles.listIcon} />
                Confirmer l'adresse email et téléphone
              </li>
              <li style={styles.infoListItem}>
                <FiCheckSquare style={styles.listIcon} />
                Valider le statut juridique
              </li>
              <li style={styles.infoListItem}>
                <FiCheckSquare style={styles.listIcon} />
                Examiner les informations du prestataire
              </li>
            </ul>
          </div>
          
          <div style={styles.infoCard}>
            <div style={styles.infoCardHeader}>
              <FiShield style={styles.infoCardIcon} />
              <h3 style={styles.infoCardTitle}>Sécurité des données</h3>
            </div>
            <p style={styles.infoCardText}>
              Toutes les données sont cryptées et protégées conformément au RGPD.
              Les documents sensibles sont automatiquement supprimés après 6 mois.
            </p>
            <div style={styles.infoCardStats}>
              <div style={styles.infoStat}>
                <div style={styles.infoStatNumber}>100%</div>
                <div style={styles.infoStatLabel}>Données cryptées</div>
              </div>
              <div style={styles.infoStat}>
                <div style={styles.infoStatNumber}>ISO 27001</div>
                <div style={styles.infoStatLabel}>Certifié</div>
              </div>
            </div>
          </div>
        </div>

        {/* Pied de page du dashboard */}
        <div style={styles.footer}>
          <div style={styles.footerText}>
            <FiGlobe style={styles.footerIcon} />
            Dernière mise à jour : {new Date().toLocaleDateString('fr-FR')}
          </div>
          <div style={styles.footerLinks}>
            <Link to="/admin/docs" style={styles.footerLink}>
              <FiFileText style={styles.footerLinkIcon} />
              Documentation
            </Link>
            <Link to="/admin/support" style={styles.footerLink}>
              <FiMail style={styles.footerLinkIcon} />
              Support
            </Link>
          </div>
        </div>
      </div>

      {/* CSS Animations */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(10px); }
          to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.7; }
        }
        
        .stat-card, .action-card, .table-row {
          transition: all 0.3s ease;
          cursor: pointer;
          animation: fadeIn 0.5s ease-out;
        }
        
        .stat-card:hover {
          transform: translateY(-4px);
          box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        
        .action-card:hover {
          transform: translateX(4px);
          background-color: rgba(59, 130, 246, 0.05);
        }
        
        .table-row:hover {
          background-color: rgba(59, 130, 246, 0.04);
          transform: scale(1.002);
        }
        
        .refreshing {
          animation: spin 1s linear infinite;
        }
        
        .pulse {
          animation: pulse 2s infinite;
        }
      `}</style>
    </div>
  );
}

// Styles object reste identique - je vais juste ajouter les styles manquants
const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f8fafc',
    padding: '2rem 0',
  },
  content: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '0 1.5rem',
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
    marginBottom: '2.5rem',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  title: {
    fontSize: '2rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.5rem',
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  titleIcon: {
    fontSize: '2rem',
    color: theme.colors.primary,
  },
  subtitle: {
    color: '#64748b',
    fontSize: '1rem',
    lineHeight: '1.5',
  },
  userName: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  headerActions: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
  },
  refreshButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    border: `1px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    padding: '0.625rem 1rem',
    color: '#475569',
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  refreshIcon: {
    fontSize: '1rem',
  },
  refreshingIcon: {
    fontSize: '1rem',
    animation: 'spin 1s linear infinite',
  },
  notificationButton: {
    position: 'relative',
    backgroundColor: '#fff',
    border: `1px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    padding: '0.625rem',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  notificationIcon: {
    fontSize: '1.25rem',
    color: '#475569',
  },
  notificationBadge: {
    position: 'absolute',
    top: '-5px',
    right: '-5px',
    backgroundColor: theme.colors.error,
    color: '#fff',
    fontSize: '0.75rem',
    fontWeight: '600',
    borderRadius: '50%',
    width: '18px',
    height: '18px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  alertBox: {
    backgroundColor: '#fffbeb',
    border: `1px solid ${theme.colors.warning}`,
    borderRadius: theme.borderRadius.lg,
    padding: '1.5rem',
    marginBottom: '2rem',
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    flexWrap: 'wrap',
  },
  alertIcon: {
    fontSize: '1.5rem',
    color: theme.colors.warning,
    flexShrink: 0,
  },
  alertContent: {
    flex: 1,
  },
  alertTitle: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: theme.colors.warning,
    marginBottom: '0.25rem',
  },
  alertText: {
    color: '#92400e',
    fontSize: '0.875rem',
  },
  alertButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: theme.colors.warning,
    color: '#fff',
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '0.875rem',
    whiteSpace: 'nowrap',
    transition: 'all 0.2s',
  },
  alertButtonIcon: {
    fontSize: '1rem',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
    gap: '1.5rem',
    marginBottom: '3rem',
  },
  statCard: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    border: `1px solid #e2e8f0`,
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
    cursor: 'pointer',
  },
  pendingCard: {
    borderColor: '#fed7aa',
  },
  validatedCard: {
    borderColor: '#bbf7d0',
  },
  rejectedCard: {
    borderColor: '#fecaca',
  },
  statIconContainer: {
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
    borderRadius: theme.borderRadius.md,
    padding: '0.75rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  statIcon: {
    fontSize: '1.5rem',
    color: theme.colors.primary,
  },
  statContent: {
    flex: 1,
  },
  statNumber: {
    fontSize: '1.75rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.25rem',
  },
  statLabel: {
    fontSize: '0.875rem',
    color: '#64748b',
    fontWeight: '500',
  },
  statArrow: {
    fontSize: '1.25rem',
    color: '#cbd5e1',
  },
  section: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: `1px solid #e2e8f0`,
    marginBottom: '2rem',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
  },
  sectionHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '1.5rem',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  sectionTitle: {
    fontSize: '1.25rem',
    fontWeight: '600',
    color: '#1e293b',
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  sectionTitleIcon: {
    fontSize: '1.25rem',
    color: theme.colors.primary,
  },
  badge: {
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.primary,
    fontSize: '0.75rem',
    fontWeight: '600',
    padding: '0.25rem 0.75rem',
    borderRadius: '9999px',
    marginLeft: '0.75rem',
  },
  sectionActions: {
    display: 'flex',
    gap: '0.75rem',
  },
  viewAllButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: 'transparent',
    border: `1px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    padding: '0.5rem 1rem',
    color: theme.colors.primary,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  viewAllIcon: {
    fontSize: '1rem',
  },
  actionsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '1rem',
  },
  actionCard: {
    backgroundColor: '#fff',
    padding: '1.25rem',
    borderRadius: theme.borderRadius.md,
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    border: `1px solid #e2e8f0`,
    cursor: 'pointer',
  },
  actionIconContainer: {
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
    borderRadius: theme.borderRadius.md,
    padding: '0.75rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionIcon: {
    fontSize: '1.25rem',
    color: theme.colors.primary,
  },
  actionContent: {
    flex: 1,
  },
  actionTitle: {
    fontSize: '1rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.25rem',
  },
  actionDesc: {
    fontSize: '0.875rem',
    color: '#64748b',
    lineHeight: '1.4',
  },
  actionArrow: {
    fontSize: '1.25rem',
    color: '#cbd5e1',
  },
  tableContainer: {
    overflowX: 'auto',
    borderRadius: theme.borderRadius.md,
    border: `1px solid #e2e8f0`,
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
    minWidth: '800px',
  },
  tableHeader: {
    backgroundColor: '#f8fafc',
  },
  th: {
    padding: '1rem',
    textAlign: 'left',
    fontWeight: '600',
    color: '#475569',
    fontSize: '0.875rem',
    borderBottom: `1px solid #e2e8f0`,
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  thIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  tableRow: {
    borderBottom: `1px solid #e2e8f0`,
  },
  td: {
    padding: '1rem',
    color: '#475569',
    fontSize: '0.875rem',
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
    backgroundColor: '#f1f5f9',
  },
  tableLogoPlaceholder: {
    width: '40px',
    height: '40px',
    borderRadius: theme.borderRadius.md,
    backgroundColor: '#f1f5f9',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  defaultLogoIcon: {
    fontSize: '1.25rem',
    color: '#94a3b8',
  },
  entrepriseInfo: {
    display: 'flex',
    flexDirection: 'column',
  },
  entrepriseName: {
    fontWeight: '600',
    color: '#1e293b',
    fontSize: '0.875rem',
  },
  entrepriseEmail: {
    color: '#94a3b8',
    fontSize: '0.75rem',
    marginTop: '0.125rem',
  },
  prestataireCell: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  prestataireAvatar: {
    width: '32px',
    height: '32px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '0.75rem',
    fontWeight: '600',
  },
  prestataireName: {
    fontWeight: '500',
    color: '#1e293b',
  },
  dateCell: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  dateIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  statusBadge: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fffbeb',
    color: '#92400e',
    padding: '0.375rem 0.75rem',
    borderRadius: '9999px',
    fontSize: '0.75rem',
    fontWeight: '600',
    width: 'fit-content',
  },
  statusIcon: {
    fontSize: '0.875rem',
  },
  actionButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: 'transparent',
    border: `1px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    padding: '0.375rem 0.75rem',
    color: theme.colors.primary,
    fontSize: '0.75rem',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  actionButtonIcon: {
    fontSize: '0.875rem',
  },
  infoGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
    gap: '1.5rem',
    marginBottom: '2rem',
  },
  infoCard: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: `1px solid #e2e8f0`,
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
  },
  infoCardHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    marginBottom: '1rem',
  },
  infoCardIcon: {
    fontSize: '1.5rem',
    color: theme.colors.primary,
  },
  infoCardTitle: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#1e293b',
  },
  infoList: {
    listStyle: 'none',
    padding: 0,
    margin: 0,
  },
  infoListItem: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    padding: '0.5rem 0',
    color: '#475569',
    fontSize: '0.875rem',
  },
  listIcon: {
    fontSize: '0.875rem',
    color: theme.colors.success,
    flexShrink: 0,
  },
  infoCardText: {
    color: '#475569',
    fontSize: '0.875rem',
    lineHeight: '1.6',
    marginBottom: '1.5rem',
  },
  infoCardStats: {
    display: 'flex',
    gap: '1.5rem',
  },
  infoStat: {
    textAlign: 'center',
  },
  infoStatNumber: {
    fontSize: '1.25rem',
    fontWeight: '700',
    color: theme.colors.primary,
    marginBottom: '0.25rem',
  },
  infoStatLabel: {
    fontSize: '0.75rem',
    color: '#64748b',
    fontWeight: '500',
  },
  footer: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: '1.5rem 0',
    borderTop: `1px solid #e2e8f0`,
    flexWrap: 'wrap',
    gap: '1rem',
  },
  footerText: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    color: '#64748b',
    fontSize: '0.875rem',
  },
  footerIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  footerLinks: {
    display: 'flex',
    gap: '1.5rem',
  },
  footerLink: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    color: theme.colors.primary,
    textDecoration: 'none',
    fontSize: '0.875rem',
    fontWeight: '500',
    transition: 'all 0.2s',
  },
  footerLinkIcon: {
    fontSize: '1rem',
  },
};