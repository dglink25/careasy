// careasy-frontend/src/api/publicApi.js
import api from './axios';

export const publicApi = {
  // Liste des entreprises validées (public)
  getEntreprises: async () => {
    const response = await api.get('/entreprises');
    return response.data;
  },

  // Détails d'une entreprise (public)
  getEntreprise: async (id) => {
    const response = await api.get(`/entreprises/${id}`);
    return response.data;
  },

  // Entreprises par domaine
  getEntreprisesByDomaine: async (domaineId) => {
    const response = await api.get(`/entreprises/domaine/${domaineId}`);
    return response.data;
  },

  // Liste des services publics
  getServices: async () => {
    const response = await api.get('/services');
    return response.data;
  },

  // Recherche
  search: async (query) => {
    const response = await api.get('/search', {
      params: { q: query }
    });
    return response.data;
  },

  // Domaines (si tu crées une route)
  getDomaines: async () => {
    const response = await api.get('/entreprises/form/data');
    return response.data.domaines;
  },
};