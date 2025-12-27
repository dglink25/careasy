// careasy-frontend/src/pages/messages/MessagesPage.jsx - VERSION CORRIGÉE
import { useState, useEffect } from 'react';
import { FiMessageSquare, FiSearch, FiUser, FiClock, FiLoader, FiRefreshCw } from 'react-icons/fi';
import { messageApi } from '../../api/messageApi';
import ChatModal from '../../components/Chat/ChatModal';
import theme from '../../config/theme';

export default function MessagesPage() {
  const [conversations, setConversations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedConversation, setSelectedConversation] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchConversations();
    
    // Auto-refresh toutes les 30 secondes
    const interval = setInterval(() => {
      fetchConversations(true);
    }, 30000);

    return () => clearInterval(interval);
  }, []);

  const fetchConversations = async (silent = false) => {
    try {
      if (!silent) setLoading(true);
      setError('');
      
      const data = await messageApi.getMyConversations();
      setConversations(data);
    } catch (err) {
      console.error('Erreur chargement conversations:', err);
      setError('Impossible de charger les conversations');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const handleRefresh = () => {
    setRefreshing(true);
    fetchConversations();
  };

  const getLastMessage = (conversation) => {
    if (!conversation.messages || conversation.messages.length === 0) {
      return 'Aucun message';
    }
    const lastMsg = conversation.messages[0];
    return lastMsg.content.substring(0, 60) + (lastMsg.content.length > 60 ? '...' : '');
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'À l\'instant';
    if (diffMins < 60) return `Il y a ${diffMins} min`;
    if (diffHours < 24) return `Il y a ${diffHours}h`;
    if (diffDays < 7) return `Il y a ${diffDays}j`;
    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
  };

  const handleConversationClick = (conversation) => {
    setSelectedConversation(conversation);
  };

  const handleCloseChat = () => {
    setSelectedConversation(null);
    fetchConversations(true);
  };

  // ✅ CORRECTION: Filtrage amélioré
  const filteredConversations = conversations.filter(conv => {
    const otherUser = conv.other_user;
    const userName = otherUser?.name || 'Utilisateur';
    const lastMsg = getLastMessage(conv);
    
    return userName.toLowerCase().includes(searchTerm.toLowerCase()) ||
           lastMsg.toLowerCase().includes(searchTerm.toLowerCase());
  });

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <FiLoader style={styles.spinner} />
          <p style={styles.loadingText}>Chargement de vos messages...</p>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header */}
        <div style={styles.header}>
          <div>
            <h1 style={styles.title}>
              <FiMessageSquare style={styles.titleIcon} />
              Messages
            </h1>
            <p style={styles.subtitle}>
              Gérez vos conversations avec vos clients
            </p>
          </div>
          <button 
            onClick={handleRefresh}
            disabled={refreshing}
            style={styles.refreshButton}
          >
            <FiRefreshCw style={refreshing ? styles.spinningIcon : styles.refreshIcon} />
            {refreshing ? 'Actualisation...' : 'Actualiser'}
          </button>
        </div>

        {/* Statistiques */}
        <div style={styles.statsGrid}>
          <div style={styles.statCard}>
            <FiMessageSquare style={styles.statIcon} />
            <div>
              <div style={styles.statNumber}>{conversations.length}</div>
              <div style={styles.statLabel}>Conversations</div>
            </div>
          </div>
          <div style={styles.statCard}>
            <FiUser style={styles.statIcon} />
            <div>
              <div style={styles.statNumber}>
                {conversations.filter(c => c.is_anonymous).length}
              </div>
              <div style={styles.statLabel}>Visiteurs anonymes</div>
            </div>
          </div>
          <div style={styles.statCard}>
            <FiClock style={styles.statIcon} />
            <div>
              <div style={styles.statNumber}>
                {conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0)}
              </div>
              <div style={styles.statLabel}>Messages non lus</div>
            </div>
          </div>
        </div>

        {/* Erreur */}
        {error && (
          <div style={styles.errorBanner}>
            {error}
            <button onClick={fetchConversations} style={styles.retryButton}>
              Réessayer
            </button>
          </div>
        )}

        {/* Barre de recherche */}
        <div style={styles.searchContainer}>
          <FiSearch style={styles.searchIcon} />
          <input
            type="text"
            placeholder="Rechercher une conversation..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            style={styles.searchInput}
          />
        </div>

        {/* Liste des conversations */}
        {filteredConversations.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIcon}>💬</div>
            <h3 style={styles.emptyTitle}>
              {conversations.length === 0 
                ? "Aucune conversation"
                : "Aucun résultat"
              }
            </h3>
            <p style={styles.emptyText}>
              {conversations.length === 0 
                ? "Vos conversations avec vos clients apparaîtront ici. Les clients peuvent vous contacter depuis vos pages d'entreprises."
                : `Aucune conversation ne correspond à "${searchTerm}"`
              }
            </p>
          </div>
        ) : (
          <div style={styles.conversationsList}>
            {filteredConversations.map((conversation) => {
              // ✅ CORRECTION: Utiliser directement other_user du backend
              const otherUser = conversation.other_user;
              const isAnonymous = conversation.is_anonymous;
              const hasUnread = (conversation.unread_count || 0) > 0;
              
              return (
                <div
                  key={conversation.id}
                  onClick={() => handleConversationClick(conversation)}
                  style={{
                    ...styles.conversationCard,
                    ...(hasUnread && styles.conversationCardUnread)
                  }}
                  className="conversation-card"
                >
                  {/* Avatar */}
                  <div style={{
                    ...styles.conversationAvatar,
                    ...(hasUnread && styles.avatarUnread)
                  }}>
                    {otherUser?.name ? (
                      otherUser.name.charAt(0).toUpperCase()
                    ) : (
                      <FiUser style={styles.avatarIcon} />
                    )}
                  </div>

                  {/* Contenu */}
                  <div style={styles.conversationContent}>
                    <div style={styles.conversationHeader}>
                      <h3 style={{
                        ...styles.conversationName,
                        ...(hasUnread && styles.conversationNameUnread)
                      }}>
                        {otherUser?.name || 'Utilisateur'}
                        {isAnonymous && ' 👤'}
                      </h3>
                      <span style={styles.conversationDate}>
                        {formatDate(conversation.updated_at)}
                      </span>
                    </div>
                    <p style={{
                      ...styles.conversationPreview,
                      ...(hasUnread && styles.conversationPreviewUnread)
                    }}>
                      {getLastMessage(conversation)}
                    </p>
                    {isAnonymous && (
                      <span style={styles.anonymousBadge}>
                        👤 Anonyme
                      </span>
                    )}
                  </div>

                  {/* Badge non lu */}
                  {hasUnread && (
                    <div style={styles.unreadBadge}>
                      {conversation.unread_count}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}

        {/* Info */}
        <div style={styles.infoBox}>
          <p style={styles.infoText}>
            💡 <strong>Astuce :</strong> Les visiteurs peuvent vous contacter directement 
            depuis vos pages d'entreprises et de services sans avoir besoin de créer un compte. 
            Les conversations sont automatiquement sauvegardées et accessibles ici.
          </p>
        </div>
      </div>

      {/* Modal de chat pour la conversation sélectionnée */}
      {selectedConversation && (
        <ChatModal
          conversationId={selectedConversation.id}
          receiverId={selectedConversation.other_user_id}
          receiverName={selectedConversation.other_user?.name || 'Utilisateur'}
          onClose={handleCloseChat}
          existingConversation={true}
        />
      )}

      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        
        .conversation-card {
          transition: all 0.3s ease;
          cursor: pointer;
        }
        
        .conversation-card:hover {
          transform: translateX(8px);
          box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
      `}</style>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f8fafc',
    padding: '2rem 0 4rem 0',
  },
  content: {
    maxWidth: '1000px',
    margin: '0 auto',
    padding: '0 1.5rem',
  },
  loadingContainer: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: '60vh',
    gap: '1rem',
  },
  spinner: {
    fontSize: '3rem',
    color: theme.colors.primary,
    animation: 'spin 1s linear infinite',
  },
  loadingText: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: '2rem',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  title: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    fontSize: '2.25rem',
    fontWeight: '800',
    color: '#1e293b',
    marginBottom: '0.5rem',
  },
  titleIcon: {
    fontSize: '2.25rem',
    color: theme.colors.primary,
  },
  subtitle: {
    color: '#64748b',
    fontSize: '1.125rem',
  },
  refreshButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    border: '1px solid #e2e8f0',
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#475569',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  refreshIcon: {
    fontSize: '1rem',
  },
  spinningIcon: {
    fontSize: '1rem',
    animation: 'spin 1s linear infinite',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: '1.25rem',
    marginBottom: '2rem',
  },
  statCard: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.xl,
    border: '1px solid #e2e8f0',
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
  },
  statIcon: {
    fontSize: '2rem',
    color: theme.colors.primary,
  },
  statNumber: {
    fontSize: '2rem',
    fontWeight: '700',
    color: '#1e293b',
  },
  statLabel: {
    fontSize: '0.875rem',
    color: '#64748b',
  },
  errorBanner: {
    backgroundColor: '#fee2e2',
    border: '1px solid #fecaca',
    borderRadius: theme.borderRadius.lg,
    padding: '1rem',
    marginBottom: '1.5rem',
    color: '#dc2626',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  retryButton: {
    backgroundColor: '#dc2626',
    color: '#fff',
    border: 'none',
    padding: '0.5rem 1rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
  },
  searchContainer: {
    position: 'relative',
    marginBottom: '2rem',
  },
  searchIcon: {
    position: 'absolute',
    left: '1rem',
    top: '50%',
    transform: 'translateY(-50%)',
    fontSize: '1.25rem',
    color: '#94a3b8',
  },
  searchInput: {
    width: '100%',
    padding: '0.875rem 1rem 0.875rem 3rem',
    border: '2px solid #e2e8f0',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.95rem',
    outline: 'none',
    backgroundColor: '#fff',
  },
  conversationsList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  conversationCard: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.xl,
    border: '1px solid #e2e8f0',
    display: 'flex',
    gap: '1rem',
    alignItems: 'flex-start',
    position: 'relative',
  },
  conversationCardUnread: {
    borderColor: theme.colors.primary,
    backgroundColor: '#fef2f2',
    boxShadow: '0 0 0 1px rgba(239, 68, 68, 0.1)',
  },
  conversationAvatar: {
    width: '56px',
    height: '56px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.primary,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    flexShrink: 0,
    fontSize: '1.5rem',
    fontWeight: 'bold',
  },
  avatarUnread: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
  },
  avatarIcon: {
    fontSize: '1.75rem',
  },
  conversationContent: {
    flex: 1,
    minWidth: 0,
  },
  conversationHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '0.5rem',
  },
  conversationName: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#1e293b',
    margin: 0,
  },
  conversationNameUnread: {
    fontWeight: '700',
    color: theme.colors.primary,
  },
  conversationDate: {
    fontSize: '0.8rem',
    color: '#94a3b8',
  },
  conversationPreview: {
    fontSize: '0.95rem',
    color: '#64748b',
    margin: 0,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
    marginBottom: '0.5rem',
  },
  conversationPreviewUnread: {
    fontWeight: '500',
    color: '#475569',
  },
  anonymousBadge: {
    display: 'inline-block',
    backgroundColor: '#fef3c7',
    color: '#92400e',
    padding: '0.25rem 0.75rem',
    borderRadius: theme.borderRadius.full,
    fontSize: '0.75rem',
    fontWeight: '600',
  },
  unreadBadge: {
    position: 'absolute',
    top: '1rem',
    right: '1rem',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    width: '28px',
    height: '28px',
    borderRadius: '50%',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '0.8rem',
    fontWeight: '700',
    boxShadow: '0 2px 8px rgba(239, 68, 68, 0.3)',
  },
  emptyState: {
    backgroundColor: '#fff',
    padding: '4rem 2rem',
    borderRadius: theme.borderRadius.xl,
    textAlign: 'center',
    border: '2px dashed #e2e8f0',
  },
  emptyIcon: {
    fontSize: '5rem',
    marginBottom: '1rem',
  },
  emptyTitle: {
    fontSize: '1.5rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.75rem',
  },
  emptyText: {
    color: '#64748b',
    fontSize: '1rem',
    maxWidth: '600px',
    margin: '0 auto',
    lineHeight: '1.6',
  },
  infoBox: {
    backgroundColor: '#dbeafe',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.xl,
    padding: '1.5rem',
    marginTop: '2rem',
  },
  infoText: {
    color: '#1e40af',
    fontSize: '0.95rem',
    margin: 0,
    lineHeight: '1.6',
  },
};