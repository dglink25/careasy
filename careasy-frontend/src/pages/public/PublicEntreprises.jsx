// careasy-frontend/src/pages/public/PublicEntreprises.jsx
import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { publicApi } from '../../api/publicApi';
import theme from '../../config/theme';

export default function PublicEntreprises() {
  const [entreprises, setEntreprises] = useState([]);
  const [domaines, setDomaines] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedDomaine, setSelectedDomaine] = useState('all');

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      setLoading(true);
      const [entreprisesData, domainesData] = await Promise.all([
        publicApi.getEntreprises(),
        publicApi.getDomaines()
      ]);
      setEntreprises(entreprisesData);
      setDomaines(domainesData);
      setError('');
    } catch (err) {
      setError('Erreur lors du chargement des entreprises');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  // Filtrage
  const filteredEntreprises = entreprises.filter(e => {
    const matchSearch = e.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                       e.pdg_full_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                       e.siege?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchDomaine = selectedDomaine === 'all' || 
                        e.domaines?.some(d => d.id === parseInt(selectedDomaine));
    
    return matchSearch && matchDomaine;
  });

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
        {/* Hero Section */}
        <div style={styles.hero}>
          <h1 style={styles.heroTitle}>🏢 Trouvez votre prestataire automobile</h1>
          <p style={styles.heroSubtitle}>
            Plus de {entreprises.length} entreprises certifiées à votre service au Bénin
          </p>
        </div>

        {/* Barre de recherche et filtres */}
        <div style={styles.filtersSection}>
          <input
            type="text"
            placeholder="🔍 Rechercher par nom, ville..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            style={styles.searchInput}
          />
          
          <select
            value={selectedDomaine}
            onChange={(e) => setSelectedDomaine(e.target.value)}
            style={styles.select}
          >
            <option value="all">Tous les domaines ({entreprises.length})</option>
            {domaines.map(domaine => {
              const count = entreprises.filter(e => 
                e.domaines?.some(d => d.id === domaine.id)
              ).length;
              return (
                <option key={domaine.id} value={domaine.id}>
                  {domaine.name} ({count})
                </option>
              );
            })}
          </select>
        </div>

        {/* Message d'erreur */}
        {error && (
          <div style={styles.error}>
            ⚠️ {error}
          </div>
        )}

        {/* Résultats */}
        {filteredEntreprises.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIcon}>🔍</div>
            <h3 style={styles.emptyTitle}>Aucune entreprise trouvée</h3>
            <p style={styles.emptyText}>
              {searchTerm || selectedDomaine !== 'all'
                ? "Essayez d'autres critères de recherche"
                : "Aucune entreprise disponible pour le moment"
              }
            </p>
          </div>
        ) : (
          <>
            <div style={styles.resultsHeader}>
              <h2 style={styles.resultsTitle}>
                {filteredEntreprises.length} entreprise{filteredEntreprises.length > 1 ? 's' : ''} trouvée{filteredEntreprises.length > 1 ? 's' : ''}
              </h2>
            </div>

            <div style={styles.grid}>
              {filteredEntreprises.map((entreprise) => (
                <Link 
                  key={entreprise.id}
                  to={`/entreprises/${entreprise.id}`}
                  style={styles.card}
                  className="entreprise-card"
                >
                  {/* Image/Logo */}
                  <div style={styles.cardImage}>
                    {entreprise.logo ? (
                      <img 
                        src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                        alt={entreprise.name}
                        style={styles.logo}
                      />
                    ) : (
                      <div style={styles.logoPlaceholder}>🏢</div>
                    )}
                  </div>

                  {/* Contenu */}
                  <div style={styles.cardBody}>
                    <h3 style={styles.cardTitle}>{entreprise.name}</h3>
                    
                    {entreprise.siege && (
                      <div style={styles.location}>
                        📍 {entreprise.siege}
                      </div>
                    )}

                    {entreprise.domaines && entreprise.domaines.length > 0 && (
                      <div style={styles.domaines}>
                        {entreprise.domaines.slice(0, 2).map((domaine) => (
                          <span key={domaine.id} style={styles.domaineTag}>
                            {domaine.name}
                          </span>
                        ))}
                        {entreprise.domaines.length > 2 && (
                          <span style={styles.domaineTag}>
                            +{entreprise.domaines.length - 2}
                          </span>
                        )}
                      </div>
                    )}

                    {entreprise.services && entreprise.services.length > 0 && (
                      <div style={styles.servicesCount}>
                        🛠️ {entreprise.services.length} service{entreprise.services.length > 1 ? 's' : ''}
                      </div>
                    )}
                  </div>

                  {/* Footer */}
                  <div style={styles.cardFooter}>
                    <span style={styles.viewLink}>Voir les détails →</span>
                  </div>
                </Link>
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
        .entreprise-card {
          transition: all 0.3s ease;
        }
        .entreprise-card:hover {
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
  filtersSection: {
    backgroundColor: theme.colors.secondary,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    marginBottom: '2rem',
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
    gap: '1rem',
  },
  searchInput: {
    width: '100%',
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    outline: 'none',
  },
  select: {
    width: '100%',
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    backgroundColor: theme.colors.secondary,
    cursor: 'pointer',
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
    textDecoration: 'none',
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.md,
    display: 'flex',
    flexDirection: 'column',
  },
  cardImage: {
    height: '200px',
    backgroundColor: theme.colors.background,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderBottom: `2px solid ${theme.colors.primaryLight}`,
  },
  logo: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  logoPlaceholder: {
    fontSize: '5rem',
  },
  cardBody: {
    padding: '1.5rem',
    flex: 1,
  },
  cardTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.75rem',
  },
  location: {
    color: theme.colors.text.secondary,
    fontSize: '0.95rem',
    marginBottom: '1rem',
    fontWeight: '500',
  },
  domaines: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '0.5rem',
    marginBottom: '1rem',
  },
  domaineTag: {
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.primary,
    padding: '0.375rem 0.75rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.8rem',
    fontWeight: '600',
  },
  servicesCount: {
    color: theme.colors.primary,
    fontWeight: '600',
    fontSize: '0.95rem',
  },
  cardFooter: {
    padding: '1rem 1.5rem',
    backgroundColor: theme.colors.background,
    borderTop: `1px solid ${theme.colors.primaryLight}`,
    textAlign: 'right',
  },
  viewLink: {
    color: theme.colors.primary,
    fontWeight: '600',
    fontSize: '0.95rem',
  },
};