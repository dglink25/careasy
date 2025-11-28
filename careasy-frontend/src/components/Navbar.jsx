// careasy-frontend/src/components/Navbar.jsx - ULTRA PROFESSIONNEL
import { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Logo from './Logo';
import theme from '../config/theme';
import { 
  FaHome, FaBuilding, FaTools, FaSearch, 
  FaUser, FaChevronDown, FaSignOutAlt 
} from 'react-icons/fa';

export default function Navbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [scrolled, setScrolled] = useState(false);
  const [showServicesDropdown, setShowServicesDropdown] = useState(false);

  // Detect scroll
  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const isActive = (path) => location.pathname === path;

  return (
    <nav style={{
      ...styles.nav,
      ...(scrolled ? styles.navScrolled : {})
    }}>
      <div style={styles.container}>
        {/* Logo */}
        <Logo size="md" showText={true} />
        
        <div style={styles.menu}>
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
                    Dashboard Admin
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
                  <Link 
                    to="/entreprises" 
                    style={{
                      ...styles.link,
                      ...(isActive('/entreprises') ? styles.linkActive : {})
                    }}
                    className="nav-link"
                  >
                    <FaSearch style={styles.icon} />
                    Voir le site
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
                    Tableau de bord
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
                <span style={styles.userName}>{user.name}</span>
                {user.role === 'admin' && (
                  <span style={styles.adminBadge}>ADMIN</span>
                )}
              </div>
              
              <button onClick={handleLogout} style={styles.buttonLogout}>
                <FaSignOutAlt style={styles.icon} />
                Déconnexion
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
              
              {/* Dropdown Services */}
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
                  <FaChevronDown style={{...styles.icon, fontSize: '0.75rem'}} />
                </Link>
                
                {showServicesDropdown && (
                  <div style={styles.dropdownMenu} className="dropdown-menu">
                    <Link to="/services" style={styles.dropdownItem}>
                      Tous les services
                    </Link>
                    <Link to="/services?type=mecanique" style={styles.dropdownItem}>
                      Mécanique
                    </Link>
                    <Link to="/services?type=peinture" style={styles.dropdownItem}>
                      Peinture & Carrosserie
                    </Link>
                    <Link to="/services?type=pneumatique" style={styles.dropdownItem}>
                      Pneumatique
                    </Link>
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
      </div>

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
        
        .dropdown-menu {
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
      `}</style>
    </nav>
  );
}

const styles = {
  nav: {
    position: 'sticky',
    top: 0,
    zIndex: 1000,
    backgroundColor: 'rgba(255, 255, 255, 0.95)',
    backdropFilter: 'blur(10px)',
    padding: '1rem 0',
    borderBottom: `3px solid ${theme.colors.primary}`,
    transition: 'all 0.3s ease',
  },
  navScrolled: {
    boxShadow: '0 4px 20px rgba(0, 0, 0, 0.1)',
    backgroundColor: 'rgba(255, 255, 255, 0.98)',
  },
  container: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '0 1rem',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  menu: {
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
  dropdownMenu: {
    position: 'absolute',
    top: '100%',
    left: 0,
    marginTop: '0.5rem',
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.lg,
    boxShadow: theme.shadows.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    minWidth: '200px',
    overflow: 'hidden',
  },
  dropdownItem: {
    display: 'block',
    padding: '0.875rem 1.25rem',
    color: theme.colors.text.primary,
    textDecoration: 'none',
    transition: 'all 0.2s',
    borderBottom: `1px solid ${theme.colors.primaryLight}`,
  },
  userInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    padding: '0.5rem 1rem',
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.lg,
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
  },
  adminBadge: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '0.25rem 0.75rem',
    borderRadius: theme.borderRadius.full,
    fontSize: '0.75rem',
    fontWeight: '700',
  },
  buttonPrimary: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    border: 'none',
    padding: '0.75rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    cursor: 'pointer',
    textDecoration: 'none',
    display: 'inline-block',
    fontWeight: '600',
    transition: 'all 0.3s',
    whiteSpace: 'nowrap',
    boxShadow: theme.shadows.md,
  },
  buttonSecondary: {
    backgroundColor: 'transparent',
    color: theme.colors.primary,
    border: `2px solid ${theme.colors.primary}`,
    padding: '0.625rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    cursor: 'pointer',
    textDecoration: 'none',
    display: 'inline-block',
    fontWeight: '600',
    transition: 'all 0.3s',
    whiteSpace: 'nowrap',
  },
  buttonLogout: {
    backgroundColor: theme.colors.text.primary,
    color: theme.colors.text.white,
    border: 'none',
    padding: '0.75rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    cursor: 'pointer',
    fontWeight: '600',
    transition: 'all 0.3s',
    whiteSpace: 'nowrap',
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    boxShadow: theme.shadows.md,
  },
};