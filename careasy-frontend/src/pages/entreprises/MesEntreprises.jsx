// careasy-frontend/src/pages/entreprises/MesEntreprises.jsx
import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { entrepriseApi } from '../../api/entrepriseApi';

// Import des icônes React Icons
import {
  FiPlus,
  FiSearch,
  FiFilter,
  FiRefreshCw,
  FiEye,
  FiEdit,
  FiTrash2,
  FiCheckCircle,
  FiClock,
  FiXCircle,
  FiAlertCircle,
  FiBriefcase,
  FiUsers,
  FiTag,
  FiCalendar,
  FiMapPin,
  FiChevronRight,
  FiDownload,
  FiPrinter,
  FiGrid,
  FiStar
} from 'react-icons/fi';
import {
  MdBusiness,
  MdOutlineVerified,
  MdOutlinePending,
  MdOutlineWarning,
  MdOutlineDashboard,
  MdOutlineLibraryAdd,
  MdOutlineLocationOn,
  MdOutlinePerson,
  MdOutlineEmail,
  MdOutlinePhone,
  MdOutlineMoreVert,
  MdOutlineAttachMoney,
  MdOutlineDescription,
  MdOutlineWork
} from 'react-icons/md';

export default function MesEntreprises() {
  const [entreprises, setEntreprises] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState('all'); // all, pending, validated, rejected
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    fetchEntreprises();
  }, []);

  const fetchEntreprises = async () => {
    try {
      setLoading(true);
      setError('');
      const data = await entrepriseApi.getMesEntreprises();
      setEntreprises(data);
    } catch (err) {
      console.error('Erreur:', err);
      setError('Erreur lors du chargement des entreprises');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const handleRefresh = () => {
    setRefreshing(true);
    fetchEntreprises();
  };

  const handleDeleteEntreprise = async (id, name) => {
    if (window.confirm(`Êtes-vous sûr de vouloir supprimer "${name}" ?`)) {
      try {
        // Appel API pour supprimer l'entreprise
        // await entrepriseApi.deleteEntreprise(id);
        setEntreprises(entreprises.filter(e => e.id !== id));
      } catch (err) {
        alert('Erreur lors de la suppression');
      }
    }
  };

  const getStatusConfig = (status) => {
    const configs = {
      pending: {
        icon: <FiClock />,
        text: 'En attente',
        color: '#f59e0b',
        bgColor: '#fef3c7',
        iconColor: '#d97706'
      },
      validated: {
        icon: <FiCheckCircle />,
        text: 'Validée',
        color: '#10b981',
        bgColor: '#d1fae5',
        iconColor: '#059669'
      },
      rejected: {
        icon: <FiXCircle />,
        text: 'Rejetée',
        color: '#ef4444',
        bgColor: '#fee2e2',
        iconColor: '#dc2626'
      },
      draft: {
        icon: <MdOutlineWarning />,
        text: 'Brouillon',
        color: '#6b7280',
        bgColor: '#f3f4f6',
        iconColor: '#4b5563'
      }
    };
    return configs[status] || configs.pending;
  };

  const filteredEntreprises = entreprises.filter(e => {
    if (filter !== 'all' && e.status !== filter) return false;
    if (!searchTerm) return true;
    
    const searchLower = searchTerm.toLowerCase();
    return (
      e.name.toLowerCase().includes(searchLower) ||
      e.pdg_full_name.toLowerCase().includes(searchLower) ||
      e.ifu_number.toLowerCase().includes(searchLower) ||
      e.rccm_number.toLowerCase().includes(searchLower) ||
      e.siege.toLowerCase().includes(searchLower)
    );
  });

  const stats = {
    total: entreprises.length,
    pending: entreprises.filter(e => e.status === 'pending').length,
    validated: entreprises.filter(e => e.status === 'validated').length,
    rejected: entreprises.filter(e => e.status === 'rejected').length,
    hasServices: entreprises.filter(e => e.services_count > 0).length,
    totalServices: entreprises.reduce((acc, e) => acc + (e.services_count || 0), 0),
    active24h: entreprises.filter(e => e.has_24h_service).length,
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement de vos entreprises...</p>
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
                <MdBusiness style={styles.titleIcon} />
                Mes Entreprises
              </h1>
              <p style={styles.subtitle}>
                Gérez vos entreprises et suivez leur statut de validation
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
              <Link to="/entreprises/creer" style={styles.createButton}>
                <FiPlus style={styles.createButtonIcon} />
                Nouvelle entreprise
              </Link>
            </div>
          </div>
        </div>

        {/* Statistiques améliorées */}
        <div style={styles.statsGrid}>
          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <MdBusiness style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.total}</div>
              <div style={styles.statLabel}>Entreprises</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={{...styles.statIconContainer, backgroundColor: '#fef3c7'}}>
              <MdOutlinePending style={{...styles.statIcon, color: '#d97706'}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: '#d97706'}}>{stats.pending}</div>
              <div style={styles.statLabel}>En attente</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={{...styles.statIconContainer, backgroundColor: '#d1fae5'}}>
              <MdOutlineVerified style={{...styles.statIcon, color: '#059669'}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: '#059669'}}>{stats.validated}</div>
              <div style={styles.statLabel}>Validées</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={{...styles.statIconContainer, backgroundColor: '#fee2e2'}}>
              <MdOutlineWarning style={{...styles.statIcon, color: '#dc2626'}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: '#dc2626'}}>{stats.rejected}</div>
              <div style={styles.statLabel}>Rejetées</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIconContainer}>
              <FiClock style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.active24h}</div>
              <div style={styles.statLabel}>Disponible 24h</div>
            </div>
          </div>
        </div>

        {/* Barre de recherche et filtres */}
        <div style={styles.searchSection}>
          <div style={styles.searchContainer}>
            <FiSearch style={styles.searchIcon} />
            <input
              type="text"
              placeholder="Rechercher une entreprise, PDG, IFU..."
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
              <FiDownload style={styles.filterIcon} />
              Exporter
            </button>
            <button style={styles.filterButton}>
              <FiPrinter style={styles.filterIcon} />
              Imprimer
            </button>
          </div>
        </div>

        {/* Filtres de statut */}
        <div style={styles.filterContainer}>
          <button 
            onClick={() => setFilter('all')}
            style={{
              ...styles.filterButtonStatus,
              ...(filter === 'all' ? styles.filterButtonStatusActive : {})
            }}
          >
            <FiGrid style={styles.filterButtonIcon} />
            Toutes ({stats.total})
          </button>
          <button 
            onClick={() => setFilter('pending')}
            style={{
              ...styles.filterButtonStatus,
              ...(filter === 'pending' ? styles.filterButtonStatusActive : {})
            }}
          >
            <FiClock style={styles.filterButtonIcon} />
            En attente ({stats.pending})
          </button>
          <button 
            onClick={() => setFilter('validated')}
            style={{
              ...styles.filterButtonStatus,
              ...(filter === 'validated' ? styles.filterButtonStatusActive : {})
            }}
          >
            <FiCheckCircle style={styles.filterButtonIcon} />
            Validées ({stats.validated})
          </button>
          <button 
            onClick={() => setFilter('rejected')}
            style={{
              ...styles.filterButtonStatus,
              ...(filter === 'rejected' ? styles.filterButtonStatusActive : {})
            }}
          >
            <FiXCircle style={styles.filterButtonIcon} />
            Rejetées ({stats.rejected})
          </button>
        </div>

        {/* Message d'erreur */}
        {error && (
          <div style={styles.error}>
            <FiAlertCircle style={styles.errorIcon} />
            <div>
              <div style={styles.errorTitle}>Erreur de chargement</div>
              <div style={styles.errorText}>{error}</div>
            </div>
            <button onClick={fetchEntreprises} style={styles.errorRetryButton}>
              Réessayer
            </button>
          </div>
        )}

        {/* Liste des entreprises */}
        {filteredEntreprises.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIconContainer}>
              <MdBusiness style={styles.emptyIcon} />
            </div>
            <h3 style={styles.emptyTitle}>
              {entreprises.length === 0 
                ? "Aucune entreprise créée"
                : `Aucune entreprise "${filter}" trouvée`
              }
            </h3>
            <p style={styles.emptyText}>
              {entreprises.length === 0 
                ? "Commencez par créer votre première entreprise pour proposer vos services."
                : `Aucune entreprise ne correspond à vos critères de recherche.`
              }
            </p>
            {entreprises.length === 0 && (
              <Link to="/entreprises/creer" style={styles.emptyButton}>
                <FiPlus style={styles.emptyButtonIcon} />
                Créer ma première entreprise
              </Link>
            )}
          </div>
        ) : (
          <div style={styles.grid}>
            {filteredEntreprises.map((entreprise) => {
              const statusConfig = getStatusConfig(entreprise.status);
              
              return (
                <div key={entreprise.id} style={styles.card}>
                  {/* Header avec logo et actions */}
                  <div style={styles.cardHeader}>
                    <div style={styles.cardHeaderMain}>
                      {entreprise.logo ? (
                        <img 
                          src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                          alt={entreprise.name}
                          style={styles.logo}
                          onError={(e) => {
                            e.target.onerror = null;
                            e.target.style.display = 'none';
                            e.target.nextElementSibling.style.display = 'flex';
                          }}
                        />
                      ) : null}
                      <div style={styles.logoPlaceholder}>
                        <MdBusiness style={styles.logoIcon} />
                      </div>
                      <div style={styles.cardHeaderInfo}>
                        <h3 style={styles.cardTitle}>{entreprise.name}</h3>
                        <div style={{
                          ...styles.statusBadge,
                          backgroundColor: statusConfig.bgColor,
                          color: statusConfig.color
                        }}>
                          {statusConfig.icon}
                          <span>{statusConfig.text}</span>
                        </div>
                      </div>
                    </div>
                    <div style={styles.cardActions}>
                      <button style={styles.cardActionButton}>
                        <MdOutlineMoreVert />
                      </button>
                    </div>
                  </div>

                  {/* Infos entreprise */}
                  <div style={styles.cardBody}>
                    <div style={styles.infoGrid}>
                      <div style={styles.infoItem}>
                        <MdOutlinePerson style={styles.infoIcon} />
                        <div>
                          <div style={styles.infoLabel}>Dirigeant</div>
                          <div style={styles.infoValue}>{entreprise.pdg_full_name}</div>
                        </div>
                      </div>
                      
                      <div style={styles.infoItem}>
                        <FiBriefcase style={styles.infoIcon} />
                        <div>
                          <div style={styles.infoLabel}>IFU / RCCM</div>
                          <div style={styles.infoValue}>
                            {entreprise.ifu_number || 'Non renseigné'}
                          </div>
                        </div>
                      </div>
                      
                      <div style={styles.infoItem}>
                        <MdOutlineLocationOn style={styles.infoIcon} />
                        <div>
                          <div style={styles.infoLabel}>Siège</div>
                          <div style={styles.infoValue}>{entreprise.siege}</div>
                        </div>
                      </div>
                      
                      <div style={styles.infoItem}>
                        <MdOutlineWork style={styles.infoIcon} />
                        <div>
                          <div style={styles.infoLabel}>Services</div>
                          <div style={styles.infoValue}>
                            {entreprise.services_count || 0} service{entreprise.services_count !== 1 ? 's' : ''}
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Domaines */}
                    {entreprise.domaines && entreprise.domaines.length > 0 && (
                      <div style={styles.domainesSection}>
                        <div style={styles.domainesLabel}>
                          <FiTag style={styles.domainesIcon} />
                          Domaines d'expertise
                        </div>
                        <div style={styles.domainesList}>
                          {entreprise.domaines.slice(0, 4).map((domaine) => (
                            <span key={domaine.id} style={styles.domaineTag}>
                              {domaine.name}
                            </span>
                          ))}
                          {entreprise.domaines.length > 4 && (
                            <span style={styles.domaineTag}>
                              +{entreprise.domaines.length - 4}
                            </span>
                          )}
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Footer avec actions */}
                  <div style={styles.cardFooter}>
                    <div style={styles.cardFooterInfo}>
                      <div style={styles.dateInfo}>
                        <FiCalendar style={styles.dateIcon} />
                        <span style={styles.dateText}>
                          Créée le {new Date(entreprise.created_at).toLocaleDateString('fr-FR')}
                        </span>
                      </div>
                    </div>
                    <div style={styles.cardFooterActions}>
                      <Link 
                        to={`/entreprises/${entreprise.id}`}
                        style={styles.viewButton}
                      >
                        <FiEye style={styles.viewButtonIcon} />
                        Voir
                      </Link>
                      <Link 
                        to={`/entreprises/${entreprise.id}/edit`}
                        style={styles.editButton}
                      >
                        <FiEdit style={styles.editButtonIcon} />
                        Modifier
                      </Link>
                      <button 
                        onClick={() => handleDeleteEntreprise(entreprise.id, entreprise.name)}
                        style={styles.deleteButton}
                      >
                        <FiTrash2 style={styles.deleteButtonIcon} />
                        Supprimer
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
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
        
        .entreprise-card {
          animation: fadeIn 0.3s ease-out;
          transition: all 0.3s ease;
        }
        
        .entreprise-card:hover {
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
    marginBottom: '1.5rem',
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
  filterContainer: {
    display: 'flex',
    gap: '0.75rem',
    marginBottom: '2rem',
    flexWrap: 'wrap',
  },
  filterButtonStatus: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    color: '#475569',
    border: '2px solid #e2e8f0',
    padding: '0.75rem 1.25rem',
    borderRadius: '0.75rem',
    cursor: 'pointer',
    fontWeight: '600',
    fontSize: '0.875rem',
    transition: 'all 0.3s',
  },
  filterButtonStatusActive: {
    backgroundColor: '#ef4444',
    color: '#fff',
    borderColor: '#ef4444',
  },
  filterButtonIcon: {
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
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(380px, 1fr))',
    gap: '2rem',
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: '1rem',
    border: '1px solid #e2e8f0',
    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
    overflow: 'hidden',
    transition: 'all 0.3s',
  },
  cardHeader: {
    padding: '1.5rem',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    borderBottom: '2px solid #f1f5f9',
  },
  cardHeaderMain: {
    display: 'flex',
    gap: '1rem',
    alignItems: 'flex-start',
    flex: 1,
  },
  logo: {
    width: '64px',
    height: '64px',
    borderRadius: '0.75rem',
    objectFit: 'cover',
    border: '2px solid #e2e8f0',
    flexShrink: 0,
  },
  logoPlaceholder: {
    width: '64px',
    height: '64px',
    borderRadius: '0.75rem',
    backgroundColor: '#dbeafe',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    flexShrink: 0,
  },
  logoIcon: {
    fontSize: '2rem',
    color: '#ef4444',
  },
  cardHeaderInfo: {
    flex: 1,
  },
  cardTitle: {
    fontSize: '1.25rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.5rem',
  },
  statusBadge: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.375rem',
    padding: '0.375rem 0.75rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '600',
    whiteSpace: 'nowrap',
  },
  cardActions: {
    marginLeft: '0.5rem',
  },
  cardActionButton: {
    backgroundColor: 'transparent',
    border: 'none',
    color: '#94a3b8',
    cursor: 'pointer',
    padding: '0.25rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardBody: {
    padding: '1.5rem',
  },
  infoGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, 1fr)',
    gap: '1rem',
    marginBottom: '1.5rem',
  },
  infoItem: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '0.75rem',
  },
  infoIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
    marginTop: '0.125rem',
    flexShrink: 0,
  },
  infoLabel: {
    fontSize: '0.75rem',
    color: '#64748b',
    fontWeight: '500',
    marginBottom: '0.125rem',
  },
  infoValue: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e293b',
    lineHeight: '1.4',
  },
  domainesSection: {
    paddingTop: '1rem',
    borderTop: '1px solid #f1f5f9',
  },
  domainesLabel: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#475569',
    marginBottom: '0.75rem',
  },
  domainesIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  domainesList: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '0.5rem',
  },
  domaineTag: {
    backgroundColor: '#f0f9ff',
    color: '#943535ff',
    padding: '0.375rem 0.75rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '600',
    whiteSpace: 'nowrap',
  },
  cardFooter: {
    padding: '1rem 1.5rem',
    backgroundColor: '#f8fafc',
    borderTop: '1px solid #f1f5f9',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  cardFooterInfo: {
    flex: 1,
  },
  dateInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  dateIcon: {
    fontSize: '0.875rem',
    color: '#94a3b8',
  },
  dateText: {
    fontSize: '0.75rem',
    color: '#64748b',
    fontWeight: '500',
  },
  cardFooterActions: {
    display: 'flex',
    gap: '0.5rem',
  },
  viewButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    backgroundColor: '#08ad53ff',
    color: '#fff',
    textDecoration: 'none',
    padding: '0.5rem 1rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '500',
    whiteSpace: 'nowrap',
  },
  viewButtonIcon: {
    fontSize: '0.75rem',
  },
  editButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    backgroundColor: '#f1f5f9',
    color: '#475569',
    border: '1px solid #e2e8f0',
    textDecoration: 'none',
    padding: '0.5rem 1rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '500',
    whiteSpace: 'nowrap',
  },
  editButtonIcon: {
    fontSize: '0.75rem',
  },
  deleteButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    backgroundColor: '#fee2e2',
    color: '#dc2626',
    border: '1px solid #fecaca',
    padding: '0.5rem 1rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '500',
    cursor: 'pointer',
    whiteSpace: 'nowrap',
  },
  deleteButtonIcon: {
    fontSize: '0.75rem',
  },

  // Styles responsives
  '@media (max-width: 1200px)': {
    grid: {
      gridTemplateColumns: 'repeat(auto-fill, minmax(350px, 1fr))',
    },
  },
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
    grid: {
      gridTemplateColumns: '1fr',
    },
    infoGrid: {
      gridTemplateColumns: '1fr',
    },
    cardFooter: {
      flexDirection: 'column',
      alignItems: 'stretch',
    },
    cardFooterActions: {
      width: '100%',
    },
    viewButton: {
      flex: 1,
      justifyContent: 'center',
    },
    editButton: {
      flex: 1,
      justifyContent: 'center',
    },
    deleteButton: {
      flex: 1,
      justifyContent: 'center',
    },
  },
  '@media (max-width: 480px)': {
    statsGrid: {
      gridTemplateColumns: '1fr',
    },
    headerMain: {
      flexDirection: 'column',
    },
    headerActions: {
      width: '100%',
      justifyContent: 'flex-end',
    },
  },
};