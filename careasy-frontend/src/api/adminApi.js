// careasy-frontend/src/api/adminApi.js
import api from './axios';

export const adminApi = {
  // Récupérer toutes les entreprises (avec filtrage optionnel)
  getEntreprises: async (status = null) => {
    const params = status ? { status } : {};
    const response = await api.get('/admin/entreprises', { params });
    return response.data;
  },

  // Récupérer les détails d'une entreprise
  getEntreprise: async (id) => {
    const response = await api.get(`/admin/entreprises/${id}`);
    return response.data;
  },

  // Approuver une entreprise
  approveEntreprise: async (id, admin_note = null) => {
    const response = await api.post(`/admin/entreprises/${id}/approve`, {
      admin_note
    });
    return response.data;
  },

  // Rejeter une entreprise
  rejectEntreprise: async (id, admin_note) => {
    const response = await api.post(`/admin/entreprises/${id}/reject`, {
      admin_note
    });
    return response.data;
  },
};