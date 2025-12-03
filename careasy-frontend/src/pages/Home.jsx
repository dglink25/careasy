// careasy-frontend/src/pages/Home.jsx - VERSION PROFESSIONNELLE AVEC REACT ICONS
import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { publicApi } from '../api/publicApi';
import theme from '../config/theme';
import { 
  FaWrench, FaPaintBrush, FaCog, FaSnowflake, 
  FaCar, FaShieldAlt, FaGraduationCap, FaOilCan,
  FaArrowRight, FaComments, FaTimes, FaPaperPlane,
  FaMapMarkerAlt, FaPhone, FaEnvelope, FaStar
} from 'react-icons/fa';

export default function Home() {
  const { user } = useAuth();
  const [currentSlide, setCurrentSlide] = useState(0);
  const [services, setServices] = useState([]);
  const [partners, setPartners] = useState([]);
  const [showChatbot, setShowChatbot] = useState(false);
  const [chatMessage, setChatMessage] = useState('');
  const sectionsRef = useRef([]);

  // Hero slides avec vraies images
  const heroSlides = [
    {
      image: 'https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?w=1600',
      title: 'Mécanique Automobile',
      subtitle: 'Réparation et entretien de tous véhicules',
      icon: <FaWrench />
    },
    {
      image: 'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?w=1600',
      title: 'Peinture & Carrosserie',
      subtitle: 'Redonnez vie à votre véhicule',
      icon: <FaPaintBrush />
    },
    {
      image: 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1600',
      title: 'Pneumatique',
      subtitle: 'Pneus neufs et vulcanisation',
      icon: <FaCog />
    },
    {
      image: 'https://images.unsplash.com/photo-1487754180451-c456f719a1fc?w=1600',
      title: 'Climatisation',
      subtitle: 'Roulez au frais toute l\'année',
      icon: <FaSnowflake />
    },
    {
      image: 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=1600',
      title: 'Auto-école',
      subtitle: 'Apprenez à conduire en toute sécurité',
      icon: <FaGraduationCap />
    },
    {
      image: 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=1600',
      title: 'Assurance Automobile',
      subtitle: 'Protégez votre véhicule',
      icon: <FaShieldAlt />
    }
  ];

  const domaines = [
    {
      id: 1,
      name: 'Mécanique',
      image: 'https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?w=800',
      description: 'Réparation moteur, boîte de vitesses, suspension',
      icon: <FaWrench />
    },
    {
      id: 2,
      name: 'Peinture',
      image: 'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?w=800',
      description: 'Carrosserie, débosselage, peinture complète',
      icon: <FaPaintBrush />
    },
    {
      id: 3,
      name: 'Pneumatique',
      image: 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800',
      description: 'Pneus, jantes, équilibrage, vulcanisation',
      icon: <FaCog />
    },
    {
      id: 4,
      name: 'Climatisation',
      image: 'https://images.unsplash.com/photo-1487754180451-c456f719a1fc?w=800',
      description: 'Recharge gaz, réparation système AC',
      icon: <FaSnowflake />
    },
    {
      id: 5,
      name: 'Auto-école',
      image: 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=800',
      description: 'Permis B, formation complète',
      icon: <FaGraduationCap />
    },
    {
      id: 6,
      name: 'Assurance',
      image: 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=800',
      description: 'Tous types d\'assurances auto',
      icon: <FaShieldAlt />
    }
  ];

  // Auto-slide carousel
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % heroSlides.length);
    }, 5000);
    return () => clearInterval(interval);
  }, []);

  // Fetch services récents
  useEffect(() => {
    fetchRecentServices();
    fetchPartners();
  }, []);

  const fetchRecentServices = async () => {
    try {
      const data = await publicApi.getServices();
      setServices(data.slice(0, 6));
    } catch (err) {
      console.error('Erreur chargement services:', err);
    }
  };

  const fetchPartners = async () => {
    try {
      const data = await publicApi.getEntreprises();
      setPartners(data.slice(0, 10));
    } catch (err) {
      console.error('Erreur chargement partenaires:', err);
    }
  };

  // Intersection Observer pour animations
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('animate-in');
          }
        });
      },
      { threshold: 0.1 }
    );

    sectionsRef.current.forEach((section) => {
      if (section) observer.observe(section);
    });

    return () => observer.disconnect();
  }, []);

  const handleChatSend = () => {
    alert(`Message envoyé : ${chatMessage}\n\n(L'IA sera disponible bientôt)`);
    setChatMessage('');
  };

  return (
    <div style={styles.container}>
      {/* Hero Carousel avec vraies images */}
      <div style={styles.heroSection}>
        {heroSlides.map((slide, index) => (
          <div
            key={index}
            style={{
              ...styles.slide,
              backgroundImage: `url(${slide.image})`,
              opacity: currentSlide === index ? 1 : 0,
              zIndex: currentSlide === index ? 1 : 0,
            }}
          >
            <div style={styles.slideOverlay} />
            <div style={styles.slideContent}>
              <div style={styles.slideIcon}>{slide.icon}</div>
              <h1 style={styles.slideTitle}>{slide.title}</h1>
              <p style={styles.slideSubtitle}>{slide.subtitle}</p>
              {!user && (
                <div style={styles.heroButtons}>
                  <Link to="/register" style={styles.primaryButton}>
                    Commencer maintenant <FaArrowRight style={{marginLeft: '0.5rem'}} />
                  </Link>
                  <Link to="/entreprises" style={styles.secondaryButton}>
                    Explorer les services
                  </Link>
                </div>
              )}
            </div>
          </div>
        ))}

        {/* Indicateurs */}
        <div style={styles.indicators}>
          {heroSlides.map((_, index) => (
            <button
              key={index}
              onClick={() => setCurrentSlide(index)}
              style={{
                ...styles.indicator,
                backgroundColor: currentSlide === index ? theme.colors.primary : 'rgba(255,255,255,0.5)'
              }}
            />
          ))}
        </div>
      </div>

      {/* Section Domaines avec vraies images */}
      <div 
        ref={el => sectionsRef.current[0] = el}
        className="animate-section"
        style={styles.section}
      >
        <h2 style={styles.sectionTitle}>Domaines d'Expertise</h2>
        <p style={styles.sectionSubtitle}>
          Plus de 20 catégories de services pour tous vos besoins automobiles
        </p>
        
        <div style={styles.domainesGrid}>
          {domaines.map((domaine) => (
            <Link
              key={domaine.id}
              to={`/entreprises?domaine=${domaine.id}`}
              style={styles.domaineCard}
              className="domaine-card"
            >
              <div 
                style={{
                  ...styles.domaineImage,
                  backgroundImage: `url(${domaine.image})`
                }}
              >
                <div style={styles.domaineOverlay}>
                  <div style={styles.domaineIcon}>{domaine.icon}</div>
                </div>
              </div>
              <div style={styles.domaineContent}>
                <h3 style={styles.domaineName}>{domaine.name}</h3>
                <p style={styles.domaineDescription}>{domaine.description}</p>
                <button style={styles.domaineButton}>
                  Voir plus <FaArrowRight style={{marginLeft: '0.5rem'}} />
                </button>
              </div>
            </Link>
          ))}
        </div>
      </div>

      {/* Section Services Récents */}
      <div 
        ref={el => sectionsRef.current[1] = el}
        className="animate-section"
        style={{...styles.section, backgroundColor: theme.colors.secondary}}
      >
        <h2 style={styles.sectionTitle}>Services Récents</h2>
        <p style={styles.sectionSubtitle}>
          Découvrez les derniers services ajoutés par nos prestataires
        </p>
        
        <div style={styles.servicesGrid}>
          {services.map((service) => (
            <div key={service.id} style={styles.serviceCard} className="service-card">
              {service.medias && service.medias.length > 0 ? (
                <div style={styles.serviceImage}>
                  <img 
                    src={`${import.meta.env.VITE_API_URL?.replace('/api', '')}/storage/${service.medias[0]?.replace(/^\/?storage\//, '')}`}
                    alt={service.name}
                    style={styles.serviceImg}
                  />
                </div>
              ) : (
                <div style={styles.servicePlaceholder}>
                  <FaWrench style={{fontSize: '3rem', color: theme.colors.primary}} />
                </div>
              )}
              <div style={styles.serviceContent}>
                <h3 style={styles.serviceName}>{service.name}</h3>
                <p style={styles.serviceEntreprise}>
                  {service.entreprise?.name || 'Entreprise'}
                </p>
                <div style={styles.servicePrice}>
                  {service.price ? `${service.price.toLocaleString()} FCFA` : 'Prix sur demande'}
                </div>
              </div>
            </div>
          ))}
        </div>
        
        <div style={styles.sectionCta}>
          <Link to="/services" style={styles.ctaButton}>
            Voir tous les services <FaArrowRight style={{marginLeft: '0.5rem'}} />
          </Link>
        </div>
      </div>

      {/* Section Partenaires avec logos/images défilants */}
      <div 
        ref={el => sectionsRef.current[2] = el}
        className="animate-section"
        style={styles.partnersSection}
      >
        <h2 style={styles.sectionTitle}>Nos Partenaires de Confiance</h2>
        <div style={styles.partnersTrack}>
          <div style={styles.partnersSlide} className="partners-scroll">
            {[...partners, ...partners].map((partner, index) => (
              <div key={index} style={styles.partnerCard}>
                {partner.logo ? (
                  <img 
                    src={`${import.meta.env.VITE_API_URL?.replace('/api', '')}/storage/${partner.logo?.replace(/^\/?storage\//, '')}`}
                    alt={partner.name}
                    style={styles.partnerImage}
                  />
                ) : (
                  <div style={styles.partnerPlaceholder}>
                    {partner.name.charAt(0)}
                  </div>
                )}
                <p style={styles.partnerName}>{partner.name}</p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* CTA Final */}
      <div 
        ref={el => sectionsRef.current[3] = el}
        className="animate-section"
        style={styles.ctaSection}
      >
        <h2 style={styles.ctaTitle}>Prêt à démarrer ?</h2>
        <p style={styles.ctaText}>
          Rejoignez des milliers de Béninois qui font confiance à CarEasy
        </p>
        {!user && (
          <Link to="/register" style={styles.ctaButtonLarge}>
            Créer un compte gratuitement
          </Link>
        )}
      </div>

      {/* Chatbot Flottant */}
      <div style={styles.chatbotContainer}>
        {showChatbot && (
          <div style={styles.chatbotModal}>
            <div style={styles.chatbotHeader}>
              <div style={styles.chatbotTitle}>
                <FaComments style={{marginRight: '0.5rem'}} />
                Assistant IA CarEasy
              </div>
              <button 
                onClick={() => setShowChatbot(false)}
                style={styles.chatbotClose}
              >
                <FaTimes />
              </button>
            </div>
            <div style={styles.chatbotBody}>
              <div style={styles.chatbotMessage}>
                Bonjour ! Je suis l'assistant IA de CarEasy. Comment puis-je vous aider aujourd'hui ?
              </div>
              <p style={styles.chatbotInfo}>
                (L'IA sera bientôt disponible - En développement)
              </p>
            </div>
            <div style={styles.chatbotFooter}>
              <input
                type="text"
                value={chatMessage}
                onChange={(e) => setChatMessage(e.target.value)}
                placeholder="Posez votre question..."
                style={styles.chatbotInput}
                onKeyPress={(e) => e.key === 'Enter' && handleChatSend()}
              />
              <button onClick={handleChatSend} style={styles.chatbotSend}>
                <FaPaperPlane />
              </button>
            </div>
          </div>
        )}
        
        <button 
          onClick={() => setShowChatbot(!showChatbot)}
          style={styles.chatbotButton}
          className="chatbot-pulse"
        >
          <FaComments style={{fontSize: '1.75rem'}} />
        </button>
      </div>

      {/* CSS Animations */}
      <style>{`
        .animate-section {
          opacity: 0;
          transform: translateY(50px);
          transition: all 0.8s ease-out;
        }
        
        .animate-section.animate-in {
          opacity: 1;
          transform: translateY(0);
        }
        
        .domaine-card {
          transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .domaine-card:hover {
          transform: translateY(-12px) scale(1.02);
          box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .service-card {
          transition: all 0.3s ease;
        }
        
        .service-card:hover {
          transform: translateY(-8px);
          box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        @keyframes scroll {
          0% { transform: translateX(0); }
          100% { transform: translateX(-50%); }
        }
        
        .partners-scroll {
          animation: scroll 30s linear infinite;
        }
        
        .partners-scroll:hover {
          animation-play-state: paused;
        }
        
        @keyframes pulse {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.1); }
        }
        
        .chatbot-pulse {
          animation: pulse 2s ease-in-out infinite;
        }
      `}</style>
    </div>
  );
}

const styles = {
  container: {
    backgroundColor: theme.colors.background,
  },
  
  // Hero avec vraies images
  heroSection: {
    position: 'relative',
    height: '100vh',
    overflow: 'hidden',
  },
  slide: {
    position: 'absolute',
    top: 0,
    left: 0,
    width: '100%',
    height: '100%',
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    transition: 'opacity 1s ease-in-out',
  },
  slideOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    width: '100%',
    height: '100%',
    background: 'linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6))',
  },
  slideContent: {
    position: 'absolute',
    top: '50%',
    left: '50%',
    transform: 'translate(-50%, -50%)',
    textAlign: 'center',
    zIndex: 2,
    width: '90%',
    maxWidth: '900px',
  },
  slideIcon: {
    fontSize: '5rem',
    color: theme.colors.primary,
    marginBottom: '1.5rem',
    filter: 'drop-shadow(0 4px 6px rgba(0,0,0,0.3))',
  },
  slideTitle: {
    fontSize: 'clamp(2.5rem, 6vw, 4rem)',
    fontWeight: 'bold',
    color: '#fff',
    marginBottom: '1rem',
    textShadow: '2px 2px 8px rgba(0,0,0,0.5)',
  },
  slideSubtitle: {
    fontSize: 'clamp(1.25rem, 3vw, 1.75rem)',
    color: '#fff',
    marginBottom: '2.5rem',
    textShadow: '1px 1px 4px rgba(0,0,0,0.5)',
  },
  heroButtons: {
    display: 'flex',
    gap: '1.5rem',
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    padding: '1.25rem 3rem',
    borderRadius: theme.borderRadius.xl,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.125rem',
    display: 'inline-flex',
    alignItems: 'center',
    boxShadow: '0 10px 30px rgba(0,0,0,0.3)',
    transition: 'all 0.3s',
    border: 'none',
  },
  secondaryButton: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    backdropFilter: 'blur(10px)',
    color: '#fff',
    padding: '1.25rem 3rem',
    borderRadius: theme.borderRadius.xl,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.125rem',
    border: '2px solid #fff',
    transition: 'all 0.3s',
  },
  indicators: {
    position: 'absolute',
    bottom: '40px',
    left: '50%',
    transform: 'translateX(-50%)',
    display: 'flex',
    gap: '12px',
    zIndex: 3,
  },
  indicator: {
    width: '14px',
    height: '14px',
    borderRadius: '50%',
    border: '2px solid #fff',
    cursor: 'pointer',
    transition: 'all 0.3s',
    background: 'transparent',
  },
  
  // Sections
  section: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '6rem 2rem',
  },
  sectionTitle: {
    fontSize: 'clamp(2rem, 5vw, 3rem)',
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: '1rem',
    color: theme.colors.text.primary,
  },
  sectionSubtitle: {
    fontSize: '1.25rem',
    textAlign: 'center',
    color: theme.colors.text.secondary,
    marginBottom: '4rem',
    maxWidth: '700px',
    margin: '0 auto 4rem',
  },
  
  // Domaines avec vraies images
  domainesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))',
    gap: '2.5rem',
  },
  domaineCard: {
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    overflow: 'hidden',
    textDecoration: 'none',
    boxShadow: theme.shadows.lg,
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  domaineImage: {
    position: 'relative',
    height: '200px',
    backgroundSize: 'cover',
    backgroundPosition: 'center',
  },
  domaineOverlay: {
    width: '100%',
    height: '100%',
    background: `linear-gradient(135deg, ${theme.colors.primary}40, ${theme.colors.primary}80)`,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  domaineIcon: {
    fontSize: '4rem',
    color: '#fff',
    filter: 'drop-shadow(0 4px 6px rgba(0,0,0,0.2))',
  },
  domaineContent: {
    padding: '2rem',
  },
  domaineName: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.75rem',
  },
  domaineDescription: {
    color: theme.colors.text.secondary,
    marginBottom: '1.5rem',
    lineHeight: '1.6',
  },
  domaineButton: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    padding: '0.875rem 2rem',
    borderRadius: theme.borderRadius.lg,
    border: 'none',
    fontWeight: '600',
    cursor: 'pointer',
    display: 'inline-flex',
    alignItems: 'center',
    transition: 'all 0.3s',
  },
  
  // Services
  servicesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
    gap: '2rem',
    marginBottom: '3rem',
  },
  serviceCard: {
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.xl,
    overflow: 'hidden',
    boxShadow: theme.shadows.md,
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  serviceImage: {
    height: '180px',
    overflow: 'hidden',
  },
  serviceImg: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  servicePlaceholder: {
    height: '180px',
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  serviceContent: {
    padding: '1.5rem',
  },
  serviceName: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  serviceEntreprise: {
    color: theme.colors.text.secondary,
    marginBottom: '0.75rem',
    fontSize: '0.95rem',
  },
  servicePrice: {
    color: theme.colors.primary,
    fontWeight: '700',
    fontSize: '1.125rem',
  },
  sectionCta: {
    textAlign: 'center',
  },
  ctaButton: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    padding: '1rem 2.5rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    display: 'inline-flex',
    alignItems: 'center',
    boxShadow: theme.shadows.lg,
  },
  
  // Partenaires avec images défilantes
  partnersSection: {
    padding: '4rem 0',
    backgroundColor: theme.colors.secondary,
    overflow: 'hidden',
  },
  partnersTrack: {
    overflow: 'hidden',
  },
  partnersSlide: {
    display: 'flex',
    gap: '3rem',
    width: 'max-content',
  },
  partnerCard: {
    backgroundColor: theme.colors.background,
    padding: '2rem',
    borderRadius: theme.borderRadius.lg,
    textAlign: 'center',
    minWidth: '180px',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  partnerImage: {
    width: '80px',
    height: '80px',
    margin: '0 auto 1rem',
    borderRadius: '50%',
    objectFit: 'cover',
  },
  partnerPlaceholder: {
    width: '80px',
    height: '80px',
    margin: '0 auto 1rem',
    borderRadius: '50%',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '2rem',
    fontWeight: 'bold',
  },
  partnerName: {
    fontWeight: '600',
    color: theme.colors.text.primary,
  },
  
  // CTA
  ctaSection: {
    padding: '6rem 2rem',
    textAlign: 'center',
    background: `linear-gradient(135deg, ${theme.colors.primary}, #991b1b)`,
  },
  ctaTitle: {
    fontSize: 'clamp(2rem, 5vw, 3rem)',
    fontWeight: 'bold',
    color: '#fff',
    marginBottom: '1rem',
  },
  ctaText: {
    fontSize: '1.25rem',
    color: 'rgba(255,255,255,0.9)',
    marginBottom: '2.5rem',
  },
  ctaButtonLarge: {
    backgroundColor: '#fff',
    color: theme.colors.primary,
    padding: '1.25rem 3rem',
    borderRadius: theme.borderRadius.xl,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.25rem',
    display: 'inline-block',
    boxShadow: '0 10px 30px rgba(0,0,0,0.3)',
  },
  
  // Chatbot
  chatbotContainer: {
    position: 'fixed',
    bottom: '30px',
    right: '30px',
    zIndex: 1000,
  },
  chatbotModal: {
    position: 'absolute',
    bottom: '80px',
    right: 0,
    width: '350px',
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    boxShadow: '0 10px 40px rgba(0,0,0,0.2)',
    border: `2px solid ${theme.colors.primaryLight}`,
    overflow: 'hidden',
  },
  chatbotHeader: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    padding: '1rem 1.5rem',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  chatbotTitle: {
    fontWeight: '600',
    display: 'flex',
    alignItems: 'center',
  },
  chatbotClose: {
    background: 'none',
    border: 'none',
    color: '#fff',
    fontSize: '1.25rem',
    cursor: 'pointer',
  },
  chatbotBody: {
    padding: '1.5rem',
    minHeight: '200px',
  },
  chatbotMessage: {
    backgroundColor: theme.colors.background,
    padding: '1rem',
    borderRadius: theme.borderRadius.md,
    marginBottom: '1rem',
  },
   chatbotInfo: {
    color: theme.colors.text.secondary,
    fontSize: '0.85rem',
    textAlign: 'center',
    marginTop: '1rem',
    fontStyle: 'italic',
    padding: '0.5rem',
    background: 'rgba(0, 0, 0, 0.05)',
    borderRadius: theme.borderRadius.md,
    border: `1px solid ${theme.colors.primaryLight}20`,
  },

  chatbotFooter: {
    padding: '1.25rem',
    borderTop: `2px solid ${theme.colors.primaryLight}`,
    display: 'flex',
    gap: '0.75rem',
    alignItems: 'center',
    background: theme.colors.background,
  },

  chatbotInput: {
    flex: 1,
    padding: '1rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: `2px solid ${theme.colors.primaryLight}`,
    fontSize: '1rem',
    fontFamily: 'inherit',
    transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
    background: theme.colors.secondary,
    color: theme.colors.text.primary,
    minHeight: '52px',
  },

  chatbotSend: {
    background: `linear-gradient(135deg, ${theme.colors.primary}, ${theme.colors.primaryDark || '#991b1b'})`,
    color: '#fff',
    border: 'none',
    borderRadius: theme.borderRadius.lg,
    width: '52px',
    height: '52px',
    minWidth: '52px',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
    fontSize: '1.25rem',
    boxShadow: '0 4px 15px rgba(0, 0, 0, 0.2)',
  },

  chatbotButton: {
    background: `linear-gradient(135deg, ${theme.colors.primary}, #991b1b)`,
    color: '#fff',
    border: `2px solid rgba(255, 255, 255, 0.2)`,
    borderRadius: '50%',
    width: '70px',
    height: '70px',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    boxShadow: '0 10px 35px rgba(0, 0, 0, 0.3)',
    transition: 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)',
    fontSize: '1.75rem',
    position: 'relative',
    overflow: 'hidden',
    zIndex: 1001,
  },

  chatbotNotification: {
    position: 'absolute',
    top: '-5px',
    right: '-5px',
    background: '#ef4444',
    color: '#fff',
    width: '25px',
    height: '25px',
    borderRadius: '50%',
    fontSize: '0.75rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontWeight: 'bold',
    border: `2px solid ${theme.colors.secondary}`,
    boxShadow: '0 3px 10px rgba(0, 0, 0, 0.3)',
  },

  typingIndicator: {
    display: 'flex',
    alignItems: 'center',
    gap: '6px',
    padding: '1rem 1.25rem',
    background: theme.colors.background,
    borderRadius: theme.borderRadius.lg,
    marginBottom: '1rem',
    width: 'fit-content',
    border: `1px solid ${theme.colors.primaryLight}40`,
    boxShadow: '0 2px 8px rgba(0, 0, 0, 0.1)',
  },

  typingDot: {
    width: '10px',
    height: '10px',
    background: theme.colors.primary,
    borderRadius: '50%',
    opacity: 0.8,
  },

  chatbotMessageUser: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    padding: '1rem 1.25rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '1rem',
    marginLeft: 'auto',
    maxWidth: '80%',
    border: `1px solid ${theme.colors.primaryDark || '#991b1b'}`,
    boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
  },

  chatbotMessageBot: {
    backgroundColor: theme.colors.background,
    color: theme.colors.text.primary,
    padding: '1rem 1.25rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '1rem',
    marginRight: 'auto',
    maxWidth: '80%',
    border: `1px solid ${theme.colors.primaryLight}40`,
    boxShadow: '0 4px 12px rgba(0, 0, 0, 0.1)',
  },

  chatbotMessagesContainer: {
    height: '300px',
    overflowY: 'auto',
    padding: '1.5rem',
    scrollBehavior: 'smooth',
  },

  chatbotWelcome: {
    textAlign: 'center',
    padding: '2rem 1rem',
    color: theme.colors.text.secondary,
  },

  chatbotWelcomeIcon: {
    fontSize: '3rem',
    color: theme.colors.primary,
    marginBottom: '1rem',
    opacity: 0.8,
  },

  chatbotWelcomeTitle: {
    fontSize: '1.25rem',
    fontWeight: '600',
    marginBottom: '0.5rem',
    color: theme.colors.text.primary,
  },

  chatbotWelcomeText: {
    fontSize: '0.95rem',
    lineHeight: '1.6',
    maxWidth: '280px',
    margin: '0 auto',
  },

  chatbotStatus: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontSize: '0.85rem',
    color: theme.colors.text.secondary,
    marginTop: '0.75rem',
  },

  chatbotStatusDot: {
    width: '8px',
    height: '8px',
    borderRadius: '50%',
    background: '#10b981',
  },
}