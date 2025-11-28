// careasy-frontend/src/pages/admin/AdminEntrepriseDetails.jsx
import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { adminApi } from '../../api/adminApi';
import theme from '../../config/theme';

export default function AdminEntrepriseDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  
  const [entreprise, setEntreprise] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  
  const [showApproveModal, setShowApproveModal] = useState(false);
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [adminNote, setAdminNote] = useState('');

  useEffect(() => {
    fetchEntreprise();
  }, [id]);

  const fetchEntreprise = async () => {
    try {
      setLoading(true);
      const data = await adminApi.getEntreprise(id);
      setEntreprise(data);
      setError('');
    } catch (err) {
      console.error('Erreur chargement entreprise:', err);
      setError('Entreprise non trouvée');
      setTimeout(() => navigate('/admin/entreprises'), 2000);
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async () => {
    setActionLoading(true);
    try {
      await adminApi.approveEntreprise(id, adminNote || null);
      alert(' Entreprise validée avec succès !');
      navigate('/admin/entreprises');
    } catch (err) {
      alert('Erreur lors de la validation: ' + (err.response?.data?.message || err.message));
    } finally {
      setActionLoading(false);
      setShowApproveModal(false);
    }
  };

  const handleReject = async () => {
    if (!adminNote.trim()) {
      alert('⚠️ Veuillez fournir une raison de rejet');
      return;
    }
    
    setActionLoading(true);
    try {
      await adminApi.rejectEntreprise(id, adminNote);
      alert('❌ Entreprise rejetée');
      navigate('/admin/entreprises');
    } catch (err) {
      alert('Erreur lors du rejet: ' + (err.response?.data?.message || err.message));
    } finally {
      setActionLoading(false);
      setShowRejectModal(false);
    }
  };

  const getStatusBadge = (status) => {
    const badges = {
      pending: { emoji: '🟡', text: 'En attente', color: theme.colors.warning, bg: '#FEF3C7' },
      validated: { emoji: '✅', text: 'Validée', color: theme.colors.success, bg: '#D1FAE5' },
      rejected: { emoji: '❌', text: 'Rejetée', color: theme.colors.error, bg: '#FEE2E2' },
    };
    const badge = badges[status] || badges.pending;
    
    return (
      <div style={{...styles.statusBanner, backgroundColor: badge.bg, borderColor: badge.color}}>
        <span style={styles.statusEmoji}>{badge.emoji}</span>
        <div>
          <div style={{...styles.statusText, color: badge.color}}>{badge.text}</div>
          {status === 'rejected' && entreprise?.admin_note && (
            <div style={styles.statusNote}>
              <strong>Raison du rejet :</strong> {entreprise.admin_note}
            </div>
          )}
        </div>
      </div>
    );
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement des détails...</p>
        </div>
      </div>
    );
  }

  if (error || !entreprise) {
    return (
      <div style={styles.container}>
        <div style={styles.errorContainer}>
          <div style={styles.errorIcon}></div>
          <h2 style={styles.errorTitle}>{error || 'Entreprise introuvable'}</h2>
          <Link to="/admin/entreprises" style={styles.errorButton}>
            ← Retour à la liste
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header */}
        <div style={styles.header}>
          <Link to="/admin/entreprises" style={styles.backButton}>
            ← Retour à la liste
          </Link>
          <div style={styles.headerTop}>
            <div>
              <h1 style={styles.title}>{entreprise.name}</h1>
              <p style={styles.subtitle}>
                Demande créée le {new Date(entreprise.created_at).toLocaleDateString('fr-FR', {
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric'
                })}
              </p>
            </div>
            
            {/* Actions si en attente */}
            {entreprise.status === 'pending' && (
              <div style={styles.actionButtons}>
                <button 
                  onClick={() => setShowRejectModal(true)}
                  style={styles.rejectButton}
                >
                   Rejeter
                </button>
                <button 
                  onClick={() => setShowApproveModal(true)}
                  style={styles.approveButton}
                >
                   Valider
                </button>
              </div>
            )}
          </div>
        </div>

        {/* Statut */}
        {getStatusBadge(entreprise.status)}

        {/* Grid principal */}
        <div style={styles.mainGrid}>
          {/* Colonne gauche */}
          <div style={styles.leftColumn}>
            {/* Carte Médias */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}> Médias</h2>
              <div style={styles.mediaGrid}>
                {entreprise.logo ? (
                  <div style={styles.mediaItem}>
                    <p style={styles.mediaLabel}>Logo</p>
                    <img 
                      src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                      alt="Logo"
                      style={styles.mediaImage}
                    />
                  </div>
                ) : (
                  <div style={styles.mediaPlaceholder}>
                    <div style={styles.placeholderIcon}></div>
                    <p style={styles.placeholderText}>Aucun logo</p>
                  </div>
                )}

                {entreprise.image_boutique ? (
                  <div style={styles.mediaItem}>
                    <p style={styles.mediaLabel}>Boutique</p>
                    <img 
                      src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.image_boutique}`}
                      alt="Boutique"
                      style={styles.mediaImage}
                    />
                  </div>
                ) : (
                  <div style={styles.mediaPlaceholder}>
                    <div style={styles.placeholderIcon}></div>
                    <p style={styles.placeholderText}>Aucune image</p>
                  </div>
                )}
              </div>
            </div>

            {/* Carte Domaines */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}> Domaines d'activité</h2>
              {entreprise.domaines && entreprise.domaines.length > 0 ? (
                <div style={styles.domainesGrid}>
                  {entreprise.domaines.map((domaine) => (
                    <div key={domaine.id} style={styles.domaineTag}>
                      {domaine.name}
                    </div>
                  ))}
                </div>
              ) : (
                <p style={styles.emptyText}>Aucun domaine défini</p>
              )}
            </div>
          </div>

          {/* Colonne droite */}
          <div style={styles.rightColumn}>
            {/* Carte Prestataire */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}> Prestataire</h2>
              <div style={styles.infoList}>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>Nom</span>
                  <span style={styles.infoValue}>
                    {entreprise.prestataire?.name || '-'}
                  </span>
                </div>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>Email</span>
                  <span style={styles.infoValue}>
                    {entreprise.prestataire?.email || '-'}
                  </span>
                </div>
              </div>
            </div>

            {/* Carte Informations */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}> Informations</h2>
              <div style={styles.infoList}>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>Nom entreprise</span>
                  <span style={styles.infoValue}>{entreprise.name}</span>
                </div>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>Siège</span>
                  <span style={styles.infoValue}>{entreprise.siege || 'Non renseigné'}</span>
                </div>
              </div>
            </div>

            {/* Carte Documents */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}> Documents légaux</h2>
              <div style={styles.infoList}>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>IFU</span>
                  <span style={styles.infoValue}>
                    <code style={styles.code}>{entreprise.ifu_number}</code>
                  </span>
                </div>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>RCCM</span>
                  <span style={styles.infoValue}>
                    <code style={styles.code}>{entreprise.rccm_number}</code>
                  </span>
                </div>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>Certificat</span>
                  <span style={styles.infoValue}>
                    <code style={styles.code}>{entreprise.certificate_number}</code>
                  </span>
                </div>
              </div>
            </div>

            {/* Carte Dirigeant */}
            <div style={styles.card}>
              <h2 style={styles.cardTitle}> Dirigeant</h2>
              <div style={styles.infoList}>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>Nom complet</span>
                  <span style={styles.infoValue}>{entreprise.pdg_full_name}</span>
                </div>
                <div style={styles.infoItem}>
                  <span style={styles.infoLabel}>Profession</span>
                  <span style={styles.infoValue}>{entreprise.pdg_full_profession}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal Validation */}
      {showApproveModal && (
        <div style={styles.modalOverlay} onClick={() => setShowApproveModal(false)}>
          <div style={styles.modal} onClick={(e) => e.stopPropagation()}>
            <h3 style={styles.modalTitle}> Valider l'entreprise</h3>
            <p style={styles.modalText}>
              Confirmez-vous la validation de <strong>{entreprise.name}</strong> ?
            </p>
            <div style={styles.formGroup}>
              <label style={styles.label}>Note admin (optionnelle)</label>
              <textarea
                value={adminNote}
                onChange={(e) => setAdminNote(e.target.value)}
                style={styles.textarea}
                rows="3"
                placeholder="Commentaire pour le prestataire..."
              />
            </div>
            <div style={styles.modalActions}>
              <button 
                onClick={() => setShowApproveModal(false)}
                style={styles.cancelModalButton}
              >
                Annuler
              </button>
              <button 
                onClick={handleApprove}
                disabled={actionLoading}
                style={{...styles.approveButton, opacity: actionLoading ? 0.6 : 1}}
              >
                {actionLoading ? 'Validation...' : 'Confirmer'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Rejet */}
      {showRejectModal && (
        <div style={styles.modalOverlay} onClick={() => setShowRejectModal(false)}>
          <div style={styles.modal} onClick={(e) => e.stopPropagation()}>
            <h3 style={styles.modalTitle}> Rejeter l'entreprise</h3>
            <p style={styles.modalText}>
              Vous êtes sur le point de rejeter <strong>{entreprise.name}</strong>.
            </p>
            <div style={styles.formGroup}>
              <label style={styles.label}>
                Raison du rejet <span style={styles.required}>*</span>
              </label>
              <textarea
                value={adminNote}
                onChange={(e) => setAdminNote(e.target.value)}
                style={styles.textarea}
                rows="4"
                placeholder="Expliquez pourquoi cette entreprise est rejetée..."
                required
              />
              <small style={styles.hint}>
                Cette note sera envoyée au prestataire par email
              </small>
            </div>
            <div style={styles.modalActions}>
              <button 
                onClick={() => setShowRejectModal(false)}
                style={styles.cancelModalButton}
              >
                Annuler
              </button>
              <button 
                onClick={handleReject}
                disabled={actionLoading || !adminNote.trim()}
                style={{
                  ...styles.rejectButton, 
                  opacity: (actionLoading || !adminNote.trim()) ? 0.6 : 1
                }}
              >
                {actionLoading ? 'Rejet...' : 'Confirmer le rejet'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* CSS Animations */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: theme.colors.background,
    paddingTop: '2rem',
    paddingBottom: '4rem',
  },
  content: {
    maxWidth: '1200px',
    margin: '0 auto',
    padding: '0 1rem',
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
    width: '50px',
    height: '50px',
    border: `4px solid ${theme.colors.primaryLight}`,
    borderTop: `4px solid ${theme.colors.primary}`,
    borderRadius: '50%',
    animation: 'spin 1s linear infinite',
  },
  loadingText: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
  },
  errorContainer: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: '60vh',
    gap: '1.5rem',
  },
  errorIcon: {
    fontSize: '5rem',
  },
  errorTitle: {
    fontSize: '1.75rem',
    color: theme.colors.text.primary,
  },
  errorButton: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '1rem 2rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
  },
  header: {
    marginBottom: '2rem',
  },
  backButton: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    marginBottom: '1rem',
    display: 'inline-block',
  },
  headerTop: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: '1rem',
    flexWrap: 'wrap',
  },
  title: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  subtitle: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
  },
  actionButtons: {
    display: 'flex',
    gap: '1rem',
    flexWrap: 'wrap',
  },
  approveButton: {
    backgroundColor: theme.colors.success,
    color: '#fff',
    padding: '0.875rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    border: 'none',
    fontWeight: '600',
    cursor: 'pointer',
    boxShadow: theme.shadows.md,
    transition: 'all 0.3s',
  },
  rejectButton: {
    backgroundColor: theme.colors.error,
    color: '#fff',
    padding: '0.875rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    border: 'none',
    fontWeight: '600',
    cursor: 'pointer',
    boxShadow: theme.shadows.md,
    transition: 'all 0.3s',
  },
  statusBanner: {
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '2rem',
    display: 'flex',
    gap: '1rem',
    alignItems: 'flex-start',
    border: '2px solid',
  },
  statusEmoji: {
    fontSize: '2rem',
    flexShrink: 0,
  },
  statusText: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    marginBottom: '0.25rem',
  },
  statusNote: {
    fontSize: '0.95rem',
    color: theme.colors.text.secondary,
    marginTop: '0.5rem',
  },
  mainGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))',
    gap: '2rem',
  },
  leftColumn: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  rightColumn: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  card: {
    backgroundColor: theme.colors.secondary,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.sm,
  },
  cardTitle: {
    fontSize: '1.25rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '1rem',
  },
  mediaGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: '1rem',
  },
  mediaItem: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  mediaLabel: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: theme.colors.text.secondary,
  },
  mediaImage: {
    width: '100%',
    height: '200px',
    objectFit: 'cover',
    borderRadius: theme.borderRadius.md,
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  mediaPlaceholder: {
    width: '100%',
    height: '200px',
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.md,
    border: `2px dashed ${theme.colors.primaryLight}`,
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '0.5rem',
  },
  placeholderIcon: {
    fontSize: '3rem',
  },
  placeholderText: {
    color: theme.colors.text.secondary,
    fontSize: '0.875rem',
  },
  domainesGrid: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '0.75rem',
  },
  domaineTag: {
    backgroundColor: theme.colors.primaryLight,
    color: theme.colors.primary,
    padding: '0.5rem 1rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    fontWeight: '600',
  },
  emptyText: {
    color: theme.colors.text.secondary,
    textAlign: 'center',
    padding: '2rem 0',
  },
  infoList: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  infoItem: {
    display: 'flex',
    justifyContent: 'space-between',
    gap: '1rem',
    paddingBottom: '1rem',
    borderBottom: `1px solid ${theme.colors.primaryLight}`,
  },
  infoLabel: {
    fontWeight: '600',
    color: theme.colors.text.secondary,
    flex: '0 0 auto',
  },
  infoValue: {
    color: theme.colors.text.primary,
    textAlign: 'right',
    flex: 1,
    wordBreak: 'break-word',
  },
  code: {
    backgroundColor: theme.colors.background,
    padding: '0.25rem 0.5rem',
    borderRadius: theme.borderRadius.sm,
    fontSize: '0.875rem',
    fontFamily: 'monospace',
  },
  modalOverlay: {
    position: 'fixed',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 1000,
    padding: '1rem',
  },
  modal: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.xl,
    maxWidth: '500px',
    width: '100%',
    boxShadow: theme.shadows.xl,
  },
  modalTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '1rem',
  },
  modalText: {
    color: theme.colors.text.secondary,
    marginBottom: '1.5rem',
    lineHeight: '1.6',
  },
  formGroup: {
    marginBottom: '1.5rem',
  },
  label: {
    display: 'block',
    fontWeight: '600',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  required: {
    color: theme.colors.error,
  },
  textarea: {
    width: '100%',
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    fontFamily: 'inherit',
    resize: 'vertical',
    outline: 'none',
  },
  hint: {
    color: theme.colors.text.secondary,
    fontSize: '0.85rem',
    marginTop: '0.5rem',
    display: 'block',
  },
  modalActions: {
    display: 'flex',
    gap: '1rem',
    justifyContent: 'flex-end',
  },
  cancelModalButton: {
    backgroundColor: 'transparent',
    color: theme.colors.text.primary,
    padding: '0.875rem 1.75rem',
    borderRadius: theme.borderRadius.lg,
    border: `2px solid ${theme.colors.primaryLight}`,
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.3s',
  },
};