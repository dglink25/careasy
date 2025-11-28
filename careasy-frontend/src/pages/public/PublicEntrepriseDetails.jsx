// careasy-frontend/src/pages/public/PublicEntrepriseDetails.jsx
import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { publicApi } from '../../api/publicApi';
import theme from '../../config/theme';

export default function PublicEntrepriseDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [entreprise, setEntreprise] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchEntreprise();
  }, [id]);

  const fetchEntreprise = async () => {
    try {
      setLoading(true);
      const data = await publicApi.getEntreprise(id);
      setEntreprise(data);
      setError('');
    } catch (err) {
      console.error('Erreur chargement entreprise:', err);
      setError('Entreprise non trouvée');
      setTimeout(() => navigate('/entreprises'), 2000);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement...</p>
        </div>
      </div>
    );
  }

  if (error || !entreprise) {
    return (
      <div style={styles.container}>
        <div style={styles.errorContainer}>
          <div style={styles.errorIcon}>❌</div>
          <h2 style={styles.errorTitle}>{error || 'Entreprise introuvable'}</h2>
          <Link to="/entreprises" style={styles.errorButton}>
            ← Retour aux entreprises
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
          <Link to="/entreprises" style={styles.backButton}>
            ← Retour aux entreprises
          </Link>
        </div>

        {/* Hero avec image */}
        <div style={styles.hero}>
          {entreprise.image_boutique ? (
            <div style={styles.heroImage}>
              <img 
                src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.image_boutique}`}
                alt={entreprise.name}
                style={styles.heroImg}
              />
            </div>
          ) : (
            <div style={styles.heroPlaceholder}>
              <div style={styles.heroIcon}></div>
            </div>
          )}
        </div>

        {/* Informations principales */}
        <div style={styles.mainSection}>
          <div style={styles.mainInfo}>
            {entreprise.logo && (
              <img 
                src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                alt={entreprise.name}
                style={styles.mainLogo}
              />
            )}
            <div>
              <h1 style={styles.title}>{entreprise.name}</h1>
              {entreprise.siege && (
                <div style={styles.location}>
                   {entreprise.siege}
                </div>
              )}
            </div>
          </div>

          {/* Domaines */}
          {entreprise.domaines && entreprise.domaines.length > 0 && (
            <div style={styles.domainesSection}>
              {entreprise.domaines.map((domaine) => (
                <span key={domaine.id} style={styles.domaineTag}>
                  {domaine.name}
                </span>
              ))}
            </div>
          )}
        </div>

        {/* Grid contenu */}
        <div style={styles.contentGrid}>
          {/* Colonne gauche - Services */}
          <div style={styles.leftColumn}>
            <div style={styles.card}>
              <h2 style={styles.cardTitle}>🛠️ Nos Services</h2>
              {entreprise.services && entreprise.services.length > 0 ? (
                <div style={styles.servicesList}>
                  {entreprise.services.map((service) => (
                    <div key={service.id} style={styles.serviceItem}>
                      {service.medias && service.medias.length > 0 && (
                        <img 
                          src={`${import.meta.env.VITE_API_URL}/storage/${service.medias[0]}`}
                          alt={service.name}
                          style={styles.serviceImage}
                        />
                      )}
                      <div style={styles.serviceInfo}>
                        <h3 style={styles.serviceName}>{service.name}</h3>
                        {service.descriptions && (
                          <p style={styles.serviceDesc}>
                            {service.descriptions.substring(0, 100)}
                            {service.descriptions.length > 100 ? '...' : ''}
                          </p>
                        )}
                        <div style={styles.serviceDetails}>
                          <span style={styles.servicePrice}>
                            {service.price 
                              ? `${service.price.toLocaleString()} FCFA`
                              : 'Prix sur demande'
                            }
                          </span>
                          {service.is_open_24h ? (
                            <span style={styles.serviceHours}>🕐 24h/24</span>
                          ) : service.start_time && service.end_time ? (
                            <span style={styles.serviceHours}>
                              🕐 {service.start_time} - {service.end_time}
                            </span>
                          ) : null}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div style={styles.emptyServices}>
                  <p style={styles.emptyText}>Aucun service disponible</p>
                </div>
              )}
            </div>
          </div>

          {/* Colonne droite - Contact */}
          <div style={styles.rightColumn}>
            {/* Carte Contact */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}>📞 Nous contacter</h2>
              <div style={styles.contactButtons}>
                <button style={styles.contactButton}>
                  📞 Appeler
                </button>
                <button style={styles.contactButton}>
                   WhatsApp
                </button>
                <button style={styles.contactButton}>
                   Email
                </button>
                <button style={styles.contactButton}>
                   Itinéraire
                </button>
              </div>
            </div>

            {/* Carte Informations */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}>ℹ️ Informations</h2>
              <div style={styles.infoList}>
                {entreprise.pdg_full_name && (
                  <div style={styles.infoItem}>
                    <span style={styles.infoLabel}>👤 Dirigeant</span>
                    <span style={styles.infoValue}>{entreprise.pdg_full_name}</span>
                  </div>
                )}
                {entreprise.ifu_number && (
                  <div style={styles.infoItem}>
                    <span style={styles.infoLabel}>🏷️ IFU</span>
                    <span style={styles.infoValue}>
                      <code style={styles.code}>{entreprise.ifu_number}</code>
                    </span>
                  </div>
                )}
              </div>
            </div>

            {/* Carte Horaires (si disponible) */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}>🕐 Horaires</h2>
              <div style={styles.horairesList}>
                <div style={styles.horaireItem}>
                  <span style={styles.horaireDay}>Lundi - Vendredi</span>
                  <span style={styles.horaireTime}>8h - 18h</span>
                </div>
                <div style={styles.horaireItem}>
                  <span style={styles.horaireDay}>Samedi</span>
                  <span style={styles.horaireTime}>9h - 15h</span>
                </div>
                <div style={styles.horaireItem}>
                  <span style={styles.horaireDay}>Dimanche</span>
                  <span style={styles.horaireClosed}>Fermé</span>
                </div>
              </div>
            </div>

            {/* Carte Actions */}
            <div style={styles.actionsCard}>
              <button style={styles.actionButton}>
                ⭐ Ajouter aux favoris
              </button>
              <button style={styles.actionButton}>
                📤 Partager
              </button>
              <button style={styles.actionButton}>
                🚨 Signaler
              </button>
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
    padding: '2rem 0 1rem',
  },
  backButton: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    display: 'inline-block',
  },
  hero: {
    marginBottom: '2rem',
    borderRadius: theme.borderRadius.xl,
    overflow: 'hidden',
  },
  heroImage: {
    height: '400px',
    width: '100%',
  },
  heroImg: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  heroPlaceholder: {
    height: '400px',
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroIcon: {
    fontSize: '8rem',
  },
  mainSection: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    marginBottom: '2rem',
  },
  mainInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '1.5rem',
    marginBottom: '1.5rem',
  },
  mainLogo: {
    width: '100px',
    height: '100px',
    borderRadius: theme.borderRadius.lg,
    objectFit: 'cover',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  title: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  location: {
    fontSize: '1.125rem',
    color: theme.colors.text.secondary,
    fontWeight: '500',
  },
  domainesSection: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '0.75rem',
  },
  domaineTag: {
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.primary,
    padding: '0.5rem 1rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    fontWeight: '600',
  },
  contentGrid: {
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
  card: {
    backgroundColor: theme.colors.secondary,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.sm,
  },
  cardTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '1.5rem',
  },
  servicesList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  serviceItem: {
    display: 'flex',
    gap: '1rem',
    padding: '1rem',
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.lg,
    border: `1px solid ${theme.colors.primaryLight}`,
  },
  serviceImage: {
    width: '100px',
    height: '100px',
    objectFit: 'cover',
    borderRadius: theme.borderRadius.md,
    flexShrink: 0,
  },
  serviceInfo: {
    flex: 1,
  },
  serviceName: {
    fontSize: '1.125rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  serviceDesc: {
    fontSize: '0.95rem',
    color: theme.colors.text.secondary,
    marginBottom: '0.75rem',
    lineHeight: '1.5',
  },
  serviceDetails: {
    display: 'flex',
    gap: '1rem',
    alignItems: 'center',
    flexWrap: 'wrap',
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
  emptyServices: {
    textAlign: 'center',
    padding: '3rem 1rem',
  },
  emptyText: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
  },
  contactButtons: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, 1fr)',
    gap: '1rem',
  },
  contactButton: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    border: 'none',
    padding: '1rem',
    borderRadius: theme.borderRadius.md,
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.3s',
    fontSize: '0.95rem',
  },
  infoList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  infoItem: {
    display: 'flex',
    justifyContent: 'space-between',
    gap: '1rem',
    paddingBottom: '1rem',
    borderBottom: `1px solid ${theme.colors.primaryLight}`,
  },
  infoLabel: {
    fontWeight: '600',
    color: theme.colors.text.secondary,
  },
  infoValue: {
    color: theme.colors.text.primary,
    textAlign: 'right',
  },
  code: {
    backgroundColor: theme.colors.background,
    padding: '0.25rem 0.5rem',
    borderRadius: theme.borderRadius.sm,
    fontSize: '0.875rem',
    fontFamily: 'monospace',
  },
  horairesList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  horaireItem: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  horaireDay: {
    fontWeight: '600',
    color: theme.colors.text.primary,
  },
  horaireTime: {
    color: theme.colors.success,
    fontWeight: '600',
  },
  horaireClosed: {
    color: theme.colors.error,
    fontWeight: '600',
  },
  actionsCard: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  actionButton: {
    backgroundColor: theme.colors.background,
    color: theme.colors.text.primary,
    border: `2px solid ${theme.colors.primaryLight}`,
    padding: '0.875rem',
    borderRadius: theme.borderRadius.md,
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.3s',
  },
};