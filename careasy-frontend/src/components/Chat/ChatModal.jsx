// careasy-frontend/src/components/Chat/ChatModal.jsx
import { useState, useEffect, useRef } from 'react';
import { FiX, FiSend, FiMapPin, FiLoader } from 'react-icons/fi';
import { messageApi } from '../../api/messageApi';
import { useAuth } from '../../contexts/AuthContext';
import theme from '../../config/theme';

export default function ChatModal({ 
  receiverId, 
  receiverName, 
  onClose, 
  conversationId = null,  // 👈 AJOUT: ID de conversation existante
  existingConversation = false // 👈 AJOUT: Flag pour conversation existante
}) {
  const { user } = useAuth();
  const [conversation, setConversation] = useState(null);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');
  const [locationSharing, setLocationSharing] = useState(false);
  const messagesEndRef = useRef(null);

  // Initialiser ou récupérer la conversation
  useEffect(() => {
    initConversation();
  }, [receiverId, conversationId]);

  // Auto-scroll vers le bas quand nouveaux messages
  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const initConversation = async () => {
    try {
      setLoading(true);
      setError('');
      
      let conv;
      
      // Si on a déjà un conversationId, charger directement les messages
      if (conversationId && existingConversation) {
        conv = { id: conversationId };
        setConversation(conv);
        
        // Charger les messages existants
        const convData = await messageApi.getMessages(conversationId);
        setMessages(convData.messages || []);
      } else {
        // Sinon, démarrer ou récupérer la conversation
        conv = await messageApi.startConversation(receiverId);
        setConversation(conv);
        
        // Charger les messages existants
        const convData = await messageApi.getMessages(conv.id);
        setMessages(convData.messages || []);
      }
    } catch (err) {
      console.error('Erreur initialisation conversation:', err);
      setError('Impossible de démarrer la conversation. Veuillez réessayer.');
    } finally {
      setLoading(false);
    }
  };

  const handleSendMessage = async (e) => {
    e.preventDefault();
    
    if (!newMessage.trim() || sending || !conversation) return;

    try {
      setSending(true);
      setError('');

      // Envoyer le message
      const sentMessage = await messageApi.sendMessage(
        conversation.id,
        newMessage.trim()
      );

      // Ajouter le message à la liste locale
      setMessages(prev => [...prev, sentMessage]);
      setNewMessage('');
      
    } catch (err) {
      console.error('Erreur envoi message:', err);
      setError('Impossible d\'envoyer le message. Veuillez réessayer.');
    } finally {
      setSending(false);
    }
  };

  const handleShareLocation = async () => {
    if (!conversation) return;

    try {
      setLocationSharing(true);
      
      if (!navigator.geolocation) {
        alert('La géolocalisation n\'est pas supportée par votre navigateur');
        return;
      }

      navigator.geolocation.getCurrentPosition(
        async (position) => {
          try {
            const { latitude, longitude } = position.coords;
            
            // Envoyer un message avec la localisation
            const locationMessage = await messageApi.sendMessage(
              conversation.id,
              `📍 Ma position actuelle`,
              { latitude, longitude }
            );

            setMessages(prev => [...prev, locationMessage]);
            setLocationSharing(false);
          } catch (err) {
            console.error('Erreur envoi localisation:', err);
            alert('Impossible d\'envoyer la localisation');
            setLocationSharing(false);
          }
        },
        (error) => {
          console.error('Erreur géolocalisation:', error);
          alert('Impossible d\'accéder à votre position');
          setLocationSharing(false);
        }
      );
    } catch (err) {
      console.error('Erreur partage localisation:', err);
      setLocationSharing(false);
    }
  };

  const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('fr-FR', { 
      hour: '2-digit', 
      minute: '2-digit' 
    });
  };

  const isMyMessage = (message) => {
    // Si l'utilisateur est connecté
    if (user) {
      // C'est mon message si le sender_id correspond à mon ID
      return message.sender_id === user.id;
    }
    
    // Si l'utilisateur n'est PAS connecté (anonyme)
    // Ses messages sont ceux sans sender_id (null)
    return message.sender_id === null;
  };

  return (
    <div style={styles.overlay} onClick={onClose}>
      <div style={styles.modal} onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div style={styles.header}>
          <div style={styles.headerInfo}>
            <div style={styles.avatar}>
              {receiverName.charAt(0).toUpperCase()}
            </div>
            <div>
              <h3 style={styles.headerTitle}>
                Discussion avec {receiverName}
              </h3>
              <div style={styles.headerStatus}>
                {user ? (
                  <span>Connecté en tant que {user.name}</span>
                ) : (
                  <span>Mode anonyme</span>
                )}
              </div>
            </div>
          </div>
          <button onClick={onClose} style={styles.closeButton}>
            <FiX />
          </button>
        </div>

        {/* Messages */}
        <div style={styles.messagesContainer}>
          {loading ? (
            <div style={styles.loadingContainer}>
              <FiLoader style={styles.spinner} />
              <p>Chargement de la conversation...</p>
            </div>
          ) : messages.length === 0 ? (
            <div style={styles.emptyState}>
              <div style={styles.emptyIcon}>💬</div>
              <p style={styles.emptyText}>
                Envoyez votre premier message pour démarrer la conversation
              </p>
            </div>
          ) : (
            <>
              {messages.map((message) => (
                <div
                  key={message.id}
                  style={{
                    ...styles.messageWrapper,
                    justifyContent: isMyMessage(message) ? 'flex-end' : 'flex-start'
                  }}
                >
                  <div
                    style={{
                      ...styles.messageBubble,
                      ...(isMyMessage(message) 
                        ? styles.myMessage 
                        : styles.theirMessage
                      )
                    }}
                  >
                    {message.sender?.name && !isMyMessage(message) && (
                      <div style={styles.senderName}>{message.sender.name}</div>
                    )}
                    <div style={styles.messageContent}>{message.content}</div>
                    {message.latitude && message.longitude && (
                      <a
                        href={`https://www.google.com/maps?q=${message.latitude},${message.longitude}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        style={styles.locationLink}
                      >
                        <FiMapPin /> Voir sur la carte
                      </a>
                    )}
                    <div style={styles.messageTime}>
                      {formatTime(message.created_at)}
                    </div>
                  </div>
                </div>
              ))}
              <div ref={messagesEndRef} />
            </>
          )}
        </div>

        {/* Error */}
        {error && (
          <div style={styles.errorBanner}>
            {error}
          </div>
        )}

        {/* Input */}
        <form onSubmit={handleSendMessage} style={styles.inputContainer}>
          <button
            type="button"
            onClick={handleShareLocation}
            disabled={locationSharing || !conversation}
            style={styles.locationButton}
            title="Partager ma position"
          >
            {locationSharing ? <FiLoader style={styles.spinner} /> : <FiMapPin />}
          </button>
          
          <input
            type="text"
            value={newMessage}
            onChange={(e) => setNewMessage(e.target.value)}
            placeholder="Écrivez votre message..."
            style={styles.input}
            disabled={sending || !conversation}
          />
          
          <button
            type="submit"
            disabled={!newMessage.trim() || sending || !conversation}
            style={styles.sendButton}
          >
            {sending ? <FiLoader style={styles.spinner} /> : <FiSend />}
          </button>
        </form>

        {/* Info footer pour utilisateurs anonymes */}
        {!user && (
          <div style={styles.infoFooter}>
            💡 Connectez-vous pour suivre vos conversations
          </div>
        )}
      </div>

      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}

const styles = {
  overlay: {
    position: 'fixed',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 9999,
    padding: '1rem',
  },
  modal: {
    backgroundColor: '#fff',
    borderRadius: theme.borderRadius.xl,
    width: '100%',
    maxWidth: '500px',
    maxHeight: '90vh',
    display: 'flex',
    flexDirection: 'column',
    boxShadow: '0 20px 60px rgba(0, 0, 0, 0.3)',
    overflow: 'hidden',
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: '1.25rem',
    borderBottom: `2px solid ${theme.colors.primaryLight}`,
    backgroundColor: theme.colors.secondary,
  },
  headerInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
  },
  avatar: {
    width: '48px',
    height: '48px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '1.5rem',
    fontWeight: 'bold',
  },
  headerTitle: {
    fontSize: '1.125rem',
    fontWeight: '700',
    color: theme.colors.text.primary,
    margin: 0,
  },
  headerStatus: {
    fontSize: '0.8rem',
    color: theme.colors.text.secondary,
    marginTop: '0.25rem',
  },
  closeButton: {
    backgroundColor: 'transparent',
    border: 'none',
    color: theme.colors.text.secondary,
    fontSize: '1.75rem',
    cursor: 'pointer',
    padding: '0.25rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  messagesContainer: {
    flex: 1,
    overflowY: 'auto',
    padding: '1.5rem',
    backgroundColor: '#f8fafc',
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  loadingContainer: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    height: '100%',
    gap: '1rem',
    color: theme.colors.text.secondary,
  },
  spinner: {
    animation: 'spin 1s linear infinite',
    fontSize: '1.5rem',
  },
  emptyState: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    height: '100%',
    textAlign: 'center',
    padding: '2rem',
  },
  emptyIcon: {
    fontSize: '4rem',
    marginBottom: '1rem',
  },
  emptyText: {
    color: theme.colors.text.secondary,
    fontSize: '0.95rem',
  },
  messageWrapper: {
    display: 'flex',
    marginBottom: '0.5rem',
  },
  messageBubble: {
    maxWidth: '75%',
    padding: '0.875rem 1rem',
    borderRadius: theme.borderRadius.lg,
    wordWrap: 'break-word',
  },
  myMessage: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    borderBottomRightRadius: '4px',
  },
  theirMessage: {
    backgroundColor: '#fff',
    color: theme.colors.text.primary,
    border: `1px solid ${theme.colors.primaryLight}`,
    borderBottomLeftRadius: '4px',
  },
  senderName: {
    fontSize: '0.75rem',
    fontWeight: '600',
    marginBottom: '0.375rem',
    opacity: 0.9,
  },
  messageContent: {
    fontSize: '0.95rem',
    lineHeight: '1.5',
  },
  locationLink: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    fontSize: '0.85rem',
    marginTop: '0.5rem',
    color: 'inherit',
    textDecoration: 'underline',
    opacity: 0.9,
  },
  messageTime: {
    fontSize: '0.7rem',
    marginTop: '0.375rem',
    opacity: 0.7,
  },
  errorBanner: {
    backgroundColor: '#fee2e2',
    color: '#dc2626',
    padding: '0.875rem',
    fontSize: '0.875rem',
    textAlign: 'center',
    borderTop: '1px solid #fecaca',
  },
  inputContainer: {
    display: 'flex',
    gap: '0.75rem',
    padding: '1.25rem',
    borderTop: `1px solid ${theme.colors.primaryLight}`,
    backgroundColor: '#fff',
  },
  locationButton: {
    backgroundColor: theme.colors.secondary,
    border: `1px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    width: '44px',
    height: '44px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    cursor: 'pointer',
    color: theme.colors.primary,
    fontSize: '1.25rem',
    flexShrink: 0,
  },
  input: {
    flex: 1,
    padding: '0.875rem 1rem',
    border: `1px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    outline: 'none',
    fontFamily: 'inherit',
  },
  sendButton: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    border: 'none',
    borderRadius: theme.borderRadius.md,
    width: '44px',
    height: '44px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    cursor: 'pointer',
    fontSize: '1.25rem',
    flexShrink: 0,
  },
  infoFooter: {
    backgroundColor: '#fef3c7',
    color: '#92400e',
    padding: '0.75rem',
    fontSize: '0.85rem',
    textAlign: 'center',
    borderTop: '1px solid #fde68a',
  },
};