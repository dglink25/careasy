import { Link } from 'react-router-dom';
import Logo from './Logo';
import theme from '../config/theme';

export default function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer style={styles.footer}>
      <div style={styles.container}>
        {/* Section Logo et Description */}
        <div style={styles.section}>
          <Logo size="md" showText={true} />
          <p style={styles.description}>
            Votre assistant automobile intelligent au Bénin. 
            Trouvez les meilleurs prestataires en quelques clics.
          </p>
          <div style={styles.socialLinks}>
            <a href="#" style={styles.socialLink} aria-label="Facebook">📘</a>
            <a href="#" style={styles.socialLink} aria-label="Twitter">🐦</a>
            <a href="#" style={styles.socialLink} aria-label="Instagram">📷</a>
            <a href="#" style={styles.socialLink} aria-label="WhatsApp">💬</a>
          </div>
        </div>

        {/* Section Services */}
        <div style={styles.section}>
          <h3 style={styles.sectionTitle}>Services</h3>
          <ul style={styles.linkList}>
            <li><Link to="/services" style={styles.link}>Nos Services</Link></li>
            <li><Link to="/prestataires" style={styles.link}>Prestataires</Link></li>
            <li><Link to="/diagnostic" style={styles.link}>Diagnostic IA</Link></li>
            <li><Link to="/rendez-vous" style={styles.link}>Rendez-vous</Link></li>
          </ul>
        </div>

        {/* Section Entreprise */}
        <div style={styles.section}>
          <h3 style={styles.sectionTitle}>Entreprise</h3>
          <ul style={styles.linkList}>
            <li><Link to="/about" style={styles.link}>À propos</Link></li>
            <li><Link to="/contact" style={styles.link}>Contact</Link></li>
            <li><Link to="/faq" style={styles.link}>FAQ</Link></li>
            <li><Link to="/blog" style={styles.link}>Blog</Link></li>
          </ul>
        </div>

        {/* Section Légal */}
        <div style={styles.section}>
          <h3 style={styles.sectionTitle}>Légal</h3>
          <ul style={styles.linkList}>
            <li><Link to="/terms" style={styles.link}>Conditions d'utilisation</Link></li>
            <li><Link to="/privacy" style={styles.link}>Politique de confidentialité</Link></li>
            <li><Link to="/cookies" style={styles.link}>Cookies</Link></li>
            <li><Link to="/mentions" style={styles.link}>Mentions légales</Link></li>
          </ul>
        </div>
      </div>

      {/* Barre de copyright */}
      <div style={styles.bottom}>
        <div style={styles.container}>
          <p style={styles.copyright}>
            © {currentYear} <strong style={styles.brandName}>CarEasy</strong>. Tous droits réservés.
          </p>
          <p style={styles.madeWith}>
            Fait avec ❤️ au Bénin 🇧🇯
          </p>
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
};