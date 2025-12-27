// careasy-frontend/src/components/Chat/ChatModal.jsx - VERSION CORRIGÉE
import { useState, useEffect, useRef } from 'react';
import { FiX, FiSend, FiMapPin, FiLoader, FiCheck, FiCheckCircle } from 'react-icons/fi';
import { messageApi } from '../../api/messageApi';
import { useAuth } from '../../contexts/AuthContext';
import theme from '../../config/theme';

export default function ChatModal({ 
  receiverId, 
  receiverName, 
  onClose, 
  conversationId = null,
  existingConversation = false
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

  useEffect(() => {
    initConversation();
  }, [receiverId, conversationId]);

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
      
      // ✅ DEBUG
      console.log('🔍 ChatModal - Initialisation');
      console.log('🔍 receiverId:', receiverId);
      console.log('🔍 conversationId:', conversationId);
      console.log('🔍 existingConversation:', existingConversation);
      console.log('🔍 user:', user);
      
      let conv;
      
      if (conversationId && existingConversation) {
        // Conversation existante
        conv = { id: conversationId };
        setConversation(conv);
        
        const convData = await messageApi.getMessages(conversationId);
        console.log('✅ Messages chargés:', convData.messages?.length || 0);
        setMessages(convData.messages || []);
      } else {
        // Nouvelle conversation
        console.log('🔍 Création nouvelle conversation avec receiverId:', receiverId);
        
        if (!receiverId && user) {
          setError('Erreur: Destinataire non défini');
          console.error('❌ receiverId est NULL mais user existe');
          return;
        }
        
        conv = await messageApi.startConversation(receiverId);
        console.log('✅ Conversation créée:', conv);
        setConversation(conv);
        
        const convData = await messageApi.getMessages(conv.id);
        setMessages(convData.messages || []);
      }
    } catch (err) {
      console.error('❌ Erreur initialisation conversation:', err);
      console.error('❌ Détails:', err.response?.data);
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

      console.log('📤 Envoi message dans conversation:', conversation.id);
      const sentMessage = await messageApi.sendMessage(
        conversation.id,
        newMessage.trim()
      );

      console.log('✅ Message envoyé:', sentMessage);
      setMessages(prev => [...prev, sentMessage]);
      setNewMessage('');
      
    } catch (err) {
      console.error('❌ Erreur envoi message:', err);
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
    if (user) {
      return message.sender_id === user.id;
    }
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
                  <span>✅ Connecté en tant que {user.name}</span>
                ) : (
                  <span>👤 Mode anonyme</span>
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
          ) : error ? (
            <div style={styles.errorState}>
              <div style={styles.errorIcon}>❌</div>
              <p style={styles.errorText}>{error}</p>
              <button onClick={initConversation} style={styles.retryButton}>
                Réessayer
              </button>
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
              {messages.map((message) => {
                const isMine = isMyMessage(message);
                return (
                  <div
                    key={message.id}
                    style={{
                      ...styles.messageWrapper,
                      justifyContent: isMine ? 'flex-end' : 'flex-start'
                    }}
                  >
                    <div
                      style={{
                        ...styles.messageBubble,
                        ...(isMine 
                          ? styles.myMessage 
                          : styles.theirMessage
                        )
                      }}
                    >
                      {!isMine && message.sender?.name && (
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
                      
                      <div style={styles.messageFooter}>
                        <span style={styles.messageTime}>
                          {formatTime(message.created_at)}
                        </span>
                        
                        {isMine && (
                          <span style={styles.readStatus}>
                            {message.read_at ? (
                              <FiCheckCircle style={styles.readIcon} title="Lu" />
                            ) : (
                              <FiCheck style={styles.sentIcon} title="Envoyé" />
                            )}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
              <div ref={messagesEndRef} />
            </>
          )}
        </div>

        {/* Error banner */}
        {error && !loading && (
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
            style={{
              ...styles.sendButton,
              ...((!newMessage.trim() || sending || !conversation) && styles.sendButtonDisabled)
            }}
          >
            {sending ? <FiLoader style={styles.spinner} /> : <FiSend />}
          </button>
        </form>

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
  errorState: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    height: '100%',
    gap: '1rem',
    padding: '2rem',
  },
  errorIcon: {
    fontSize: '3rem',
  },
  errorText: {
    color: theme.colors.error,
    textAlign: 'center',
  },
  retryButton: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    border: 'none',
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    cursor: 'pointer',
    fontWeight: '600',
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
    boxShadow: '0 2px 8px rgba(239, 68, 68, 0.2)',
  },
  theirMessage: {
    backgroundColor: '#fff',
    color: theme.colors.text.primary,
    border: `2px solid ${theme.colors.primaryLight}`,
    borderBottomLeftRadius: '4px',
    boxShadow: '0 2px 8px rgba(0, 0, 0, 0.05)',
  },
  senderName: {
    fontSize: '0.75rem',
    fontWeight: '600',
    marginBottom: '0.375rem',
    opacity: 0.8,
    color: theme.colors.primary,
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
  messageFooter: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: '0.375rem',
    gap: '0.5rem',
  },
  messageTime: {
    fontSize: '0.7rem',
    opacity: 0.7,
  },
  readStatus: {
    display: 'flex',
    alignItems: 'center',
    fontSize: '0.875rem',
  },
  readIcon: {
    color: '#10b981',
    fontSize: '0.875rem',
  },
  sentIcon: {
    color: 'currentColor',
    opacity: 0.6,
    fontSize: '0.875rem',
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
    transition: 'all 0.2s',
  },
  input: {
    flex: 1,
    padding: '0.875rem 1rem',
    border: `1px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    outline: 'none',
    fontFamily: 'inherit',
    transition: 'border-color 0.2s',
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
    transition: 'all 0.2s',
  },
  sendButtonDisabled: {
    backgroundColor: '#cbd5e1',
    cursor: 'not-allowed',
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