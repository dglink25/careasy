// careasy-frontend/src/pages/services/CreerService.jsx
import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { serviceApi } from '../../api/serviceApi';
import { entrepriseApi } from '../../api/entrepriseApi';

// Import des icônes React Icons
import {
  FiArrowLeft,
  FiPlus,
  FiUpload,
  FiCamera,
  FiClock,
  FiDollarSign,
  FiEdit,
  FiCheckCircle,
  FiAlertCircle,
  FiInfo,
  FiBriefcase,
  FiTag,
  FiFileText,
  FiMapPin,
  FiCheck,
  FiX,
  FiRefreshCw,
  FiHelpCircle,
  FiCalendar,
  FiShoppingBag,
  FiChevronDown
} from 'react-icons/fi';
import {
  MdBusiness,
  MdOutlineWork,
  MdOutlineDescription,
  MdOutlineAccessTime,
  MdOutlineAttachMoney,
  MdOutlineImage,
  MdOutlineCheckCircle,
  MdOutlineWarning,
  MdOutlineInfo,
  MdOutlineLibraryAdd,
  MdOutlineLocationOn,
  MdOutlinePerson,
  MdOutlineSchedule,
  MdOutlinePhotoLibrary,
  MdOutlineDone,
  MdOutlineClose,
  MdOutlineCategory,
  MdOutlineDashboard,
  MdOutlineTipsAndUpdates,
  MdOutlineStar,
  MdOutlineVerified
} from 'react-icons/md';

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
  const [activeSection, setActiveSection] = useState('entreprise');

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

  const removeImage = (index) => {
    const newFiles = [...formData.medias];
    newFiles.splice(index, 1);
    setFormData(prev => ({ ...prev, medias: newFiles }));
    
    const newPreviews = [...previews];
    newPreviews.splice(index, 1);
    setPreviews(newPreviews);
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
          <p style={styles.loadingText}>Chargement des données...</p>
          <p style={styles.loadingSubtext}>Préparation du formulaire</p>
        </div>
      </div>
    );
  }

  if (entreprises.length === 0) {
    return (
      <div style={styles.container}>
        <div style={styles.content}>
          <div style={styles.emptyState}>
            <div style={styles.emptyIconContainer}>
              <MdOutlineWarning style={styles.emptyIcon} />
            </div>
            <h2 style={styles.emptyTitle}>Aucune entreprise validée</h2>
            <p style={styles.emptyText}>
              Vous devez avoir au moins une entreprise validée pour créer un service.
            </p>
            <div style={styles.emptyActions}>
              <Link to="/mes-entreprises" style={styles.emptyButton}>
                <MdBusiness style={styles.emptyButtonIcon} />
                Voir mes entreprises
              </Link>
              <Link to="/entreprises/creer" style={styles.emptyButtonSecondary}>
                <FiPlus style={styles.emptyButtonIcon} />
                Créer une entreprise
              </Link>
            </div>
          </div>
        </div>
      </div>
    );
  }

  const sections = [
    { id: 'entreprise', icon: <MdBusiness />, label: 'Entreprise', completed: !!formData.entreprise_id },
    { id: 'domaine', icon: <FiTag />, label: 'Domaine', completed: !!formData.domaine_id },
    { id: 'details', icon: <FiFileText />, label: 'Détails', completed: !!formData.name },
    { id: 'horaires', icon: <FiClock />, label: 'Horaires', completed: true },
    { id: 'medias', icon: <FiCamera />, label: 'Médias', completed: formData.medias.length > 0 },
  ];

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header */}
        <div style={styles.header}>
          <Link to="/mes-services" style={styles.backButton}>
            <FiArrowLeft style={styles.backButtonIcon} />
            Retour aux services
          </Link>
          <div>
            <h1 style={styles.title}>
              <MdOutlineLibraryAdd style={styles.titleIcon} />
              Nouveau Service
            </h1>
            <p style={styles.subtitle}>
              Ajoutez un nouveau service à l'une de vos entreprises validées
            </p>
          </div>
        </div>

        {/* Navigation par étapes */}
        <div style={styles.stepsContainer}>
          <div style={styles.steps}>
            {sections.map((section, index) => (
              <div key={section.id} style={styles.stepItem}>
                <button
                  onClick={() => setActiveSection(section.id)}
                  style={{
                    ...styles.stepButton,
                    ...(activeSection === section.id && styles.stepButtonActive),
                    ...(section.completed && styles.stepCompleted)
                  }}
                >
                  <div style={styles.stepIcon}>
                    {section.completed ? <MdOutlineDone /> : section.icon}
                  </div>
                  <span style={styles.stepLabel}>{section.label}</span>
                </button>
                {index < sections.length - 1 && (
                  <div style={styles.stepConnector}></div>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Messages */}
        {error && (
          <div style={styles.error}>
            <FiAlertCircle style={styles.errorIcon} />
            <div>
              <div style={styles.errorTitle}>Erreur de validation</div>
              <div style={styles.errorText}>{error}</div>
            </div>
            <button 
              onClick={() => setError('')}
              style={styles.errorCloseButton}
            >
              <FiX />
            </button>
          </div>
        )}

        {success && (
          <div style={styles.success}>
            <FiCheckCircle style={styles.successIcon} />
            <div>
              <div style={styles.successTitle}>Succès !</div>
              <div style={styles.successText}>{success}</div>
            </div>
          </div>
        )}

        {/* Formulaire */}
        <form onSubmit={handleSubmit} style={styles.form}>
          {/* Section 1: Entreprise */}
          {activeSection === 'entreprise' && (
            <div style={styles.section}>
              <div style={styles.sectionHeader}>
                <div style={styles.sectionIcon}>
                  <MdBusiness />
                </div>
                <div>
                  <h2 style={styles.sectionTitle}>Sélection de l'entreprise</h2>
                  <p style={styles.sectionSubtitle}>
                    Choisissez l'entreprise pour laquelle vous créez ce service
                  </p>
                </div>
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <FiBriefcase style={styles.labelIcon} />
                  Entreprise <span style={styles.required}>*</span>
                </label>
                <select
                  name="entreprise_id"
                  value={formData.entreprise_id}
                  onChange={handleChange}
                  required
                  style={styles.select}
                  className="form-select"
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
                <div style={styles.entrepriseCard}>
                  <div style={styles.entrepriseCardHeader}>
                    {selectedEntreprise.logo ? (
                      <img 
                        src={`${import.meta.env.VITE_API_URL}/storage/${selectedEntreprise.logo}`}
                        alt={selectedEntreprise.name}
                        style={styles.entrepriseCardLogo}
                        onError={(e) => {
                          e.target.onerror = null;
                          e.target.style.display = 'none';
                          e.target.nextElementSibling.style.display = 'flex';
                        }}
                      />
                    ) : (
                      <div style={styles.entrepriseCardLogoPlaceholder}>
                        <MdBusiness style={styles.entrepriseCardLogoIcon} />
                      </div>
                    )}
                    <div>
                      <h3 style={styles.entrepriseCardName}>{selectedEntreprise.name}</h3>
                      <div style={styles.entrepriseCardStatus}>
                        <MdOutlineVerified style={styles.entrepriseCardStatusIcon} />
                        Entreprise validée
                      </div>
                    </div>
                  </div>
                  <div style={styles.entrepriseCardBody}>
                    <div style={styles.entrepriseCardInfo}>
                      <div style={styles.entrepriseCardInfoItem}>
                        <MdOutlinePerson style={styles.entrepriseCardInfoIcon} />
                        <div>
                          <div style={styles.entrepriseCardInfoLabel}>Dirigeant</div>
                          <div style={styles.entrepriseCardInfoValue}>{selectedEntreprise.pdg_full_name}</div>
                        </div>
                      </div>
                      <div style={styles.entrepriseCardInfoItem}>
                        <MdOutlineLocationOn style={styles.entrepriseCardInfoIcon} />
                        <div>
                          <div style={styles.entrepriseCardInfoLabel}>Siège</div>
                          <div style={styles.entrepriseCardInfoValue}>{selectedEntreprise.siege}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Section 2: Domaine */}
          {activeSection === 'domaine' && selectedEntreprise && (
            <div style={styles.section}>
              <div style={styles.sectionHeader}>
                <div style={styles.sectionIcon}>
                  <FiTag />
                </div>
                <div>
                  <h2 style={styles.sectionTitle}>Domaine d'activité</h2>
                  <p style={styles.sectionSubtitle}>
                    Choisissez le domaine correspondant à votre service
                  </p>
                </div>
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <MdOutlineCategory style={styles.labelIcon} />
                  Domaine du service <span style={styles.required}>*</span>
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
                  className="form-select"
                >
                  <option value="">-- Sélectionnez un domaine --</option>
                  {selectedEntreprise.domaines?.map(domaine => (
                    <option key={domaine.id} value={domaine.id}>
                      {domaine.name}
                    </option>
                  ))}
                </select>
              </div>

              {selectedEntreprise.domaines && selectedEntreprise.domaines.length > 0 && (
                <div style={styles.domainesGrid}>
                  {selectedEntreprise.domaines.map(domaine => (
                    <div 
                      key={domaine.id}
                      onClick={() => setFormData(prev => ({ ...prev, domaine_id: domaine.id }))}
                      style={{
                        ...styles.domaineCard,
                        ...(formData.domaine_id === domaine.id && styles.domaineCardActive)
                      }}
                    >
                      <div style={styles.domaineCardIcon}>
                        <FiTag />
                      </div>
                      <div style={styles.domaineCardName}>{domaine.name}</div>
                      {formData.domaine_id === domaine.id && (
                        <div style={styles.domaineCardCheck}>
                          <FiCheck />
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Section 3: Détails */}
          {activeSection === 'details' && (
            <div style={styles.section}>
              <div style={styles.sectionHeader}>
                <div style={styles.sectionIcon}>
                  <FiFileText />
                </div>
                <div>
                  <h2 style={styles.sectionTitle}>Détails du service</h2>
                  <p style={styles.sectionSubtitle}>
                    Décrivez votre service de manière précise et attractive
                  </p>
                </div>
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <MdOutlineWork style={styles.labelIcon} />
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
                  className="form-input"
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <MdOutlineDescription style={styles.labelIcon} />
                  Description détaillée
                </label>
                <p style={styles.hint}>
                  Décrivez ce que comprend le service, les avantages, les spécifications...
                </p>
                <textarea
                  name="descriptions"
                  value={formData.descriptions}
                  onChange={handleChange}
                  style={styles.textarea}
                  rows="6"
                  placeholder="Décrivez votre service en détail..."
                  className="form-textarea"
                />
              </div>

              <div style={styles.formGroup}>
                <label style={styles.label}>
                  <MdOutlineAttachMoney style={styles.labelIcon} />
                  Tarif (FCFA)
                </label>
                <p style={styles.hint}>
                  Laissez vide pour "Prix sur demande" ou indiquez un tarif fixe
                </p>
                <div style={styles.priceInputContainer}>
                  <FiDollarSign style={styles.priceIcon} />
                  <input
                    type="number"
                    name="price"
                    value={formData.price}
                    onChange={handleChange}
                    style={styles.priceInput}
                    placeholder="Ex: 25000"
                    min="0"
                    className="form-input"
                  />
                  <span style={styles.currency}>FCFA</span>
                </div>
              </div>
            </div>
          )}

          {/* Section 4: Horaires */}
          {activeSection === 'horaires' && (
            <div style={styles.section}>
              <div style={styles.sectionHeader}>
                <div style={styles.sectionIcon}>
                  <FiClock />
                </div>
                <div>
                  <h2 style={styles.sectionTitle}>Disponibilité</h2>
                  <p style={styles.sectionSubtitle}>
                    Définissez les horaires de disponibilité de votre service
                  </p>
                </div>
              </div>

              <div style={styles.formGroup}>
                <div style={styles.checkboxCard}>
                  <input
                    type="checkbox"
                    name="is_open_24h"
                    checked={formData.is_open_24h}
                    onChange={handleChange}
                    style={styles.checkbox}
                    id="24h_checkbox"
                  />
                  <label htmlFor="24h_checkbox" style={styles.checkboxLabel}>
                    <div style={styles.checkboxIcon}>
                      <MdOutlineSchedule />
                    </div>
                    <div>
                      <div style={styles.checkboxTitle}>Service disponible 24h/24</div>
                      <div style={styles.checkboxDescription}>
                        Votre service est accessible à tout moment, même la nuit
                      </div>
                    </div>
                  </label>
                </div>
              </div>

              {!formData.is_open_24h && (
                <div style={styles.scheduleSection}>
                  <h3 style={styles.scheduleTitle}>Horaires spécifiques</h3>
                  <div style={styles.scheduleGrid}>
                    <div style={styles.scheduleItem}>
                      <label style={styles.scheduleLabel}>
                        <MdOutlineAccessTime style={styles.scheduleIcon} />
                        Heure d'ouverture
                      </label>
                      <input
                        type="time"
                        name="start_time"
                        value={formData.start_time}
                        onChange={handleChange}
                        style={styles.scheduleInput}
                        className="form-input"
                      />
                    </div>
                    <div style={styles.scheduleItem}>
                      <label style={styles.scheduleLabel}>
                        <MdOutlineAccessTime style={styles.scheduleIcon} />
                        Heure de fermeture
                      </label>
                      <input
                        type="time"
                        name="end_time"
                        value={formData.end_time}
                        onChange={handleChange}
                        style={styles.scheduleInput}
                        className="form-input"
                      />
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Section 5: Médias */}
          {activeSection === 'medias' && (
            <div style={styles.section}>
              <div style={styles.sectionHeader}>
                <div style={styles.sectionIcon}>
                  <FiCamera />
                </div>
                <div>
                  <h2 style={styles.sectionTitle}>Photos du service</h2>
                  <p style={styles.sectionSubtitle}>
                    Ajoutez des images pour illustrer votre service
                  </p>
                </div>
              </div>

              <div style={styles.formGroup}>
                <div style={styles.uploadArea}>
                  <input
                    type="file"
                    accept="image/*"
                    multiple
                    onChange={handleFilesChange}
                    style={styles.fileInput}
                    id="file-upload"
                  />
                  <label htmlFor="file-upload" style={styles.uploadLabel}>
                    <div style={styles.uploadIcon}>
                      <FiUpload />
                    </div>
                    <div style={styles.uploadContent}>
                      <div style={styles.uploadTitle}>Glissez-déposez ou cliquez pour uploader</div>
                      <div style={styles.uploadSubtitle}>
                        Formats: JPG, PNG, WEBP • Max: 2MB par image
                      </div>
                    </div>
                  </label>
                </div>
                
                {previews.length > 0 && (
                  <div style={styles.previewsContainer}>
                    <h3 style={styles.previewsTitle}>
                      <MdOutlinePhotoLibrary style={styles.previewsTitleIcon} />
                      Images sélectionnées ({previews.length})
                    </h3>
                    <div style={styles.previewsGrid}>
                      {previews.map((preview, index) => (
                        <div key={index} style={styles.previewCard}>
                          <div style={styles.previewImageContainer}>
                            <img 
                              src={preview} 
                              alt={`Preview ${index + 1}`} 
                              style={styles.previewImage}
                            />
                            <button 
                              type="button"
                              onClick={() => removeImage(index)}
                              style={styles.removeImageButton}
                            >
                              <FiX />
                            </button>
                          </div>
                          <div style={styles.previewInfo}>
                            <span style={styles.previewName}>
                              Image {index + 1}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Navigation entre sections */}
          <div style={styles.sectionNavigation}>
            <div style={styles.navigationButtons}>
              {sections.findIndex(s => s.id === activeSection) > 0 && (
                <button
                  type="button"
                  onClick={() => {
                    const currentIndex = sections.findIndex(s => s.id === activeSection);
                    setActiveSection(sections[currentIndex - 1].id);
                  }}
                  style={styles.navButton}
                >
                  <FiArrowLeft style={styles.navButtonIcon} />
                  Précédent
                </button>
              )}
              
              <div style={styles.navSpacer}></div>
              
              {sections.findIndex(s => s.id === activeSection) < sections.length - 1 ? (
                <button
                  type="button"
                  onClick={() => {
                    const currentIndex = sections.findIndex(s => s.id === activeSection);
                    setActiveSection(sections[currentIndex + 1].id);
                  }}
                  style={styles.navButtonPrimary}
                >
                  Suivant
                  <FiChevronDown style={styles.navButtonIconRight} />
                </button>
              ) : (
                <button 
                  type="submit" 
                  disabled={loading}
                  style={{
                    ...styles.submitButton,
                    opacity: loading ? 0.6 : 1,
                    cursor: loading ? 'not-allowed' : 'pointer',
                  }}
                >
                  {loading ? (
                    <>
                      <FiRefreshCw style={styles.submitButtonIconLoading} />
                      Création en cours...
                    </>
                  ) : (
                    <>
                      <FiCheckCircle style={styles.submitButtonIcon} />
                      Créer le service
                    </>
                  )}
                </button>
              )}
            </div>
          </div>
        </form>

        {/* Conseils */}
        <div style={styles.tipsCard}>
          <div style={styles.tipsHeader}>
            <MdOutlineTipsAndUpdates style={styles.tipsIcon} />
            <h3 style={styles.tipsTitle}>Conseils pour un service réussi</h3>
          </div>
          <div style={styles.tipsGrid}>
            <div style={styles.tipItem}>
              <div style={styles.tipIcon}>
                <FiInfo />
              </div>
              <div>
                <div style={styles.tipTitle}>Soyez précis</div>
                <div style={styles.tipText}>
                  Décrivez exactement ce que comprend votre service
                </div>
              </div>
            </div>
            <div style={styles.tipItem}>
              <div style={styles.tipIcon}>
                <FiCamera />
              </div>
              <div>
                <div style={styles.tipTitle}>Photos de qualité</div>
                <div style={styles.tipText}>
                  Utilisez des images claires et professionnelles
                </div>
              </div>
            </div>
            <div style={styles.tipItem}>
              <div style={styles.tipIcon}>
                <FiDollarSign />
              </div>
              <div>
                <div style={styles.tipTitle}>Prix transparent</div>
                <div style={styles.tipText}>
                  Indiquez un prix clair ou proposez un devis
                </div>
              </div>
            </div>
            <div style={styles.tipItem}>
              <div style={styles.tipIcon}>
                <FiClock />
              </div>
              <div>
                <div style={styles.tipTitle}>Disponibilité</div>
                <div style={styles.tipText}>
                  Précisez vos horaires de disponibilité
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* CSS Animations */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
          from { opacity: 0; transform: translateX(-20px); }
          to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes pulse {
          0% { transform: scale(1); }
          50% { transform: scale(1.05); }
          100% { transform: scale(1); }
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
          outline: none;
          border-color: #ef4444;
          box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
          transform: translateY(-1px);
        }
        
        .section-animation {
          animation: fadeIn 0.5s ease-out;
        }
        
        .upload-label:hover {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          transform: translateY(-2px);
          box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }
        
        .preview-card:hover {
          transform: translateY(-4px);
          box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        
        .domaine-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .submit-button:hover:not(:disabled) {
          animation: pulse 0.3s ease-in-out;
          box-shadow: 0 10px 30px rgba(37, 99, 235, 0.3);
        }
      `}</style>
    </div>
  );
}

// Styles
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
    width: '50px',
    height: '50px',
    border: '4px solid #dbeafe',
    borderTop: '4px solid #ef4444',
    borderRadius: '50%',
    animation: 'spin 1s linear infinite',
  },
  loadingText: {
    color: '#475569',
    fontSize: '1.125rem',
  },
  loadingSubtext: {
    color: '#94a3b8',
    fontSize: '0.875rem',
  },
  emptyState: {
    backgroundColor: '#fff',
    padding: '4rem 2rem',
    borderRadius: '1.5rem',
    textAlign: 'center',
    border: '2px dashed #e2e8f0',
    maxWidth: '600px',
    margin: '4rem auto',
  },
  emptyIconContainer: {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '80px',
    height: '80px',
    backgroundColor: '#fee2e2',
    borderRadius: '50%',
    marginBottom: '1.5rem',
  },
  emptyIcon: {
    fontSize: '2.5rem',
    color: '#dc2626',
  },
  emptyTitle: {
    fontSize: '1.75rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.75rem',
  },
  emptyText: {
    color: '#64748b',
    fontSize: '1.125rem',
    marginBottom: '2rem',
    lineHeight: '1.6',
  },
  emptyActions: {
    display: 'flex',
    gap: '1rem',
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  emptyButton: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#ef4444',
    color: '#fff',
    padding: '0.875rem 1.75rem',
    borderRadius: '0.75rem',
    textDecoration: 'none',
    fontWeight: '600',
    boxShadow: '0 4px 6px -1px rgba(59, 130, 246, 0.2)',
  },
  emptyButtonSecondary: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#f1f5f9',
    color: '#475569',
    border: '1px solid #e2e8f0',
    padding: '0.875rem 1.75rem',
    borderRadius: '0.75rem',
    textDecoration: 'none',
    fontWeight: '600',
  },
  emptyButtonIcon: {
    fontSize: '1.125rem',
  },
  header: {
    marginBottom: '2rem',
  },
  backButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    color: '#ef4444',
    textDecoration: 'none',
    fontWeight: '600',
    marginBottom: '1rem',
    padding: '0.5rem 0',
  },
  backButtonIcon: {
    fontSize: '1.25rem',
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
    color: '#ef4444',
  },
  subtitle: {
    color: '#64748b',
    fontSize: '1.125rem',
    lineHeight: '1.6',
  },
  stepsContainer: {
    marginBottom: '2rem',
  },
  steps: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    position: 'relative',
  },
  stepItem: {
    display: 'flex',
    alignItems: 'center',
    flex: 1,
  },
  stepButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    backgroundColor: '#fff',
    border: '2px solid #e2e8f0',
    padding: '1rem 1.5rem',
    borderRadius: '0.75rem',
    cursor: 'pointer',
    transition: 'all 0.3s',
    zIndex: 2,
    flex: 1,
    minWidth: '150px',
  },
  stepButtonActive: {
    backgroundColor: '#ef4444',
    borderColor: '#ef4444',
    color: '#fff',
    transform: 'translateY(-2px)',
    boxShadow: '0 8px 20px rgba(59, 130, 246, 0.2)',
  },
  stepCompleted: {
    backgroundColor: '#10b981',
    borderColor: '#10b981',
    color: '#fff',
  },
  stepIcon: {
    fontSize: '1.25rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepLabel: {
    fontSize: '0.875rem',
    fontWeight: '600',
    whiteSpace: 'nowrap',
  },
  stepConnector: {
    height: '2px',
    backgroundColor: '#e2e8f0',
    flex: 1,
    margin: '0 0.5rem',
    minWidth: '40px',
  },
  error: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    backgroundColor: '#fef2f2',
    border: '1px solid #fecaca',
    padding: '1rem 1.5rem',
    borderRadius: '0.75rem',
    marginBottom: '2rem',
    animation: 'slideIn 0.3s ease-out',
  },
  errorIcon: {
    fontSize: '1.5rem',
    color: '#ef4444',
    flexShrink: 0,
  },
  errorTitle: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#dc2626',
    marginBottom: '0.25rem',
  },
  errorText: {
    fontSize: '0.875rem',
    color: '#991b1b',
  },
  errorCloseButton: {
    marginLeft: 'auto',
    backgroundColor: 'transparent',
    border: 'none',
    color: '#dc2626',
    cursor: 'pointer',
    padding: '0.25rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  success: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    backgroundColor: '#d1fae5',
    border: '1px solid #a7f3d0',
    padding: '1rem 1.5rem',
    borderRadius: '0.75rem',
    marginBottom: '2rem',
    animation: 'slideIn 0.3s ease-out',
  },
  successIcon: {
    fontSize: '1.5rem',
    color: '#059669',
    flexShrink: 0,
  },
  successTitle: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#059669',
    marginBottom: '0.25rem',
  },
  successText: {
    fontSize: '0.875rem',
    color: '#065f46',
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: '2rem',
  },
  section: {
    backgroundColor: '#fff',
    padding: '2rem',
    borderRadius: '1rem',
    border: '1px solid #e2e8f0',
    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
    animation: 'fadeIn 0.5s ease-out',
  },
  sectionHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    marginBottom: '2rem',
    paddingBottom: '1.5rem',
    borderBottom: '2px solid #f1f5f9',
  },
  sectionIcon: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '56px',
    height: '56px',
    backgroundColor: '#dbeafe',
    borderRadius: '0.75rem',
    fontSize: '1.75rem',
    color: '#ef4444',
  },
  sectionTitle: {
    fontSize: '1.5rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.25rem',
  },
  sectionSubtitle: {
    color: '#64748b',
    fontSize: '0.95rem',
  },
  formGroup: {
    marginBottom: '1.5rem',
  },
  label: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.75rem',
    fontSize: '0.95rem',
  },
  labelIcon: {
    fontSize: '1.125rem',
    color: '#ef4444',
  },
  required: {
    color: '#ef4444',
  },
  hint: {
    color: '#64748b',
    fontSize: '0.875rem',
    marginBottom: '0.75rem',
    lineHeight: '1.5',
  },
  input: {
    width: '100%',
    padding: '0.875rem 1rem',
    border: '2px solid #e2e8f0',
    borderRadius: '0.75rem',
    fontSize: '0.95rem',
    transition: 'all 0.3s',
    outline: 'none',
    backgroundColor: '#fff',
  },
  select: {
    width: '100%',
    padding: '0.875rem 1rem',
    border: '2px solid #e2e8f0',
    borderRadius: '0.75rem',
    fontSize: '0.95rem',
    transition: 'all 0.3s',
    outline: 'none',
    backgroundColor: '#fff',
    cursor: 'pointer',
  },
  textarea: {
    width: '100%',
    padding: '0.875rem 1rem',
    border: '2px solid #e2e8f0',
    borderRadius: '0.75rem',
    fontSize: '0.95rem',
    transition: 'all 0.3s',
    outline: 'none',
    fontFamily: 'inherit',
    resize: 'vertical',
    minHeight: '120px',
  },
  entrepriseCard: {
    backgroundColor: '#f8fafc',
    borderRadius: '0.75rem',
    border: '1px solid #e2e8f0',
    overflow: 'hidden',
  },
  entrepriseCardHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    padding: '1.5rem',
    borderBottom: '1px solid #e2e8f0',
  },
  entrepriseCardLogo: {
    width: '60px',
    height: '60px',
    borderRadius: '0.75rem',
    objectFit: 'cover',
    border: '2px solid #e2e8f0',
  },
  entrepriseCardLogoPlaceholder: {
    width: '60px',
    height: '60px',
    borderRadius: '0.75rem',
    backgroundColor: '#dbeafe',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  entrepriseCardLogoIcon: {
    fontSize: '2rem',
    color: '#3b82f6',
  },
  entrepriseCardName: {
    fontSize: '1.125rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.25rem',
  },
  entrepriseCardStatus: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.375rem',
    backgroundColor: '#d1fae5',
    color: '#059669',
    padding: '0.25rem 0.75rem',
    borderRadius: '0.5rem',
    fontSize: '0.75rem',
    fontWeight: '600',
    width: 'fit-content',
  },
  entrepriseCardStatusIcon: {
    fontSize: '0.875rem',
  },
  entrepriseCardBody: {
    padding: '1.5rem',
  },
  entrepriseCardInfo: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '1rem',
  },
  entrepriseCardInfoItem: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '0.75rem',
  },
  entrepriseCardInfoIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
    marginTop: '0.125rem',
    flexShrink: 0,
  },
  entrepriseCardInfoLabel: {
    fontSize: '0.75rem',
    color: '#64748b',
    fontWeight: '500',
    marginBottom: '0.125rem',
  },
  entrepriseCardInfoValue: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e293b',
    lineHeight: '1.4',
  },
  domainesGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
    gap: '1rem',
    marginTop: '1rem',
  },
  domaineCard: {
    backgroundColor: '#fff',
    border: '2px solid #e2e8f0',
    borderRadius: '0.75rem',
    padding: '1rem',
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    cursor: 'pointer',
    transition: 'all 0.3s',
    position: 'relative',
  },
  domaineCardActive: {
    backgroundColor: '#dbeafe',
    borderColor: '#ef4444',
    color: '#ef4444',
  },
  domaineCardIcon: {
    fontSize: '1.25rem',
    color: '#ef4444',
  },
  domaineCardName: {
    fontSize: '0.875rem',
    fontWeight: '600',
    flex: 1,
  },
  domaineCardCheck: {
    fontSize: '1rem',
    color: '#10b981',
  },
  priceInputContainer: {
    position: 'relative',
    display: 'flex',
    alignItems: 'center',
  },
  priceIcon: {
    position: 'absolute',
    left: '1rem',
    color: '#64748b',
    fontSize: '1.125rem',
  },
  priceInput: {
    width: '100%',
    padding: '0.875rem 1rem 0.875rem 3rem',
    border: '2px solid #e2e8f0',
    borderRadius: '0.75rem',
    fontSize: '0.95rem',
    transition: 'all 0.3s',
    outline: 'none',
  },
  currency: {
    position: 'absolute',
    right: '1rem',
    color: '#64748b',
    fontSize: '0.875rem',
    fontWeight: '500',
  },
  checkboxCard: {
    backgroundColor: '#f8fafc',
    border: '2px solid #e2e8f0',
    borderRadius: '0.75rem',
    padding: '1.25rem',
    cursor: 'pointer',
    transition: 'all 0.3s',
  },
  checkbox: {
    display: 'none',
  },
  checkboxLabel: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    cursor: 'pointer',
    width: '100%',
  },
  checkboxIcon: {
    fontSize: '1.5rem',
    color: '#ef4444',
  },
  checkboxTitle: {
    fontSize: '1rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.25rem',
  },
  checkboxDescription: {
    fontSize: '0.875rem',
    color: '#64748b',
  },
  scheduleSection: {
    marginTop: '2rem',
  },
  scheduleTitle: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '1rem',
  },
  scheduleGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: '1.5rem',
  },
  scheduleItem: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  scheduleLabel: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#475569',
  },
  scheduleIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  scheduleInput: {
    padding: '0.875rem 1rem',
    border: '2px solid #e2e8f0',
    borderRadius: '0.75rem',
    fontSize: '0.95rem',
    transition: 'all 0.3s',
    outline: 'none',
    backgroundColor: '#fff',
  },
  uploadArea: {
    border: '2px dashed #e2e8f0',
    borderRadius: '0.75rem',
    backgroundColor: '#f8fafc',
    transition: 'all 0.3s',
  },
  fileInput: {
    display: 'none',
  },
  uploadLabel: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '3rem 2rem',
    cursor: 'pointer',
    textAlign: 'center',
  },
  uploadIcon: {
    fontSize: '3rem',
    color: '#94a3b8',
    marginBottom: '1rem',
  },
  uploadContent: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  uploadTitle: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#475569',
  },
  uploadSubtitle: {
    fontSize: '0.875rem',
    color: '#94a3b8',
  },
  previewsContainer: {
    marginTop: '2rem',
  },
  previewsTitle: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '1rem',
  },
  previewsTitleIcon: {
    fontSize: '1.25rem',
    color: '#ef4444',
  },
  previewsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
    gap: '1rem',
  },
  previewCard: {
    backgroundColor: '#fff',
    borderRadius: '0.75rem',
    border: '1px solid #e2e8f0',
    overflow: 'hidden',
    transition: 'all 0.3s',
  },
  previewImageContainer: {
    position: 'relative',
    height: '150px',
    overflow: 'hidden',
  },
  previewImage: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  removeImageButton: {
    position: 'absolute',
    top: '0.5rem',
    right: '0.5rem',
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    color: '#fff',
    border: 'none',
    width: '32px',
    height: '32px',
    borderRadius: '50%',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    cursor: 'pointer',
    fontSize: '1rem',
    transition: 'all 0.2s',
  },
  previewInfo: {
    padding: '0.75rem',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  previewName: {
    fontSize: '0.75rem',
    color: '#64748b',
    fontWeight: '500',
  },
  sectionNavigation: {
    marginTop: '1rem',
  },
  navigationButtons: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  navButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    border: '2px solid #e2e8f0',
    color: '#475569',
    padding: '0.75rem 1.5rem',
    borderRadius: '0.75rem',
    fontSize: '0.875rem',
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.3s',
  },
  navButtonPrimary: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#ef4444',
    color: '#fff',
    border: 'none',
    padding: '0.75rem 1.5rem',
    borderRadius: '0.75rem',
    fontSize: '0.875rem',
    fontWeight: '600',
    cursor: 'pointer',
    transition: 'all 0.3s',
  },
  navButtonIcon: {
    fontSize: '1rem',
  },
  navButtonIconRight: {
    fontSize: '1rem',
    transform: 'rotate(-90deg)',
  },
  navSpacer: {
    flex: 1,
  },
  submitButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#10b981',
    color: '#fff',
    border: 'none',
    padding: '1rem 2rem',
    borderRadius: '0.75rem',
    fontSize: '1rem',
    fontWeight: '600',
    cursor: 'pointer',
    boxShadow: '0 4px 6px -1px rgba(16, 185, 129, 0.2)',
    transition: 'all 0.3s',
  },
  submitButtonIcon: {
    fontSize: '1.25rem',
  },
  submitButtonIconLoading: {
    fontSize: '1.25rem',
    animation: 'spin 1s linear infinite',
  },
  tipsCard: {
    backgroundColor: '#dbeafe',
    border: '1px solid #93c5fd',
    borderRadius: '1rem',
    padding: '2rem',
    marginTop: '2rem',
  },
  tipsHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    marginBottom: '1.5rem',
  },
  tipsIcon: {
    fontSize: '1.75rem',
    color: '#1e40af',
  },
  tipsTitle: {
    fontSize: '1.25rem',
    fontWeight: '700',
    color: '#1e40af',
  },
  tipsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: '1.5rem',
  },
  tipItem: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '0.75rem',
  },
  tipIcon: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '36px',
    height: '36px',
    backgroundColor: '#fff',
    borderRadius: '0.5rem',
    fontSize: '1rem',
    color: '#1e40af',
    flexShrink: 0,
  },
  tipTitle: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e40af',
    marginBottom: '0.25rem',
  },
  tipText: {
    fontSize: '0.75rem',
    color: '#3730a3',
    lineHeight: '1.4',
  },

  // Responsive
  '@media (max-width: 768px)': {
    content: {
      padding: '0 1rem',
    },
    title: {
      fontSize: '1.75rem',
    },
    steps: {
      flexDirection: 'column',
      gap: '1rem',
      alignItems: 'stretch',
    },
    stepItem: {
      flexDirection: 'column',
      alignItems: 'stretch',
    },
    stepButton: {
      minWidth: 'auto',
    },
    stepConnector: {
      width: '2px',
      height: '20px',
      margin: '0.25rem 0',
      alignSelf: 'center',
    },
    scheduleGrid: {
      gridTemplateColumns: '1fr',
    },
    previewsGrid: {
      gridTemplateColumns: 'repeat(2, 1fr)',
    },
    domainesGrid: {
      gridTemplateColumns: 'repeat(2, 1fr)',
    },
    tipsGrid: {
      gridTemplateColumns: '1fr',
    },
    navigationButtons: {
      flexDirection: 'column',
      gap: '1rem',
    },
    navButton: {
      width: '100%',
      justifyContent: 'center',
    },
    navButtonPrimary: {
      width: '100%',
      justifyContent: 'center',
    },
  },
  '@media (max-width: 480px)': {
    section: {
      padding: '1.5rem',
    },
    previewsGrid: {
      gridTemplateColumns: '1fr',
    },
    domainesGrid: {
      gridTemplateColumns: '1fr',
    },
    entrepriseCardInfo: {
      gridTemplateColumns: '1fr',
    },
  },
};