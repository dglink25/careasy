// careasy-frontend/src/components/Navbar.jsx - VERSION ULTRA PROFESSIONNELLE
import { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Logo from './Logo';
import theme from '../config/theme';
import { 
  FaHome, 
  FaBuilding, 
  FaTools, 
  FaSearch, 
  FaUser, 
  FaSignOutAlt,
  FaBars,
  FaTimes,
  FaWrench,
  FaPaintBrush,
  FaCog,
  FaSnowflake,
  FaGraduationCap,
  FaShieldAlt,
  FaChevronDown
} from 'react-icons/fa';

// 👉 AJOUT
import { FiMessageSquare } from 'react-icons/fi';

export default function Navbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [scrolled, setScrolled] = useState(false);
  const [showServicesDropdown, setShowServicesDropdown] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  // Services disponibles
  const servicesCategories = [
    { icon: <FaWrench />, name: 'Mécanique', path: '/services?type=mecanique' },
    { icon: <FaPaintBrush />, name: 'Peinture & Carrosserie', path: '/services?type=peinture' },
    { icon: <FaCog />, name: 'Pneumatique', path: '/services?type=pneumatique' },
    { icon: <FaSnowflake />, name: 'Climatisation', path: '/services?type=climatisation' },
    { icon: <FaGraduationCap />, name: 'Auto-école', path: '/services?type=autoecole' },
    { icon: <FaShieldAlt />, name: 'Assurance', path: '/services?type=assurance' },
  ];

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const handleLogout = async () => {
    await logout();
    navigate('/');
    setMobileMenuOpen(false);
  };

  const isActive = (path) => location.pathname === path;

  const closeMobileMenu = () => setMobileMenuOpen(false);

  return (
    <>
      <nav style={{
        ...styles.nav,
        ...(scrolled ? styles.navScrolled : {})
      }}>
        <div style={styles.container}>
          {/* Logo */}
          <Logo size="md" showText={true} />
          
          {/* Desktop Menu */}
          <div style={styles.desktopMenu}>
            {user ? (
              <>
                {/* Menu Admin */}
                {user.role === 'admin' ? (
                  <>
                    <Link 
                      to="/admin/dashboard" 
                      style={{
                        ...styles.link,
                        ...(isActive('/admin/dashboard') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FaUser style={styles.icon} />
                      Dashboard
                    </Link>
                    <Link 
                      to="/admin/entreprises" 
                      style={{
                        ...styles.link,
                        ...(isActive('/admin/entreprises') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FaBuilding style={styles.icon} />
                      Entreprises
                    </Link>

                    {/* 👉 NOUVEAU LIEN MESSAGES */}
                    <Link 
                      to="/messages" 
                      style={{
                        ...styles.link,
                        ...(isActive('/messages') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FiMessageSquare style={styles.icon} />
                      Messages
                      {/* Badge optionnel pour non lus */}
                      {/* <span style={styles.messageBadge}>3</span> */}
                    </Link>

                    <Link 
                      to="/entreprises" 
                      style={{
                        ...styles.link,
                        ...(isActive('/entreprises') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FaSearch style={styles.icon} />
                      Site Public
                    </Link>
                  </>
                ) : (
                  /* Menu Prestataire */
                  <>
                    <Link 
                      to="/dashboard" 
                      style={{
                        ...styles.link,
                        ...(isActive('/dashboard') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FaHome style={styles.icon} />
                      Dashboard
                    </Link>
                    <Link 
                      to="/mes-entreprises" 
                      style={{
                        ...styles.link,
                        ...(isActive('/mes-entreprises') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FaBuilding style={styles.icon} />
                      Mes Entreprises
                    </Link>
                    <Link 
                      to="/mes-services" 
                      style={{
                        ...styles.link,
                        ...(isActive('/mes-services') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FaTools style={styles.icon} />
                      Mes Services
                    </Link>

                     {/* 👉 NOUVEAU LIEN MESSAGES */}
                    <Link 
                      to="/messages" 
                      style={{
                        ...styles.link,
                        ...(isActive('/messages') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FiMessageSquare style={styles.icon} />
                      Messages
                      {/* Badge optionnel pour non lus */}
                      {/* <span style={styles.messageBadge}>3</span> */}
                    </Link>
                    
                    <Link 
                      to="/entreprises" 
                      style={{
                        ...styles.link,
                        ...(isActive('/entreprises') ? styles.linkActive : {})
                      }}
                      className="nav-link"
                    >
                      <FaSearch style={styles.icon} />
                      Explorer
                    </Link>
                  </>
                )}
                
                <div style={styles.userInfo}>
                  <div style={styles.userAvatar}>
                    {user.name.charAt(0).toUpperCase()}
                  </div>
                  <span style={styles.userName}>{user.name.split(' ')[0]}</span>
                  {user.role === 'admin' && (
                    <span style={styles.adminBadge}>ADMIN</span>
                  )}
                </div>
                
                <button onClick={handleLogout} style={styles.buttonLogout}>
                  <FaSignOutAlt style={styles.icon} />
                  <span style={styles.buttonText}>Déconnexion</span>
                </button>
              </>
            ) : (
              <>
                {/* Menu Public */}
                <Link 
                  to="/" 
                  style={{
                    ...styles.link,
                    ...(isActive('/') ? styles.linkActive : {})
                  }}
                  className="nav-link"
                >
                  <FaHome style={styles.icon} />
                  Accueil
                </Link>
                
                <Link 
                  to="/entreprises" 
                  style={{
                    ...styles.link,
                    ...(isActive('/entreprises') ? styles.linkActive : {})
                  }}
                  className="nav-link"
                >
                  <FaBuilding style={styles.icon} />
                  Entreprises
                </Link>
                
                {/* Mega Dropdown Services */}
                <div 
                  style={styles.dropdown}
                  onMouseEnter={() => setShowServicesDropdown(true)}
                  onMouseLeave={() => setShowServicesDropdown(false)}
                >
                  <Link 
                    to="/services" 
                    style={{
                      ...styles.link,
                      ...(isActive('/services') ? styles.linkActive : {})
                    }}
                    className="nav-link"
                  >
                    <FaTools style={styles.icon} />
                    Services
                    <FaChevronDown style={{...styles.icon, fontSize: '0.75rem', marginLeft: '0.25rem'}} />
                  </Link>
                  
                  {showServicesDropdown && (
                    <div style={styles.megaDropdown} className="mega-dropdown">
                      <div style={styles.megaDropdownHeader}>
                        <h3 style={styles.megaDropdownTitle}>Nos Services</h3>
                        <p style={styles.megaDropdownSubtitle}>Choisissez le service qu'il vous faut</p>
                      </div>
                      <div style={styles.servicesGrid}>
                        {servicesCategories.map((service, index) => (
                          <Link 
                            key={index}
                            to={service.path}
                            style={styles.serviceItem}
                            className="service-item"
                          >
                            <div style={styles.serviceIcon}>{service.icon}</div>
                            <span style={styles.serviceName}>{service.name}</span>
                          </Link>
                        ))}
                      </div>
                      <div style={styles.megaDropdownFooter}>
                        <Link to="/services" style={styles.viewAllLink}>
                          Voir tous les services →
                        </Link>
                      </div>
                    </div>
                  )}
                </div>
                <Link to="/login" style={styles.buttonSecondary}>
                  Connexion
                </Link>
                <Link to="/register" style={styles.buttonPrimary}>
                  Inscription
                </Link>
              </>
            )}
          </div>

          {/* Mobile Menu Button */}
          <button 
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            style={styles.mobileMenuButton}
            aria-label="Toggle menu"
          >
            {mobileMenuOpen ? <FaTimes size={24} /> : <FaBars size={24} />}
          </button>
        </div>
      </nav>

      {/* Mobile Menu */}
      {mobileMenuOpen && (
        <div style={styles.mobileMenuOverlay} onClick={closeMobileMenu}>
          <div style={styles.mobileMenu} onClick={(e) => e.stopPropagation()}>
            <div style={styles.mobileMenuHeader}>
              <Logo size="md" showText={true} />
              <button 
                onClick={closeMobileMenu}
                style={styles.mobileCloseButton}
              >
                <FaTimes size={24} />
              </button>
            </div>

            <div style={styles.mobileMenuContent}>
              {user ? (
                <>
                  {/* Mobile User Info */}
                  <div style={styles.mobileUserInfo}>
                    <div style={styles.mobileUserAvatar}>
                      {user.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                      <div style={styles.mobileUserName}>{user.name}</div>
                      <div style={styles.mobileUserRole}>
                        {user.role === 'admin' ? 'Administrateur' : 'Prestataire'}
                      </div>
                    </div>
                  </div>

                  {user.role === 'admin' ? (
                    <>
                      <Link to="/admin/dashboard" style={styles.mobileLink} onClick={closeMobileMenu}>
                        <FaUser /> Dashboard Admin
                      </Link>
                      <Link to="/admin/entreprises" style={styles.mobileLink} onClick={closeMobileMenu}>
                        <FaBuilding /> Entreprises
                      </Link>
                      <Link to="/entreprises" style={styles.mobileLink} onClick={closeMobileMenu}>
                        <FaSearch /> Site Public
                      </Link>
                    </>
                  ) : (
                    <>
                      <Link to="/dashboard" style={styles.mobileLink} onClick={closeMobileMenu}>
                        <FaHome /> Tableau de bord
                      </Link>
                      <Link to="/mes-entreprises" style={styles.mobileLink} onClick={closeMobileMenu}>
                        <FaBuilding /> Mes Entreprises
                      </Link>
                      <Link to="/mes-services" style={styles.mobileLink} onClick={closeMobileMenu}>
                        <FaTools /> Mes Services
                      </Link>
                        {/* 👉 NOUVEAU LIEN MESSAGES - MOBILE */}
                <Link to="/messages" style={styles.mobileLink} onClick={closeMobileMenu}>
                  <FiMessageSquare /> Messages
                </Link>
                      <Link to="/entreprises" style={styles.mobileLink} onClick={closeMobileMenu}>
                        <FaSearch /> Explorer
                      </Link>
                    </>
                  )}

                  <button onClick={handleLogout} style={styles.mobileLogoutButton}>
                    <FaSignOutAlt /> Déconnexion
                  </button>
                </>
              ) : (
                <>
                  <Link to="/" style={styles.mobileLink} onClick={closeMobileMenu}>
                    <FaHome /> Accueil
                  </Link>
                  <Link to="/entreprises" style={styles.mobileLink} onClick={closeMobileMenu}>
                    <FaBuilding /> Entreprises
                  </Link>
                  
                  <div style={styles.mobileServicesSection}>
                    <div style={styles.mobileSectionTitle}>
                      <FaTools /> Services
                    </div>
                    {servicesCategories.map((service, index) => (
                      <Link 
                        key={index}
                        to={service.path}
                        style={styles.mobileServiceLink}
                        onClick={closeMobileMenu}
                      >
                        {service.icon}
                        {service.name}
                      </Link>
                    ))}
                  </div>

                  <div style={styles.mobileAuthButtons}>
                    <Link to="/login" style={styles.mobileButtonSecondary} onClick={closeMobileMenu}>
                      Connexion
                    </Link>
                    <Link to="/register" style={styles.mobileButtonPrimary} onClick={closeMobileMenu}>
                      Inscription
                    </Link>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* CSS Animations */}
      <style>{`
        .nav-link {
          position: relative;
          transition: all 0.3s ease;
        }
        
        .nav-link::after {
          content: '';
          position: absolute;
          bottom: -5px;
          left: 50%;
          transform: translateX(-50%) scaleX(0);
          width: 80%;
          height: 3px;
          background: ${theme.colors.primary};
          border-radius: 2px;
          transition: transform 0.3s ease;
        }
        
        .nav-link:hover::after {
          transform: translateX(-50%) scaleX(1);
        }
        
        .mega-dropdown {
          animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
          from {
            opacity: 0;
            transform: translateY(-10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .service-item {
          transition: all 0.3s ease;
        }

        .service-item:hover {
          transform: translateY(-3px);
          box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        @media (max-width: 968px) {
          .desktop-menu {
            display: none;
          }
        }
      `}</style>
    </>
  );
}

const styles = {
  nav: {
    position: 'sticky',
    top: 0,
    zIndex: 1000,
    backgroundColor: 'rgba(255, 255, 255, 0.98)',
    backdropFilter: 'blur(10px)',
    padding: '1rem 0',
    borderBottom: `3px solid ${theme.colors.primary}`,
    transition: 'all 0.3s ease',
  },
  navScrolled: {
    boxShadow: '0 4px 20px rgba(0, 0, 0, 0.1)',
    padding: '0.75rem 0',
  },
  container: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '0 1rem',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  desktopMenu: {
    display: 'flex',
    gap: '1.5rem',
    alignItems: 'center',
    flexWrap: 'wrap',
  },
  link: {
    color: theme.colors.text.primary,
    textDecoration: 'none',
    padding: '0.5rem 1rem',
    fontWeight: '500',
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    whiteSpace: 'nowrap',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
  },
  linkActive: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  icon: {
    fontSize: '1rem',
  },
  dropdown: {
    position: 'relative',
  },
  megaDropdown: {
    position: 'absolute',
    top: '100%',
    left: '50%',
    transform: 'translateX(-50%)',
    marginTop: '0.5rem',
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    boxShadow: '0 20px 60px rgba(0,0,0,0.15)',
    border: `2px solid ${theme.colors.primaryLight}`,
    minWidth: '500px',
    maxWidth: '600px',
    overflow: 'hidden',
  },
  megaDropdownHeader: {
    padding: '1.5rem',
    borderBottom: `2px solid ${theme.colors.primaryLight}`,
    background: `linear-gradient(135deg, ${theme.colors.primaryLight} 0%, ${theme.colors.secondary} 100%)`,
  },
  megaDropdownTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '0.25rem',
  },
  megaDropdownSubtitle: {
    color: theme.colors.text.secondary,
    fontSize: '0.9rem',
  },
  servicesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(2, 1fr)',
    gap: '0.5rem',
    padding: '1rem',
  },
  serviceItem: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    padding: '1rem',
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.md,
    textDecoration: 'none',
    color: theme.colors.text.primary,
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  serviceIcon: {
    fontSize: '1.5rem',
    color: theme.colors.primary,
  },
  serviceName: {
    fontWeight: '600',
    fontSize: '0.95rem',
  },
  megaDropdownFooter: {
    padding: '1rem 1.5rem',
    borderTop: `1px solid ${theme.colors.primaryLight}`,
    textAlign: 'center',
    backgroundColor: theme.colors.background,
  },
  viewAllLink: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    fontSize: '0.95rem',
  },
  userInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    padding: '0.5rem 1rem',
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.lg,
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  userAvatar: {
    width: '35px',
    height: '35px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontWeight: 'bold',
    fontSize: '1rem',
  },
  userName: {
    color: theme.colors.text.primary,
    fontWeight: '600',
    fontSize: '0.9rem',
  },
  adminBadge: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '0.25rem 0.75rem',
    borderRadius: theme.borderRadius.full,
    fontSize: '0.7rem',
    fontWeight: '700',
  },
  buttonPrimary: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    border: 'none',
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    cursor: 'pointer',
    textDecoration: 'none',
    display: 'inline-block',
    fontWeight: '600',
    transition: 'all 0.3s',
    whiteSpace: 'nowrap',
    boxShadow: theme.shadows.md,
    fontSize: '0.9rem',
  },
  buttonSecondary: {
    backgroundColor: 'transparent',
    color: theme.colors.primary,
    border: `2px solid ${theme.colors.primary}`,
    padding: '0.625rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    cursor: 'pointer',
    textDecoration: 'none',
    display: 'inline-block',
    fontWeight: '600',
    transition: 'all 0.3s',
    whiteSpace: 'nowrap',
    fontSize: '0.9rem',
  },
  buttonLogout: {
    backgroundColor: theme.colors.text.primary,
    color: theme.colors.text.white,
    border: 'none',
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    cursor: 'pointer',
    fontWeight: '600',
    transition: 'all 0.3s',
    whiteSpace: 'nowrap',
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    boxShadow: theme.shadows.md,
    fontSize: '0.9rem',
  },
  buttonText: {
    display: 'inline',
  },
  
  // Mobile Menu
  mobileMenuButton: {
    display: 'none',
    backgroundColor: 'transparent',
    border: 'none',
    color: theme.colors.primary,
    cursor: 'pointer',
    padding: '0.5rem',
  },
  mobileMenuOverlay: {
    position: 'fixed',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    zIndex: 9999,
    display: 'none',
  },
  mobileMenu: {
    position: 'fixed',
    top: 0,
    right: 0,
    bottom: 0,
    width: '85%',
    maxWidth: '400px',
    backgroundColor: theme.colors.secondary,
    boxShadow: '-4px 0 20px rgba(0,0,0,0.2)',
    overflowY: 'auto',
    animation: 'slideInRight 0.3s ease',
  },
  mobileMenuHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: '1.5rem',
    borderBottom: `2px solid ${theme.colors.primaryLight}`,
  },
  mobileCloseButton: {
    backgroundColor: 'transparent',
    border: 'none',
    color: theme.colors.primary,
    cursor: 'pointer',
    padding: '0.5rem',
  },
  mobileMenuContent: {
    padding: '1.5rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  mobileUserInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    padding: '1rem',
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.lg,
    marginBottom: '1rem',
  },
  mobileUserAvatar: {
    width: '50px',
    height: '50px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontWeight: 'bold',
    fontSize: '1.5rem',
  },
  mobileUserName: {
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    fontSize: '1.1rem',
  },
  mobileUserRole: {
    color: theme.colors.text.secondary,
    fontSize: '0.85rem',
  },
  mobileLink: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    padding: '1rem',
    color: theme.colors.text.primary,
    textDecoration: 'none',
    fontWeight: '600',
    borderRadius: theme.borderRadius.md,
    transition: 'all 0.3s',
    backgroundColor: theme.colors.background,
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  mobileServicesSection: {
    backgroundColor: theme.colors.background,
    padding: '1rem',
    borderRadius: theme.borderRadius.lg,
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  mobileSectionTitle: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '0.75rem',
    fontSize: '1.1rem',
  },
  mobileServiceLink: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    padding: '0.75rem',
    color: theme.colors.text.primary,
    textDecoration: 'none',
    fontWeight: '500',
    borderRadius: theme.borderRadius.md,
    transition: 'all 0.3s',
    marginBottom: '0.5rem',
    fontSize: '0.95rem',
  },
  mobileAuthButtons: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
    marginTop: '1rem',
  },
  mobileButtonPrimary: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '1rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    textAlign: 'center',
    boxShadow: theme.shadows.md,
  },
  mobileButtonSecondary: {
    backgroundColor: 'transparent',
    color: theme.colors.primary,
    border: `2px solid ${theme.colors.primary}`,
    padding: '1rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    textAlign: 'center',
  },
  mobileLogoutButton: {
    backgroundColor: theme.colors.error,
    color: theme.colors.text.white,
    border: 'none',
    padding: '1rem',
    borderRadius: theme.borderRadius.lg,
    fontWeight: '600',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '0.75rem',
    marginTop: '1rem',
  },
};

// Media Query inline
if (typeof window !== 'undefined') {
  const mediaQuery = window.matchMedia('(max-width: 968px)');
  const updateStyles = () => {
    if (mediaQuery.matches) {
      styles.desktopMenu.display = 'none';
      styles.mobileMenuButton.display = 'block';
      styles.mobileMenuOverlay.display = 'block';
    }
  };
  mediaQuery.addListener(updateStyles);
  updateStyles();
} 