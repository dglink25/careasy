import { Link } from 'react-router-dom';
import Logo from './Logo';
import theme from '../config/theme';
//import { FaMapMarkerAlt, FaEnvelope, FaPhoneAlt } from 'react-icons/fa';

export default function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer style={styles.footer}>
      <div style={styles.container}>
        {/* Section Logo et Description */}
        <div style={styles.section}>
          <Logo size="md" showText={true} />
           <p style={styles.description}>
            La plateforme #1 pour tous vos besoins automobiles au Bénin
          </p>
          
        </div>

        {/* Section Services */}
        <div style={styles.section}>
            <h3 style={styles.sectionTitle}>Liens Rapides</h3>
          <ul style={styles.linkList}>
            <li><Link to="/services" style={styles.link}>Nos Services</Link></li>
            <li><Link to="/about" style={styles.link}>À Propos</Link></li>
            <li><Link to="/prestataires" style={styles.link}>Prestataires</Link></li>
            <li><Link to="/contact" style={styles.link}>Contact</Link></li>
          </ul>
        </div>

 {/* Section Contact */}
<div style={styles.section}>
  <h3 style={styles.sectionTitle}>Contact</h3>
  <ul style={styles.linkList}>
    <li style={styles.contactItem}>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style={styles.icon}>
        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
      </svg>
      CarEasy, Bénin
    </li>
    <li style={styles.contactItem}>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style={styles.icon}>
        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
      </svg>
      contact@careasy.bj
    </li>
    <li style={styles.contactItem}>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style={styles.icon}>
        <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
      </svg>
      +229 90 00 00 00
    </li>
  </ul>
</div>

        {/* Section Légal */}
        <div style={styles.section}>
          <h3 style={styles.sectionTitle}>Suivez nous</h3>
          <div style={styles.socialLinks}>
            <a href="#" style={styles.socialLink} aria-label="Facebook"> <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg></a>
            <a href="#" style={styles.socialLink} aria-label="Twitter"> <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
              </svg></a>
            <a href="#" style={styles.socialLink} aria-label="Instagram"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
              </svg></a>
            <a href="#" style={styles.socialLink} aria-label="LinkedIn"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
              </svg></a>
          </div>
        </div>
      </div>

      {/* Barre de copyright */}
      <div style={styles.bottom}>
        <div style={styles.container}>
          <p style={styles.copyright}>
            © {currentYear} <strong style={styles.brandName}>CarEasy</strong>. Tous droits réservés.
          </p>
           <div style={styles.footerBottomLinks}>
            <a href="#" style={styles.footerBottomLink}>Politique de confidentialité</a>
            <span style={styles.separator}>•</span>
            <a href="#" style={styles.footerBottomLink}>Conditions d'utilisation</a>
          </div>
        </div>
      </div>
    </footer>
  );
}

const styles = {
  footer: {
    backgroundColor: theme.colors.text.primary,
    color: theme.colors.text.white,
    paddingTop: '3rem',
    marginTop: '4rem',
  },
  container: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '0 1rem',
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '2rem',
  },
  section: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  description: {
    color: '#D1D5DB',
    fontSize: '0.95rem',
    lineHeight: '1.6',
    marginTop: '0.5rem',
  },
  socialLinks: {
    display: 'flex',
    gap: '1rem',
    marginTop: '0.5rem',
  },
  socialLink: {
    fontSize: '1.5rem',
    textDecoration: 'none',
    transition: 'transform 0.3s',
  },
  sectionTitle: {
    fontSize: '1.125rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '0.5rem',
  },
  linkList: {
    listStyle: 'none',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  link: {
    color: '#D1D5DB',
    textDecoration: 'none',
    fontSize: '0.95rem',
    transition: 'color 0.3s',
  },
  bottom: {
    borderTop: '1px solid rgba(255, 255, 255, 0.1)',
    marginTop: '2rem',
    padding: '1.5rem 0',
  },
  contactItem: {
    color: '#D1D5DB',
    fontSize: '0.95rem',
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  icon: {
    flexShrink: 0,
    color: theme.colors.primary, // ou la couleur que vous voulez pour les icônes
  },
  copyright: {
    textAlign: 'center',
    color: '#D1D5DB',
    fontSize: '0.95rem',
    marginBottom: '0.5rem',
  },
  brandName: {
    color: theme.colors.primary,
  },
  madeWith: {
    textAlign: 'center',
    color: '#9CA3AF',
    fontSize: '0.875rem',
  },
  footerBottomLinks: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
    gap: '0.5rem',
    flexWrap: 'wrap',
  },
  footerBottomLink: {
    color: '#D1D5DB',
    textDecoration: 'none',
    fontSize: '0.875rem',
    transition: 'color 0.3s ease',
  },
  separator: {
    color: '#9CA3AF',
    fontSize: '0.875rem',
  },
};