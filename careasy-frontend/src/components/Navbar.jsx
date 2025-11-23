import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

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
        <Link to="/" style={styles.logo}>
          CareEasy
        </Link>
        
        <div style={styles.menu}>
          {user ? (
            <>
              <Link to="/dashboard" style={styles.link}>
                Dashboard
              </Link>
              <span style={styles.userName}>
                Bonjour, {user.name}
              </span>
              <button onClick={handleLogout} style={styles.button}>
                Déconnexion
              </button>
            </>
          ) : (
            <>
              <Link to="/login" style={styles.link}>
                Connexion
              </Link>
              <Link to="/register" style={styles.button}>
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
    backgroundColor: '#2563eb',
    padding: '1rem 0',
    boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
  },
  container: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '0 1rem',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  logo: {
    color: 'white',
    fontSize: '1.5rem',
    fontWeight: 'bold',
    textDecoration: 'none',
  },
  menu: {
    display: 'flex',
    gap: '1rem',
    alignItems: 'center',
  },
  link: {
    color: 'white',
    textDecoration: 'none',
    padding: '0.5rem 1rem',
  },
  userName: {
    color: 'white',
  },
  button: {
    backgroundColor: 'white',
    color: '#2563eb',
    border: 'none',
    padding: '0.5rem 1rem',
    borderRadius: '0.375rem',
    cursor: 'pointer',
    textDecoration: 'none',
    display: 'inline-block',
  },
};