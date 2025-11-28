// careasy-frontend/src/pages/services/CreerService.jsx
import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { serviceApi } from '../../api/serviceApi';
import { entrepriseApi } from '../../api/entrepriseApi';
import theme from '../../config/theme';

export default function CreerService() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const entrepriseIdParam = searchParams.get('entreprise');

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [entreprises, setEntreprises] = useState([]);
  const [loadingEntreprises, setLoadingEntreprises] = useState(true);
  const [selectedEntreprise, setSelectedEntreprise] = useState(null);

  const [formData, setFormData] = useState({
    entreprise_id: entrepriseIdParam || '',
    domaine_id: '',
    name: '',
    price: '',
    descriptions: '',
    start_time: '',
    end_time: '',
    is_open_24h: false,
    medias: [],
  });

  const [previews, setPreviews] = useState([]);

  useEffect(() => {
    fetchEntreprises();
  }, []);

  useEffect(() => {
    if (formData.entreprise_id) {
      const entreprise = entreprises.find(e => e.id === parseInt(formData.entreprise_id));
      setSelectedEntreprise(entreprise || null);
    }
  }, [formData.entreprise_id, entreprises]);

  const fetchEntreprises = async () => {
    try {
      setLoadingEntreprises(true);
      const data = await entrepriseApi.getMesEntreprises();
      
      // Filtrer seulement les entreprises validées
      const validatedEntreprises = data.filter(e => e.status === 'validated');
      setEntreprises(validatedEntreprises);
      
      if (validatedEntreprises.length === 0) {
        setError('Vous devez avoir au moins une entreprise validée pour créer un service.');
      }
    } catch (err) {
      console.error('Erreur chargement entreprises:', err);
      setError('Erreur lors du chargement des entreprises');
    } finally {
      setLoadingEntreprises(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handleFilesChange = (e) => {
    const files = Array.from(e.target.files);
    setFormData(prev => ({ ...prev, medias: files }));
    
    // Créer previews
    const newPreviews = files.map(file => {
      const reader = new FileReader();
      return new Promise((resolve) => {
        reader.onloadend = () => resolve(reader.result);
        reader.readAsDataURL(file);
      });
    });
    
    Promise.all(newPreviews).then(setPreviews);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    // Validation
    if (!formData.entreprise_id) {
      setError('Veuillez sélectionner une entreprise');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    if (!formData.domaine_id) {
      setError('Veuillez sélectionner un domaine');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    setLoading(true);

    try {
      const submitData = new FormData();
      
      // Ajouter tous les champs
      Object.keys(formData).forEach(key => {
        if (key === 'medias') {
          formData[key].forEach(file => {
            submitData.append('medias[]', file);
          });
        } else if (key === 'is_open_24h') {
          submitData.append(key, formData[key] ? '1' : '0');
        } else {
          submitData.append(key, formData[key]);
        }
      });

      await serviceApi.createService(submitData);
      
      setSuccess('✅ Service créé avec succès ! Redirection en cours...');
      setTimeout(() => {
        navigate('/mes-services');
      }, 2000);
      
    } catch (err) {
      console.error('Erreur création:', err);
      setError(
        err.response?.data?.message || 
        'Erreur lors de la création du service'
      );
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setLoading(false);
    }
  };

  if (loadingEntreprises) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement du formulaire...</p>
        </div>
      </div>
    );
  }

  if (entreprises.length === 0) {
    return (
      <div style={styles.container}>
        <div style={styles.emptyState}>
          <div style={styles.emptyIcon}>⚠️</div>
          <h2 style={styles.emptyTitle}>Aucune entreprise validée</h2>
          <p style={styles.emptyText}>
            Vous devez avoir au moins une entreprise validée pour créer un service.
          </p>
          <Link to="/mes-entreprises" style={styles.emptyButton}>
            Voir mes entreprises
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
          <Link to="/mes-services" style={styles.backButton}>
            ← Retour
          </Link>
          <h1 style={styles.title}>Créer un service</h1>
          <p style={styles.subtitle}>
            Ajoutez un nouveau service à l'une de vos entreprises validées
          </p>
        </div>

        {/* Messages */}
        {error && (
          <div style={styles.error}>
            ⚠️ {error}
          </div>
        )}

        {success && (
          <div style={styles.success}>
            {success}
          </div>
        )}

        {/* Formulaire */}
        <form onSubmit={handleSubmit} style={styles.form}>
          {/* Section 1: Entreprise */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}>🏢 Entreprise</h2>
            
            <div style={styles.formGroup}>
              <label style={styles.label}>
                Sélectionnez l'entreprise <span style={styles.required}>*</span>
              </label>
              <select
                name="entreprise_id"
                value={formData.entreprise_id}
                onChange={handleChange}
                required
                style={styles.select}
              >
                <option value="">-- Choisir une entreprise --</option>
                {entreprises.map(entreprise => (
                  <option key={entreprise.id} value={entreprise.id}>
                    {entreprise.name}
                  </option>
                ))}
              </select>
            </div>

            {selectedEntreprise && (
              <div style={styles.entreprisePreview}>
                {selectedEntreprise.logo && (
                  <img 
                    src={`${import.meta.env.VITE_API_URL}/storage/${selectedEntreprise.logo}`}
                    alt={selectedEntreprise.name}
                    style={styles.entrepriseLogo}
                  />
                )}
                <div>
                  <div style={styles.entrepriseName}>{selectedEntreprise.name}</div>
                  <div style={styles.entrepriseInfo}>
                    📍 {selectedEntreprise.siege || 'Localisation non renseignée'}
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Section 2: Domaine */}
          {selectedEntreprise && (
            <div style={styles.section}>
              <h2 style={styles.sectionTitle}>🏷️ Domaine d'activité</h2>
              
              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Sélectionnez le domaine <span style={styles.required}>*</span>
                </label>
                <p style={styles.hint}>
                  Choisissez parmi les domaines de votre entreprise
                </p>
                <select
                  name="domaine_id"
                  value={formData.domaine_id}
                  onChange={handleChange}
                  required
                  style={styles.select}
                >
                  <option value="">-- Choisir un domaine --</option>
                  {selectedEntreprise.domaines?.map(domaine => (
                    <option key={domaine.id} value={domaine.id}>
                      {domaine.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          )}

          {/* Section 3: Informations service */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}>📋 Informations du service</h2>
            
            <div style={styles.formGroup}>
              <label style={styles.label}>
                Nom du service <span style={styles.required}>*</span>
              </label>
              <input
                type="text"
                name="name"
                value={formData.name}
                onChange={handleChange}
                required
                style={styles.input}
                placeholder="Ex: Vidange complète, Réparation carrosserie..."
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Description détaillée</label>
              <textarea
                name="descriptions"
                value={formData.descriptions}
                onChange={handleChange}
                style={styles.textarea}
                rows="5"
                placeholder="Décrivez votre service en détail..."
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>Prix (FCFA)</label>
              <p style={styles.hint}>Laissez vide pour "Prix sur demande"</p>
              <input
                type="number"
                name="price"
                value={formData.price}
                onChange={handleChange}
                style={styles.input}
                placeholder="Ex: 25000"
                min="0"
              />
            </div>
          </div>

          {/* Section 4: Horaires */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}>🕐 Horaires d'ouverture</h2>
            
            <div style={styles.formGroup}>
              <label style={styles.checkboxLabel}>
                <input
                  type="checkbox"
                  name="is_open_24h"
                  checked={formData.is_open_24h}
                  onChange={handleChange}
                  style={styles.checkbox}
                />
                <span>Service disponible 24h/24</span>
              </label>
            </div>

            {!formData.is_open_24h && (
              <div style={styles.formRow}>
                <div style={styles.formGroup}>
                  <label style={styles.label}>Heure d'ouverture</label>
                  <input
                    type="time"
                    name="start_time"
                    value={formData.start_time}
                    onChange={handleChange}
                    style={styles.input}
                  />
                </div>

                <div style={styles.formGroup}>
                  <label style={styles.label}>Heure de fermeture</label>
                  <input
                    type="time"
                    name="end_time"
                    value={formData.end_time}
                    onChange={handleChange}
                    style={styles.input}
                  />
                </div>
              </div>
            )}
          </div>

          {/* Section 5: Médias */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}>📸 Photos du service</h2>
            
            <div style={styles.formGroup}>
              <label style={styles.label}>Images (optionnel)</label>
              <p style={styles.hint}>
                Format: JPG, PNG, WEBP (max 2MB par image)
              </p>
              <input
                type="file"
                accept="image/*"
                multiple
                onChange={handleFilesChange}
                style={styles.fileInput}
              />
              
              {previews.length > 0 && (
                <div style={styles.previewsGrid}>
                  {previews.map((preview, index) => (
                    <div key={index} style={styles.previewItem}>
                      <img src={preview} alt={`Preview ${index + 1}`} style={styles.previewImage} />
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Boutons d'action */}
          <div style={styles.actions}>
            <Link to="/mes-services" style={styles.cancelButton}>
              Annuler
            </Link>
            <button 
              type="submit" 
              disabled={loading}
              style={{
                ...styles.submitButton,
                opacity: loading ? 0.6 : 1,
                cursor: loading ? 'not-allowed' : 'pointer',
              }}
            >
              {loading ? 'Création en cours...' : '✅ Créer le service'}
            </button>
          </div>
        </form>

        {/* Info box */}
        <div style={styles.infoBox}>
          <div style={styles.infoIcon}>💡</div>
          <div>
            <h3 style={styles.infoTitle}>Conseils</h3>
            <p style={styles.infoText}>
              • Soyez précis dans la description de votre service<br/>
              • Ajoutez des photos de qualité pour attirer les clients<br/>
              • Indiquez un prix juste ou proposez un devis personnalisé<br/>
              • Les champs marqués d'un <span style={styles.required}>*</span> sont obligatoires
            </p>
          </div>
        </div>
      </div>

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
    maxWidth: '900px',
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
  emptyState: {
    backgroundColor: theme.colors.secondary,
    padding: '4rem 2rem',
    borderRadius: theme.borderRadius.xl,
    textAlign: 'center',
    border: `2px solid ${theme.colors.primaryLight}`,
    maxWidth: '600px',
    margin: '4rem auto',
  },
  emptyIcon: {
    fontSize: '5rem',
    marginBottom: '1rem',
  },
  emptyTitle: {
    fontSize: '1.75rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.75rem',
  },
  emptyText: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
    marginBottom: '2rem',
    lineHeight: '1.6',
  },
  emptyButton: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    padding: '1rem 2rem',
    borderRadius: theme.borderRadius.lg,
    textDecoration: 'none',
    fontWeight: '600',
    display: 'inline-block',
    boxShadow: theme.shadows.md,
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
  title: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.5rem',
  },
  subtitle: {
    color: theme.colors.text.secondary,
    fontSize: '1.125rem',
    lineHeight: '1.6',
  },
  error: {
    backgroundColor: '#FEE2E2',
    color: theme.colors.error,
    padding: '1rem',
    borderRadius: theme.borderRadius.md,
    marginBottom: '2rem',
    border: `2px solid ${theme.colors.error}`,
  },
  success: {
    backgroundColor: '#D1FAE5',
    color: theme.colors.success,
    padding: '1rem',
    borderRadius: theme.borderRadius.md,
    marginBottom: '2rem',
    border: `2px solid ${theme.colors.success}`,
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: '2rem',
  },
  section: {
    backgroundColor: theme.colors.secondary,
    padding: '2rem',
    borderRadius: theme.borderRadius.xl,
    border: `2px solid ${theme.colors.primaryLight}`,
    boxShadow: theme.shadows.sm,
  },
  sectionTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: theme.colors.primary,
    marginBottom: '1.5rem',
  },
  formGroup: {
    marginBottom: '1.5rem',
  },
  formRow: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '1.5rem',
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
  hint: {
    color: theme.colors.text.secondary,
    fontSize: '0.875rem',
    marginBottom: '0.75rem',
  },
  input: {
    width: '100%',
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    transition: 'all 0.3s',
    outline: 'none',
  },
  select: {
    width: '100%',
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    transition: 'all 0.3s',
    outline: 'none',
    backgroundColor: theme.colors.secondary,
    cursor: 'pointer',
  },
  textarea: {
    width: '100%',
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    transition: 'all 0.3s',
    outline: 'none',
    fontFamily: 'inherit',
    resize: 'vertical',
  },
  fileInput: {
    width: '100%',
    padding: '0.875rem',
    border: `2px dashed ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    cursor: 'pointer',
  },
  checkboxLabel: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    cursor: 'pointer',
    fontSize: '1rem',
    fontWeight: '600',
  },
  checkbox: {
    width: '20px',
    height: '20px',
    cursor: 'pointer',
  },
  entreprisePreview: {
    backgroundColor: theme.colors.background,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.md,
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  entrepriseLogo: {
    width: '60px',
    height: '60px',
    borderRadius: theme.borderRadius.md,
    objectFit: 'cover',
  },
  entrepriseName: {
    fontWeight: 'bold',
    color: theme.colors.text.primary,
    marginBottom: '0.25rem',
  },
  entrepriseInfo: {
    color: theme.colors.text.secondary,
    fontSize: '0.9rem',
  },
  previewsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))',
    gap: '1rem',
    marginTop: '1rem',
  },
  previewItem: {
    borderRadius: theme.borderRadius.md,
    overflow: 'hidden',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  previewImage: {
    width: '100%',
    height: '150px',
    objectFit: 'cover',
  },
  actions: {
    display: 'flex',
    gap: '1rem',
    justifyContent: 'flex-end',
    paddingTop: '1rem',
  },
  cancelButton: {
    padding: '0.875rem 2rem',
    border: `2px solid ${theme.colors.primary}`,
    borderRadius: theme.borderRadius.md,
    backgroundColor: 'transparent',
    color: theme.colors.primary,
    fontWeight: '600',
    textDecoration: 'none',
    display: 'inline-block',
    transition: 'all 0.3s',
  },
  submitButton: {
    padding: '0.875rem 2rem',
    border: 'none',
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    fontWeight: '600',
    fontSize: '1rem',
    cursor: 'pointer',
    boxShadow: theme.shadows.md,
    transition: 'all 0.3s',
  },
  infoBox: {
    backgroundColor: '#DBEAFE',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: '2px solid #3B82F6',
    display: 'flex',
    gap: '1rem',
    marginTop: '2rem',
  },
  infoIcon: {
    fontSize: '2rem',
    flexShrink: 0,
  },
  infoTitle: {
    fontWeight: 'bold',
    color: '#1E40AF',
    marginBottom: '0.5rem',
  },
  infoText: {
    color: '#1E40AF',
    fontSize: '0.95rem',
    lineHeight: '1.8',
  },
};