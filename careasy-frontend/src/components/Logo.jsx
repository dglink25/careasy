// careasy-frontend/src/components/Logo.jsx - VERSION PROFESSIONNELLE
import { Link } from 'react-router-dom';

export default function Logo({ size = 'md', showText = true }) {
  const sizes = {
    sm: { container: '35px', text: '1rem' },
    md: { container: '45px', text: '1.25rem' },
    lg: { container: '60px', text: '1.5rem' },
  };

  return (
    <Link to="/" style={styles.link}>
      <div style={styles.container}>
        {/* Logo dans un cercle */}
        <div 
          style={{
            ...styles.logoCircle,
            width: sizes[size].container,
            height: sizes[size].container,
          }}
        >
          <img 
            src="/logo.png" 
            alt="CarEasy Logo" 
            style={styles.logoImage}
            onError={(e) => {
              // Fallback si l'image n'existe pas
              e.target.style.display = 'none';
              e.target.parentElement.innerHTML = '<span style="font-size: 1.5rem; font-weight: bold; color: white;">C</span>';
            }}
          />
        </div>
        
        {showText && (
          <span 
            style={{
              ...styles.text,
              fontSize: sizes[size].text,
            }}
          >
            <span style={styles.textAccent}>CarEasy</span>
          </span>
        )}
      </div>
    </Link>
  );
}

const styles = {
  link: {
    textDecoration: 'none',
  },
  container: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  logoCircle: {
    borderRadius: '50%',
    backgroundColor: '#DC2626',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
    boxShadow: '0 4px 12px rgba(220, 38, 38, 0.3)',
    border: '3px solid #FEE2E2',
    transition: 'transform 0.3s ease',
  },
  logoImage: {
    width: '70%',
    height: '70%',
    objectFit: 'contain',
  },
  text: {
    fontWeight: '700',
    color: '#1F2937',
    letterSpacing: '-0.025em',
    display: 'flex',
    alignItems: 'center',
  },
  textAccent: {
    color: '#DC2626',
  },
};