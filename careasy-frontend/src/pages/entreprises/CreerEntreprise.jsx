// careasy-frontend/src/pages/entreprises/CreerEntreprise.jsx
import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { entrepriseApi } from '../../api/entrepriseApi';
import theme from '../../config/theme';

export default function CreerEntreprise() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [domaines, setDomaines] = useState([]);
  const [loadingDomaines, setLoadingDomaines] = useState(true);

  const [formData, setFormData] = useState({
    name: '',
    domaine_ids: [],
    ifu_number: '',
    rccm_number: '',
    pdg_full_name: '',
    pdg_full_profession: '',
    siege: '',
    certificate_number: '',
    logo: null,
    image_boutique: null,
  });

  const [previews, setPreviews] = useState({
    logo: null,
    image_boutique: null,
  });

  useEffect(() => {
    fetchDomaines();
  }, []);

  const fetchDomaines = async () => {
    try {
      setLoadingDomaines(true);
      const data = await entrepriseApi.getFormData();
      setDomaines(data.domaines || []);
    } catch (err) {
      console.error('Erreur chargement domaines:', err);
      setError('Erreur lors du chargement des domaines');
    } finally {
      setLoadingDomaines(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleDomaineToggle = (domaineId) => {
    setFormData(prev => {
      const isSelected = prev.domaine_ids.includes(domaineId);
      return {
        ...prev,
        domaine_ids: isSelected
          ? prev.domaine_ids.filter(id => id !== domaineId)
          : [...prev.domaine_ids, domaineId]
      };
    });
  };

  const handleFileChange = (e, field) => {
    const file = e.target.files[0];
    if (file) {
      setFormData(prev => ({ ...prev, [field]: file }));
      
      // Créer preview
      const reader = new FileReader();
      reader.onloadend = () => {
        setPreviews(prev => ({ ...prev, [field]: reader.result }));
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    // Validation
    if (formData.domaine_ids.length === 0) {
      setError('Veuillez sélectionner au moins un domaine');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    setLoading(true);

    try {
      const submitData = new FormData();
      
      // Ajouter tous les champs texte
      Object.keys(formData).forEach(key => {
        if (key === 'domaine_ids') {
          formData[key].forEach(id => {
            submitData.append('domaine_ids[]', id);
          });
        } else if (key !== 'logo' && key !== 'image_boutique') {
          submitData.append(key, formData[key]);
        }
      });

      // Ajouter les fichiers
      if (formData.logo) {
        submitData.append('logo', formData.logo);
      }
      if (formData.image_boutique) {
        submitData.append('image_boutique', formData.image_boutique);
      }

      await entrepriseApi.createEntreprise(submitData);
      
      setSuccess(' Entreprise créée avec succès ! Redirection en cours...');
      setTimeout(() => {
        navigate('/mes-entreprises');
      }, 2000);
      
    } catch (err) {
      console.error('Erreur création:', err);
      setError(
        err.response?.data?.message || 
        'Erreur lors de la création de l\'entreprise'
      );
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setLoading(false);
    }
  };

  if (loadingDomaines) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement du formulaire...</p>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header */}
        <div style={styles.header}>
          <Link to="/mes-entreprises" style={styles.backButton}>
            ← Retour
          </Link>
          <h1 style={styles.title}>Créer une entreprise</h1>
          <p style={styles.subtitle}>
            Remplissez le formulaire pour créer votre entreprise. 
            Elle sera soumise à validation par l'administration.
          </p>
        </div>

        {/* Messages */}
        {error && (
          <div style={styles.error}>
             {error}
          </div>
        )}

        {success && (
          <div style={styles.success}>
            {success}
          </div>
        )}

        {/* Formulaire */}
        <form onSubmit={handleSubmit} style={styles.form}>
          {/* Section 1: Informations générales */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}> Informations générales</h2>
            
            <div style={styles.formGroup}>
              <label style={styles.label}>
                Nom de l'entreprise <span style={styles.required}>*</span>
              </label>
              <input
                type="text"
                name="name"
                value={formData.name}
                onChange={handleChange}
                required
                style={styles.input}
                placeholder="Ex: Garage Auto Excellence"
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>
                Domaines d'activité <span style={styles.required}>*</span>
              </label>
              <p style={styles.hint}>Sélectionnez au moins un domaine</p>
              <div style={styles.domainesGrid}>
                {domaines.map(domaine => (
                  <button
                    key={domaine.id}
                    type="button"
                    onClick={() => handleDomaineToggle(domaine.id)}
                    style={{
                      ...styles.domaineButton,
                      ...(formData.domaine_ids.includes(domaine.id) 
                        ? styles.domaineButtonActive 
                        : {})
                    }}
                  >
                    {formData.domaine_ids.includes(domaine.id) ? '✓ ' : ''}
                    {domaine.name}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Section 2: Documents légaux */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}> Documents légaux</h2>
            
            <div style={styles.formRow}>
              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Numéro IFU <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="ifu_number"
                  value={formData.ifu_number}
                  onChange={handleChange}
                  required
                  style={styles.input}
                  placeholder="Ex: 1234567890123"
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Numéro RCCM <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="rccm_number"
                  value={formData.rccm_number}
                  onChange={handleChange}
                  required
                  style={styles.input}
                  placeholder="Ex: RB/COT/12/B/345"
                />
              </div>
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>
                Numéro de certificat <span style={styles.required}>*</span>
              </label>
              <input
                type="text"
                name="certificate_number"
                value={formData.certificate_number}
                onChange={handleChange}
                required
                style={styles.input}
                placeholder="Ex: CERT-2024-12345"
              />
            </div>
          </div>

          {/* Section 3: Dirigeant */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}> Informations du dirigeant</h2>
            
            <div style={styles.formRow}>
              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Nom complet du PDG <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="pdg_full_name"
                  value={formData.pdg_full_name}
                  onChange={handleChange}
                  required
                  style={styles.input}
                  placeholder="Ex: Jean Dupont"
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Profession du PDG <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="pdg_full_profession"
                  value={formData.pdg_full_profession}
                  onChange={handleChange}
                  required
                  style={styles.input}
                  placeholder="Ex: Ingénieur mécanicien"
                />
              </div>
            </div>
          </div>

          {/* Section 4: Localisation */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}> Localisation</h2>
            
            <div style={styles.formGroup}>
              <label style={styles.label}>Siège de l'entreprise</label>
              <input
                type="text"
                name="siege"
                value={formData.siege}
                onChange={handleChange}
                style={styles.input}
                placeholder="Ex: Cotonou, Akpakpa"
              />
            </div>
          </div>

          {/* Section 5: Médias */}
          <div style={styles.section}>
            <h2 style={styles.sectionTitle}> Médias</h2>
            
            <div style={styles.formRow}>
              <div style={styles.formGroup}>
                <label style={styles.label}>Logo de l'entreprise</label>
                <p style={styles.hint}>Format: JPG, PNG (max 2MB)</p>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => handleFileChange(e, 'logo')}
                  style={styles.fileInput}
                />
                {previews.logo && (
                  <div style={styles.preview}>
                    <img src={previews.logo} alt="Preview logo" style={styles.previewImage} />
                  </div>
                )}
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>Image de la boutique</label>
                <p style={styles.hint}>Format: JPG, PNG (max 2MB)</p>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => handleFileChange(e, 'image_boutique')}
                  style={styles.fileInput}
                />
                {previews.image_boutique && (
                  <div style={styles.preview}>
                    <img src={previews.image_boutique} alt="Preview boutique" style={styles.previewImage} />
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Boutons d'action */}
          <div style={styles.actions}>
            <Link to="/mes-entreprises" style={styles.cancelButton}>
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
              {loading ? 'Création en cours...' : ' Créer l\'entreprise'}
            </button>
          </div>
        </form>

        {/* Info box */}
        <div style={styles.infoBox}>
          <div style={styles.infoIcon}></div>
          <div>
            <h3 style={styles.infoTitle}>À savoir</h3>
            <p style={styles.infoText}>
              Votre entreprise sera soumise à validation par l'administration. 
              Vous recevrez une notification par email une fois qu'elle sera validée ou rejetée.
              Les champs marqués d'un <span style={styles.required}>*</span> sont obligatoires.
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
  fileInput: {
    width: '100%',
    padding: '0.875rem',
    border: `2px dashed ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    cursor: 'pointer',
  },
  domainesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
    gap: '0.75rem',
  },
  domaineButton: {
    padding: '0.875rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.secondary,
    color: theme.colors.text.primary,
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.3s',
    textAlign: 'left',
  },
  domaineButtonActive: {
    backgroundColor: theme.colors.primary,
    color: theme.colors.text.white,
    borderColor: theme.colors.primary,
  },
  preview: {
    marginTop: '1rem',
    borderRadius: theme.borderRadius.md,
    overflow: 'hidden',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  previewImage: {
    width: '100%',
    maxHeight: '200px',
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
    lineHeight: '1.6',
  },
};