// careasy-frontend/src/api/messageApi.js
import api from './axios';

export const messageApi = {
  /**
   * Démarrer ou récupérer une conversation
   * @param {number|null} receiverId - ID du destinataire (prestataire), null si conversation anonyme
   */
  startConversation: async (receiverId = null) => {
    const payload = receiverId ? { receiver_id: receiverId } : {};
    const response = await api.post('/conversation/start', payload);
    return response.data;
  },

  /**
   * Envoyer un message dans une conversation
   * @param {number} conversationId - ID de la conversation
   * @param {string} content - Contenu du message
   * @param {object} location - {latitude, longitude} optionnel
   */
  sendMessage: async (conversationId, content, location = null) => {
    const payload = {
      content,
      ...(location && {
        latitude: location.latitude,
        longitude: location.longitude
      })
    };
    const response = await api.post(`/conversation/${conversationId}/send`, payload);
    return response.data;
  },

  /**
   * Récupérer les messages d'une conversation
   * @param {number} conversationId - ID de la conversation
   */
  getMessages: async (conversationId) => {
    const response = await api.get(`/conversation/${conversationId}`);
    return response.data;
  },

  /**
   * Récupérer toutes les conversations de l'utilisateur connecté
   * Note: Cette route n'existe pas encore dans ton backend, 
   * mais on peut l'implémenter plus tard si nécessaire
   */
  getMyConversations: async () => {
    try {
      const response = await api.get('/conversations');
      return response.data;
    } catch (error) {
      console.warn('Route /conversations pas encore implémentée');
      return [];
    }
  }
};