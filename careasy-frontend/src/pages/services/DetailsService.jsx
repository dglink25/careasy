// careasy-frontend/src/pages/services/DetailsService.jsx
import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { serviceApi } from '../../api/serviceApi';
import theme from '../../config/theme';

export default function DetailsService() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [service, setService] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [currentImageIndex, setCurrentImageIndex] = useState(0);

  useEffect(() => {
    fetchService();
  }, [id]);

  const fetchService = async () => {
    try {
      setLoading(true);
      // Note: L'API actuelle retourne entreprise, on doit adapter
      // Pour l'instant on simule, tu devras créer une vraie route backend
      const data = await serviceApi.getService(id);
      setService(data);
      setError('');
    } catch (err) {
      console.error('Erreur chargement service:', err);
      setError('Service non trouvé');
      setTimeout(() => navigate('/mes-services'), 2000);
    } finally {
      setLoading(false);
    }
  };

  const nextImage = () => {
    if (service?.medias && service.medias.length > 0) {
      setCurrentImageIndex((prev) => 
        prev === service.medias.length - 1 ? 0 : prev + 1
      );
    }
  };

  const prevImage = () => {
    if (service?.medias && service.medias.length > 0) {
      setCurrentImageIndex((prev) => 
        prev === 0 ? service.medias.length - 1 : prev - 1
      );
    }
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement des détails...</p>
        </div>
      </div>
    );
  }

  if (error || !service) {
    return (
      <div style={styles.container}>
        <div style={styles.errorContainer}>
          <div style={styles.errorIcon}>❌</div>
          <h2 style={styles.errorTitle}>{error || 'Service introuvable'}</h2>
          <Link to="/mes-services" style={styles.errorButton}>
            ← Retour à mes services
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header */}
        <div style={styles.header}>
          <Link to="/mes-services" style={styles.backButton}>
            ← Retour à mes services
          </Link>
        </div>

        {/* Grid principal */}
        <div style={styles.mainGrid}>
          {/* Colonne gauche - Images */}
          <div style={styles.leftColumn}>
            {service.medias && service.medias.length > 0 ? (
              <div style={styles.galleryCard}>
                <div style={styles.mainImageContainer}>
                  <img 
                    src={`${import.meta.env.VITE_API_URL}/storage/${service.medias[currentImageIndex]}`}
                    alt={service.name}
                    style={styles.mainImage}
                  />
                  
                  {service.medias.length > 1 && (
                    <>
                      <button 
                        onClick={prevImage}
                        style={{...styles.navButton, left: '10px'}}
                      >
                        ‹
                      </button>
                      <button 
                        onClick={nextImage}
                        style={{...styles.navButton, right: '10px'}}
                      >
                        ›
                      </button>
                      <div style={styles.imageCounter}>
                        {currentImageIndex + 1} / {service.medias.length}
                      </div>
                    </>
                  )}
                </div>
                
                {service.medias.length > 1 && (
                  <div style={styles.thumbnailsContainer}>
                    {service.medias.map((media, index) => (
                      <button
                        key={index}
                        onClick={() => setCurrentImageIndex(index)}
                        style={{
                          ...styles.thumbnail,
                          ...(index === currentImageIndex ? styles.thumbnailActive : {})
                        }}
                      >
                        <img 
                          src={`${import.meta.env.VITE_API_URL}/storage/${media}`}
                          alt={`${service.name} ${index + 1}`}
                          style={styles.thumbnailImage}
                        />
                      </button>
                    ))}
                  </div>
                )}
              </div>
            ) : (
              <div style={styles.noImageCard}>
                <div style={styles.noImageIcon}>📸</div>
                <p style={styles.noImageText}>Aucune image disponible</p>
              </div>
            )}

            {/* Carte Entreprise */}
            {service.entreprise && (
              <div style={styles.card}>
                <h2 style={styles.cardTitle}>🏢 Entreprise</h2>
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
                      <div style={styles.entrepriseLogoPlaceholder}>🏢</div>
                    )}
                    <div>
                      <div style={styles.entrepriseName}>
                        {service.entreprise.name}
                      </div>
                      <div style={styles.entrepriseDetails}>
                        📍 {service.entreprise.siege || 'Localisation non renseignée'}
                      </div>
                    </div>
                  </div>
                  <span style={styles.viewLink}>Voir →</span>
                </Link>
              </div>
            )}
          </div>

          {/* Colonne droite - Informations */}
          <div style={styles.rightColumn}>
            {/* Carte principale */}
            <div style={styles.card}>
              <h1 style={styles.serviceName}>{service.name}</h1>
              
              {service.domaine && (
                <div style={styles.domaineTag}>
                  🏷️ {service.domaine.name}
                </div>
              )}

              {service.descriptions && (
                <div style={styles.descriptionSection}>
                  <h3 style={styles.sectionSubtitle}>📋 Description</h3>
                  <p style={styles.description}>{service.descriptions}</p>
                </div>
              )}
            </div>

            {/* Carte Prix & Horaires */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}>💰 Tarification & Horaires</h2>
              
              <div style={styles.infoList}>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>💵 Prix</span>
                  <span style={styles.priceValue}>
                    {service.price 
                      ? `${service.price.toLocaleString()} FCFA`
                      : 'Prix sur demande'
                    }
                  </span>
                </div>

                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>🕐 Horaires</span>
                  <span style={styles.infoValue}>
                    {service.is_open_24h 
                      ? '24h/24 - 7j/7'
                      : service.start_time && service.end_time
                        ? `${service.start_time} - ${service.end_time}`
                        : 'Non renseignés'
                    }
                  </span>
                </div>
              </div>
            </div>

            {/* Carte Actions */}
            <div style={styles.actionsCard}>
              <h3 style={styles.actionsTitle}>Actions rapides</h3>
              <div style={styles.actionsGrid}>
                <button style={styles.actionButton}>
                  📞 Contacter
                </button>
                <button style={styles.actionButton}>
                  📅 Réserver
                </button>
                <button style={styles.actionButton}>
                  ⭐ Ajouter aux favoris
                </button>
                <button style={styles.actionButton}>
                  📤 Partager
                </button>
              </div>
            </div>

            {/* Info box */}
            <div style={styles.infoBox}>
              <div style={styles.infoBoxIcon}>💡</div>
              <div>
                <h4 style={styles.infoBoxTitle}>Besoin de modifications ?</h4>
                <p style={styles.infoBoxText}>
                  Pour modifier ce service, contactez l'équipe CarEasy ou 
                  gérez-le depuis votre dashboard prestataire.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* CSS Animations */}
      <style>{`
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
  errorContainer: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: '60vh',
    gap: '1.5rem',
  },
  errorIcon: {
    fontSize: '5rem',
  },
  errorTitle: {
    fontSize: '1.75rem',
    color: theme.colors.text.primary,
  },
  errorButton: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '1rem 2rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
  },
  header: {
    marginBottom: '2rem',
  },
  backButton: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    display: 'inline-block',
  },
  mainGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))',
    gap: '2rem',
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
  galleryCard: {
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    overflow: 'hidden',
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.md,
  },
  mainImageContainer: {
    position: 'relative',
    height: '400px',
    backgroundColor: theme.colors.background,
  },
  mainImage: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  navButton: {
    position: 'absolute',
    top: '50%',
    transform: 'translateY(-50%)',
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    color: '#fff',
    border: 'none',
    width: '50px',
    height: '50px',
    borderRadius: '50%',
    fontSize: '2rem',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: 'all 0.3s',
  },
  imageCounter: {
    position: 'absolute',
    bottom: '15px',
    right: '15px',
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    color: '#fff',
    padding: '0.5rem 1rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.9rem',
    fontWeight: '600',
  },
  thumbnailsContainer: {
    display: 'flex',
    gap: '0.75rem',
    padding: '1rem',
    overflowX: 'auto',
  },
  thumbnail: {
    width: '80px',
    height: '80px',
    borderRadius: theme.borderRadius.md,
    overflow: 'hidden',
    border: `3px solid transparent`,
    cursor: 'pointer',
    flexShrink: 0,
    transition: 'all 0.3s',
    padding: 0,
    backgroundColor: 'transparent',
  },
  thumbnailActive: {
    borderColor: theme.colors.primary,
  },
  thumbnailImage: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  noImageCard: {
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    border: `2px dashed ${theme.colors.primaryLight}`,
    padding: '4rem 2rem',
    textAlign: 'center',
  },
  noImageIcon: {
    fontSize: '4rem',
    marginBottom: '1rem',
  },
  noImageText: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
  },
  card: {
    backgroundColor: theme.colors.secondary,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.sm,
  },
  cardTitle: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '1rem',
  },
  serviceName: {
    fontSize: '2rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '1rem',
  },
  domaineTag: {
    display: 'inline-block',
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.primary,
    padding: '0.5rem 1rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    fontWeight: '600',
    marginBottom: '1.5rem',
  },
  descriptionSection: {
    marginTop: '1.5rem',
  },
  sectionSubtitle: {
    fontSize: '1.125rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.75rem',
  },
  description: {
    color: theme.colors.text.secondary,
    fontSize: '1rem',
    lineHeight: '1.8',
    whiteSpace: 'pre-wrap',
  },
  infoList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  infoItem: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingBottom: '1rem',
    borderBottom: `1px solid ${theme.colors.primaryLight}`,
  },
  infoLabel: {
    fontWeight: '600',
    color: theme.colors.text.secondary,
  },
  infoValue: {
    color: theme.colors.text.primary,
    fontWeight: '600',
  },
  priceValue: {
    color: theme.colors.primary,
    fontWeight: '700',
    fontSize: '1.25rem',
  },
  entrepriseLink: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    textDecoration: 'none',
    padding: '1rem',
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.md,
    border: `1px solid ${theme.colors.primaryLight}`,
    transition: 'all 0.3s',
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
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.25rem',
  },
  entrepriseDetails: {
    color: theme.colors.text.secondary,
    fontSize: '0.9rem',
  },
  viewLink: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  actionsCard: {
    backgroundColor: theme.colors.primaryLight,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primary}`,
  },
  actionsTitle: {
    fontSize: '1.125rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '1rem',
  },
  actionsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, 1fr)',
    gap: '0.75rem',
  },
  actionButton: {
    backgroundColor: theme.colors.secondary,
    border: `2px solid ${theme.colors.primary}`,
    padding: '0.875rem',
    borderRadius: theme.borderRadius.md,
    color: theme.colors.primary,
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.3s',
    fontSize: '0.95rem',
  },
  infoBox: {
    backgroundColor: '#DBEAFE',
    padding: '1.25rem',
    borderRadius: theme.borderRadius.lg,
    border: '2px solid #3B82F6',
    display: 'flex',
    gap: '1rem',
    alignItems: 'flex-start',
  },
  infoBoxIcon: {
    fontSize: '1.5rem',
    flexShrink: 0,
  },
  infoBoxTitle: {
    fontWeight: 'bold',
    color: '#1E40AF',
    marginBottom: '0.5rem',
    fontSize: '0.95rem',
  },
  infoBoxText: {
    color: '#1E40AF',
    fontSize: '0.875rem',
    lineHeight: '1.6',
  },
};