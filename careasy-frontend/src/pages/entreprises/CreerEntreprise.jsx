// careasy-frontend/src/pages/entreprises/CreerEntreprise.jsx
import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { entrepriseApi } from '../../api/entrepriseApi';
import { useGeolocation } from '../../hooks/useGeolocation';
import theme from '../../config/theme';

// Import des icônes React Icons
import { 
  FaClipboardList, 
  FaFileAlt, 
  FaUserTie, 
  FaMapMarkerAlt, 
  FaCheckCircle,
  FaArrowLeft,
  FaArrowRight,
  FaCheck,
  FaExclamationTriangle,
  FaSpinner,
  FaMapPin,
  FaImage,
  FaUpload,
  FaGlobe,
  FaBuilding,
  FaIdCard,
  FaCertificate,
  FaIndustry,
  FaUser,
  FaBriefcase,
  FaCog
} from 'react-icons/fa';
import { 
  MdBusiness, 
  MdLocationOn, 
  MdWarning,
  MdError
} from 'react-icons/md';
import { HiOfficeBuilding } from 'react-icons/hi';

const STEPS = [
  { id: 1, title: 'Informations générales', icon: <FaClipboardList /> },
  { id: 2, title: 'Documents légaux', icon: <FaFileAlt /> },
  { id: 3, title: 'Dirigeant', icon: <FaUserTie /> },
  { id: 4, title: 'Localisation & Médias', icon: <FaMapMarkerAlt /> },
  { id: 5, title: 'Résumé', icon: <FaCheckCircle /> }
];

export default function CreerEntreprise() {
  const navigate = useNavigate();
  const [currentStep, setCurrentStep] = useState(1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [domainLoading, setDomainLoading] = useState(false);
  const [domaines, setDomaines] = useState([]);

  // États pour les animations
  const [isTransitioning, setIsTransitioning] = useState(false);
  const [stepDirection, setStepDirection] = useState('next');

  const { latitude, longitude, loading: geoLoading, error: geoError } = useGeolocation();

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

  useEffect(() => {
    if (geoError) {
      setError(`Impossible de récupérer votre position. Veuillez activer la géolocalisation.`);
    } else if (latitude && longitude) {
      setError('');
    }
  }, [geoError, latitude, longitude]);

  const fetchDomaines = async () => {
    setDomainLoading(true);
    try {
      const data = await entrepriseApi.getFormData();
      setDomaines(data.domaines || []);
    } catch (err) {
      setError('Erreur lors du chargement des domaines');
    } finally {
      setDomainLoading(false);
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
      // Validation de taille (max 5MB)
      if (file.size > 5 * 1024 * 1024) {
        setError('Le fichier ne doit pas dépasser 5MB');
        return;
      }

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
    setSuccess('');
    
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
      setStepDirection('next');
      setIsTransitioning(true);
      setTimeout(() => {
        setCurrentStep(prev => Math.min(prev + 1, 5));
        setIsTransitioning(false);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }, 300);
    }
  };

  const prevStep = () => {
    setStepDirection('prev');
    setIsTransitioning(true);
    setTimeout(() => {
      setCurrentStep(prev => Math.max(prev - 1, 1));
      setIsTransitioning(false);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 300);
  };

  const handleSubmit = async () => {
    // Validation géolocalisation
    if (geoLoading) {
      setError('Géolocalisation en cours... Veuillez patienter quelques secondes.');
      return;
    }

    if (!latitude || !longitude) {
      setError('Impossible de récupérer votre position. Veuillez activer la géolocalisation.');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

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

      // Ajouter latitude et longitude
      submitData.append('latitude', latitude.toString());
      submitData.append('longitude', longitude.toString());

      const response = await entrepriseApi.createEntreprise(submitData);
      
      setSuccess('Entreprise créée avec succès ! Redirection en cours...');
      
      setTimeout(() => {
        navigate('/mes-entreprises', { 
          state: { 
            success: 'Votre entreprise a été créée avec succès et est en attente de validation.' 
          } 
        });
      }, 2000);
      
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la création de l\'entreprise');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setLoading(false);
    }
  };

  const renderStep = () => {
    const stepContent = (() => {
      switch (currentStep) {
        case 1:
          return (
            <div style={styles.stepContent}>
              <div style={styles.stepHeader}>
                <FaClipboardList style={styles.stepIcon} />
                <h2 style={styles.stepTitle}>Informations générales</h2>
              </div>
              
              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <MdBusiness style={styles.labelIcon} />
                  Nom de l'entreprise <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  style={styles.input}
                  placeholder="Ex: Garage Auto Excellence"
                  required
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <FaIndustry style={styles.labelIcon} />
                  Domaines d'activité <span style={styles.required}>*</span>
                </label>
                <p style={styles.hint}>Sélectionnez au moins un domaine</p>
                {domainLoading ? (
                  <div style={styles.loadingContainer}>
                    <FaSpinner className="spin" style={styles.spinner} />
                    <span>Chargement des domaines...</span>
                  </div>
                ) : (
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
                        {formData.domaine_ids.includes(domaine.id) && <FaCheck style={{ marginRight: '8px' }} />}
                        {domaine.name}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            </div>
          );

        case 2:
          return (
            <div style={styles.stepContent}>
              <div style={styles.stepHeader}>
                <FaFileAlt style={styles.stepIcon} />
                <h2 style={styles.stepTitle}>Documents légaux</h2>
              </div>
              
              <div style={styles.formRow}>
                <div style={styles.formGroup}>
                  <label style={styles.label}>
                    <FaIdCard style={styles.labelIcon} />
                    Numéro IFU <span style={styles.required}>*</span>
                  </label>
                  <input
                    type="text"
                    name="ifu_number"
                    value={formData.ifu_number}
                    onChange={handleChange}
                    style={styles.input}
                    placeholder="Ex: 1234567890123"
                    required
                  />
                </div>

                <div style={styles.formGroup}>
                  <label style={styles.label}>
                    <FaUpload style={styles.labelIcon} />
                    Fichier IFU (PDF/Image) <span style={styles.required}>*</span>
                  </label>
                  <div style={styles.fileUpload}>
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png"
                      onChange={(e) => handleFileChange(e, 'ifu_file')}
                      style={styles.fileInput}
                      id="ifu-file"
                    />
                    <label htmlFor="ifu-file" style={styles.fileLabel}>
                      <FaUpload /> Choisir un fichier
                    </label>
                    {previews.ifu_file && (
                      <div style={styles.fileSuccess}>
                        <FaCheck /> Fichier chargé
                      </div>
                    )}
                  </div>
                </div>
              </div>

              <div style={styles.formRow}>
                <div style={styles.formGroup}>
                  <label style={styles.label}>
                    <FaCertificate style={styles.labelIcon} />
                    Numéro RCCM <span style={styles.required}>*</span>
                  </label>
                  <input
                    type="text"
                    name="rccm_number"
                    value={formData.rccm_number}
                    onChange={handleChange}
                    style={styles.input}
                    placeholder="Ex: RB/COT/12/B/345"
                    required
                  />
                </div>

                <div style={styles.formGroup}>
                  <label style={styles.label}>
                    <FaUpload style={styles.labelIcon} />
                    Fichier RCCM (PDF/Image) <span style={styles.required}>*</span>
                  </label>
                  <div style={styles.fileUpload}>
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png"
                      onChange={(e) => handleFileChange(e, 'rccm_file')}
                      style={styles.fileInput}
                      id="rccm-file"
                    />
                    <label htmlFor="rccm-file" style={styles.fileLabel}>
                      <FaUpload /> Choisir un fichier
                    </label>
                    {previews.rccm_file && (
                      <div style={styles.fileSuccess}>
                        <FaCheck /> Fichier chargé
                      </div>
                    )}
                  </div>
                </div>
              </div>

              <div style={styles.formRow}>
                <div style={styles.formGroup}>
                  <label style={styles.label}>
                    <FaCertificate style={styles.labelIcon} />
                    Numéro de certificat <span style={styles.required}>*</span>
                  </label>
                  <input
                    type="text"
                    name="certificate_number"
                    value={formData.certificate_number}
                    onChange={handleChange}
                    style={styles.input}
                    placeholder="Ex: CERT-2024-12345"
                    required
                  />
                </div>

                <div style={styles.formGroup}>
                  <label style={styles.label}>
                    <FaUpload style={styles.labelIcon} />
                    Fichier certificat (PDF/Image) <span style={styles.required}>*</span>
                  </label>
                  <div style={styles.fileUpload}>
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png"
                      onChange={(e) => handleFileChange(e, 'certificate_file')}
                      style={styles.fileInput}
                      id="certificate-file"
                    />
                    <label htmlFor="certificate-file" style={styles.fileLabel}>
                      <FaUpload /> Choisir un fichier
                    </label>
                    {previews.certificate_file && (
                      <div style={styles.fileSuccess}>
                        <FaCheck /> Fichier chargé
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          );

        case 3:
          return (
            <div style={styles.stepContent}>
              <div style={styles.stepHeader}>
                <FaUserTie style={styles.stepIcon} />
                <h2 style={styles.stepTitle}>Informations du dirigeant</h2>
              </div>
              
              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <FaUser style={styles.labelIcon} />
                  Nom complet du PDG <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="pdg_full_name"
                  value={formData.pdg_full_name}
                  onChange={handleChange}
                  style={styles.input}
                  placeholder="Ex: Jean Dupont"
                  required
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <FaBriefcase style={styles.labelIcon} />
                  Profession du PDG <span style={styles.required}>*</span>
                </label>
                <input
                  type="text"
                  name="pdg_full_profession"
                  value={formData.pdg_full_profession}
                  onChange={handleChange}
                  style={styles.input}
                  placeholder="Ex: Ingénieur mécanicien"
                  required
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <FaCog style={styles.labelIcon} />
                  Votre rôle dans l'entreprise <span style={styles.required}>*</span>
                </label>
                <select
                  name="role_user"
                  value={formData.role_user}
                  onChange={handleChange}
                  style={styles.select}
                  required
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
              <div style={styles.stepHeader}>
                <FaMapMarkerAlt style={styles.stepIcon} />
                <h2 style={styles.stepTitle}>Localisation & Médias</h2>
              </div>
              
              {/* Indicateur de géolocalisation */}
              {geoLoading && (
                <div style={styles.geoAlert}>
                  <FaSpinner className="spin" style={styles.spinner} />
                  <span>Récupération automatique de votre position...</span>
                </div>
              )}

              {geoError && (
                <div style={styles.geoError}>
                  <MdError style={styles.geoIcon} />
                  <div>
                    <strong>Erreur de géolocalisation</strong>
                    <p>{geoError}</p>
                    <small>Veuillez activer la géolocalisation dans votre navigateur</small>
                  </div>
                </div>
              )}

              {latitude && longitude && (
                <div style={styles.geoSuccess}>
                  <FaMapPin style={styles.geoIcon} />
                  <div>
                    <strong>Position détectée automatiquement</strong>
                    <p style={styles.geoCoords}>
                      {latitude.toFixed(6)}°, {longitude.toFixed(6)}°
                    </p>
                  </div>
                </div>
              )}
              
              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <HiOfficeBuilding style={styles.labelIcon} />
                  Siège de l'entreprise
                </label>
                <p style={styles.hint}>
                  Adresse approximative (ex: Cotonou, Akpakpa). Votre position exacte est détectée automatiquement.
                </p>
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
                  <label style={styles.label}>
                    <FaImage style={styles.labelIcon} />
                    Logo de l'entreprise
                  </label>
                  <div style={styles.imageUpload}>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => handleFileChange(e, 'logo')}
                      style={styles.fileInput}
                      id="logo-file"
                    />
                    <label htmlFor="logo-file" style={styles.imageLabel}>
                      {previews.logo ? (
                        <>
                          <img src={previews.logo} alt="Logo" style={styles.imagePreview} />
                          <span style={styles.imageOverlay}>Changer l'image</span>
                        </>
                      ) : (
                        <div style={styles.imagePlaceholder}>
                          <FaImage size={48} />
                          <span>Choisir un logo</span>
                        </div>
                      )}
                    </label>
                  </div>
                </div>

                <div style={styles.formGroup}>
                  <label style={styles.label}>
                    <FaBuilding style={styles.labelIcon} />
                    Image de la boutique
                  </label>
                  <div style={styles.imageUpload}>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => handleFileChange(e, 'image_boutique')}
                      style={styles.fileInput}
                      id="boutique-file"
                    />
                    <label htmlFor="boutique-file" style={styles.imageLabel}>
                      {previews.image_boutique ? (
                        <>
                          <img src={previews.image_boutique} alt="Boutique" style={styles.imagePreview} />
                          <span style={styles.imageOverlay}>Changer l'image</span>
                        </>
                      ) : (
                        <div style={styles.imagePlaceholder}>
                          <FaBuilding size={48} />
                          <span>Choisir une image</span>
                        </div>
                      )}
                    </label>
                  </div>
                </div>
              </div>
            </div>
          );

        case 5:
          const selectedDomaines = domaines.filter(d => formData.domaine_ids.includes(d.id));
          
          return (
            <div style={styles.stepContent}>
              <div style={styles.stepHeader}>
                <FaCheckCircle style={styles.stepIcon} />
                <h2 style={styles.stepTitle}>Résumé de votre entreprise</h2>
              </div>
              
              <div style={styles.summary}>
                <div style={styles.summarySection}>
                  <h3 style={styles.summaryTitle}>
                    <FaClipboardList style={styles.summaryIcon} />
                    Informations générales
                  </h3>
                  <p><strong>Nom :</strong> {formData.name}</p>
                  <p><strong>Domaines :</strong> {selectedDomaines.map(d => d.name).join(', ')}</p>
                </div>

                <div style={styles.summarySection}>
                  <h3 style={styles.summaryTitle}>
                    <FaFileAlt style={styles.summaryIcon} />
                    Documents
                  </h3>
                  <p><strong>IFU :</strong> {formData.ifu_number} {previews.ifu_file && <FaCheck style={{ color: theme.colors.success, marginLeft: '5px' }} />}</p>
                  <p><strong>RCCM :</strong> {formData.rccm_number} {previews.rccm_file && <FaCheck style={{ color: theme.colors.success, marginLeft: '5px' }} />}</p>
                  <p><strong>Certificat :</strong> {formData.certificate_number} {previews.certificate_file && <FaCheck style={{ color: theme.colors.success, marginLeft: '5px' }} />}</p>
                </div>

                <div style={styles.summarySection}>
                  <h3 style={styles.summaryTitle}>
                    <FaUserTie style={styles.summaryIcon} />
                    Dirigeant
                  </h3>
                  <p><strong>Nom :</strong> {formData.pdg_full_name}</p>
                  <p><strong>Profession :</strong> {formData.pdg_full_profession}</p>
                  <p><strong>Votre rôle :</strong> {formData.role_user}</p>
                </div>

                <div style={styles.summarySection}>
                  <h3 style={styles.summaryTitle}>
                    <FaMapMarkerAlt style={styles.summaryIcon} />
                    Localisation & Médias
                  </h3>
                  <p><strong>Siège :</strong> {formData.siege || 'Non renseigné'}</p>
                  {latitude && longitude && (
                    <p>
                      <strong>Position GPS :</strong> 
                      <FaMapPin style={{ color: theme.colors.success, marginLeft: '5px' }} />
                      ({latitude.toFixed(4)}°, {longitude.toFixed(4)}°)
                    </p>
                  )}
                  <p><strong>Logo :</strong> {previews.logo ? <FaCheck style={{ color: theme.colors.success, marginLeft: '5px' }} /> : 'Non fourni'}</p>
                  <p><strong>Image boutique :</strong> {previews.image_boutique ? <FaCheck style={{ color: theme.colors.success, marginLeft: '5px' }} /> : 'Non fournie'}</p>
                </div>

                <div style={styles.warningBox}>
                  <MdWarning style={styles.warningIcon} />
                  <div>
                    <strong>Information importante</strong>
                    <p>Une fois soumise, votre entreprise sera envoyée à l'administration pour validation. Vous recevrez une notification par email dès qu'elle sera validée.</p>
                  </div>
                </div>
              </div>
            </div>
          );

        default:
          return null;
      }
    })();

    return (
      <div style={{
        ...styles.stepContainer,
        opacity: isTransitioning ? 0 : 1,
        transform: isTransitioning 
          ? `translateX(${stepDirection === 'next' ? '20px' : '-20px'})` 
          : 'translateX(0)',
        transition: 'all 0.3s ease'
      }}>
        {stepContent}
      </div>
    );
  };

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        <Link to="/mes-entreprises" style={styles.backButton}>
          <FaArrowLeft style={styles.backIcon} />
          Retour
        </Link>
        
        <div style={styles.header}>
          <h1 style={styles.title}>Créer une entreprise</h1>
          <p style={styles.subtitle}>Remplissez les informations nécessaires pour enregistrer votre entreprise</p>
        </div>

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
                ...styles.stepCircle,
                ...(step.id === currentStep ? styles.stepCircleActive : {}),
                ...(step.id < currentStep ? styles.stepCircleCompleted : {})
              }}>
                {step.id < currentStep ? <FaCheck /> : step.icon}
              </div>
              <div style={styles.stepLabel}>{step.title}</div>
              {step.id < STEPS.length && (
                <div style={styles.stepConnector}></div>
              )}
            </div>
          ))}
        </div>

        {/* Messages */}
        {error && (
          <div style={styles.errorMessage}>
            <MdError style={styles.messageIcon} />
            <span>{error}</span>
          </div>
        )}
        
        {success && (
          <div style={styles.successMessage}>
            <FaCheckCircle style={styles.messageIcon} />
            <span>{success}</span>
          </div>
        )}

        <div style={styles.card}>
          {renderStep()}
        </div>

        {/* Navigation */}
        <div style={styles.navigation}>
          {currentStep > 1 && (
            <button 
              onClick={prevStep} 
              disabled={isTransitioning}
              style={styles.btnSecondary}
            >
              <FaArrowLeft style={styles.btnIcon} />
              Précédent
            </button>
          )}
          
          <div style={{flex: 1}} />
          
          {currentStep < 5 ? (
            <button 
              onClick={nextStep} 
              disabled={isTransitioning}
              style={styles.btnPrimary}
            >
              Suivant
              <FaArrowRight style={styles.btnIcon} />
            </button>
          ) : (
            <button 
              onClick={handleSubmit} 
              disabled={loading || geoLoading || !latitude || !longitude || isTransitioning}
              style={{
                ...styles.btnPrimary, 
                ...styles.btnSuccess,
                opacity: (loading || geoLoading || !latitude || !longitude) ? 0.6 : 1
              }}
            >
              {loading ? (
                <>
                  <FaSpinner className="spin" style={{ ...styles.btnIcon, marginRight: '8px' }} />
                  Envoi en cours...
                </>
              ) : geoLoading ? (
                <>
                  <FaSpinner className="spin" style={{ ...styles.btnIcon, marginRight: '8px' }} />
                  Localisation...
                </>
              ) : !latitude || !longitude ? (
                <>
                  <MdError style={{ ...styles.btnIcon, marginRight: '8px' }} />
                  Localisation requise
                </>
              ) : (
                <>
                  <FaCheckCircle style={{ ...styles.btnIcon, marginRight: '8px' }} />
                  Finaliser et envoyer
                </>
              )}
            </button>
          )}
        </div>
      </div>

      {/* CSS Animations */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        .spin {
          animation: spin 1s linear infinite;
        }
      `}</style>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: theme.colors.background,
    padding: '2rem 1rem',
    background: 'linear-gradient(135deg, #f5f7fa 0%, #f5f7fa 0%)',
  },
  content: {
    maxWidth: '1000px',
    margin: '0 auto',
  },
  header: {
    marginBottom: '2.5rem',
    textAlign: 'center',
  },
  title: {
    fontSize: '2.5rem',
    fontWeight: 'bold',
    marginBottom: '0.5rem',
    color: theme.colors.text.primary,
    background: 'linear-gradient(90deg, ' + theme.colors.primary + ', ' + theme.colors.accent + ')',
    WebkitBackgroundClip: 'text',
    WebkitTextFillColor: 'transparent',
  },
  subtitle: {
    fontSize: '1.1rem',
    color: theme.colors.text.secondary,
    maxWidth: '600px',
    margin: '0 auto',
  },
  backButton: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.5rem',
    color: theme.colors.primary,
    textDecoration: 'none',
    fontWeight: '600',
    marginBottom: '1.5rem',
    padding: '0.75rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
    transition: 'all 0.3s',
    border: '1px solid rgba(59, 130, 246, 0.2)',
  },
  backIcon: {
    fontSize: '0.9rem',
  },
  stepper: {
    display: 'flex',
    justifyContent: 'space-between',
    marginBottom: '3rem',
    position: 'relative',
  },
  stepIndicator: {
    flex: 1,
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    position: 'relative',
    zIndex: 1,
  },
  stepCircle: {
    width: '60px',
    height: '60px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primaryLight,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '1.5rem',
    fontWeight: 'bold',
    color: '#fff',
    marginBottom: '0.75rem',
    border: `3px solid ${theme.colors.primaryLight}`,
    transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
    boxShadow: theme.shadows.md,
  },
  stepCircleActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
    transform: 'scale(1.1)',
    boxShadow: '0 10px 25px rgba(59, 130, 246, 0.4)',
  },
  stepCircleCompleted: {
    backgroundColor: theme.colors.success,
    borderColor: theme.colors.success,
  },
  stepLabel: {
    fontSize: '0.9rem',
    color: theme.colors.text.secondary,
    fontWeight: '600',
    textAlign: 'center',
    padding: '0 0.5rem',
  },
  stepConnector: {
    position: 'absolute',
    top: '30px',
    left: '60%',
    right: '-40%',
    height: '3px',
    backgroundColor: theme.colors.primaryLight,
    zIndex: 0,
  },
  stepActive: {
    '& $stepLabel': {
      color: theme.colors.primary,
      fontWeight: '700',
    },
  },
  stepCompleted: {
    '& $stepConnector': {
      backgroundColor: theme.colors.success,
    },
  },
  errorMessage: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    backgroundColor: '#fee2e2',
    color: theme.colors.error,
    padding: '1.25rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '1.5rem',
    border: `2px solid ${theme.colors.error}`,
    animation: 'slideIn 0.3s ease-out',
  },
  successMessage: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    backgroundColor: '#d1fae5',
    color: theme.colors.success,
    padding: '1.25rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '1.5rem',
    border: `2px solid ${theme.colors.success}`,
    animation: 'slideIn 0.3s ease-out',
  },
  messageIcon: {
    fontSize: '1.25rem',
    flexShrink: 0,
  },
  card: {
    backgroundColor: theme.colors.secondary,
    borderRadius: theme.borderRadius.xl,
    padding: '2.5rem',
    boxShadow: '0 20px 60px rgba(0, 0, 0, 0.1)',
    marginBottom: '2.5rem',
    border: `1px solid rgba(59, 130, 246, 0.1)`,
    minHeight: '500px',
    position: 'relative',
    overflow: 'hidden',
  },
  stepContainer: {
    opacity: 1,
    transition: 'all 0.3s ease',
  },
  stepContent: {
    animation: 'fadeIn 0.5s ease-out',
  },
  stepHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    marginBottom: '2rem',
    paddingBottom: '1rem',
    borderBottom: `2px solid ${theme.colors.primaryLight}`,
  },
  stepIcon: {
    fontSize: '2rem',
    color: theme.colors.primary,
  },
  stepTitle: {
    fontSize: '1.75rem',
    fontWeight: 'bold',
    color: theme.colors.text.primary,
  },
  formGroup: {
    marginBottom: '2rem',
  },
  formRow: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
    gap: '2rem',
    marginBottom: '1rem',
  },
  label: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontWeight: '600',
    marginBottom: '0.75rem',
    color: theme.colors.text.primary,
    fontSize: '1rem',
  },
  labelIcon: {
    color: theme.colors.primary,
    fontSize: '1rem',
  },
  required: {
    color: theme.colors.error,
    marginLeft: '2px',
  },
  hint: {
    fontSize: '0.875rem',
    color: theme.colors.text.secondary,
    marginBottom: '1rem',
    lineHeight: '1.5',
  },
  input: {
    width: '100%',
    padding: '1rem 1.25rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.lg,
    fontSize: '1rem',
    outline: 'none',
    transition: 'all 0.3s',
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
  },
  select: {
    width: '100%',
    padding: '1rem 1.25rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.lg,
    fontSize: '1rem',
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    outline: 'none',
    cursor: 'pointer',
    transition: 'all 0.3s',
  },
  fileUpload: {
    position: 'relative',
  },
  fileInput: {
    display: 'none',
  },
  fileLabel: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.5rem',
    padding: '1rem 1.5rem',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    borderRadius: theme.borderRadius.lg,
    cursor: 'pointer',
    fontWeight: '600',
    transition: 'all 0.3s',
    border: 'none',
    fontSize: '0.95rem',
  },
  fileSuccess: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    marginTop: '0.75rem',
    color: theme.colors.success,
    fontWeight: '600',
    fontSize: '0.95rem',
  },
  imageUpload: {
    border: `2px dashed ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.lg,
    padding: '1rem',
    transition: 'all 0.3s',
    backgroundColor: 'rgba(255, 255, 255, 0.5)',
  },
  imageLabel: {
    display: 'block',
    cursor: 'pointer',
    position: 'relative',
    overflow: 'hidden',
    borderRadius: theme.borderRadius.md,
  },
  imagePreview: {
    width: '100%',
    height: '200px',
    objectFit: 'cover',
    borderRadius: theme.borderRadius.md,
    transition: 'transform 0.3s',
  },
  imagePlaceholder: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    height: '200px',
    color: theme.colors.text.secondary,
    backgroundColor: 'rgba(59, 130, 246, 0.05)',
    borderRadius: theme.borderRadius.md,
    gap: '1rem',
    transition: 'all 0.3s',
  },
  imageOverlay: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(59, 130, 246, 0.9)',
    color: '#fff',
    padding: '0.75rem',
    textAlign: 'center',
    fontWeight: '600',
    transform: 'translateY(100%)',
    transition: 'transform 0.3s',
  },
  domainesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
    gap: '1rem',
  },
  domaineButton: {
    display: 'flex',
    alignItems: 'center',
    padding: '1rem 1.5rem',
    border: `2px solid ${theme.colors.primaryLight}`,
    borderRadius: theme.borderRadius.lg,
    backgroundColor: 'rgba(255, 255, 255, 0.8)',
    cursor: 'pointer',
    fontWeight: '600',
    transition: 'all 0.3s',
    fontSize: '0.95rem',
    textAlign: 'left',
    minHeight: '60px',
  },
  domaineButtonActive: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    borderColor: theme.colors.primary,
    transform: 'translateY(-2px)',
    boxShadow: theme.shadows.md,
  },
  loadingContainer: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    padding: '2rem',
    justifyContent: 'center',
    color: theme.colors.text.secondary,
  },
  spinner: {
    fontSize: '1.25rem',
  },
  geoAlert: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    backgroundColor: '#FEF3C7',
    border: `2px solid ${theme.colors.warning}`,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '2rem',
    fontSize: '1rem',
    color: theme.colors.text.primary,
  },
  geoSuccess: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    backgroundColor: '#D1FAE5',
    border: `2px solid ${theme.colors.success}`,
    color: theme.colors.success,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '2rem',
  },
  geoError: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '1rem',
    backgroundColor: '#FEE2E2',
    border: `2px solid ${theme.colors.error}`,
    color: theme.colors.error,
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    marginBottom: '2rem',
  },
  geoIcon: {
    fontSize: '1.5rem',
    flexShrink: 0,
  },
  geoCoords: {
    fontSize: '0.9rem',
    fontFamily: 'monospace',
    marginTop: '0.25rem',
  },
  summary: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  summarySection: {
    padding: '1.5rem',
    backgroundColor: 'rgba(255, 255, 255, 0.5)',
    borderRadius: theme.borderRadius.lg,
    border: `1px solid ${theme.colors.primaryLight}`,
    transition: 'transform 0.3s',
  },
  summaryTitle: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    fontSize: '1.125rem',
    fontWeight: 'bold',
    marginBottom: '1rem',
    color: theme.colors.primary,
  },
  summaryIcon: {
    fontSize: '1.25rem',
  },
  warningBox: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '1rem',
    padding: '1.5rem',
    backgroundColor: '#fef3c7',
    border: `2px solid ${theme.colors.warning}`,
    borderRadius: theme.borderRadius.lg,
    color: '#92400e',
    lineHeight: '1.6',
  },
  warningIcon: {
    fontSize: '1.5rem',
    color: theme.colors.warning,
    flexShrink: 0,
  },
  navigation: {
    display: 'flex',
    justifyContent: 'space-between',
    gap: '1rem',
    alignItems: 'center',
    padding: '2rem 0',
  },
  btnPrimary: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.75rem',
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
    minWidth: '180px',
    justifyContent: 'center',
  },
  btnSecondary: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.75rem',
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
  btnSuccess: {
    backgroundColor: theme.colors.success,
    '&:hover:not(:disabled)': {
      backgroundColor: theme.colors.success,
      transform: 'translateY(-2px)',
      boxShadow: '0 10px 25px rgba(34, 197, 94, 0.4)',
    },
  },
  btnIcon: {
    fontSize: '0.9rem',
    transition: 'transform 0.3s',
  },
};