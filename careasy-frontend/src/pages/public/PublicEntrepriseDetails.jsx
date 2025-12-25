// careasy-frontend/src/pages/public/PublicEntrepriseDetails.jsx
import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { publicApi } from '../../api/publicApi';
import ChatButton from '../../components/Chat/ChatButton'; // 👈 IMPORT
import theme from '../../config/theme';

// Import des icônes React Icons
import {
  FiArrowLeft,
  FiMapPin,
  FiTool,
  FiClock,
  FiPhone,
  FiMail,
  FiMessageCircle,
  FiNavigation,
  FiStar,
  FiShare2,
  FiAlertCircle,
  FiHeart,
  FiCheck,
  FiCalendar,
  FiDollarSign,
  FiInfo,
  FiShield,
  FiUsers,
  FiRefreshCw,
  FiExternalLink,
  FiChevronRight,
  FiCornerUpRight
} from 'react-icons/fi';
import {
  MdBusiness,
  MdVerified,
  MdOutlineLocationOn,
  MdOutlineWork,
  MdOutlineDirectionsCar,
  MdOutlineDescription,
  MdOutlinePhone,
  MdOutlineEmail,
  MdOutlineWhatsapp,
  MdOutlineAccessTime,
  MdOutlineEventAvailable,
  MdOutlineStar,
  MdOutlineShare,
  MdOutlineWarning,
  MdOutlinePerson
} from 'react-icons/md';

export default function PublicEntrepriseDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [entreprise, setEntreprise] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const [isFavorite, setIsFavorite] = useState(false);
  const [activeService, setActiveService] = useState(null);

  useEffect(() => {
    fetchEntreprise();
    
    // Vérifier si l'entreprise est dans les favoris
    const favorites = JSON.parse(localStorage.getItem('favoriteEntreprises') || '[]');
    setIsFavorite(favorites.includes(id));
  }, [id]);

  const fetchEntreprise = useCallback(async () => {
    try {
      setLoading(true);
      setError('');
      const data = await publicApi.getEntreprise(id);
      setEntreprise(data);
    } catch (err) {
      console.error('Erreur chargement entreprise:', err);
      setError('Entreprise non trouvée');
      setTimeout(() => navigate('/entreprises'), 3000);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [id, navigate]);

  const handleRefresh = () => {
    setRefreshing(true);
    fetchEntreprise();
  };

  const toggleFavorite = () => {
    const favorites = JSON.parse(localStorage.getItem('favoriteEntreprises') || '[]');
    
    if (isFavorite) {
      const newFavorites = favorites.filter(favId => favId !== id);
      localStorage.setItem('favoriteEntreprises', JSON.stringify(newFavorites));
      setIsFavorite(false);
    } else {
      favorites.push(id);
      localStorage.setItem('favoriteEntreprises', JSON.stringify(favorites));
      setIsFavorite(true);
    }
  };

  const handleShare = () => {
    if (navigator.share && entreprise) {
      navigator.share({
        title: `Découvrez ${entreprise.name}`,
        text: `${entreprise.name} - ${entreprise.description || 'Prestataire automobile professionnel'}`,
        url: window.location.href,
      });
    } else {
      navigator.clipboard.writeText(window.location.href);
      alert('Lien copié dans le presse-papier !');
    }
  };

  const handleContact = (method) => {
    if (!entreprise) return;

    switch(method) {
      case 'phone':
        window.open(`tel:${entreprise.phone || ''}`, '_blank');
        break;
      case 'email':
        window.open(`mailto:${entreprise.email || ''}`, '_blank');
        break;
      case 'whatsapp':
        const message = encodeURIComponent(`Bonjour, je suis intéressé(e) par vos services (${entreprise.name})`);
        window.open(`https://wa.me/${entreprise.phone?.replace(/\D/g, '') || ''}?text=${message}`, '_blank');
        break;
      case 'maps':
        if (entreprise.siege) {
          const address = encodeURIComponent(entreprise.siege);
          window.open(`https://www.google.com/maps/search/?api=1&query=${address}`, '_blank');
        }
        break;
    }
  };

  const handleServiceClick = (service) => {
    setActiveService(activeService?.id === service.id ? null : service);
  };

  const formatPrice = (price) => {
    if (!price) return 'Prix sur demande';
    return `${parseFloat(price).toLocaleString('fr-FR')} FCFA`;
  };

  const getRatingColor = (rating) => {
    if (rating >= 4.5) return '#10b981';
    if (rating >= 4.0) return '#3b82f6';
    if (rating >= 3.0) return '#f59e0b';
    return '#ef4444';
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement des détails...</p>
          <p style={styles.loadingSubtext}>Veuillez patienter</p>
        </div>
      </div>
    );
  }

  if (error || !entreprise) {
    return (
      <div style={styles.container}>
        <div style={styles.errorContainer}>
          <FiAlertCircle style={styles.errorIcon} />
          <h2 style={styles.errorTitle}>{error || 'Entreprise introuvable'}</h2>
          <p style={styles.errorText}>
            L'entreprise que vous cherchez n'existe pas ou a été supprimée.
          </p>
          <div style={styles.errorActions}>
            <button 
              onClick={() => navigate('/entreprises')}
              style={styles.errorButton}
            >
              <FiArrowLeft style={styles.errorButtonIcon} />
              Retour aux entreprises
            </button>
            <button 
              onClick={fetchEntreprise}
              style={styles.errorRetryButton}
            >
              <FiRefreshCw style={styles.errorRetryIcon} />
              Réessayer
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header avec navigation */}
        <div style={styles.header}>
          <div style={styles.headerTop}>
            <button 
              onClick={() => navigate('/entreprises')}
              style={styles.backButton}
            >
              <FiArrowLeft style={styles.backButtonIcon} />
              Retour aux entreprises
            </button>
            <div style={styles.headerActions}>
              <button 
                onClick={handleRefresh}
                style={styles.headerActionButton}
                disabled={refreshing}
              >
                <FiRefreshCw style={refreshing ? styles.refreshingIcon : styles.headerActionIcon} />
                {refreshing ? 'Rafraîchissement...' : 'Rafraîchir'}
              </button>
              {/* 👉 BOUTON CHAT - OPTION 1: Dans le header */}
          <div style={styles.headerActions}>
            <ChatButton
              receiverId={entreprise.prestataire_id}
              receiverName={entreprise.name}
              buttonText="💬 Discuter avec nous"
              variant="primary"
            />
          </div>
              <button 
                onClick={handleShare}
                style={styles.headerActionButton}
                title="Partager"
              >
                <MdOutlineShare style={styles.headerActionIcon} />
                Partager
              </button>
            </div>
          </div>

          {/* Hero avec image */}
          <div style={styles.heroSection}>
            {entreprise.image_boutique ? (
              <div style={styles.heroImage}>
                <img 
                  src={`${import.meta.env.VITE_API_URL?.replace('/api', '')}/storage/${entreprise.image_boutique?.replace(/^\/?storage\//, '')}`}
                  alt={`Boutique ${entreprise.name}`}
                  style={styles.heroImg}
                  onError={(e) => {
                    e.target.onerror = null;
                    e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwMCIgaGVpZ2h0PSI0MDAiIHZpZXdCb3g9IjAgMCAxMjAwIDQwMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTIwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9IiNFQkVDRUYiLz48cGF0aCBkPSJNNjAwIDIwMEM2MzAuMzEzNyAyMDAgNjU2LjUgMjI2LjE4NjMgNjU2LjUgMjU2LjVDNjU2LjUgMjg2LjgxMzcgNjMwLjMxMzcgMzEzIDYwMCAzMTNDNTY5LjY4NjMgMzEzIDU0My41IDI4Ni44MTM3IDU0My41IDI1Ni41QzU0My41IDIyNi4xODYzIDU2OS42ODYzIDIwMCA2MDAgMjAwWiIgZmlsbD0iIzlDNUNBQiIvPjxwYXRoIGQ9Ik02ODAgMzQwSDUyMEM1MTAuODk1NCAzNDAgNTAwIDMzMC4xMDQ2IDUwMCAzMjBWMTgwQzUwMCAxNjguODk1NCA1MTAuODk1NCAxNjAgNTIwIDE2MEg2ODBDNjkxLjEwNDYgMTYwIDcwMCAxNjguODk1NCA3MDAgMTgwVjMyMEM3MDAgMzMwLjEwNDYgNjkxLjEwNDYgMzQwIDY4MCAzNDBaIiBmaWxsPSIjOUM1Q0FCIi8+PC9zdmc+';
                  }}
                />
                <div style={styles.heroOverlay}>
                  <div style={styles.heroContent}>
                    <h1 style={styles.heroTitle}>{entreprise.name}</h1>
                    {entreprise.siege && (
                      <div style={styles.heroLocation}>
                        <MdOutlineLocationOn style={styles.heroLocationIcon} />
                        {entreprise.siege}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ) : (
              <div style={styles.heroPlaceholder}>
                <MdBusiness style={styles.heroPlaceholderIcon} />
                <h1 style={styles.heroTitle}>{entreprise.name}</h1>
              </div>
            )}
          </div>

          {/* Actions rapides */}
          <div style={styles.quickActions}>
            <button 
              onClick={toggleFavorite}
              style={styles.quickActionButton}
              title={isFavorite ? "Retirer des favoris" : "Ajouter aux favoris"}
            >
              <FiHeart style={{
                ...styles.quickActionIcon,
                color: isFavorite ? '#ef4444' : '#64748b',
                fill: isFavorite ? '#ef4444' : 'none'
              }} />
              {isFavorite ? 'Favori' : 'Favoris'}
            </button>
            
            <button 
              onClick={() => handleContact('phone')}
              style={styles.quickActionButton}
              title="Appeler"
            >
              <FiPhone style={styles.quickActionIcon} />
              Appeler
            </button>
            
            <button 
              onClick={() => handleContact('whatsapp')}
              style={{...styles.quickActionButton, ...styles.whatsappButton}}
              title="WhatsApp"
            >
              <MdOutlineWhatsapp style={styles.quickActionIcon} />
              WhatsApp
            </button>
            
            <button 
              onClick={() => handleContact('maps')}
              style={styles.quickActionButton}
              title="Itinéraire"
            >
              <FiNavigation style={styles.quickActionIcon} />
              Y aller
            </button>
          </div>
        </div>

        {/* Contenu principal */}
        <div style={styles.mainContent}>
          <div style={styles.contentGrid}>
            {/* Colonne gauche - Services */}
            <div style={styles.leftColumn}>
              {/* À propos */}
              {entreprise.description && (
                <div style={styles.card}>
                  <div style={styles.cardHeader}>
                    <MdOutlineDescription style={styles.cardHeaderIcon} />
                    <h2 style={styles.cardTitle}>À propos de nous</h2>
                  </div>
                  <div style={styles.cardBody}>
                    <p style={styles.description}>{entreprise.description}</p>
                  </div>
                </div>
              )}

              {/* Domaines d'expertise */}
              {entreprise.domaines && entreprise.domaines.length > 0 && (
                <div style={styles.card}>
                  <div style={styles.cardHeader}>
                    <FiTool style={styles.cardHeaderIcon} />
                    <h2 style={styles.cardTitle}>Domaines d'expertise</h2>
                  </div>
                  <div style={styles.cardBody}>
                    <div style={styles.expertiseGrid}>
                      {entreprise.domaines.map((domaine) => (
                        <div key={domaine.id} style={styles.expertiseItem}>
                          <FiCheck style={styles.expertiseIcon} />
                          <span style={styles.expertiseName}>{domaine.name}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              )}

              {/* Services */}
              <div style={styles.card}>
                <div style={styles.cardHeader}>
                  <MdOutlineWork style={styles.cardHeaderIcon} />
                  <h2 style={styles.cardTitle}>Nos Services</h2>
                  {entreprise.services && (
                    <span style={styles.servicesCount}>
                      {entreprise.services.length} service{entreprise.services.length > 1 ? 's' : ''}
                    </span>
                  )}
                </div>
                <div style={styles.cardBody}>
                  {entreprise.services && entreprise.services.length > 0 ? (
                    <div style={styles.servicesList}>
                      {entreprise.services.map((service) => (
                        <div 
                          key={service.id}
                          style={{
                            ...styles.serviceCard,
                            ...(activeService?.id === service.id && styles.serviceCardActive)
                          }}
                          onClick={() => handleServiceClick(service)}
                          className="service-card"
                        >
                          <div style={styles.serviceHeader}>
                            {service.medias && service.medias.length > 0 && (
                              <div style={styles.serviceImageContainer}>
                                <img 
                                  src={`${import.meta.env.VITE_API_URL?.replace('/api', '')}/storage/${service.medias[0]?.replace(/^\/?storage\//, '')}`}
                                  alt={service.name}
                                  style={styles.serviceImage}
                                  onError={(e) => {
                                    e.target.onerror = null;
                                    e.target.style.display = 'none';
                                    e.target.nextElementSibling.style.display = 'flex';
                                  }}
                                />
                                <div style={styles.serviceImagePlaceholder}>
                                  <MdOutlineWork />
                                </div>
                              </div>
                            )}
                            <div style={styles.serviceInfo}>
                              <h3 style={styles.serviceName}>{service.name}</h3>
                              <div style={styles.servicePrice}>
                                <FiDollarSign style={styles.servicePriceIcon} />
                                {formatPrice(service.price)}
                              </div>
                            </div>
                            <button style={styles.serviceToggle}>
                              <FiChevronRight style={{
                                transform: activeService?.id === service.id ? 'rotate(90deg)' : 'none',
                                transition: 'transform 0.3s'
                              }} />
                            </button>
                          </div>
                          
                          {/* Détails du service */}
                          {activeService?.id === service.id && (
                            <div style={styles.serviceDetails}>
                              {service.descriptions && (
                                <p style={styles.serviceDescription}>
                                  {service.descriptions}
                                </p>
                              )}
                              
                              <div style={styles.serviceMeta}>
                                {service.is_open_24h ? (
                                  <div style={styles.serviceMetaItem}>
                                    <FiClock style={styles.serviceMetaIcon} />
                                    <span style={styles.serviceMetaText}>Disponible 24h/24</span>
                                  </div>
                                ) : service.start_time && service.end_time && (
                                  <div style={styles.serviceMetaItem}>
                                    <FiClock style={styles.serviceMetaIcon} />
                                    <span style={styles.serviceMetaText}>
                                      {service.start_time} - {service.end_time}
                                    </span>
                                  </div>
                                )}
                                
                                {service.duration && (
                                  <div style={styles.serviceMetaItem}>
                                    <MdOutlineAccessTime style={styles.serviceMetaIcon} />
                                    <span style={styles.serviceMetaText}>Durée: {service.duration}</span>
                                  </div>
                                )}
                                
                                {service.warranty && (
                                  <div style={styles.serviceMetaItem}>
                                    <FiShield style={styles.serviceMetaIcon} />
                                    <span style={styles.serviceMetaText}>Garantie: {service.warranty}</span>
                                  </div>
                                )}
                              </div>
                              
                              <div style={styles.serviceActions}>
                                <button 
                                  onClick={(e) => {
                                    e.stopPropagation();
                                    handleContact('phone');
                                  }}
                                  style={styles.serviceActionButton}
                                >
                                  <FiPhone style={styles.serviceActionIcon} />
                                  Réserver
                                </button>
                                <button 
                                  onClick={(e) => {
                                    e.stopPropagation();
                                    handleContact('whatsapp');
                                  }}
                                  style={{...styles.serviceActionButton, ...styles.serviceWhatsappButton}}
                                >
                                  <MdOutlineWhatsapp style={styles.serviceActionIcon} />
                                  Demander un devis
                                </button>
                              </div>
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div style={styles.noServices}>
                      <FiInfo style={styles.noServicesIcon} />
                      <p style={styles.noServicesText}>
                        Aucun service disponible pour le moment
                      </p>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Colonne droite - Informations et contact */}
            <div style={styles.rightColumn}>
              {/* Carte de contact */}
              <div style={styles.card}>
                <div style={styles.cardHeader}>
                  <FiPhone style={styles.cardHeaderIcon} />
                  <h2 style={styles.cardTitle}>Contact & Rendez-vous</h2>
                </div>
                <div style={styles.cardBody}>
                  <div style={styles.contactButtons}>
                    <button 
                      onClick={() => handleContact('phone')}
                      style={styles.contactButton}
                    >
                      <MdOutlinePhone style={styles.contactButtonIcon} />
                      <div style={styles.contactButtonContent}>
                        <div style={styles.contactButtonTitle}>Appeler</div>
                        <div style={styles.contactButtonSubtitle}>
                          {entreprise.phone || 'Numéro non disponible'}
                        </div>
                      </div>
                    </button>
                    
                    {/* 👉 BOUTON CHAT - OPTION 2: Dans la section contact */}
                    <div style={styles.contactActions}>
              <ChatButton
                receiverId={entreprise.prestataire_id}
                receiverName={entreprise.name}
                buttonText="Envoyer un message"
                variant="secondary"
              />
            </div>
                    <button 
                      onClick={() => handleContact('whatsapp')}
                      style={{...styles.contactButton, ...styles.contactWhatsappButton}}
                    >
                      <MdOutlineWhatsapp style={styles.contactButtonIcon} />
                      <div style={styles.contactButtonContent}>
                        <div style={styles.contactButtonTitle}>WhatsApp</div>
                        <div style={styles.contactButtonSubtitle}>Message direct</div>
                      </div>
                    </button>
                    
                    <button 
                      onClick={() => handleContact('email')}
                      style={styles.contactButton}
                    >
                      <MdOutlineEmail style={styles.contactButtonIcon} />
                      <div style={styles.contactButtonContent}>
                        <div style={styles.contactButtonTitle}>Email</div>
                        <div style={styles.contactButtonSubtitle}>
                          {entreprise.email || 'Email non disponible'}
                        </div>
                      </div>
                    </button>
                    
                    <button 
                      onClick={() => handleContact('maps')}
                      style={styles.contactButton}
                    >
                      <FiNavigation style={styles.contactButtonIcon} />
                      <div style={styles.contactButtonContent}>
                        <div style={styles.contactButtonTitle}>Itinéraire</div>
                        <div style={styles.contactButtonSubtitle}>Google Maps</div>
                      </div>
                    </button>
                  </div>
                </div>
              </div>

              {/* Informations légales */}
              <div style={styles.card}>
                <div style={styles.cardHeader}>
                  <MdVerified style={styles.cardHeaderIcon} />
                  <h2 style={styles.cardTitle}>Informations légales</h2>
                </div>
                <div style={styles.cardBody}>
                  <div style={styles.infoList}>
                    {entreprise.pdg_full_name && (
                      <div style={styles.infoItem}>
                        <div style={styles.infoItemLabel}>
                          <MdOutlinePerson style={styles.infoItemIcon} />
                          Dirigeant
                        </div>
                        <div style={styles.infoItemValue}>{entreprise.pdg_full_name}</div>
                      </div>
                    )}
                    
                    {entreprise.ifu_number && (
                      <div style={styles.infoItem}>
                        <div style={styles.infoItemLabel}>
                          <FiShield style={styles.infoItemIcon} />
                          Numéro IFU
                        </div>
                        <div style={styles.infoItemValue}>
                          <code style={styles.code}>{entreprise.ifu_number}</code>
                        </div>
                      </div>
                    )}
                    
                    {entreprise.rccm_number && (
                      <div style={styles.infoItem}>
                        <div style={styles.infoItemLabel}>
                          <MdBusiness style={styles.infoItemIcon} />
                          RCCM
                        </div>
                        <div style={styles.infoItemValue}>
                          <code style={styles.code}>{entreprise.rccm_number}</code>
                        </div>
                      </div>
                    )}
                    
                    {entreprise.certificate_number && (
                      <div style={styles.infoItem}>
                        <div style={styles.infoItemLabel}>
                          <FiCheck style={styles.infoItemIcon} />
                          Certificat
                        </div>
                        <div style={styles.infoItemValue}>
                          <code style={styles.code}>{entreprise.certificate_number}</code>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Horaires d'ouverture */}
              <div style={styles.card}>
                <div style={styles.cardHeader}>
                  <FiClock style={styles.cardHeaderIcon} />
                  <h2 style={styles.cardTitle}>Horaires d'ouverture</h2>
                </div>
                <div style={styles.cardBody}>
                  <div style={styles.scheduleList}>
                    <div style={styles.scheduleItem}>
                      <span style={styles.scheduleDay}>Lundi - Vendredi</span>
                      <span style={styles.scheduleHours}>8h - 18h</span>
                    </div>
                    <div style={styles.scheduleItem}>
                      <span style={styles.scheduleDay}>Samedi</span>
                      <span style={styles.scheduleHours}>9h - 15h</span>
                    </div>
                    <div style={styles.scheduleItem}>
                      <span style={styles.scheduleDay}>Dimanche</span>
                      <span style={styles.scheduleClosed}>Fermé</span>
                    </div>
                  </div>
                  
                  <div style={styles.scheduleNote}>
                    <FiInfo style={styles.scheduleNoteIcon} />
                    Horaires indicatifs, recommandé d'appeler avant de vous déplacer
                  </div>
                </div>
              </div>

              {/* Actions supplémentaires */}
              <div style={styles.card}>
                <div style={styles.cardHeader}>
                  <FiCornerUpRight style={styles.cardHeaderIcon} />
                  <h2 style={styles.cardTitle}>Actions</h2>
                </div>
                <div style={styles.cardBody}>
                  <div style={styles.actionsList}>
                    <button 
                      onClick={toggleFavorite}
                      style={styles.actionButton}
                    >
                      <FiHeart style={{
                        ...styles.actionIcon,
                        color: isFavorite ? '#ef4444' : '#64748b',
                        fill: isFavorite ? '#ef4444' : 'none'
                      }} />
                      <div style={styles.actionContent}>
                        <div style={styles.actionTitle}>
                          {isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'}
                        </div>
                        <div style={styles.actionDescription}>
                          {isFavorite ? 'Retirer de votre liste' : 'Sauvegarder pour plus tard'}
                        </div>
                      </div>
                    </button>
                    
                    <button 
                      onClick={handleShare}
                      style={styles.actionButton}
                    >
                      <FiShare2 style={styles.actionIcon} />
                      <div style={styles.actionContent}>
                        <div style={styles.actionTitle}>Partager cette entreprise</div>
                        <div style={styles.actionDescription}>Partagez avec vos contacts</div>
                      </div>
                    </button>
                    
                    <button 
                      onClick={() => window.print()}
                      style={styles.actionButton}
                    >
                      <FiExternalLink style={styles.actionIcon} />
                      <div style={styles.actionContent}>
                        <div style={styles.actionTitle}>Imprimer cette page</div>
                        <div style={styles.actionDescription}>Version imprimable</div>
                      </div>
                    </button>
                  </div>
                </div>
              </div>

              {/* Statistiques rapides */}
              <div style={styles.statsCard}>
                <div style={styles.statsGrid}>
                  <div style={styles.statItem}>
                    <div style={styles.statIcon}>
                      <MdOutlineStar />
                    </div>
                    <div style={styles.statContent}>
                      <div style={styles.statNumber}>4.8</div>
                      <div style={styles.statLabel}>Note</div>
                    </div>
                  </div>
                  <div style={styles.statItem}>
                    <div style={styles.statIcon}>
                      <FiUsers />
                    </div>
                    <div style={styles.statContent}>
                      <div style={styles.statNumber}>124</div>
                      <div style={styles.statLabel}>Clients</div>
                    </div>
                  </div>
                  <div style={styles.statItem}>
                    <div style={styles.statIcon}>
                      <FiCalendar />
                    </div>
                    <div style={styles.statContent}>
                      <div style={styles.statNumber}>5+</div>
                      <div style={styles.statLabel}>Ans exp.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Call to Action */}
        <div style={styles.ctaSection}>
          <div style={styles.ctaContent}>
            <div style={styles.ctaIcon}>
              <MdOutlineDirectionsCar />
            </div>
            <div style={styles.ctaText}>
              <h3 style={styles.ctaTitle}>Prêt à réparer votre véhicule ?</h3>
              <p style={styles.ctaDescription}>
                Contactez {entreprise.name} dès maintenant pour un devis gratuit et personnalisé.
              </p>
            </div>
            <div style={styles.ctaButtons}>
              <button 
                onClick={() => handleContact('phone')}
                style={styles.ctaPrimaryButton}
              >
                <MdOutlinePhone style={styles.ctaButtonIcon} />
                Appeler maintenant
              </button>
              <button 
                onClick={() => handleContact('whatsapp')}
                style={styles.ctaSecondaryButton}
              >
                <MdOutlineWhatsapp style={styles.ctaButtonIcon} />
                WhatsApp
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
        
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
          from { opacity: 0; transform: translateX(-20px); }
          to { opacity: 1; transform: translateX(0); }
        }
        
        .service-card {
          animation: fadeIn 0.3s ease-out;
          transition: all 0.3s ease;
        }
        
        .service-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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
  loadingSubtext: {
    color: '#94a3b8',
    fontSize: '0.875rem',
  },
  errorContainer: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: '60vh',
    gap: '1.5rem',
    textAlign: 'center',
  },
  errorIcon: {
    fontSize: '5rem',
    color: '#ef4444',
  },
  errorTitle: {
    fontSize: '2rem',
    fontWeight: '700',
    color: '#1e293b',
  },
  errorText: {
    color: '#64748b',
    fontSize: '1.125rem',
    maxWidth: '400px',
  },
  errorActions: {
    display: 'flex',
    gap: '1rem',
    flexWrap: 'wrap',
  },
  errorButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#3b82f6',
    color: '#fff',
    border: 'none',
    padding: '0.875rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
  },
  errorButtonIcon: {
    fontSize: '1.125rem',
  },
  errorRetryButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#f1f5f9',
    color: '#475569',
    border: '1px solid #e2e8f0',
    padding: '0.875rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
  },
  errorRetryIcon: {
    fontSize: '1.125rem',
  },
  header: {
    marginBottom: '2rem',
  },
  headerTop: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: '1.5rem 0',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  backButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: 'transparent',
    border: 'none',
    color: '#ef4444',
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    padding: '0.5rem 0',
    textDecoration: 'none',
  },
  backButtonIcon: {
    fontSize: '1.25rem',
  },
  headerActions: {
    display: 'flex',
    gap: '0.75rem',
  },
  headerActionButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    padding: '0.625rem 1rem',
    borderRadius: theme.borderRadius.md,
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
  heroSection: {
    borderRadius: theme.borderRadius.lg,
    overflow: 'hidden',
    marginBottom: '1.5rem',
  },
  heroImage: {
    position: 'relative',
    height: '400px',
    backgroundColor: '#1e293b',
  },
  heroImg: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
    opacity: 0.8,
  },
  heroOverlay: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    padding: '2rem',
    background: 'linear-gradient(transparent, rgba(0,0,0,0.8))',
  },
  heroContent: {
    color: '#fff',
  },
  heroTitle: {
    fontSize: '2.5rem',
    fontWeight: '800',
    color: '#fff',
    margin: '0 0 0.5rem 0',
    textShadow: '0 2px 4px rgba(0,0,0,0.3)',
  },
  heroLocation: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontSize: '1.125rem',
    opacity: 0.9,
  },
  heroLocationIcon: {
    fontSize: '1.5rem',
  },
  heroPlaceholder: {
    height: '200px',
    backgroundColor: '#dbeafe',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '1rem',
    padding: '2rem',
  },
  heroPlaceholderIcon: {
    fontSize: '4rem',
    color: '#3b82f6',
  },
  quickActions: {
    display: 'flex',
    gap: '1rem',
    flexWrap: 'wrap',
    marginBottom: '2rem',
  },
  quickActionButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#475569',
    cursor: 'pointer',
    transition: 'all 0.2s',
    flex: 1,
    minWidth: '140px',
    justifyContent: 'center',
  },
  whatsappButton: {
    backgroundColor: '#25D366',
    color: '#fff',
    borderColor: '#25D366',
  },
  quickActionIcon: {
    fontSize: '1.25rem',
  },
  mainContent: {
    marginBottom: '3rem',
  },
  contentGrid: {
    display: 'grid',
    gridTemplateColumns: '1fr 400px',
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
    backgroundColor: '#fff',
    borderRadius: theme.borderRadius.lg,
    border: '1px solid #e2e8f0',
    overflow: 'hidden',
  },
  cardHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  
    padding: '1.5rem',
    borderBottom: '1px solid #f1f5f9',
    backgroundColor: '#f8fafc',
  },
  cardHeaderIcon: {
    fontSize: '1.5rem',
    color: theme.colors.primary,
  },
  cardTitle: {
    fontSize: '1.25rem',
    fontWeight: '600',
    color: '#1e293b',
    margin: 0,
  },
  servicesCount: {
    marginLeft: 'auto',
    backgroundColor: '#e0f2fe',
    color: '#0369a1',
    padding: '0.25rem 0.75rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.875rem',
    fontWeight: '500',
  },
  cardBody: {
    padding: '1.5rem',
  },
  description: {
    color: '#475569',
    lineHeight: '1.6',
    fontSize: '1rem',
    margin: 0,
  },
  expertiseGrid: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '0.75rem',
  },
  expertiseItem: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#f0f9ff',
    padding: '0.625rem 1rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.875rem',
    color: '#0369a1',
  },
  expertiseIcon: {
    fontSize: '1rem',
    color: '#0ea5e9',
  },
  expertiseName: {
    fontWeight: '500',
  },
  servicesList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  serviceCard: {
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    borderRadius: theme.borderRadius.lg,
    overflow: 'hidden',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  serviceCardActive: {
    borderColor: theme.colors.primary,
    boxShadow: '0 4px 12px rgba(59, 130, 246, 0.1)',
  },
  serviceHeader: {
    display: 'flex',
    alignItems: 'center',
    padding: '1rem',
    gap: '1rem',
  },
  serviceImageContainer: {
    position: 'relative',
    width: '60px',
    height: '60px',
    borderRadius: theme.borderRadius.md,
    overflow: 'hidden',
    backgroundColor: '#f1f5f9',
    flexShrink: 0,
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
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    color: '#94a3b8',
    fontSize: '1.5rem',
  },
  serviceInfo: {
    flex: 1,
  },
  serviceName: {
    fontSize: '1rem',
    fontWeight: '600',
    color: '#1e293b',
    margin: '0 0 0.25rem 0',
  },
  servicePrice: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    color: theme.colors.primary,
    fontWeight: '600',
    fontSize: '0.875rem',
  },
  servicePriceIcon: {
    fontSize: '0.875rem',
  },
  serviceToggle: {
    backgroundColor: 'transparent',
    border: 'none',
    color: '#94a3b8',
    cursor: 'pointer',
    padding: '0.5rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  serviceDetails: {
    padding: '0 1rem 1rem 1rem',
    borderTop: '1px solid #f1f5f9',
    animation: 'fadeIn 0.3s ease-out',
  },
  serviceDescription: {
    color: '#64748b',
    fontSize: '0.875rem',
    lineHeight: '1.5',
    margin: '0 0 1rem 0',
  },
  serviceMeta: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '1rem',
    marginBottom: '1rem',
  },
  serviceMetaItem: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    fontSize: '0.875rem',
    color: '#64748b',
  },
  serviceMetaIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  serviceMetaText: {
    fontWeight: '500',
  },
  serviceActions: {
    display: 'flex',
    gap: '0.75rem',
  },
  serviceActionButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    border: 'none',
    padding: '0.625rem 1.25rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    flex: 1,
  },
  serviceWhatsappButton: {
    backgroundColor: '#25D366',
  },
  serviceActionIcon: {
    fontSize: '1rem',
  },
  noServices: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '3rem',
    color: '#94a3b8',
  },
  noServicesIcon: {
    fontSize: '3rem',
    marginBottom: '1rem',
  },
  noServicesText: {
    textAlign: 'center',
    fontSize: '0.875rem',
  },
  contactButtons: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  contactButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    padding: '1rem',
    borderRadius: theme.borderRadius.lg,
    textAlign: 'left',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  contactWhatsappButton: {
    backgroundColor: '#f0fff4',
    borderColor: '#bbf7d0',
  },
  contactButtonIcon: {
    fontSize: '1.5rem',
    color: theme.colors.primary,
  },
  contactButtonContent: {
    flex: 1,
  },
  contactButtonTitle: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e293b',
  },
  contactButtonSubtitle: {
    fontSize: '0.875rem',
    color: '#64748b',
  },
  infoList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.25rem',
  },
  infoItem: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  infoItemLabel: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontSize: '0.875rem',
    color: '#64748b',
    flex: 1,
  },
  infoItemIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  infoItemValue: {
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#1e293b',
    textAlign: 'right',
    maxWidth: '200px',
    wordBreak: 'break-word',
  },
  code: {
    backgroundColor: '#f1f5f9',
    padding: '0.25rem 0.5rem',
    borderRadius: theme.borderRadius.sm,
    fontFamily: 'monospace',
    fontSize: '0.75rem',
  },
  scheduleList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  scheduleItem: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: '0.75rem 0',
    borderBottom: '1px solid #f1f5f9',
  },
  scheduleDay: {
    fontSize: '0.875rem',
    color: '#475569',
  },
  scheduleHours: {
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#059669',
  },
  scheduleClosed: {
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#ef4444',
  },
  scheduleNote: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '0.5rem',
    marginTop: '1rem',
    padding: '0.75rem',
    backgroundColor: '#fef3c7',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.75rem',
    color: '#92400e',
  },
  scheduleNoteIcon: {
    fontSize: '0.875rem',
    flexShrink: 0,
  },
  actionsList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  actionButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    backgroundColor: 'transparent',
    border: 'none',
    padding: '0.75rem',
    borderRadius: theme.borderRadius.md,
    textAlign: 'left',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  actionIcon: {
    fontSize: '1.25rem',
    flexShrink: 0,
  },
  actionContent: {
    flex: 1,
  },
  actionTitle: {
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#1e293b',
  },
  actionDescription: {
    fontSize: '0.75rem',
    color: '#64748b',
  },
  statsCard: {
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    borderRadius: theme.borderRadius.lg,
    padding: '1.5rem',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(3, 1fr)',
    gap: '1rem',
  },
  statItem: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  statIcon: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '40px',
    height: '40px',
    backgroundColor: '#f0f9ff',
    borderRadius: theme.borderRadius.lg,
    color: theme.colors.primary,
    fontSize: '1.25rem',
  },
  statContent: {
    display: 'flex',
    flexDirection: 'column',
  },
  statNumber: {
    fontSize: '1.25rem',
    fontWeight: '700',
    color: '#1e293b',
  },
  statLabel: {
    fontSize: '0.75rem',
    color: '#64748b',
  },
  ctaSection: {
    backgroundColor: '#1e293b',
    borderRadius: theme.borderRadius.lg,
    padding: '3rem',
    marginBottom: '3rem',
  },
  ctaContent: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: '2rem',
    color: '#fff',
  },
  ctaIcon: {
    fontSize: '4rem',
    color: '#0ea5e9',
    flexShrink: 0,
  },
  ctaText: {
    flex: 1,
  },
  ctaTitle: {
    fontSize: '1.5rem',
    fontWeight: '700',
    margin: '0 0 0.5rem 0',
  },
  ctaDescription: {
    fontSize: '1rem',
    opacity: 0.9,
    margin: 0,
  },
  ctaButtons: {
    display: 'flex',
    gap: '1rem',
    flexShrink: 0,
  },
  ctaPrimaryButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#3b82f6',
    color: '#fff',
    border: 'none',
    padding: '0.875rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
  },
  ctaSecondaryButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#25D366',
    color: '#fff',
    border: 'none',
    padding: '0.875rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
  },
  ctaButtonIcon: {
    fontSize: '1.125rem',
  },

  // Styles responsives
  '@media (max-width: 1024px)': {
    contentGrid: {
      gridTemplateColumns: '1fr',
    },
    quickActionButton: {
      minWidth: '120px',
    },
  },
  '@media (max-width: 768px)': {
    content: {
      padding: '0 1rem',
    },
    heroTitle: {
      fontSize: '2rem',
    },
    heroImage: {
      height: '300px',
    },
    ctaContent: {
      flexDirection: 'column',
      textAlign: 'center',
      gap: '1.5rem',
    },
    ctaButtons: {
      flexDirection: 'column',
      width: '100%',
    },
    quickActions: {
      justifyContent: 'center',
    },
    headerTop: {
      flexDirection: 'column',
      alignItems: 'stretch',
    },
    headerActions: {
      justifyContent: 'center',
    },
    statsGrid: {
      gridTemplateColumns: 'repeat(3, 1fr)',
    },
  },
  '@media (max-width: 480px)': {
    heroTitle: {
      fontSize: '1.75rem',
    },
    heroImage: {
      height: '250px',
    },
    quickActionButton: {
      minWidth: 'calc(50% - 0.5rem)',
    },
    serviceActions: {
      flexDirection: 'column',
    },
    statsGrid: {
      gridTemplateColumns: '1fr',
      gap: '0.75rem',
    },
    statItem: {
      justifyContent: 'center',
    },
  },
};