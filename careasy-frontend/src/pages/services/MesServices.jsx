// careasy-frontend/src/pages/services/MesServices.jsx
import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { serviceApi } from '../../api/serviceApi';
import theme from '../../config/theme';

export default function MesServices() {
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');

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
    }
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

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement de vos services...</p>
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
            <h1 style={styles.title}>Mes Services</h1>
            <p style={styles.subtitle}>
              Gérez tous les services proposés par vos entreprises
            </p>
          </div>
          <Link to="/services/creer" style={styles.createButton}>
            ➕ Créer un service
          </Link>
        </div>

        {/* Statistiques */}
        <div style={styles.statsGrid}>
          <div style={styles.statCard}>
            <div style={styles.statIcon}>🛠️</div>
            <div>
              <div style={styles.statNumber}>{services.length}</div>
              <div style={styles.statLabel}>Services totaux</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIcon}>🏢</div>
            <div>
              <div style={styles.statNumber}>{Object.keys(servicesByEntreprise).length}</div>
              <div style={styles.statLabel}>Entreprises</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIcon}>🏷️</div>
            <div>
              <div style={styles.statNumber}>
                {new Set(services.map(s => s.domaine?.id)).size}
              </div>
              <div style={styles.statLabel}>Domaines</div>
            </div>
          </div>
          
          <div style={styles.statCard}>
            <div style={styles.statIcon}>💰</div>
            <div>
              <div style={styles.statNumber}>
                {services.filter(s => s.price).length}
              </div>
              <div style={styles.statLabel}>Avec tarif</div>
            </div>
          </div>
        </div>

        {/* Barre de recherche */}
        <div style={styles.searchContainer}>
          <input
            type="text"
            placeholder="🔍 Rechercher un service, entreprise ou domaine..."
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

        {/* Liste des services */}
        {filteredServices.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIcon}>🛠️</div>
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
                ➕ Créer mon premier service
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
                      />
                    ) : (
                      <div style={styles.entrepriseLogoPlaceholder}>🏢</div>
                    )}
                    <div>
                      <h2 style={styles.entrepriseName}>
                        {data.entreprise?.name || 'Entreprise'}
                      </h2>
                      <p style={styles.entrepriseCount}>
                        {data.services.length} service{data.services.length > 1 ? 's' : ''}
                      </p>
                    </div>
                  </div>
                  <Link 
                    to={`/entreprises/${entrepriseId}`}
                    style={styles.viewEntrepriseLink}
                  >
                    Voir l'entreprise →
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
                          />
                          {service.medias.length > 1 && (
                            <div style={styles.imageBadge}>
                              +{service.medias.length - 1} photo{service.medias.length > 2 ? 's' : ''}
                            </div>
                          )}
                        </div>
                      ) : (
                        <div style={styles.serviceImagePlaceholder}>
                          <div style={styles.placeholderIcon}>🛠️</div>
                        </div>
                      )}

                      {/* Infos service */}
                      <div style={styles.serviceBody}>
                        <h3 style={styles.serviceName}>{service.name}</h3>
                        
                        {service.domaine && (
                          <div style={styles.domaineTag}>
                            🏷️ {service.domaine.name}
                          </div>
                        )}

                        {service.descriptions && (
                          <p style={styles.serviceDescription}>
                            {service.descriptions.length > 100
                              ? service.descriptions.substring(0, 100) + '...'
                              : service.descriptions
                            }
                          </p>
                        )}

                        {/* Prix et horaires */}
                        <div style={styles.serviceDetails}>
                          <div style={styles.servicePrice}>
                            {service.price 
                              ? `💰 ${service.price.toLocaleString()} FCFA`
                              : '💬 Prix sur demande'
                            }
                          </div>
                          
                          {service.is_open_24h ? (
                            <div style={styles.serviceHours}>🕐 24h/24</div>
                          ) : service.start_time && service.end_time ? (
                            <div style={styles.serviceHours}>
                              🕐 {service.start_time} - {service.end_time}
                            </div>
                          ) : null}
                        </div>
                      </div>

                      {/* Footer */}
                      <div style={styles.serviceFooter}>
                        <Link 
                          to={`/services/${service.id}`}
                          style={styles.viewButton}
                        >
                          Voir détails →
                        </Link>
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
        .service-card {
          transition: all 0.3s ease;
        }
        .service-card:hover {
          transform: translateY(-8px);
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
  searchContainer: {
    marginBottom: '2rem',
  },
  searchInput: {
    width: '100%',
    padding: '1rem 1.5rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.lg,
    fontSize: '1rem',
    outline: 'none',
    transition: 'all 0.3s',
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
  servicesContainer: {
    display: 'flex',
    flexDirection: 'column',
    gap: '3rem',
  },
  entrepriseSection: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.sm,
  },
  entrepriseHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '2rem',
    paddingBottom: '1.5rem',
    borderBottom: `2px solid ${theme.colors.primaryLight}`,
    flexWrap: 'wrap',
    gap: '1rem',
  },
  entrepriseInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
  },
  entrepriseLogo: {
    width: '60px',
    height: '60px',
    borderRadius: theme.borderRadius.md,
    objectFit: 'cover',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  entrepriseLogoPlaceholder: {
    width: '60px',
    height: '60px',
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '2rem',
  },
  entrepriseName: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.25rem',
  },
  entrepriseCount: {
    color: theme.colors.text.secondary,
    fontSize: '0.95rem',
  },
  viewEntrepriseLink: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
  },
  servicesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
    gap: '1.5rem',
  },
  serviceCard: {
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.lg,
    overflow: 'hidden',
    border: `2px solid ${theme.colors.primaryLight}`,
    display: 'flex',
    flexDirection: 'column',
  },
  serviceImageContainer: {
    position: 'relative',
    height: '200px',
    overflow: 'hidden',
  },
  serviceImage: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  imageBadge: {
    position: 'absolute',
    bottom: '10px',
    right: '10px',
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    color: '#fff',
    padding: '0.375rem 0.75rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.8rem',
    fontWeight: '600',
  },
  serviceImagePlaceholder: {
    height: '200px',
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderIcon: {
    fontSize: '4rem',
  },
  serviceBody: {
    padding: '1.25rem',
    flex: 1,
  },
  serviceName: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.75rem',
  },
  domaineTag: {
    display: 'inline-block',
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.primary,
    padding: '0.375rem 0.75rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.8rem',
    fontWeight: '600',
    marginBottom: '0.75rem',
  },
  serviceDescription: {
    color: theme.colors.text.secondary,
    fontSize: '0.95rem',
    lineHeight: '1.6',
    marginBottom: '1rem',
  },
  serviceDetails: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  servicePrice: {
    color: theme.colors.primary,
    fontWeight: '700',
    fontSize: '1.125rem',
  },
  serviceHours: {
    color: theme.colors.text.secondary,
    fontSize: '0.9rem',
    fontWeight: '600',
  },
  serviceFooter: {
    padding: '1rem 1.25rem',
    borderTop: `1px solid ${theme.colors.primaryLight}`,
  },
  viewButton: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '0.95rem',
  },
};