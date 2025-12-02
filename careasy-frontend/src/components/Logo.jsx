// careasy-frontend/src/components/Logo.jsx
import { Link } from 'react-router-dom';

export default function Logo({ size = 'md', showText = true }) {
  const sizes = {
    sm: { container: '30px', text: '1rem' },
    md: { container: '40px', text: '1.25rem' },
    lg: { container: '60px', text: '1.5rem' },
  };

  return (
    <Link to="/" style={styles.link}>
      <div style={styles.container}>
        {/* Option 1: Si vous avez un logo dans public/logo.png */}
        <div 
          style={{
            ...styles.logoPlaceholder,
            width: sizes[size].container,
            height: sizes[size].container,
          }}
        >
          {/* Si vous avez un logo, décommentez la ligne ci-dessous et mettez le logo dans public/logo.png */}
          { <img src="/logo.png" alt="CarEasy Logo" style={styles.logoImage} /> }
          
          {/* Sinon, utilisez le SVG temporaire */}
         {/* <svg 
            width={sizes[size].container} 
            height={sizes[size].container} 
            viewBox="0 0 40 40"
            style={styles.logoSvg}
          >
            <circle cx="20" cy="20" r="18" fill="#DC2626"/>
            <text x="20" y="26" fontSize="18" fontWeight="bold" fill="white" textAnchor="middle">
              C
            </text>
          </svg>*/}
        </div>
        
        {showText && (
          <span 
            style={{
              ...styles.text,
              fontSize: sizes[size].text,
            }}
          >
            CarEasy
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
  logoPlaceholder: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoImage: {
    width: '100%',
    height: '100%',
    objectFit: 'contain',
  },
  logoSvg: {
    display: 'block',
  },
  text: {
    fontWeight: 'bold',
    color: '#DC2626',
    letterSpacing: '-0.025em',
  },
};