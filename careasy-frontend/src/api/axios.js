// careasy-frontend/src/api/axios.js - VERSION COMPLÈTE AVEC AUTH
import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
});

// ✅ INTERCEPTEUR POUR AJOUTER LE TOKEN
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
      console.log('🔑 Token ajouté:', token.substring(0, 20) + '...');
    } else {
      console.warn('⚠️ Aucun token trouvé dans localStorage');
    }
    
    console.log('📡 Requête:', config.method.toUpperCase(), config.url);
    console.log('📡 Headers:', config.headers);
    
    return config;
  },
  (error) => {
    console.error('❌ Erreur intercepteur request:', error);
    return Promise.reject(error);
  }
);

// ✅ INTERCEPTEUR POUR GÉRER LES RÉPONSES
api.interceptors.response.use(
  (response) => {
    console.log('✅ Réponse reçue:', response.status, response.config.url);
    return response;
  },
  (error) => {
    console.error('❌ Erreur API:', error.response?.status, error.response?.data);
    
    // Si 401 Unauthorized, déconnecter l'utilisateur
    if (error.response?.status === 401) {
      console.warn('⚠️ Non authentifié - Redirection vers login');
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    
    return Promise.reject(error);
  }
);

export default api;