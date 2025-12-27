// careasy-frontend/src/api/messageApi.js - VERSION AVEC DEBUG
import api from './axios';

export const messageApi = {
  /**
   * Démarrer ou récupérer une conversation
   */
  startConversation: async (receiverId = null) => {
    console.log('🔵 API startConversation - receiverId:', receiverId);
    
    const payload = receiverId ? { receiver_id: receiverId } : {};
    console.log('🔵 Payload:', payload);
    
    const response = await api.post('/conversation/start', payload);
    console.log('🔵 Réponse:', response.data);
    
    return response.data;
  },

  /**
   * Envoyer un message dans une conversation
   */
  sendMessage: async (conversationId, content, location = null) => {
    console.log('🔵 API sendMessage - conversationId:', conversationId);
    console.log('🔵 Content:', content);
    
    const payload = {
      content,
      ...(location && {
        latitude: location.latitude,
        longitude: location.longitude
      })
    };
    
    const response = await api.post(`/conversation/${conversationId}/send`, payload);
    console.log('🔵 Message envoyé:', response.data);
    
    return response.data;
  },

  /**
   * Récupérer les messages d'une conversation
   */
  getMessages: async (conversationId) => {
    console.log('🔵 API getMessages - conversationId:', conversationId);
    
    const response = await api.get(`/conversation/${conversationId}`);
    console.log('🔵 Messages reçus:', response.data.messages?.length || 0);
    
    return response.data;
  },

  /**
   * Récupérer toutes les conversations de l'utilisateur connecté
   */
  getMyConversations: async () => {
    console.log('🔵 API getMyConversations');
    
    const response = await api.get('/conversations');
    console.log('🔵 Conversations reçues:', response.data.length);
    
    return response.data;
  },

  /**
   * Marquer les messages comme lus
   */
  markAsRead: async (conversationId) => {
    console.log('🔵 API markAsRead - conversationId:', conversationId);
    
    const response = await api.post(`/conversation/${conversationId}/mark-read`);
    console.log('🔵 Messages marqués comme lus');
    
    return response.data;
  }
};