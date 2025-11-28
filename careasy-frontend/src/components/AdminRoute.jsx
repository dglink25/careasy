// careasy-frontend/src/components/AdminRoute.jsx
import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function AdminRoute({ children }) {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div style={{ 
        display: 'flex', 
        justifyContent: 'center', 
        alignItems: 'center', 
        height: '100vh' 
      }}>
        <p>Chargement...</p>
      </div>
    );
  }

  // Vérifier si l'utilisateur est connecté ET admin
  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (user.role !== 'admin') {
    return (
      <div style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '60vh',
        gap: '1rem',
        padding: '2rem',
        textAlign: 'center'
      }}>
        <div style={{ fontSize: '5rem' }}>🚫</div>
        <h2 style={{ fontSize: '2rem', color: '#DC2626' }}>Accès refusé</h2>
        <p style={{ fontSize: '1.125rem', color: '#6B7280' }}>
          Cette page est réservée aux administrateurs.
        </p>
        <a 
          href="/dashboard" 
          style={{
            backgroundColor: '#DC2626',
            color: '#fff',
            padding: '0.875rem 2rem',
            borderRadius: '0.5rem',
            textDecoration: 'none',
            fontWeight: '600',
            marginTop: '1rem'
          }}
        >
          Retour au dashboard
        </a>
      </div>
    );
  }

  return children;
}