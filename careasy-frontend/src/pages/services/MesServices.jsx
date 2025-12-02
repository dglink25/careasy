// careasy-frontend/src/pages/services/MesServices.jsx
import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { serviceApi } from '../../api/serviceApi';
import theme from '../../config/theme';

// Import des icônes React Icons
import {
  FiPlus,
  FiSearch,
  FiTool,
  FiBriefcase,
  FiTag,
  FiDollarSign,
  FiClock,
  FiEye,
  FiChevronRight,
  FiAlertCircle,
  FiRefreshCw,
  FiGrid,
  FiMapPin,
  FiUser,
  FiEdit,
  FiTrash2,
  FiFilter,
  FiDownload,
  FiPrinter
} from 'react-icons/fi';
import {
  MdBusiness,
  MdOutlineWork,
  MdOutlineDescription,
  MdOutlineLocationOn,
  MdOutlineAccessTime,
  MdOutlineStar,
  MdOutlineVerified,
  MdOutlineLibraryAdd,
  MdOutlineDashboard,
  MdOutlineInventory,
  MdOutlineAttachMoney,
  MdOutlineSchedule,
  MdOutlineVisibility,
  MdOutlineMoreVert
} from 'react-icons/md';

export default function MesServices() {
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    fetchServices();
  }, []);

  const fetchServices = async () => {
    try {
      setLoading(true);
      const data = await serviceApi.getMesServices();
      setServices(data);
      setError('');
    } catch (err) {
      setError('Erreur lors du chargement des services');
      console.error(err);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const handleRefresh = () => {
    setRefreshing(true);
    fetchServices();
  };

  // Filtrer services par recherche
  const filteredServices = services.filter(service =>
    service.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    service.entreprise?.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    service.domaine?.name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // Grouper par entreprise
  const servicesByEntreprise = filteredServices.reduce((acc, service) => {
    const entrepriseId = service.entreprise?.id || 'unknown';
    if (!acc[entrepriseId]) {
      acc[entrepriseId] = {
        entreprise: service.entreprise,
        services: []
      };
    }
    acc[entrepriseId].services.push(service);
    return acc;
  }, {});

  // Statistiques
  const stats = {
    totalServices: services.length,
    entreprises: Object.keys(servicesByEntreprise).length,
    domaines: new Set(services.map(s => s.domaine?.id)).size,
    withPrice: services.filter(s => s.price).length,
    active24h: services.filter(s => s.is_open_24h).length,
    averagePrice: services.filter(s => s.price).reduce((acc, s) => acc + s.price, 0) / services.filter(s => s.price).length || 0
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement de vos services...</p>
          <p style={styles.loadingSubtext}>Veuillez patienter</p>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header avec actions */}
        <div style={styles.header}>
          <div style={styles.headerMain}>
            <div>
              <h1 style={styles.title}>
                <MdOutlineInventory style={styles.titleIcon} />
                Mes Services
              </h1>
              <p style={styles.subtitle}>
                Gérez tous les services proposés par vos entreprises
              </p>
            </div>
            <div style={styles.headerActions}>
              <button 
                onClick={handleRefresh}
                style={styles.headerActionButton}
                disabled={refreshing}
              >
                <FiRefreshCw style={refreshing ? styles.refreshingIcon : styles.headerActionIcon} />
                {refreshing ? 'Rafraîchissement...' : 'Rafraîchir'}
              </button>
              <Link to="/services/creer" style={styles.createButton}>
                <FiPlus style={styles.createButtonIcon} />
                Créer un service
              </Link>
            </div>
          </div>
        </div>

        {/* Statistiques améliorées */}
        <div style={styles.statsGrid}>
          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <MdOutlineInventory style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.totalServices}</div>
              <div style={styles.statLabel}>Services totaux</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <MdBusiness style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.entreprises}</div>
              <div style={styles.statLabel}>Entreprises</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <FiTag style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.domaines}</div>
              <div style={styles.statLabel}>Domaines</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <FiDollarSign style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.withPrice}</div>
              <div style={styles.statLabel}>Avec tarif</div>
            </div>
          </div>

          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <FiClock style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.active24h}</div>
              <div style={styles.statLabel}>Disponible 24h/24</div>
            </div>
          </div>

          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <MdOutlineAttachMoney style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>
                {stats.averagePrice > 0 ? Math.round(stats.averagePrice).toLocaleString() : '0'}
              </div>
              <div style={styles.statLabel}>Moyenne prix (FCFA)</div>
            </div>
          </div>
        </div>

        {/* Barre de recherche et filtres */}
        <div style={styles.searchSection}>
          <div style={styles.searchContainer}>
            <FiSearch style={styles.searchIcon} />
            <input
              type="text"
              placeholder="Rechercher un service, entreprise ou domaine..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              style={styles.searchInput}
            />
            {searchTerm && (
              <button 
                onClick={() => setSearchTerm('')}
                style={styles.clearSearchButton}
              >
                ✕
              </button>
            )}
          </div>
          <div style={styles.filterActions}>
            <button style={styles.filterButton}>
              <FiFilter style={styles.filterIcon} />
              Filtrer
            </button>
            <button style={styles.filterButton}>
              <FiDownload style={styles.filterIcon} />
              Exporter
            </button>
            <button style={styles.filterButton}>
              <FiPrinter style={styles.filterIcon} />
              Imprimer
            </button>
          </div>
        </div>

        {/* Message d'erreur */}
        {error && (
          <div style={styles.error}>
            <FiAlertCircle style={styles.errorIcon} />
            <div>
              <div style={styles.errorTitle}>Erreur de chargement</div>
              <div style={styles.errorText}>{error}</div>
            </div>
            <button onClick={fetchServices} style={styles.errorRetryButton}>
              Réessayer
            </button>
          </div>
        )}

        {/* Liste des services */}
        {filteredServices.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIconContainer}>
              <MdOutlineWork style={styles.emptyIcon} />
            </div>
            <h3 style={styles.emptyTitle}>
              {services.length === 0 
                ? "Aucun service créé"
                : "Aucun résultat trouvé"
              }
            </h3>
            <p style={styles.emptyText}>
              {services.length === 0 
                ? "Commencez par créer votre premier service pour vos entreprises validées."
                : `Aucun service ne correspond à "${searchTerm}"`
              }
            </p>
            {services.length === 0 && (
              <Link to="/services/creer" style={styles.emptyButton}>
                <FiPlus style={styles.emptyButtonIcon} />
                Créer mon premier service
              </Link>
            )}
          </div>
        ) : (
          <div style={styles.servicesContainer}>
            {Object.entries(servicesByEntreprise).map(([entrepriseId, data]) => (
              <div key={entrepriseId} style={styles.entrepriseSection}>
                {/* Header Entreprise */}
                <div style={styles.entrepriseHeader}>
                  <div style={styles.entrepriseInfo}>
                    {data.entreprise?.logo ? (
                      <img 
                        src={`${import.meta.env.VITE_API_URL}/storage/${data.entreprise.logo}`}
                        alt={data.entreprise.name}
                        style={styles.entrepriseLogo}
                        onError={(e) => {
                          e.target.onerror = null;
                          e.target.style.display = 'none';
                          e.target.nextElementSibling.style.display = 'flex';
                        }}
                      />
                    ) : (
                      <div style={styles.entrepriseLogoPlaceholder}>
                        <MdBusiness style={styles.entrepriseLogoIcon} />
                      </div>
                    )}
                    <div style={styles.entrepriseDetails}>
                      <h2 style={styles.entrepriseName}>
                        {data.entreprise?.name || 'Entreprise'}
                      </h2>
                      <div style={styles.entrepriseMeta}>
                        {data.entreprise?.siege && (
                          <div style={styles.entrepriseMetaItem}>
                            <MdOutlineLocationOn style={styles.entrepriseMetaIcon} />
                            <span>{data.entreprise.siege}</span>
                          </div>
                        )}
                        <div style={styles.entrepriseMetaItem}>
                          <MdOutlineVerified style={styles.entrepriseMetaIcon} />
                          <span>{data.services.length} service{data.services.length > 1 ? 's' : ''}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <Link 
                    to={`/entreprises/${entrepriseId}`}
                    style={styles.viewEntrepriseLink}
                  >
                    <span>Voir l'entreprise</span>
                    <FiChevronRight style={styles.viewEntrepriseIcon} />
                  </Link>
                </div>

                {/* Grid des services */}
                <div style={styles.servicesGrid}>
                  {data.services.map((service) => (
                    <div 
                      key={service.id} 
                      style={styles.serviceCard}
                      className="service-card"
                    >
                      {/* Images du service */}
                      {service.medias && service.medias.length > 0 ? (
                        <div style={styles.serviceImageContainer}>
                          <img 
                            src={`${import.meta.env.VITE_API_URL}/storage/${service.medias[0]}`}
                            alt={service.name}
                            style={styles.serviceImage}
                            onError={(e) => {
                              e.target.onerror = null;
                              e.target.style.display = 'none';
                              e.target.nextElementSibling.style.display = 'flex';
                            }}
                          />
                          <div style={styles.serviceImagePlaceholder}>
                            <MdOutlineWork style={styles.serviceImageIcon} />
                          </div>
                          {service.medias.length > 1 && (
                            <div style={styles.imageBadge}>
                              +{service.medias.length - 1}
                            </div>
                          )}
                        </div>
                      ) : (
                        <div style={styles.serviceImagePlaceholder}>
                          <MdOutlineWork style={styles.serviceImageIcon} />
                        </div>
                      )}

                      {/* Infos service */}
                      <div style={styles.serviceBody}>
                        <div style={styles.serviceHeader}>
                          <h3 style={styles.serviceName}>{service.name}</h3>
                          <div style={styles.serviceActionsMenu}>
                            <button style={styles.serviceActionButton}>
                              <MdOutlineMoreVert />
                            </button>
                          </div>
                        </div>
                        
                        {service.domaine && (
                          <div style={styles.domaineTag}>
                            <FiTag style={styles.domaineTagIcon} />
                            <span>{service.domaine.name}</span>
                          </div>
                        )}

                        {service.descriptions && (
                          <p style={styles.serviceDescription}>
                            {service.descriptions.length > 120
                              ? service.descriptions.substring(0, 120) + '...'
                              : service.descriptions
                            }
                          </p>
                        )}

                        {/* Prix et horaires */}
                        <div style={styles.serviceDetails}>
                          <div style={styles.servicePrice}>
                            <FiDollarSign style={styles.servicePriceIcon} />
                            {service.price 
                              ? `${service.price.toLocaleString('fr-FR')} FCFA`
                              : 'Prix sur demande'
                            }
                          </div>
                          
                          {service.is_open_24h ? (
                            <div style={styles.serviceHours}>
                              <FiClock style={styles.serviceHoursIcon} />
                              Disponible 24h/24
                            </div>
                          ) : service.start_time && service.end_time ? (
                            <div style={styles.serviceHours}>
                              <MdOutlineAccessTime style={styles.serviceHoursIcon} />
                              {service.start_time} - {service.end_time}
                            </div>
                          ) : null}
                        </div>
                      </div>

                      {/* Footer */}
                      <div style={styles.serviceFooter}>
                        <div style={styles.serviceFooterActions}>
                          <Link 
                            to={`/services/${service.id}`}
                            style={styles.viewButton}
                          >
                            <FiEye style={styles.viewButtonIcon} />
                            Voir détails
                          </Link>
                          <button style={styles.editButton}>
                            <FiEdit style={styles.editButtonIcon} />
                            Modifier
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* CSS pour animations */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
        }
        
        .service-card {
          animation: fadeIn 0.3s ease-out;
          transition: all 0.3s ease;
        }
        
        .service-card:hover {
          transform: translateY(-8px);
          box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .refreshing {
          animation: spin 1s linear infinite;
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
    border: `4px solid #dbeafe`,
    borderTop: `4px solid #3b82f6`,
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
    alignItems: 'flex-start',
    marginBottom: '1.5rem',
    flexWrap: 'wrap',
    gap: '1rem',
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
    gap: '0.75rem',
    alignItems: 'center',
  },
  headerActionButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    padding: '0.625rem 1rem',
    borderRadius: '0.75rem',
    fontSize: '0.875rem',
    color: '#475569',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  headerActionIcon: {
    fontSize: '1rem',
  },
  refreshingIcon: {
    fontSize: '1rem',
    animation: 'spin 1s linear infinite',
  },
  createButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#ef4444',
    color: '#fff',
    padding: '0.875rem 1.75rem',
    borderRadius: '0.75rem',
    textDecoration: 'none',
    fontWeight: '600',
    boxShadow: '0 4px 6px -1px rgba(59, 130, 246, 0.2)',
    transition: 'all 0.2s',
  },
  createButtonIcon: {
    fontSize: '1.125rem',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
    gap: '1.25rem',
    marginBottom: '2rem',
  },
  statCard: {
    backgroundColor: '#fff',
    padding: '1.25rem',
    borderRadius: '1rem',
    border: '1px solid #e2e8f0',
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    transition: 'all 0.3s',
  },
  statIconContainer: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '48px',
    height: '48px',
    backgroundColor: '#dbeafe',
    borderRadius: '0.75rem',
  },
  statIcon: {
    fontSize: '1.5rem',
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
    color: '#64748b',
    fontWeight: '500',
  },
  searchSection: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '2rem',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  searchContainer: {
    position: 'relative',
    flex: 1,
    minWidth: '300px',
  },
  searchIcon: {
    position: 'absolute',
    left: '1rem',
    top: '50%',
    transform: 'translateY(-50%)',
    fontSize: '1.25rem',
    color: '#94a3b8',
  },
  searchInput: {
    width: '100%',
    padding: '0.875rem 1rem 0.875rem 3rem',
    border: '2px solid #e2e8f0',
    borderRadius: '0.75rem',
    fontSize: '0.875rem',
    outline: 'none',
    transition: 'all 0.3s',
    backgroundColor: '#fff',
  },
  clearSearchButton: {
    position: 'absolute',
    right: '1rem',
    top: '50%',
    transform: 'translateY(-50%)',
    backgroundColor: 'transparent',
    border: 'none',
    color: '#94a3b8',
    fontSize: '1.25rem',
    cursor: 'pointer',
    padding: '0',
  },
  filterActions: {
    display: 'flex',
    gap: '0.5rem',
  },
  filterButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    padding: '0.625rem 1rem',
    borderRadius: '0.75rem',
    fontSize: '0.875rem',
    color: '#475569',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  filterIcon: {
    fontSize: '1rem',
  },
  error: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    backgroundColor: '#fef2f2',
    border: '1px solid #fecaca',
    padding: '1rem',
    borderRadius: '0.75rem',
    marginBottom: '2rem',
  },
  errorIcon: {
    fontSize: '1.5rem',
    color: '#ef4444',
    flexShrink: 0,
  },
  errorTitle: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#dc2626',
    marginBottom: '0.25rem',
  },
  errorText: {
    fontSize: '0.875rem',
    color: '#991b1b',
  },
  errorRetryButton: {
    marginLeft: 'auto',
    backgroundColor: '#dc2626',
    color: '#fff',
    border: 'none',
    padding: '0.5rem 1rem',
    borderRadius: '0.5rem',
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
  },
  emptyState: {
    backgroundColor: '#fff',
    padding: '4rem 2rem',
    borderRadius: '1.5rem',
    textAlign: 'center',
    border: '2px dashed #e2e8f0',
  },
  emptyIconContainer: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '80px',
    height: '80px',
    backgroundColor: '#dbeafe',
    borderRadius: '50%',
    marginBottom: '1.5rem',
  },
  emptyIcon: {
    fontSize: '2.5rem',
    color: '#ef4444',
  },
  emptyTitle: {
    fontSize: '1.5rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.75rem',
  },
  emptyText: {
    color: '#64748b',
    fontSize: '1rem',
    marginBottom: '2rem',
    maxWidth: '500px',
    marginLeft: 'auto',
    marginRight: 'auto',
  },
  emptyButton: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#ef4444',
    color: '#fff',
    padding: '0.875rem 2rem',
    borderRadius: '0.75rem',
    textDecoration: 'none',
    fontWeight: '600',
    boxShadow: '0 4px 6px -1px rgba(59, 130, 246, 0.2)',
  },
  emptyButtonIcon: {
    fontSize: '1.125rem',
  },
  servicesContainer: {
    display: 'flex',
    flexDirection: 'column',
    gap: '2.5rem',
  },
  entrepriseSection: {
    backgroundColor: '#fff',
    padding: '2rem',
    borderRadius: '1.5rem',
    border: '1px solid #e2e8f0',
    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
  },
  entrepriseHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '2rem',
    paddingBottom: '1.5rem',
    borderBottom: '2px solid #f1f5f9',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  entrepriseInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
  },
  entrepriseLogo: {
    width: '64px',
    height: '64px',
    borderRadius: '0.75rem',
    objectFit: 'cover',
    border: '2px solid #e2e8f0',
  },
  entrepriseLogoPlaceholder: {
    width: '64px',
    height: '64px',
    borderRadius: '0.75rem',
    backgroundColor: '#dbeafe',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  entrepriseLogoIcon: {
    fontSize: '2rem',
    color: '#ef4444',
  },
  entrepriseDetails: {
    flex: 1,
  },
  entrepriseName: {
    fontSize: '1.5rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.5rem',
  },
  entrepriseMeta: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  entrepriseMetaItem: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    fontSize: '0.875rem',
    color: '#64748b',
  },
  entrepriseMetaIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  viewEntrepriseLink: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    color: '#ef4444',
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '0.875rem',
  },
  viewEntrepriseIcon: {
    fontSize: '1rem',
  },
  servicesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
    gap: '1.5rem',
  },
  serviceCard: {
    backgroundColor: '#fff',
    borderRadius: '1rem',
    overflow: 'hidden',
    border: '1px solid #e2e8f0',
    display: 'flex',
    flexDirection: 'column',
    transition: 'all 0.3s',
  },
  serviceImageContainer: {
    position: 'relative',
    height: '180px',
    overflow: 'hidden',
    backgroundColor: '#f1f5f9',
  },
  serviceImage: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  serviceImagePlaceholder: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    display: 'none',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#f1f5f9',
  },
  serviceImageIcon: {
    fontSize: '3rem',
    color: '#94a3b8',
  },
  imageBadge: {
    position: 'absolute',
    bottom: '0.75rem',
    right: '0.75rem',
    backgroundColor: 'rgba(0, 0, 0, 0.75)',
    color: '#fff',
    padding: '0.25rem 0.5rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '600',
  },
  serviceBody: {
    padding: '1.5rem',
    flex: 1,
  },
  serviceHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: '0.75rem',
  },
  serviceName: {
    fontSize: '1.125rem',
    fontWeight: '700',
    color: '#1e293b',
    margin: 0,
    flex: 1,
  },
  serviceActionsMenu: {
    marginLeft: '0.5rem',
  },
  serviceActionButton: {
    backgroundColor: 'transparent',
    border: 'none',
    color: '#94a3b8',
    cursor: 'pointer',
    padding: '0.25rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  domaineTag: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.375rem',
    backgroundColor: '#f0f9ff',
    color: '#0369a1',
    padding: '0.375rem 0.75rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '600',
    marginBottom: '1rem',
  },
  domaineTagIcon: {
    fontSize: '0.875rem',
  },
  serviceDescription: {
    color: '#64748b',
    fontSize: '0.875rem',
    lineHeight: '1.5',
    marginBottom: '1rem',
  },
  serviceDetails: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
    marginTop: 'auto',
  },
  servicePrice: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    color: '#059669',
    fontWeight: '700',
    fontSize: '1rem',
  },
  servicePriceIcon: {
    fontSize: '0.875rem',
  },
  serviceHours: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    color: '#6366f1',
    fontSize: '0.875rem',
    fontWeight: '600',
  },
  serviceHoursIcon: {
    fontSize: '0.875rem',
  },
  serviceFooter: {
    padding: '1rem 1.5rem',
    borderTop: '1px solid #f1f5f9',
  },
  serviceFooterActions: {
    display: 'flex',
    gap: '0.75rem',
  },
  viewButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    backgroundColor: '#ef4444',
    color: '#fff',
    textDecoration: 'none',
    padding: '0.5rem 1rem',
    borderRadius: '0.5rem',
    fontSize: '0.875rem',
    fontWeight: '500',
    flex: 1,
    justifyContent: 'center',
  },
  viewButtonIcon: {
    fontSize: '0.875rem',
  },
  editButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    backgroundColor: '#f1f5f9',
    color: '#475569',
    border: '1px solid #e2e8f0',
    padding: '0.5rem 1rem',
    borderRadius: '0.5rem',
    fontSize: '0.875',
    fontWeight: '500',
    cursor: 'pointer',
    flex: 1,
    justifyContent: 'center',
  },
  editButtonIcon: {
    fontSize: '0.875rem',
  },

  // Styles responsives
  '@media (max-width: 1024px)': {
    statsGrid: {
      gridTemplateColumns: 'repeat(3, 1fr)',
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
    servicesGrid: {
      gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
    },
    entrepriseHeader: {
      flexDirection: 'column',
      alignItems: 'flex-start',
    },
    viewEntrepriseLink: {
      alignSelf: 'flex-start',
    },
  },
  '@media (max-width: 480px)': {
    statsGrid: {
      gridTemplateColumns: '1fr',
    },
    servicesGrid: {
      gridTemplateColumns: '1fr',
    },
    serviceFooterActions: {
      flexDirection: 'column',
    },
  },
};