// careasy-frontend/src/api/entrepriseApi.js
import api from './axios';

export const entrepriseApi = {
  // Récupérer les entreprises du prestataire connecté
  getMesEntreprises: async () => {
    const response = await api.get('/entreprises/mine');
    return response.data;
  },

  // Récupérer les données du formulaire (domaines)
  getFormData: async () => {
    const response = await api.get('/entreprises/form/data');
    return response.data;
  },

  // Créer une entreprise
  createEntreprise: async (formData) => {
    const response = await api.post('/entreprises', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  // Récupérer les détails d'une entreprise
  getEntreprise: async (id) => {
    const response = await api.get(`/entreprises/${id}`);
    return response.data;
  },
};