// careasy-frontend/src/pages/public/PublicServices.jsx
import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { publicApi } from '../../api/publicApi';
import theme from '../../config/theme';

export default function PublicServices() {
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
      const data = await publicApi.getServices();
      setServices(data);
      setError('');
    } catch (err) {
      setError('Erreur lors du chargement des services');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  // Filtrage
  const filteredServices = services.filter(s =>
    s.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    s.entreprise?.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    s.domaine?.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    s.descriptions?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement des services...</p>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Hero */}
        <div style={styles.hero}>
          <h1 style={styles.heroTitle}>🛠️ Tous nos services</h1>
          <p style={styles.heroSubtitle}>
            Découvrez {services.length} services automobiles certifiés
          </p>
        </div>

        {/* Recherche */}
        <div style={styles.searchSection}>
          <input
            type="text"
            placeholder="🔍 Rechercher un service..."
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

        {/* Résultats */}
        {filteredServices.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIcon}>🔍</div>
            <h3 style={styles.emptyTitle}>Aucun service trouvé</h3>
            <p style={styles.emptyText}>
              {searchTerm
                ? `Aucun résultat pour "${searchTerm}"`
                : "Aucun service disponible"
              }
            </p>
          </div>
        ) : (
          <>
            <div style={styles.resultsHeader}>
              <h2 style={styles.resultsTitle}>
                {filteredServices.length} service{filteredServices.length > 1 ? 's' : ''} disponible{filteredServices.length > 1 ? 's' : ''}
              </h2>
            </div>

            <div style={styles.grid}>
              {filteredServices.map((service) => (
                <div 
                  key={service.id}
                  style={styles.card}
                  className="service-card"
                >
                  {/* Image */}
                  {service.medias && service.medias.length > 0 ? (
                    <div style={styles.cardImage}>
                      <img 
                        src={`${import.meta.env.VITE_API_URL}/storage/${service.medias[0]}`}
                        alt={service.name}
                        style={styles.image}
                      />
                      {service.medias.length > 1 && (
                        <div style={styles.imageBadge}>
                          +{service.medias.length - 1} photo{service.medias.length > 2 ? 's' : ''}
                        </div>
                      )}
                    </div>
                  ) : (
                    <div style={styles.imagePlaceholder}>
                      <div style={styles.placeholderIcon}>🛠️</div>
                    </div>
                  )}

                  {/* Contenu */}
                  <div style={styles.cardBody}>
                    <h3 style={styles.cardTitle}>{service.name}</h3>
                    
                    {service.domaine && (
                      <div style={styles.domaineTag}>
                        🏷️ {service.domaine.name}
                      </div>
                    )}

                    {service.descriptions && (
                      <p style={styles.description}>
                        {service.descriptions.substring(0, 120)}
                        {service.descriptions.length > 120 ? '...' : ''}
                      </p>
                    )}

                    <div style={styles.details}>
                      <div style={styles.price}>
                        {service.price 
                          ? `${service.price.toLocaleString()} FCFA`
                          : 'Prix sur demande'
                        }
                      </div>
                      {service.is_open_24h ? (
                        <div style={styles.hours}>🕐 24h/24</div>
                      ) : service.start_time && service.end_time ? (
                        <div style={styles.hours}>
                          🕐 {service.start_time} - {service.end_time}
                        </div>
                      ) : null}
                    </div>
                  </div>

                  {/* Footer */}
                  <div style={styles.cardFooter}>
                    {service.entreprise && (
                      <Link 
                        to={`/entreprises/${service.entreprise.id}`}
                        style={styles.entrepriseLink}
                      >
                        <div style={styles.entrepriseInfo}>
                          {service.entreprise.logo ? (
                            <img 
                              src={`${import.meta.env.VITE_API_URL}/storage/${service.entreprise.logo}`}
                              alt={service.entreprise.name}
                              style={styles.entrepriseLogo}
                            />
                          ) : (
                            <div style={styles.logoPlaceholder}>🏢</div>
                          )}
                          <span style={styles.entrepriseName}>
                            {service.entreprise.name}
                          </span>
                        </div>
                        <span style={styles.viewLink}>Voir →</span>
                      </Link>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </>
        )}
      </div>

      {/* CSS Animations */}
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
  hero: {
    textAlign: 'center',
    marginBottom: '3rem',
    padding: '2rem 1rem',
  },
  heroTitle: {
    fontSize: 'clamp(2rem, 5vw, 3rem)',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '1rem',
  },
  heroSubtitle: {
    fontSize: '1.25rem',
    color: theme.colors.text.secondary,
  },
  searchSection: {
    marginBottom: '2rem',
  },
  searchInput: {
    width: '100%',
    padding: '1rem 1.5rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.lg,
    fontSize: '1rem',
    outline: 'none',
    backgroundColor: theme.colors.secondary,
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
  resultsHeader: {
    marginBottom: '2rem',
  },
  resultsTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
    gap: '2rem',
  },
  card: {
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    overflow: 'hidden',
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.md,
    display: 'flex',
    flexDirection: 'column',
  },
  cardImage: {
    position: 'relative',
    height: '200px',
  },
  image: {
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
  imagePlaceholder: {
    height: '200px',
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderIcon: {
    fontSize: '4rem',
  },
  cardBody: {
    padding: '1.5rem',
    flex: 1,
  },
  cardTitle: {
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
  description: {
    color: theme.colors.text.secondary,
    fontSize: '0.95rem',
    lineHeight: '1.6',
    marginBottom: '1rem',
  },
  details: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  price: {
    color: theme.colors.primary,
    fontWeight: '700',
    fontSize: '1.125rem',
  },
  hours: {
    color: theme.colors.text.secondary,
    fontSize: '0.9rem',
    fontWeight: '600',
  },
  cardFooter: {
    padding: '1rem 1.5rem',
    backgroundColor: theme.colors.background,
    borderTop: `1px solid ${theme.colors.primaryLight}`,
  },
  entrepriseLink: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    textDecoration: 'none',
  },
  entrepriseInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  entrepriseLogo: {
    width: '40px',
    height: '40px',
    borderRadius: theme.borderRadius.md,
    objectFit: 'cover',
  },
  logoPlaceholder: {
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
  viewLink: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
};