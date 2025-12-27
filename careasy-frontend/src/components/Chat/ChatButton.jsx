// careasy-frontend/src/components/Chat/ChatButton.jsx - VERSION CORRIGÉE AVEC DEBUG
import { useState } from 'react';
import { FiMessageCircle } from 'react-icons/fi';
import ChatModal from './ChatModal';
import theme from '../../config/theme';

export default function ChatButton({ 
  receiverId, 
  receiverName = "l'entreprise",
  buttonText = "Discuter",
  variant = "primary" // primary, secondary, float
}) {
  const [isOpen, setIsOpen] = useState(false);

  // ✅ DEBUG: Vérifier que receiverId est bien défini
  const handleOpenChat = () => {
    console.log('🔍 ChatButton - receiverId:', receiverId);
    console.log('🔍 ChatButton - receiverName:', receiverName);
    
    if (!receiverId) {
      console.error('❌ ERREUR: receiverId est NULL ou undefined !');
      alert('Erreur: Impossible d\'identifier le destinataire. Veuillez réessayer.');
      return;
    }
    
    setIsOpen(true);
  };

  const getButtonStyle = () => {
    if (variant === 'float') {
      return {
        ...styles.floatButton,
        position: 'fixed',
        bottom: '100px',
        right: '30px',
        zIndex: 999,
        boxShadow: '0 8px 24px rgba(239, 68, 68, 0.3)',
      };
    }
    
    if (variant === 'secondary') {
      return styles.secondaryButton;
    }
    
    return styles.primaryButton;
  };

  return (
    <>
      <button 
        onClick={handleOpenChat}
        style={getButtonStyle()}
        className="chat-button"
      >
        <FiMessageCircle style={styles.icon} />
        {variant !== 'float' && <span>{buttonText}</span>}
      </button>

      {isOpen && (
        <ChatModal
          receiverId={receiverId}
          receiverName={receiverName}
          onClose={() => setIsOpen(false)}
        />
      )}

      <style>{`
        .chat-button {
          transition: all 0.3s ease;
        }
        .chat-button:hover {
          transform: translateY(-2px);
          box-shadow: 0 12px 32px rgba(239, 68, 68, 0.4);
        }
      `}</style>
    </>
  );
}

const styles = {
  primaryButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    border: 'none',
    padding: '0.875rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.95rem',
    fontWeight: '600',
    cursor: 'pointer',
    boxShadow: '0 4px 12px rgba(239, 68, 68, 0.2)',
  },
  secondaryButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: 'transparent',
    color: theme.colors.primary,
    border: `2px solid ${theme.colors.primary}`,
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    fontSize: '0.875rem',
    fontWeight: '600',
    cursor: 'pointer',
  },
  floatButton: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    border: 'none',
    width: '60px',
    height: '60px',
    borderRadius: '50%',
    cursor: 'pointer',
    fontSize: '1.75rem',
  },
  icon: {
    fontSize: '1.25rem',
  },
};