import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Logo from './Logo';
import theme from '../config/theme';

export default function Navbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <nav style={styles.nav}>
      <div style={styles.container}>
        {/* Logo CarEasy */}
        <Logo size="md" showText={true} />
        
        <div style={styles.menu}>
          {user ? (
            <>
              <Link to="/dashboard" style={styles.link}>
                Tableau de bord
              </Link>
              <div style={styles.userInfo}>
                <span style={styles.userName}>
                  👤 {user.name}
                </span>
              </div>
              <button onClick={handleLogout} style={styles.buttonLogout}>
                Déconnexion
              </button>
            </>
          ) : (
            <>
              <Link to="/" style={styles.link}>
                Accueil
              </Link>
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
    </nav>
  );
}

const styles = {
  nav: {
    backgroundColor: theme.colors.secondary,
    padding: '1rem 0',
    boxShadow: theme.shadows.md,
    borderBottom: `3px solid ${theme.colors.primary}`,
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
    gap: '1rem',
    alignItems: 'center',
  },
  link: {
    color: theme.colors.text.primary,
    textDecoration: 'none',
    padding: '0.5rem 1rem',
    fontWeight: '500',
    transition: 'color 0.3s',
  },
  userInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  userName: {
    color: theme.colors.text.primary,
    fontWeight: '500',
  },
  buttonPrimary: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    border: 'none',
    padding: '0.625rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    cursor: 'pointer',
    textDecoration: 'none',
    display: 'inline-block',
    fontWeight: '600',
    transition: 'all 0.3s',
  },
  buttonSecondary: {
    backgroundColor: 'transparent',
    color: theme.colors.primary,
    border: `2px solid ${theme.colors.primary}`,
    padding: '0.5rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    cursor: 'pointer',
    textDecoration: 'none',
    display: 'inline-block',
    fontWeight: '600',
    transition: 'all 0.3s',
  },
  buttonLogout: {
    backgroundColor: theme.colors.text.primary,
    color: theme.colors.text.white,
    border: 'none',
    padding: '0.625rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    cursor: 'pointer',
    fontWeight: '600',
    transition: 'all 0.3s',
  },
};