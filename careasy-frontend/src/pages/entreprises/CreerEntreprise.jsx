// careasy-frontend/src/pages/entreprises/CreerEntreprise.jsx
import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { entrepriseApi } from '../../api/entrepriseApi';
import theme from '../../config/theme';

const STEPS = [
  { id: 1, title: 'Informations générales', icon: '📋' },
  { id: 2, title: 'Documents légaux', icon: '📄' },
  { id: 3, title: 'Dirigeant', icon: '👤' },
  { id: 4, title: 'Localisation & Médias', icon: '📍' },
  { id: 5, title: 'Résumé', icon: '✅' }
];

export default function CreerEntreprise() {
  const navigate = useNavigate();
  const [currentStep, setCurrentStep] = useState(1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [domaines, setDomaines] = useState([]);

  const [formData, setFormData] = useState({
    name: '',
    domaine_ids: [],
    ifu_number: '',
    ifu_file: null,
    rccm_number: '',
    rccm_file: null,
    certificate_number: '',
    certificate_file: null,
    pdg_full_name: '',
    pdg_full_profession: '',
    role_user: '',
    siege: '',
    logo: null,
    image_boutique: null,
  });

  const [previews, setPreviews] = useState({
    logo: null,
    image_boutique: null,
    ifu_file: null,
    rccm_file: null,
    certificate_file: null,
  });

  useEffect(() => {
    fetchDomaines();
  }, []);

  const fetchDomaines = async () => {
    try {
      const data = await entrepriseApi.getFormData();
      setDomaines(data.domaines || []);
    } catch (err) {
      setError('Erreur lors du chargement des domaines');
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
      
      const reader = new FileReader();
      reader.onloadend = () => {
        setPreviews(prev => ({ ...prev, [field]: reader.result }));
      };
      reader.readAsDataURL(file);
    }
  };

  const validateStep = () => {
    setError('');
    
    switch (currentStep) {
      case 1:
        if (!formData.name.trim()) {
          setError('Le nom de l\'entreprise est obligatoire');
          return false;
        }
        if (formData.domaine_ids.length === 0) {
          setError('Sélectionnez au moins un domaine');
          return false;
        }
        break;
      
      case 2:
        if (!formData.ifu_number.trim()) {
          setError('Le numéro IFU est obligatoire');
          return false;
        }
        if (!formData.ifu_file) {
          setError('Le fichier IFU est obligatoire');
          return false;
        }
        if (!formData.rccm_number.trim()) {
          setError('Le numéro RCCM est obligatoire');
          return false;
        }
        if (!formData.rccm_file) {
          setError('Le fichier RCCM est obligatoire');
          return false;
        }
        if (!formData.certificate_number.trim()) {
          setError('Le numéro de certificat est obligatoire');
          return false;
        }
        if (!formData.certificate_file) {
          setError('Le fichier certificat est obligatoire');
          return false;
        }
        break;
      
      case 3:
        if (!formData.pdg_full_name.trim()) {
          setError('Le nom du dirigeant est obligatoire');
          return false;
        }
        if (!formData.pdg_full_profession.trim()) {
          setError('La profession du dirigeant est obligatoire');
          return false;
        }
        if (!formData.role_user.trim()) {
          setError('Le rôle dans l\'entreprise est obligatoire');
          return false;
        }
        break;
      
      case 4:
        // Optionnel, pas de validation
        break;
    }
    
    return true;
  };

  const nextStep = () => {
    if (validateStep()) {
      setCurrentStep(prev => Math.min(prev + 1, 5));
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const prevStep = () => {
    setCurrentStep(prev => Math.max(prev - 1, 1));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSubmit = async () => {
    setLoading(true);
    setError('');

    try {
      const submitData = new FormData();
      
      // Texte
      Object.keys(formData).forEach(key => {
        if (key === 'domaine_ids') {
          formData[key].forEach(id => submitData.append('domaine_ids[]', id));
        } else if (!['logo', 'image_boutique', 'ifu_file', 'rccm_file', 'certificate_file'].includes(key)) {
          submitData.append(key, formData[key]);
        }
      });

      // Fichiers
      if (formData.logo) submitData.append('logo', formData.logo);
      if (formData.image_boutique) submitData.append('image_boutique', formData.image_boutique);
      if (formData.ifu_file) submitData.append('ifu_file', formData.ifu_file);
      if (formData.rccm_file) submitData.append('rccm_file', formData.rccm_file);
      if (formData.certificate_file) submitData.append('certificate_file', formData.certificate_file);

      await entrepriseApi.createEntreprise(submitData);
      
      alert('Entreprise créée avec succès !');
      navigate('/mes-entreprises');
      
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la création');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setLoading(false);
    }
  };

  const renderStep = () => {
    switch (currentStep) {
      case 1:
        return (
          <div style={styles.stepContent}>
            <h2 style={styles.stepTitle}>📋 Informations générales</h2>
            
            <div style={styles.formGroup}>
              <label style={styles.label}>
                Nom de l'entreprise <span style={styles.required}>*</span>
              </label>
              <input
                type="text"
                name="name"
                value={formData.name}
                onChange={handleChange}
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
                      ...(formData.domaine_ids.includes(domaine.id) ? styles.domaineButtonActive : {})
                    }}
                  >
                    {formData.domaine_ids.includes(domaine.id) ? '✓ ' : ''}
                    {domaine.name}
                  </button>
                ))}
              </div>
            </div>
          </div>
        );

      case 2:
        return (
          <div style={styles.stepContent}>
            <h2 style={styles.stepTitle}>📄 Documents légaux</h2>
            
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
                  style={styles.input}
                  placeholder="Ex: 1234567890123"
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Fichier IFU (PDF/Image) <span style={styles.required}>*</span>
                </label>
                <input
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png"
                  onChange={(e) => handleFileChange(e, 'ifu_file')}
                  style={styles.fileInput}
                />
                {previews.ifu_file && (
                  <div style={styles.filePreview}>✅ Fichier chargé</div>
                )}
              </div>
            </div>

            <div style={styles.formRow}>
              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Numéro RCCM <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="rccm_number"
                  value={formData.rccm_number}
                  onChange={handleChange}
                  style={styles.input}
                  placeholder="Ex: RB/COT/12/B/345"
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Fichier RCCM (PDF/Image) <span style={styles.required}>*</span>
                </label>
                <input
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png"
                  onChange={(e) => handleFileChange(e, 'rccm_file')}
                  style={styles.fileInput}
                />
                {previews.rccm_file && (
                  <div style={styles.filePreview}>✅ Fichier chargé</div>
                )}
              </div>
            </div>

            <div style={styles.formRow}>
              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Numéro de certificat <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="certificate_number"
                  value={formData.certificate_number}
                  onChange={handleChange}
                  style={styles.input}
                  placeholder="Ex: CERT-2024-12345"
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  Fichier certificat (PDF/Image) <span style={styles.required}>*</span>
                </label>
                <input
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png"
                  onChange={(e) => handleFileChange(e, 'certificate_file')}
                  style={styles.fileInput}
                />
                {previews.certificate_file && (
                  <div style={styles.filePreview}>✅ Fichier chargé</div>
                )}
              </div>
            </div>
          </div>
        );

      case 3:
        return (
          <div style={styles.stepContent}>
            <h2 style={styles.stepTitle}>👤 Informations du dirigeant</h2>
            
            <div style={styles.formGroup}>
              <label style={styles.label}>
                Nom complet du PDG <span style={styles.required}>*</span>
              </label>
              <input
                type="text"
                name="pdg_full_name"
                value={formData.pdg_full_name}
                onChange={handleChange}
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
                style={styles.input}
                placeholder="Ex: Ingénieur mécanicien"
              />
            </div>

            <div style={styles.formGroup}>
              <label style={styles.label}>
                Votre rôle dans l'entreprise <span style={styles.required}>*</span>
              </label>
              <select
                name="role_user"
                value={formData.role_user}
                onChange={handleChange}
                style={styles.select}
              >
                <option value="">-- Choisir un rôle --</option>
                <option value="PDG">PDG</option>
                <option value="Directeur Général">Directeur Général</option>
                <option value="Gérant">Gérant</option>
                <option value="Directeur">Directeur</option>
                <option value="Manager">Manager</option>
                <option value="Autre">Autre</option>
              </select>
            </div>
          </div>
        );

      case 4:
        return (
          <div style={styles.stepContent}>
            <h2 style={styles.stepTitle}>📍 Localisation & Médias</h2>
            
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

            <div style={styles.formRow}>
              <div style={styles.formGroup}>
                <label style={styles.label}>Logo de l'entreprise</label>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => handleFileChange(e, 'logo')}
                  style={styles.fileInput}
                />
                {previews.logo && (
                  <img src={previews.logo} alt="Logo" style={styles.preview} />
                )}
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>Image de la boutique</label>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => handleFileChange(e, 'image_boutique')}
                  style={styles.fileInput}
                />
                {previews.image_boutique && (
                  <img src={previews.image_boutique} alt="Boutique" style={styles.preview} />
                )}
              </div>
            </div>
          </div>
        );

      case 5:
        const selectedDomaines = domaines.filter(d => formData.domaine_ids.includes(d.id));
        
        return (
          <div style={styles.stepContent}>
            <h2 style={styles.stepTitle}>✅ Résumé de votre entreprise</h2>
            
            <div style={styles.summary}>
              <div style={styles.summarySection}>
                <h3 style={styles.summaryTitle}>📋 Informations générales</h3>
                <p><strong>Nom :</strong> {formData.name}</p>
                <p><strong>Domaines :</strong> {selectedDomaines.map(d => d.name).join(', ')}</p>
              </div>

              <div style={styles.summarySection}>
                <h3 style={styles.summaryTitle}>📄 Documents</h3>
                <p><strong>IFU :</strong> {formData.ifu_number} {previews.ifu_file && '✅'}</p>
                <p><strong>RCCM :</strong> {formData.rccm_number} {previews.rccm_file && '✅'}</p>
                <p><strong>Certificat :</strong> {formData.certificate_number} {previews.certificate_file && '✅'}</p>
              </div>

              <div style={styles.summarySection}>
                <h3 style={styles.summaryTitle}>👤 Dirigeant</h3>
                <p><strong>Nom :</strong> {formData.pdg_full_name}</p>
                <p><strong>Profession :</strong> {formData.pdg_full_profession}</p>
                <p><strong>Votre rôle :</strong> {formData.role_user}</p>
              </div>

              <div style={styles.summarySection}>
                <h3 style={styles.summaryTitle}>📍 Localisation & Médias</h3>
                <p><strong>Siège :</strong> {formData.siege || 'Non renseigné'}</p>
                <p><strong>Logo :</strong> {previews.logo ? '✅ Chargé' : 'Non fourni'}</p>
                <p><strong>Image boutique :</strong> {previews.image_boutique ? '✅ Chargée' : 'Non fournie'}</p>
              </div>

              <div style={styles.warningBox}>
                <strong>⚠️ Attention :</strong> Une fois soumise, votre entreprise sera envoyée 
                à l'administration pour validation. Vous recevrez une notification par email.
              </div>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        <Link to="/mes-entreprises" style={styles.backButton}>← Retour</Link>
        
        <h1 style={styles.title}>Créer une entreprise</h1>

        {/* Stepper */}
        <div style={styles.stepper}>
          {STEPS.map((step) => (
            <div
              key={step.id}
              style={{
                ...styles.stepIndicator,
                ...(step.id === currentStep ? styles.stepActive : {}),
                ...(step.id < currentStep ? styles.stepCompleted : {})
              }}
            >
              <div style={{
                ...styles.stepNumber,
                ...(step.id === currentStep ? styles.stepNumberActive : {}),
                ...(step.id < currentStep ? styles.stepNumberCompleted : {})
              }}>
                {step.id < currentStep ? '✓' : step.icon}
              </div>
              <div style={styles.stepLabel}>{step.title}</div>
            </div>
          ))}
        </div>

        {error && <div style={styles.error}>⚠️ {error}</div>}

        <div style={styles.card}>
          {renderStep()}
        </div>

        {/* Navigation */}
        <div style={styles.navigation}>
          {currentStep > 1 && (
            <button onClick={prevStep} style={styles.btnSecondary}>
              ← Précédent
            </button>
          )}
          
          <div style={{flex: 1}} />
          
          {currentStep < 5 ? (
            <button onClick={nextStep} style={styles.btnPrimary}>
              Suivant →
            </button>
          ) : (
            <button 
              onClick={handleSubmit} 
              disabled={loading}
              style={{...styles.btnPrimary, opacity: loading ? 0.6 : 1}}
            >
              {loading ? '⏳ Envoi en cours...' : '✅ Finaliser et envoyer'}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: theme.colors.background,
    padding: '2rem 1rem',
  },
  content: {
    maxWidth: '900px',
    margin: '0 auto',
  },
  backButton: {
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    display: 'inline-block',
    marginBottom: '1rem',
  },
  title: {
    fontSize: '2rem',
    fontWeight: 'bold',
    marginBottom: '2rem',
    color: theme.colors.text.primary,
  },
  stepper: {
    display: 'flex',
    justifyContent: 'space-between',
    marginBottom: '2rem',
    gap: '0.5rem',
    flexWrap: 'wrap',
  },
  stepIndicator: {
    flex: 1,
    minWidth: '100px',
    textAlign: 'center',
    opacity: 0.4,
    transition: 'all 0.3s',
  },
  stepActive: {
    opacity: 1,
  },
  stepCompleted: {
    opacity: 1,
  },
  stepNumber: {
    width: '50px',
    height: '50px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primaryLight,
    margin: '0 auto 0.5rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '1.5rem',
    fontWeight: 'bold',
    border: `2px solid ${theme.colors.primaryLight}`,
    transition: 'all 0.3s',
  },
  stepNumberActive: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    borderColor: theme.colors.primary,
  },
  stepNumberCompleted: {
    backgroundColor: theme.colors.success,
    color: '#fff',
    borderColor: theme.colors.success,
  },
  stepLabel: {
    fontSize: '0.875rem',
    color: theme.colors.text.secondary,
    fontWeight: '600',
  },
  error: {
    backgroundColor: '#fee2e2',
    color: theme.colors.error,
    padding: '1rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '1rem',
    border: `2px solid ${theme.colors.error}`,
  },
  card: {
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    padding: '2rem',
    boxShadow: theme.shadows.lg,
    marginBottom: '2rem',
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  stepContent: {
    minHeight: '400px',
  },
  stepTitle: {
    fontSize: '1.5rem',
    fontWeight: 'bold',
    marginBottom: '1.5rem',
    color: theme.colors.primary,
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
    marginBottom: '0.5rem',
    color: theme.colors.text.primary,
  },
  required: {
    color: theme.colors.error,
  },
  hint: {
    fontSize: '0.875rem',
    color: theme.colors.text.secondary,
    marginBottom: '0.5rem',
  },
  input: {
    width: '100%',
    padding: '0.75rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    outline: 'none',
    transition: 'border-color 0.2s',
  },
  select: {
    width: '100%',
    padding: '0.75rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    backgroundColor: theme.colors.secondary,
    outline: 'none',
    cursor: 'pointer',
  },
  fileInput: {
    width: '100%',
    padding: '0.75rem',
    border: `2px dashed ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    cursor: 'pointer',
  },
  filePreview: {
    marginTop: '0.5rem',
    color: theme.colors.success,
    fontWeight: '600',
    fontSize: '0.875rem',
  },
  preview: {
    marginTop: '1rem',
    maxWidth: '200px',
    maxHeight: '200px',
    borderRadius: theme.borderRadius.md,
    border: `2px solid ${theme.colors.primaryLight}`,
  },
  domainesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
    gap: '0.75rem',
  },
  domaineButton: {
    padding: '0.75rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.secondary,
    cursor: 'pointer',
    fontWeight: '600',
    transition: 'all 0.2s',
    fontSize: '0.95rem',
    textAlign: 'left',
  },
  domaineButtonActive: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    borderColor: theme.colors.primary,
  },
  summary: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  summarySection: {
    padding: '1.25rem',
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.lg,
    border: `1px solid ${theme.colors.primaryLight}`,
  },
  summaryTitle: {
    fontSize: '1.125rem',
    fontWeight: 'bold',
    marginBottom: '1rem',
    color: theme.colors.primary,
  },
  warningBox: {
    padding: '1.25rem',
    backgroundColor: '#fef3c7',
    border: `2px solid ${theme.colors.warning}`,
    borderRadius: theme.borderRadius.lg,
    color: '#92400e',
    lineHeight: '1.6',
  },
  navigation: {
    display: 'flex',
    justifyContent: 'space-between',
    gap: '1rem',
    alignItems: 'center',
  },
  btnPrimary: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    padding: '1rem 2rem',
    borderRadius: theme.borderRadius.lg,
    border: 'none',
    fontWeight: '600',
    cursor: 'pointer',
    fontSize: '1rem',
    transition: 'all 0.3s',
    boxShadow: theme.shadows.md,
  },
  btnSecondary: {
    backgroundColor: 'transparent',
    color: theme.colors.primary,
    padding: '1rem 2rem',
    borderRadius: theme.borderRadius.lg,
    border: `2px solid ${theme.colors.primary}`,
    fontWeight: '600',
    cursor: 'pointer',
    fontSize: '1rem',
    transition: 'all 0.3s',
  },
};