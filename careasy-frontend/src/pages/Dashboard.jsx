import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { entrepriseApi } from '../api/entrepriseApi';
import { serviceApi } from '../api/serviceApi';

// Import des icônes React Icons
import {
  FiSearch,
  FiCalendar,
  FiMessageSquare,
  FiStar,
  FiEye,
  FiUsers,
  FiDollarSign,
  FiTrendingUp,
  FiBell,
  FiSettings,
  FiHelpCircle,
  FiDownload,
  FiActivity,
  FiBarChart2,
  FiTarget,
  FiCheckCircle,
  FiClock,
  FiAlertCircle,
  FiRefreshCw,
  FiChevronRight,
  FiPlus,
  FiGrid,
  FiBriefcase,
  FiTool
} from 'react-icons/fi';
import {
  MdDashboard,
  MdOutlineBusiness,
  MdOutlineWork,
  MdOutlinePerson,
  MdOutlineEmail,
  MdOutlinePhone,
  MdOutlineVerified,
  MdOutlineNotifications,
  MdOutlineSettings,
  MdOutlineDescription,
  MdOutlineLocationOn,
  MdOutlineAccessTime,
  MdOutlineAttachMoney,
  MdOutlineAnalytics,
  MdOutlineInsights,
  MdOutlineStorefront,
  MdOutlineLibraryAdd,
  MdOutlineInventory,
  MdOutlineSchedule,
  MdOutlineDirectionsCar,
  MdOutlineLocalShipping,
  MdOutlineTwoWheeler
} from 'react-icons/md';

export default function Dashboard() {
  const { user } = useAuth();
  const navigate = useNavigate();
  
  const [loading, setLoading] = useState(true);
  const [entreprises, setEntreprises] = useState([]);
  const [services, setServices] = useState([]);
  const [stats, setStats] = useState({
    totalEntreprises: 0,
    validatedEntreprises: 0,
    pendingEntreprises: 0,
    totalServices: 0,
    servicesWithPrice: 0,
    services24h: 0,
    activeClients: 0,
    monthlyRevenue: 0
  });

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      
      // Charger les données en parallèle
      const [entreprisesData, servicesData] = await Promise.all([
        entrepriseApi.getMesEntreprises(),
        serviceApi.getMesServices()
      ]);

      setEntreprises(entreprisesData);
      setServices(servicesData);

      // Calculer les statistiques
      const validatedEntreprises = entreprisesData.filter(e => e.status === 'validated').length;
      const pendingEntreprises = entreprisesData.filter(e => e.status === 'pending').length;
      const servicesWithPrice = servicesData.filter(s => s.price).length;
      const services24h = servicesData.filter(s => s.is_open_24h).length;
      
      // Calculer le revenue mensuel estimé (exemple)
      const monthlyRevenue = servicesData
        .filter(s => s.price)
        .reduce((acc, s) => acc + (s.price * 3), 0); // Estimation: 3 ventes par service

      setStats({
        totalEntreprises: entreprisesData.length,
        validatedEntreprises,
        pendingEntreprises,
        totalServices: servicesData.length,
        servicesWithPrice,
        services24h,
        activeClients: 24, // À remplacer par API réelle
        monthlyRevenue
      });

    } catch (err) {
      console.error('Erreur chargement dashboard:', err);
    } finally {
      setLoading(false);
    }
  };

  const getInitials = (name) => {
    if (!name) return '?';
    return name
      .split(' ')
      .map(word => word[0])
      .join('')
      .toUpperCase()
      .substring(0, 2);
  };

  const formatPrice = (price) => {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'XOF',
      minimumFractionDigits: 0
    }).format(price);
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement du tableau de bord...</p>
          <p style={styles.loadingSubtext}>Préparation de vos données</p>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header avec informations utilisateur */}
        <div style={styles.header}>
          <div style={styles.headerMain}>
            <div>
              <h1 style={styles.title}>
                <MdDashboard style={styles.titleIcon} />
                Tableau de Bord
              </h1>
              <p style={styles.subtitle}>
                Bienvenue sur votre espace professionnel CarEasy
              </p>
            </div>
            <div style={styles.headerActions}>
              <button style={styles.headerActionButton}>
                <FiBell style={styles.headerActionIcon} />
                <span style={styles.notificationBadge}>3</span>
              </button>
              <button style={styles.headerActionButton}>
                <FiSettings style={styles.headerActionIcon} />
              </button>
              <button style={styles.headerActionButton}>
                <FiHelpCircle style={styles.headerActionIcon} />
              </button>
            </div>
          </div>

          
        </div>

        {/* Statistiques principales */}
        <div style={styles.statsGrid}>
          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <MdOutlineBusiness style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.totalEntreprises}</div>
              <div style={styles.statLabel}>Entreprises</div>
              <div style={styles.statSubtext}>
                {stats.validatedEntreprises} validées • {stats.pendingEntreprises} en attente
              </div>
            </div>
            <div style={styles.statTrend}>
              <FiTrendingUp style={styles.statTrendIcon} />
              <span style={styles.statTrendText}>+2 ce mois</span>
            </div>
          </div>

          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <MdOutlineWork style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.totalServices}</div>
              <div style={styles.statLabel}>Services</div>
              <div style={styles.statSubtext}>
                {stats.servicesWithPrice} avec tarif • {stats.services24h} 24h/24
              </div>
            </div>
            <div style={styles.statTrend}>
              <FiTrendingUp style={styles.statTrendIcon} />
              <span style={styles.statTrendText}>+5 ce mois</span>
            </div>
          </div>

          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <FiUsers style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.activeClients}</div>
              <div style={styles.statLabel}>Clients actifs</div>
              <div style={styles.statSubtext}>
                12 nouveaux ce mois
              </div>
            </div>
            <div style={styles.statTrend}>
              <FiActivity style={styles.statTrendIcon} />
              <span style={styles.statTrendText}>Actif</span>
            </div>
          </div>

          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <FiDollarSign style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{formatPrice(stats.monthlyRevenue)}</div>
              <div style={styles.statLabel}>Revenue estimé</div>
              <div style={styles.statSubtext}>
                Ce mois
              </div>
            </div>
            <div style={styles.statTrend}>
              <FiBarChart2 style={styles.statTrendIcon} />
              <span style={styles.statTrendText}>+15%</span>
            </div>
          </div>
        </div>

        {/* Actions rapides et contenu principal */}
        <div style={styles.mainContent}>
          {/* Colonne gauche - Actions rapides */}
          <div style={styles.leftColumn}>
            {/* Actions rapides */}
            <div style={styles.sectionCard}>
              <div style={styles.sectionHeader}>
                <h3 style={styles.sectionTitle}>
                  <FiTarget style={styles.sectionIcon} />
                  Actions rapides
                </h3>
              </div>
              <div style={styles.actionsGrid}>
                <Link to="/services/creer" style={styles.actionCard}>
                  <div style={styles.actionIcon}>
                    <FiPlus />
                  </div>
                  <div style={styles.actionContent}>
                    <div style={styles.actionTitle}>Nouveau service</div>
                    <div style={styles.actionDescription}>Ajouter un service</div>
                  </div>
                  <FiChevronRight style={styles.actionArrow} />
                </Link>

                <Link to="/entreprises/creer" style={styles.actionCard}>
                  <div style={styles.actionIcon}>
                    <MdOutlineBusiness />
                  </div>
                  <div style={styles.actionContent}>
                    <div style={styles.actionTitle}>Nouvelle entreprise</div>
                    <div style={styles.actionDescription}>Créer une entreprise</div>
                  </div>
                  <FiChevronRight style={styles.actionArrow} />
                </Link>

                <Link to="/mes-services" style={styles.actionCard}>
                  <div style={styles.actionIcon}>
                    <MdOutlineWork />
                  </div>
                  <div style={styles.actionContent}>
                    <div style={styles.actionTitle}>Gérer les services</div>
                    <div style={styles.actionDescription}>Voir tous les services</div>
                  </div>
                  <FiChevronRight style={styles.actionArrow} />
                </Link>

                <Link to="/mes-entreprises" style={styles.actionCard}>
                  <div style={styles.actionIcon}>
                    <FiBriefcase />
                  </div>
                  <div style={styles.actionContent}>
                    <div style={styles.actionTitle}>Gérer les entreprises</div>
                    <div style={styles.actionDescription}>Voir les entreprises</div>
                  </div>
                  <FiChevronRight style={styles.actionArrow} />
                </Link>

                <button style={styles.actionCard}>
                  <div style={styles.actionIcon}>
                    <FiDownload />
                  </div>
                  <div style={styles.actionContent}>
                    <div style={styles.actionTitle}>Exporter les données</div>
                    <div style={styles.actionDescription}>Télécharger un rapport</div>
                  </div>
                  <FiChevronRight style={styles.actionArrow} />
                </button>

                <button style={styles.actionCard}>
                  <div style={styles.actionIcon}>
                    <FiSettings />
                  </div>
                  <div style={styles.actionContent}>
                    <div style={styles.actionTitle}>Paramètres</div>
                    <div style={styles.actionDescription}>Configurer votre compte</div>
                  </div>
                  <FiChevronRight style={styles.actionArrow} />
                </button>
              </div>
            </div>

           
          </div>

          {/* Colonne droite - État et notifications */}
          <div style={styles.rightColumn}>
            {/* État des entreprises */}
            <div style={styles.sectionCard}>
              <div style={styles.sectionHeader}>
                <h3 style={styles.sectionTitle}>
                  <MdOutlineStorefront style={styles.sectionIcon} />
                  État des entreprises
                </h3>
                <Link to="/mes-entreprises" style={styles.viewAllLink}>
                  Voir toutes
                </Link>
              </div>
              <div style={styles.entreprisesList}>
                {entreprises.slice(0, 3).map((entreprise) => (
                  <div key={entreprise.id} style={styles.entrepriseItem}>
                    <div style={styles.entrepriseInfo}>
                      {entreprise.logo ? (
                        <img 
                          src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                          alt={entreprise.name}
                          style={styles.entrepriseLogo}
                        />
                      ) : (
                        <div style={styles.entrepriseLogoPlaceholder}>
                          <MdOutlineBusiness />
                        </div>
                      )}
                      <div>
                        <div style={styles.entrepriseName}>{entreprise.name}</div>
                        <div style={styles.entrepriseStatus}>
                          <span style={{
                            ...styles.statusBadge,
                            backgroundColor: entreprise.status === 'validated' ? '#d1fae5' : 
                                          entreprise.status === 'pending' ? '#fef3c7' : '#fee2e2',
                            color: entreprise.status === 'validated' ? '#059669' : 
                                  entreprise.status === 'pending' ? '#d97706' : '#dc2626'
                          }}>
                            {entreprise.status === 'validated' ? 'Validée' : 
                             entreprise.status === 'pending' ? 'En attente' : 'Rejetée'}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div style={styles.entrepriseServices}>
                      {entreprise.services_count || 0} service{entreprise.services_count !== 1 ? 's' : ''}
                    </div>
                  </div>
                ))}
                {entreprises.length === 0 && (
                  <div style={styles.emptyState}>
                    <MdOutlineBusiness style={styles.emptyStateIcon} />
                    <p style={styles.emptyStateText}>Aucune entreprise créée</p>
                    <Link to="/entreprises/creer" style={styles.emptyStateButton}>
                      Créer une entreprise
                    </Link>
                  </div>
                )}
              </div>
            </div>

            {/* Services récents */}
            <div style={styles.sectionCard}>
              <div style={styles.sectionHeader}>
                <h3 style={styles.sectionTitle}>
                  <MdOutlineInventory style={styles.sectionIcon} />
                  Services récents
                </h3>
                <Link to="/mes-services" style={styles.viewAllLink}>
                  Voir tous
                </Link>
              </div>
              <div style={styles.servicesList}>
                {services.slice(0, 3).map((service) => (
                  <div key={service.id} style={styles.serviceItem}>
                    <div style={styles.serviceIcon}>
                      <MdOutlineWork />
                    </div>
                    <div style={styles.serviceInfo}>
                      <div style={styles.serviceName}>{service.name}</div>
                      <div style={styles.serviceDetails}>
                        <span style={styles.servicePrice}>
                          {service.price ? formatPrice(service.price) : 'Sur devis'}
                        </span>
                        {service.is_open_24h && (
                          <span style={styles.service24h}>24h/24</span>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
                {services.length === 0 && (
                  <div style={styles.emptyState}>
                    <MdOutlineWork style={styles.emptyStateIcon} />
                    <p style={styles.emptyStateText}>Aucun service créé</p>
                    <Link to="/services/creer" style={styles.emptyStateButton}>
                      Créer un service
                    </Link>
                  </div>
                )}
              </div>
            </div>

            
          </div>
        </div>

        {/* Conseils */}
        <div style={styles.tipsSection}>
          <div style={styles.tipsHeader}>
            <MdOutlineInsights style={styles.tipsIcon} />
            <h3 style={styles.tipsTitle}>Conseils pour optimiser votre présence</h3>
          </div>
          <div style={styles.tipsGrid}>
            <div style={styles.tipCard}>
              <div style={styles.tipIcon}>
                <FiTool />
              </div>
              <h4 style={styles.tipCardTitle}>Services complets</h4>
              <p style={styles.tipCardText}>
                Ajoutez des photos de qualité et des descriptions détaillées pour chaque service.
              </p>
            </div>
            <div style={styles.tipCard}>
              <div style={styles.tipIcon}>
                <MdOutlineAttachMoney />
              </div>
              <h4 style={styles.tipCardTitle}>Tarifs transparents</h4>
              <p style={styles.tipCardText}>
                Indiquez des prix clairs pour augmenter vos chances d'être contacté.
              </p>
            </div>
            <div style={styles.tipCard}>
              <div style={styles.tipIcon}>
                <MdOutlineAccessTime />
              </div>
              <h4 style={styles.tipCardTitle}>Disponibilité</h4>
              <p style={styles.tipCardText}>
                Mettez à jour vos horaires régulièrement pour éviter les rendez-vous manqués.
              </p>
            </div>
            <div style={styles.tipCard}>
              <div style={styles.tipIcon}>
                <MdOutlineDescription />
              </div>
              <h4 style={styles.tipCardTitle}>Descriptions riches</h4>
              <p style={styles.tipCardText}>
                Décrivez précisément vos services, compétences et certifications.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* CSS Animations */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
          0% { transform: scale(1); }
          50% { transform: scale(1.05); }
          100% { transform: scale(1); }
        }
        
        .action-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
        }
        
        .stat-card:hover {
          transform: translateY(-4px);
          box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        }
        
        .notification-item:hover {
          background-color: #f8fafc;
        }
      `}</style>
    </div>
  );
}

// Styles
const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f8fafc',
    padding: '2rem 0 4rem 0',
  },
  content: {
    maxWidth: '1400px',
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
    border: '4px solid #dbeafe',
    borderTop: '4px solid #ef4444',
    borderRadius: '50%',
    animation: 'spin 1s linear infinite',
  },
  loadingText: {
    color: '#475569',
    fontSize: '1.125rem',
  },
  loadingSubtext: {
    color: '#94a3b8',
    fontSize: '0.875rem',
  },
  header: {
    marginBottom: '2rem',
  },
  headerMain: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '1.5rem',
  },
  title: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    fontSize: '2.25rem',
    fontWeight: '800',
    color: '#1e293b',
    marginBottom: '0.5rem',
  },
  titleIcon: {
    fontSize: '2.25rem',
    color: '#ef4444',
  },
  subtitle: {
    color: '#64748b',
    fontSize: '1.125rem',
  },
  headerActions: {
    display: 'flex',
    gap: '0.5rem',
  },
  headerActionButton: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    width: '44px',
    height: '44px',
    borderRadius: '0.75rem',
    color: '#475569',
    cursor: 'pointer',
    position: 'relative',
    transition: 'all 0.2s',
  },
  headerActionIcon: {
    fontSize: '1.25rem',
  },
  notificationBadge: {
    position: 'absolute',
    top: '-4px',
    right: '-4px',
    backgroundColor: '#ef4444',
    color: '#fff',
    fontSize: '0.75rem',
    fontWeight: '600',
    width: '18px',
    height: '18px',
    borderRadius: '50%',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  userCard: {
    backgroundColor: '#fff',
    padding: '2rem',
    borderRadius: '1.5rem',
    border: '1px solid #e2e8f0',
    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05)',
    display: 'flex',
    alignItems: 'center',
    gap: '1.5rem',
  },
  userAvatar: {
    width: '80px',
    height: '80px',
    borderRadius: '50%',
    backgroundColor: '#ef4444',
    color: '#fff',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '1.75rem',
    fontWeight: '700',
    flexShrink: 0,
  },
  userInfo: {
    flex: 1,
  },
  userName: {
    fontSize: '1.75rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.75rem',
  },
  userDetails: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  userDetail: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    color: '#64748b',
    fontSize: '0.95rem',
  },
  userDetailIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  userStatus: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-end',
    gap: '0.75rem',
  },
  badge: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#d1fae5',
    color: '#059669',
    padding: '0.5rem 1rem',
    borderRadius: '0.75rem',
    fontSize: '0.875rem',
    fontWeight: '600',
  },
  badgeIcon: {
    fontSize: '1rem',
  },
  joinDate: {
    color: '#64748b',
    fontSize: '0.875rem',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
    gap: '1.5rem',
    marginBottom: '2rem',
  },
  statCard: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: '1rem',
    border: '1px solid #e2e8f0',
    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    transition: 'all 0.3s',
  },
  statIconContainer: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '56px',
    height: '56px',
    backgroundColor: '#dbeafe',
    borderRadius: '0.75rem',
  },
  statIcon: {
    fontSize: '1.75rem',
    color: '#ef4444',
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
    fontWeight: '600',
    color: '#64748b',
    marginBottom: '0.25rem',
  },
  statSubtext: {
    fontSize: '0.75rem',
    color: '#94a3b8',
  },
  statTrend: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    backgroundColor: '#f0fdf4',
    color: '#16a34a',
    padding: '0.375rem 0.75rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '600',
  },
  statTrendIcon: {
    fontSize: '0.875rem',
  },
  statTrendText: {
    whiteSpace: 'nowrap',
  },
  mainContent: {
    display: 'grid',
    gridTemplateColumns: '1fr 400px',
    gap: '2rem',
    marginBottom: '2rem',
  },
  leftColumn: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  rightColumn: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  sectionCard: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: '1rem',
    border: '1px solid #e2e8f0',
    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
  },
  sectionHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '1.5rem',
    paddingBottom: '1rem',
    borderBottom: '2px solid #f1f5f9',
  },
  sectionTitle: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontSize: '1.25rem',
    fontWeight: '700',
    color: '#1e293b',
  },
  sectionIcon: {
    fontSize: '1.25rem',
    color: '#ef4444',
  },
  viewAllLink: {
    color: '#ef4444',
    textDecoration: 'none',
    fontSize: '0.875rem',
    fontWeight: '600',
  },
  actionsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, 1fr)',
    gap: '0.75rem',
  },
  actionCard: {
    backgroundColor: '#f8fafc',
    border: '1px solid #e2e8f0',
    padding: '1rem',
    borderRadius: '0.75rem',
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    cursor: 'pointer',
    textDecoration: 'none',
    color: 'inherit',
    transition: 'all 0.3s',
  },
  actionIcon: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '40px',
    height: '40px',
    backgroundColor: '#dbeafe',
    borderRadius: '0.5rem',
    fontSize: '1.25rem',
    color: '#ef4444',
    flexShrink: 0,
  },
  actionContent: {
    flex: 1,
  },
  actionTitle: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.125rem',
  },
  actionDescription: {
    fontSize: '0.75rem',
    color: '#64748b',
  },
  actionArrow: {
    fontSize: '1rem',
    color: '#94a3b8',
    flexShrink: 0,
  },
  detailedStats: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  detailedStatItem: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  detailedStatLabel: {
    fontSize: '0.875rem',
    color: '#64748b',
  },
  detailedStatValue: {
    fontSize: '1.125rem',
    fontWeight: '700',
    color: '#1e293b',
  },
  detailedStatProgress: {
    height: '6px',
    backgroundColor: '#f1f5f9',
    borderRadius: '3px',
    overflow: 'hidden',
  },
  detailedStatProgressBar: {
    height: '100%',
    backgroundColor: '#ef4444',
    borderRadius: '3px',
  },
  entreprisesList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  entrepriseItem: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: '0.75rem',
    borderRadius: '0.75rem',
    border: '1px solid #f1f5f9',
  },
  entrepriseInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  entrepriseLogo: {
    width: '40px',
    height: '40px',
    borderRadius: '0.5rem',
    objectFit: 'cover',
    border: '1px solid #e2e8f0',
  },
  entrepriseLogoPlaceholder: {
    width: '40px',
    height: '40px',
    borderRadius: '0.5rem',
    backgroundColor: '#dbeafe',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '1.25rem',
    color: '#3b82f6',
  },
  entrepriseName: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.125rem',
  },
  entrepriseStatus: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
  },
  statusBadge: {
    padding: '0.25rem 0.5rem',
    borderRadius: '0.375rem',
    fontSize: '0.75rem',
    fontWeight: '600',
  },
  entrepriseServices: {
    fontSize: '0.875rem',
    color: '#64748b',
    fontWeight: '500',
  },
  servicesList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  serviceItem: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    padding: '0.75rem',
    borderRadius: '0.75rem',
    border: '1px solid #f1f5f9',
  },
  serviceIcon: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '36px',
    height: '36px',
    backgroundColor: '#f0f9ff',
    borderRadius: '0.5rem',
    fontSize: '1rem',
    color: '#0369a1',
    flexShrink: 0,
  },
  serviceInfo: {
    flex: 1,
  },
  serviceName: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.125rem',
  },
  serviceDetails: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  servicePrice: {
    fontSize: '0.75rem',
    fontWeight: '600',
    color: '#059669',
  },
  service24h: {
    fontSize: '0.75rem',
    color: '#ef4444',
    fontWeight: '600',
  },
  emptyState: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '2rem',
    textAlign: 'center',
  },
  emptyStateIcon: {
    fontSize: '2.5rem',
    color: '#94a3b8',
    marginBottom: '1rem',
  },
  emptyStateText: {
    color: '#64748b',
    fontSize: '0.875rem',
    marginBottom: '1rem',
  },
  emptyStateButton: {
    backgroundColor: '#ef4444',
    color: '#fff',
    padding: '0.5rem 1rem',
    borderRadius: '0.5rem',
    textDecoration: 'none',
    fontSize: '0.875rem',
    fontWeight: '600',
  },
  notificationsList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  notificationItem: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '0.75rem',
    padding: '0.75rem',
    borderRadius: '0.75rem',
    border: '1px solid #f1f5f9',
    transition: 'all 0.2s',
  },
  notificationIcon: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '32px',
    height: '32px',
    backgroundColor: '#f0f9ff',
    borderRadius: '0.5rem',
    fontSize: '1rem',
    color: '#ef4444',
    flexShrink: 0,
  },
  notificationContent: {
    flex: 1,
  },
  notificationTitle: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.125rem',
  },
  notificationText: {
    fontSize: '0.75rem',
    color: '#64748b',
    marginBottom: '0.125rem',
  },
  notificationTime: {
    fontSize: '0.75rem',
    color: '#94a3b8',
  },
  tipsSection: {
    backgroundColor: '#fff',
    padding: '2rem',
    borderRadius: '1rem',
    border: '1px solid #e2e8f0',
    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
  },
  tipsHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    marginBottom: '1.5rem',
  },
  tipsIcon: {
    fontSize: '1.75rem',
    color: '#ef4444',
  },
  tipsTitle: {
    fontSize: '1.25rem',
    fontWeight: '700',
    color: '#1e293b',
  },
  tipsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '1.5rem',
  },
  tipCard: {
    backgroundColor: '#f8fafc',
    padding: '1.5rem',
    borderRadius: '0.75rem',
    border: '1px solid #e2e8f0',
  },
  tipIcon: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '48px',
    height: '48px',
    backgroundColor: '#dbeafe',
    borderRadius: '0.75rem',
    fontSize: '1.5rem',
    color: '#3b82f6',
    marginBottom: '1rem',
  },
  tipCardTitle: {
    fontSize: '1rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.5rem',
  },
  tipCardText: {
    fontSize: '0.875rem',
    color: '#64748b',
    lineHeight: '1.5',
  },

  // Responsive
  '@media (max-width: 1200px)': {
    mainContent: {
      gridTemplateColumns: '1fr',
    },
  },
  '@media (max-width: 768px)': {
    content: {
      padding: '0 1rem',
    },
    title: {
      fontSize: '1.75rem',
    },
    statsGrid: {
      gridTemplateColumns: 'repeat(2, 1fr)',
    },
    actionsGrid: {
      gridTemplateColumns: '1fr',
    },
    userCard: {
      flexDirection: 'column',
      textAlign: 'center',
      gap: '1rem',
    },
    userStatus: {
      alignItems: 'center',
    },
    tipsGrid: {
      gridTemplateColumns: '1fr',
    },
  },
  '@media (max-width: 480px)': {
    statsGrid: {
      gridTemplateColumns: '1fr',
    },
    headerMain: {
      flexDirection: 'column',
      alignItems: 'flex-start',
      gap: '1rem',
    },
    headerActions: {
      alignSelf: 'flex-end',
    },
  },
};