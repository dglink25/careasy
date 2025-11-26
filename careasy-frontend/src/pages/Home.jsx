import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Logo from '../components/Logo';
import theme from '../config/theme';

export default function Home() {
  const { user } = useAuth();
  const [currentSlide, setCurrentSlide] = useState(0);
  const [isVisible, setIsVisible] = useState({});

  // Images du carousel (remplace par tes vraies images)
  const heroSlides = [
    {
      image: '/images/hero/h.jpeg',
      title: 'Trouvez le bon mécanicien en quelques clics',
      subtitle: 'Plus de 10 000 professionnels certifiés à votre service'
    },
    {
      image: '/images/hero/h1.jpeg',
      title: 'Diagnostic intelligent par IA',
      subtitle: 'Identifiez votre panne et trouvez la solution rapidement'
    },
    {
      image: '/images/hero/h2.web',
      title: 'Prenez rendez-vous en ligne',
      subtitle: 'Gagnez du temps avec notre système de réservation'
    }
  ];

  // Auto-slide du carousel toutes les 5 secondes
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % heroSlides.length);
    }, 5000);
    return () => clearInterval(interval);
  }, []);

  // Animation au scroll
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setIsVisible((prev) => ({ ...prev, [entry.target.id]: true }));
          }
        });
      },
      { threshold: 0.1 }
    );

    document.querySelectorAll('.animate-section').forEach((section) => {
      observer.observe(section);
    });

    return () => observer.disconnect();
  }, []);

  const nextSlide = () => {
    setCurrentSlide((prev) => (prev + 1) % heroSlides.length);
  };

  const prevSlide = () => {
    setCurrentSlide((prev) => (prev - 1 + heroSlides.length) % heroSlides.length);
  };

  const goToSlide = (index) => {
    setCurrentSlide(index);
  };

  const services = [
    { icon: '🔧', title: 'Mécanique', desc: 'Diesel & Essence' },
    { icon: '🎨', title: 'Peinture', desc: 'Carrosserie' },
    { icon: '⚙️', title: 'Vulcanisation', desc: 'Pneus & Jantes' },
    { icon: '❄️', title: 'Frigoriste', desc: 'Climatisation' },
    { icon: '🚗', title: 'Auto-école', desc: 'Formation' },
    { icon: '🛡️', title: 'Assurance', desc: 'Protection' }
  ];

  const team = [
    { name: 'HOUNDOKINNOU Diègue', role: 'Co-fondateur & Développeur', image: '/images/team/diegue.jpeg' },
    { name: 'LOGBO Maurel Oswald', role: 'Co-fondateur & Développeur', image: '/images/team/maurel.jpeg' }
  ];

  const partners = [
    { name: 'UNSTIM', logo: '🎓' },
    { name: 'INSTI Lokossa', logo: '🏛️' },
    { name: 'Artisans Béninois', logo: '🔨' },
    { name: 'Auto Pro', logo: '🚙' }
  ];

  return (
    <div style={styles.container}>
      {/* Hero Section avec Carousel */}
      <div style={styles.heroSection}>
        <div style={styles.carouselContainer}>
          {heroSlides.map((slide, index) => (
            <div
              key={index}
              style={{
                ...styles.slide,
                opacity: currentSlide === index ? 1 : 0,
                transform: currentSlide === index ? 'scale(1)' : 'scale(1.1)',
              }}
            >
              <div style={styles.slideOverlay} />
              <img src={slide.image} alt={slide.title} style={styles.slideImage} />
              <div style={styles.slideContent}>
                <div style={styles.logoContainer}>
                  <Logo size="lg" showText={false} />
                </div>
                <h1 style={styles.slideTitle}>{slide.title}</h1>
                <p style={styles.slideSubtitle}>{slide.subtitle}</p>
                {!user ? (
                  <div style={styles.heroButtons}>
                    <Link to="/register" style={styles.primaryButton}>
                      Commencer maintenant
                    </Link>
                    <Link to="/login" style={styles.secondaryButton}>
                      Se connecter
                    </Link>
                  </div>
                ) : (
                  <Link to="/dashboard" style={styles.primaryButton}>
                    Accéder au Dashboard
                  </Link>
                )}
              </div>
            </div>
          ))}

          {/* Navigation du carousel */}
          <button onClick={prevSlide} style={{...styles.carouselButton, left: '20px'}}>
            ‹
          </button>
          <button onClick={nextSlide} style={{...styles.carouselButton, right: '20px'}}>
            ›
          </button>

          {/* Indicateurs */}
          <div style={styles.indicators}>
            {heroSlides.map((_, index) => (
              <button
                key={index}
                onClick={() => goToSlide(index)}
                style={{
                  ...styles.indicator,
                  backgroundColor: currentSlide === index ? theme.colors.primary : 'rgba(255,255,255,0.5)'
                }}
              />
            ))}
          </div>
        </div>
      </div>

      {/* Services Section */}
      <div id="services" className="animate-section" style={{
        ...styles.section,
        opacity: isVisible.services ? 1 : 0,
        transform: isVisible.services ? 'translateY(0)' : 'translateY(50px)',
        transition: 'all 0.8s ease-out'
      }}>
        <h2 style={styles.sectionTitle}>Nos Services</h2>
        <p style={styles.sectionSubtitle}>15+ catégories de services automobiles</p>
        <div style={styles.servicesGrid}>
          {services.map((service, index) => (
            <div
              key={index}
              className="service-card"
              style={{
                ...styles.serviceCard,
                animationDelay: `${index * 0.1}s`
              }}
            >
              <div style={styles.serviceIcon}>{service.icon}</div>
              <h3 style={styles.serviceTitle}>{service.title}</h3>
              <p style={styles.serviceDesc}>{service.desc}</p>
            </div>
          ))}
        </div>
      </div>

      {/* À Propos Section */}
      <div id="about" className="animate-section" style={{
        ...styles.aboutSection,
        opacity: isVisible.about ? 1 : 0,
        transform: isVisible.about ? 'translateY(0)' : 'translateY(50px)',
        transition: 'all 0.8s ease-out'
      }}>
        <div style={styles.aboutContent}>
          <div style={styles.aboutText}>
            <h2 style={styles.sectionTitle}>À Propos de CarEasy</h2>
            <p style={styles.aboutParagraph}>
              CarEasy est né d'une vision : <strong>moderniser le secteur automobile béninois</strong>. 
              Avec plus de 90% des artisans opérant sans solution numérique, nous créons le pont 
              entre tradition et innovation.
            </p>
            <p style={styles.aboutParagraph}>
              Notre plateforme centralise plus de <strong>15 catégories de services</strong>, intègre 
              l'intelligence artificielle pour le diagnostic de pannes, et offre une expérience 
              utilisateur optimale pour tous les Béninois.
            </p>
            <div style={styles.statsGrid}>
              <div style={styles.statBox}>
                <div style={styles.statNumber}>10,000+</div>
                <div style={styles.statLabel}>Artisans</div>
              </div>
              <div style={styles.statBox}>
                <div style={styles.statNumber}>15+</div>
                <div style={styles.statLabel}>Catégories</div>
              </div>
              <div style={styles.statBox}>
                <div style={styles.statNumber}>100%</div>
                <div style={styles.statLabel}>Gratuit</div>
              </div>
            </div>
          </div>
          <div style={styles.aboutImage}>
            <div style={styles.imageCard}>🚗</div>
          </div>
        </div>
      </div>

      {/* Notre Équipe */}
      <div id="team" className="animate-section" style={{
        ...styles.section,
        opacity: isVisible.team ? 1 : 0,
        transform: isVisible.team ? 'translateY(0)' : 'translateY(50px)',
        transition: 'all 0.8s ease-out'
      }}>
        <h2 style={styles.sectionTitle}>Notre Équipe</h2>
        <p style={styles.sectionSubtitle}>Les créateurs de CarEasy</p>
        <div style={styles.teamGrid}>
          {team.map((member, index) => (
            <div key={index} className="team-card" style={styles.teamCard}>
              <div style={styles.teamImageWrapper}>
                <img src={member.image} alt={member.name} style={styles.teamImage} />
              </div>
              <h3 style={styles.teamName}>{member.name}</h3>
              <p style={styles.teamRole}>{member.role}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Nos Partenaires */}
      <div id="partners" className="animate-section" style={{
        ...styles.partnersSection,
        opacity: isVisible.partners ? 1 : 0,
        transform: isVisible.partners ? 'translateY(0)' : 'translateY(50px)',
        transition: 'all 0.8s ease-out'
      }}>
        <h2 style={styles.sectionTitle}>Nos Partenaires</h2>
        <div style={styles.partnersGrid}>
          {partners.map((partner, index) => (
            <div key={index} className="partner-card" style={styles.partnerCard}>
              <div style={styles.partnerLogo}>{partner.logo}</div>
              <p style={styles.partnerName}>{partner.name}</p>
            </div>
          ))}
        </div>
      </div>

      {/* CTA Section */}
      <div id="cta" className="animate-section" style={{
        ...styles.ctaSection,
        opacity: isVisible.cta ? 1 : 0,
        transform: isVisible.cta ? 'scale(1)' : 'scale(0.9)',
        transition: 'all 0.8s ease-out'
      }}>
        <h2 style={styles.ctaTitle}>Prêt à simplifier votre expérience automobile ?</h2>
        <p style={styles.ctaText}>Rejoignez des milliers de Béninois qui font confiance à CarEasy</p>
        {!user && (
          <Link to="/register" style={styles.ctaButton}>
            Créer un compte gratuitement
          </Link>
        )}
      </div>

      {/* Footer */}
      <footer style={styles.footer}>
        <div style={styles.footerContent}>
          <div style={styles.footerSection}>
            <div style={styles.footerLogo}>
              <Logo size="md" showText={true} />
            </div>
            <p style={styles.footerDesc}>
              La plateforme #1 pour tous vos besoins automobiles au Bénin
            </p>
          </div>

          <div style={styles.footerSection}>
            <h4 style={styles.footerTitle}>Liens Rapides</h4>
            <ul style={styles.footerLinks}>
              <li><a href="#services" style={styles.footerLink}>Nos Services</a></li>
              <li><a href="#about" style={styles.footerLink}>À Propos</a></li>
              <li><a href="#team" style={styles.footerLink}>Notre Équipe</a></li>
              <li><a href="#partners" style={styles.footerLink}>Partenaires</a></li>
            </ul>
          </div>

          <div style={styles.footerSection}>
            <h4 style={styles.footerTitle}>Contact</h4>
            <ul style={styles.footerLinks}>
              <li style={styles.contactItem}>📍 CarEasy, Bénin</li>
              <li style={styles.contactItem}>📧 contact@careasy.bj</li>
              <li style={styles.contactItem}>📱 +229 XX XX XX XX</li>
            </ul>
          </div>

          <div style={styles.footerSection}>
            <h4 style={styles.footerTitle}>Suivez-nous</h4>
            <div style={styles.socialLinks}>
              <a href="#" style={styles.socialIcon}>📘</a>
              <a href="#" style={styles.socialIcon}>📷</a>
              <a href="#" style={styles.socialIcon}>🐦</a>
              <a href="#" style={styles.socialIcon}>💼</a>
            </div>
          </div>
        </div>

        <div style={styles.footerBottom}>
          <p style={styles.copyright}>
            © 2025 CarEasy - Tous droits réservés | Développé avec ❤️ au Bénin
          </p>
          <div style={styles.footerBottomLinks}>
            <a href="#" style={styles.footerBottomLink}>Politique de confidentialité</a>
            <span style={styles.separator}>•</span>
            <a href="#" style={styles.footerBottomLink}>Conditions d'utilisation</a>
          </div>
        </div>
      </footer>

      {/* CSS pour les animations */}
      <style>{`
        @keyframes slideUp {
          from {
            opacity: 0;
            transform: translateY(30px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        
        .service-card {
          animation: slideUp 0.6s ease-out forwards;
        }
        
        .service-card:hover,
        .team-card:hover,
        .partner-card:hover {
          transform: translateY(-10px);
          box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        ${styles.carouselButton}:hover {
          background-color: rgba(255,255,255,0.5) !important;
        }
        
        a[style*="footerLink"]:hover,
        a[style*="footerBottomLink"]:hover {
          color: ${theme.colors.primary} !important;
        }
        
        a[style*="socialIcon"]:hover {
          transform: scale(1.2);
        }
      `}</style>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: theme.colors.background,
  },
  
  // Hero Carousel
  heroSection: {
    position: 'relative',
    height: '100vh',
    overflow: 'hidden',
  },
  carouselContainer: {
    position: 'relative',
    width: '100%',
    height: '100%',
  },
  slide: {
    position: 'absolute',
    top: 0,
    left: 0,
    width: '100%',
    height: '100%',
    transition: 'opacity 1s ease-in-out, transform 1s ease-in-out',
  },
  slideImage: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  slideOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    width: '100%',
    height: '100%',
    background: 'linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7))',
    zIndex: 1,
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
  logoContainer: {
    marginBottom: '2rem',
  },
  slideTitle: {
    fontSize: 'clamp(2rem, 5vw, 3.5rem)',
    fontWeight: 'bold',
    color: '#fff',
    marginBottom: '1rem',
    textShadow: '2px 2px 4px rgba(0,0,0,0.5)',
  },
  slideSubtitle: {
    fontSize: 'clamp(1rem, 3vw, 1.5rem)',
    color: '#fff',
    marginBottom: '2rem',
    textShadow: '1px 1px 2px rgba(0,0,0,0.5)',
  },
  heroButtons: {
    display: 'flex',
    gap: '1rem',
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '1rem 2.5rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.125rem',
    display: 'inline-block',
    boxShadow: theme.shadows.lg,
    transition: 'all 0.3s',
    cursor: 'pointer',
  },
  secondaryButton: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    backdropFilter: 'blur(10px)',
    color: '#fff',
    padding: '1rem 2.5rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.125rem',
    border: '2px solid #fff',
    display: 'inline-block',
    transition: 'all 0.3s',
    cursor: 'pointer',
  },
  carouselButton: {
    position: 'absolute',
    top: '50%',
    transform: 'translateY(-50%)',
    backgroundColor: 'rgba(255,255,255,0.3)',
    backdropFilter: 'blur(10px)',
    border: 'none',
    color: '#fff',
    fontSize: '3rem',
    width: '60px',
    height: '60px',
    borderRadius: '50%',
    cursor: 'pointer',
    zIndex: 3,
    transition: 'all 0.3s',
  },
  indicators: {
    position: 'absolute',
    bottom: '30px',
    left: '50%',
    transform: 'translateX(-50%)',
    display: 'flex',
    gap: '10px',
    zIndex: 3,
  },
  indicator: {
    width: '12px',
    height: '12px',
    borderRadius: '50%',
    border: '2px solid #fff',
    cursor: 'pointer',
    transition: 'all 0.3s',
  },

  // Sections
  section: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '5rem 2rem',
  },
  sectionTitle: {
    fontSize: 'clamp(2rem, 4vw, 3rem)',
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: '1rem',
    color: theme.colors.text.primary,
  },
  sectionSubtitle: {
    fontSize: '1.125rem',
    textAlign: 'center',
    color: theme.colors.text.secondary,
    marginBottom: '3rem',
  },

  // Services
  servicesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '2rem',
  },
  serviceCard: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.xl,
    textAlign: 'center',
    border: `2px solid ${theme.colors.primaryLight}`,
    transition: 'all 0.3s',
    cursor: 'pointer',
  },
  serviceIcon: {
    fontSize: '3.5rem',
    marginBottom: '1rem',
  },
  serviceTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '0.5rem',
  },
  serviceDesc: {
    color: theme.colors.text.secondary,
  },

  // À Propos
  aboutSection: {
    backgroundColor: theme.colors.secondary,
    padding: '5rem 2rem',
  },
  aboutContent: {
    maxWidth: '1200px',
    margin: '0 auto',
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
    gap: '4rem',
    alignItems: 'center',
  },
  aboutText: {
    flex: 1,
  },
  aboutParagraph: {
    fontSize: '1.125rem',
    lineHeight: '1.8',
    color: theme.colors.text.secondary,
    marginBottom: '1.5rem',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(3, 1fr)',
    gap: '2rem',
    marginTop: '3rem',
  },
  statBox: {
    textAlign: 'center',
  },
  statNumber: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '0.5rem',
  },
  statLabel: {
    color: theme.colors.text.secondary,
    fontWeight: '600',
  },
  aboutImage: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },
  imageCard: {
    width: '300px',
    height: '300px',
    backgroundColor: theme.colors.primaryLight,
    borderRadius: theme.borderRadius.xl,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '8rem',
    boxShadow: theme.shadows.xl,
  },

  // Équipe
  teamGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
    gap: '3rem',
    maxWidth: '800px',
    margin: '0 auto',
  },
  teamCard: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.xl,
    textAlign: 'center',
    border: `2px solid ${theme.colors.primaryLight}`,
    transition: 'all 0.3s',
  },
  teamImageWrapper: {
    width: '150px',
    height: '150px',
    margin: '0 auto 1.5rem',
    borderRadius: '50%',
    overflow: 'hidden',
    border: `4px solid ${theme.colors.primary}`,
  },
  teamImage: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  teamName: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  teamRole: {
    color: theme.colors.primary,
    fontWeight: '600',
  },

  // Partenaires
  partnersSection: {
    backgroundColor: theme.colors.secondary,
    padding: '5rem 2rem',
  },
  partnersGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: '2rem',
    maxWidth: '1000px',
    margin: '0 auto',
  },
  partnerCard: {
    backgroundColor: '#fff',
    padding: '2rem',
    borderRadius: theme.borderRadius.lg,
    textAlign: 'center',
    transition: 'all 0.3s',
    border: `1px solid ${theme.colors.primaryLight}`,
  },
  partnerLogo: {
    fontSize: '4rem',
    marginBottom: '1rem',
  },
  partnerName: {
    fontWeight: '600',
    color: theme.colors.text.primary,
  },

  // CTA
  ctaSection: {
    backgroundColor: theme.colors.primary,
    padding: '5rem 2rem',
    textAlign: 'center',
  },
  ctaTitle: {
    fontSize: 'clamp(1.5rem, 4vw, 2.5rem)',
    fontWeight: 'bold',
    color: '#fff',
    marginBottom: '1rem',
  },
  ctaText: {
    fontSize: '1.25rem',
    color: 'rgba(255,255,255,0.9)',
    marginBottom: '2rem',
  },
  ctaButton: {
    backgroundColor: theme.colors.secondary,
    color: theme.colors.primary,
    padding: '1rem 3rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '1.25rem',
    display: 'inline-block',
    boxShadow: theme.shadows.xl,
    transition: 'all 0.3s',
  },

  // Footer
  footer: {
    backgroundColor: '#1a1a1a',
    color: '#fff',
    padding: '4rem 2rem 2rem',
  },
  footerContent: {
    maxWidth: '1200px',
    margin: '0 auto',
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '3rem',
    marginBottom: '3rem',
  },
  footerSection: {
    flex: 1,
  },
  footerLogo: {
    marginBottom: '1rem',
  },
  footerDesc: {
    color: 'rgba(255,255,255,0.7)',
    lineHeight: '1.6',
  },
  footerTitle: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    marginBottom: '1.5rem',
    color: theme.colors.primary,
  },
  footerLinks: {
    listStyle: 'none',
    padding: 0,
    margin: 0,
  },
  footerLink: {
    color: 'rgba(255,255,255,0.7)',
    textDecoration: 'none',
    display: 'block',
    padding: '0.5rem 0',
    transition: 'all 0.3s',
  },
  contactItem: {
    color: 'rgba(255,255,255,0.7)',
    padding: '0.5rem 0',
  },
  socialLinks: {
    display: 'flex',
    gap: '1rem',
  },
  socialIcon: {
    fontSize: '2rem',
    transition: 'all 0.3s',
    cursor: 'pointer',
  },
  footerBottom: {
    maxWidth: '1200px',
    margin: '0 auto',
    paddingTop: '2rem',
    borderTop: '1px solid rgba(255,255,255,0.1)',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  copyright: {
    color: 'rgba(255,255,255,0.5)',
    fontSize: '0.9rem',
  },
  footerBottomLinks: {
    display: 'flex',
    gap: '1rem',
    alignItems: 'center',
  },
  footerBottomLink: {
    color: 'rgba(255,255,255,0.5)',
    textDecoration: 'none',
    fontSize: '0.9rem',
    transition: 'all 0.3s',
  },
  separator: {
    color: 'rgba(255,255,255,0.3)',
  },
};