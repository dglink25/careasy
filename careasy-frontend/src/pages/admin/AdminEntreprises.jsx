// careasy-frontend/src/pages/admin/AdminEntreprises.jsx
import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import { adminApi } from '../../api/adminApi';
import theme from '../../config/theme';

// Import des icônes React Icons
import {
  FiSearch,
  FiFilter,
  FiEye,
  FiChevronLeft,
  FiChevronRight,
  FiFileText,
  FiCheckCircle,
  FiXCircle,
  FiClock,
  FiAlertCircle,
  FiDownload,
  FiMail,
  FiPhone,
  FiMapPin,
  FiUsers,
  FiHome,
  FiRefreshCw,
  FiTrash2,
  FiEdit,
  FiCopy,
  FiPrinter,
  FiBarChart2,
  FiGrid,
  FiList,
  FiArrowUp,
  FiArrowDown,
  FiMoreVertical,
  FiExternalLink
} from 'react-icons/fi';
import {
  MdBusiness,
  MdPendingActions,
  MdVerified,
  MdCancel,
  MdDashboard,
  MdOutlineBusinessCenter,
  MdOutlineEmail,
  MdOutlinePhone,
  MdOutlineLocationOn,
  MdOutlineDescription,
  MdOutlineAttachFile
} from 'react-icons/md';
import { 
  FaRegBuilding,
  FaUserTie,
  FaChartLine,
  FaFileInvoice,
  FaRegCalendarAlt,
  FaIdCard,
  FaRegCheckCircle,
  FaRegTimesCircle,
  FaRegClock
} from 'react-icons/fa';
import { HiOutlineDocumentText, HiOutlineExclamationCircle } from 'react-icons/hi';
import { BsBuilding, BsCardChecklist, BsPersonBadge } from 'react-icons/bs';
import { BiSearchAlt } from 'react-icons/bi';

export default function AdminEntreprises() {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  const statusParam = searchParams.get('status');
  const searchParam = searchParams.get('search');

  const [entreprises, setEntreprises] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState(statusParam || 'all');
  const [searchTerm, setSearchTerm] = useState(searchParam || '');
  const [sortConfig, setSortConfig] = useState({ key: 'created_at', direction: 'desc' });
  const [selectedEntreprises, setSelectedEntreprises] = useState([]);
  const [bulkAction, setBulkAction] = useState('');

  useEffect(() => {
    fetchEntreprises();
  }, []);

  useEffect(() => {
    if (statusParam) {
      setFilter(statusParam);
    }
    if (searchParam) {
      setSearchTerm(searchParam);
    }
  }, [statusParam, searchParam]);

  const fetchEntreprises = useCallback(async () => {
    try {
      setLoading(true);
      const data = await adminApi.getEntreprises();
      setEntreprises(data.data);
      setError('');
    } catch (err) {
      setError('Erreur lors du chargement des entreprises');
      console.error('Erreur fetchEntreprises:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  const handleFilterChange = (newFilter) => {
    setFilter(newFilter);
    if (newFilter === 'all') {
      setSearchParams(searchTerm ? { search: searchTerm } : {});
    } else {
      setSearchParams({ 
        status: newFilter,
        ...(searchTerm && { search: searchTerm })
      });
    }
    setSelectedEntreprises([]);
  };

  const handleSearch = (value) => {
    setSearchTerm(value);
    const params = {};
    if (filter !== 'all') params.status = filter;
    if (value) params.search = value;
    setSearchParams(params);
  };

  const handleSort = (key) => {
    let direction = 'asc';
    if (sortConfig.key === key && sortConfig.direction === 'asc') {
      direction = 'desc';
    }
    setSortConfig({ key, direction });
  };

  const getStatusBadge = (status) => {
    const badges = {
      pending: { 
        icon: <FaRegClock style={{ marginRight: '6px' }} />, 
        text: 'En attente', 
        color: theme.colors.warning, 
        bg: '#FEF3C7' 
      },
      validated: { 
        icon: <FaRegCheckCircle style={{ marginRight: '6px' }} />, 
        text: 'Validée', 
        color: theme.colors.success, 
        bg: '#D1FAE5' 
      },
      rejected: { 
        icon: <FaRegTimesCircle style={{ marginRight: '6px' }} />, 
        text: 'Rejetée', 
        color: theme.colors.error, 
        bg: '#FEE2E2' 
      },
    };
    const badge = badges[status] || badges.pending;
    
    return (
      <span style={{...styles.badge, backgroundColor: badge.bg, color: badge.color}}>
        {badge.icon}
        {badge.text}
      </span>
    );
  };

  const getSortIcon = (key) => {
    if (sortConfig.key !== key) {
      return <FiChevronRight style={styles.sortIcon} />;
    }
    return sortConfig.direction === 'asc' 
      ? <FiArrowUp style={styles.sortIcon} /> 
      : <FiArrowDown style={styles.sortIcon} />;
  };

  const sortedEntreprises = [...entreprises].sort((a, b) => {
    if (sortConfig.key === 'name') {
      return sortConfig.direction === 'asc' 
        ? a.name.localeCompare(b.name)
        : b.name.localeCompare(a.name);
    }
    if (sortConfig.key === 'created_at') {
      return sortConfig.direction === 'asc'
        ? new Date(a.created_at) - new Date(b.created_at)
        : new Date(b.created_at) - new Date(a.created_at);
    }
    return 0;
  });

  // Filtrer par statut et recherche
  const filteredEntreprises = sortedEntreprises
    .filter(e => filter === 'all' || e.status === filter)
    .filter(e => 
      e.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      e.pdg_full_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      e.prestataire?.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      e.ifu_number?.toLowerCase().includes(searchTerm.toLowerCase())
    );

  const stats = {
    total: entreprises.length,
    pending: entreprises.filter(e => e.status === 'pending').length,
    validated: entreprises.filter(e => e.status === 'validated').length,
    rejected: entreprises.filter(e => e.status === 'rejected').length,
  };

  const handleSelectEntreprise = (id) => {
    setSelectedEntreprises(prev => 
      prev.includes(id) 
        ? prev.filter(item => item !== id)
        : [...prev, id]
    );
  };

  const handleSelectAll = () => {
    if (selectedEntreprises.length === filteredEntreprises.length) {
      setSelectedEntreprises([]);
    } else {
      setSelectedEntreprises(filteredEntreprises.map(e => e.id));
    }
  };

  const handleBulkAction = async () => {
    if (!bulkAction || selectedEntreprises.length === 0) return;
    
    try {
      // Ici vous implémenteriez l'action en masse
      console.log(`Bulk action ${bulkAction} on:`, selectedEntreprises);
      setSelectedEntreprises([]);
      setBulkAction('');
    } catch (error) {
      console.error('Erreur action en masse:', error);
    }
  };

  const handleExport = () => {
    // Logique d'exportation
    const dataToExport = filteredEntreprises.map(entreprise => ({
      'Nom': entreprise.name,
      'PDG': entreprise.pdg_full_name,
      'IFU': entreprise.ifu_number,
      'Prestataire': entreprise.prestataire?.name || 'Non assigné',
      'Date': new Date(entreprise.created_at).toLocaleDateString('fr-FR'),
      'Statut': entreprise.status === 'pending' ? 'En attente' : 
                entreprise.status === 'validated' ? 'Validée' : 'Rejetée'
    }));
    
    console.log('Export data:', dataToExport);
    // Ici vous ajouteriez la logique pour télécharger le fichier
  };

  if (loading) {
    return (
      <div style={styles.container}>
        <div style={styles.loadingContainer}>
          <div style={styles.spinner}></div>
          <p style={styles.loadingText}>Chargement des entreprises...</p>
        </div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      <div style={styles.content}>
        {/* Header avec navigation */}
        <div style={styles.header}>
          <div style={styles.headerLeft}>
            <button 
              onClick={() => navigate('/admin/dashboard')}
              style={styles.backButton}
            >
              <FiChevronLeft style={styles.backButtonIcon} />
              Dashboard
            </button>
            <div>
              <h1 style={styles.title}>
                <MdOutlineBusinessCenter style={styles.titleIcon} />
                Gestion des Entreprises
              </h1>
              <p style={styles.subtitle}>
                Validez ou rejetez les demandes d'inscription des prestataires
              </p>
            </div>
          </div>
          <div style={styles.headerActions}>
            <button 
              onClick={fetchEntreprises}
              style={styles.headerActionButton}
            >
              <FiRefreshCw style={styles.headerActionIcon} />
              Rafraîchir
            </button>
            <button 
              onClick={handleExport}
              style={styles.headerActionButton}
            >
              <FiDownload style={styles.headerActionIcon} />
              Exporter
            </button>
          </div>
        </div>

        {/* Cartes de statistiques */}
        <div style={styles.statsGrid}>
          <div 
            style={styles.statCard}
            onClick={() => handleFilterChange('all')}
            className="stat-card"
          >
            <div style={styles.statIconContainer}>
              <BsBuilding style={styles.statIcon} />
            </div>
            <div style={styles.statContent}>
              <div style={styles.statNumber}>{stats.total}</div>
              <div style={styles.statLabel}>Total entreprises</div>
            </div>
            <FiChevronRight style={styles.statArrow} />
          </div>
          
          <div 
            style={{...styles.statCard, ...styles.pendingCard}}
            onClick={() => handleFilterChange('pending')}
            className="stat-card"
          >
            <div style={{...styles.statIconContainer, backgroundColor: 'rgba(245, 158, 11, 0.1)'}}>
              <FaRegClock style={{...styles.statIcon, color: theme.colors.warning}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: theme.colors.warning}}>
                {stats.pending}
              </div>
              <div style={styles.statLabel}>En attente</div>
            </div>
            <FiChevronRight style={styles.statArrow} />
          </div>
          
          <div 
            style={{...styles.statCard, ...styles.validatedCard}}
            onClick={() => handleFilterChange('validated')}
            className="stat-card"
          >
            <div style={{...styles.statIconContainer, backgroundColor: 'rgba(34, 197, 94, 0.1)'}}>
              <FaRegCheckCircle style={{...styles.statIcon, color: theme.colors.success}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: theme.colors.success}}>
                {stats.validated}
              </div>
              <div style={styles.statLabel}>Validées</div>
            </div>
            <FiChevronRight style={styles.statArrow} />
          </div>
          
          <div 
            style={{...styles.statCard, ...styles.rejectedCard}}
            onClick={() => handleFilterChange('rejected')}
            className="stat-card"
          >
            <div style={{...styles.statIconContainer, backgroundColor: 'rgba(239, 68, 68, 0.1)'}}>
              <FaRegTimesCircle style={{...styles.statIcon, color: theme.colors.error}} />
            </div>
            <div style={styles.statContent}>
              <div style={{...styles.statNumber, color: theme.colors.error}}>
                {stats.rejected}
              </div>
              <div style={styles.statLabel}>Rejetées</div>
            </div>
            <FiChevronRight style={styles.statArrow} />
          </div>
        </div>

        {/* Barre de contrôle */}
        <div style={styles.controlBar}>
          <div style={styles.filterButtons}>
            <button 
              onClick={() => handleFilterChange('all')}
              style={{
                ...styles.filterButton,
                ...(filter === 'all' ? styles.filterButtonActive : {})
              }}
            >
              <FiGrid style={styles.filterButtonIcon} />
              Toutes
              <span style={styles.filterCount}>{stats.total}</span>
            </button>
            <button 
              onClick={() => handleFilterChange('pending')}
              style={{
                ...styles.filterButton,
                ...(filter === 'pending' ? styles.filterButtonActive : {})
              }}
            >
              <FaRegClock style={styles.filterButtonIcon} />
              En attente
              <span style={styles.filterCount}>{stats.pending}</span>
            </button>
            <button 
              onClick={() => handleFilterChange('validated')}
              style={{
                ...styles.filterButton,
                ...(filter === 'validated' ? styles.filterButtonActive : {})
              }}
            >
              <FaRegCheckCircle style={styles.filterButtonIcon} />
              Validées
              <span style={styles.filterCount}>{stats.validated}</span>
            </button>
            <button 
              onClick={() => handleFilterChange('rejected')}
              style={{
                ...styles.filterButton,
                ...(filter === 'rejected' ? styles.filterButtonActive : {})
              }}
            >
              <FaRegTimesCircle style={styles.filterButtonIcon} />
              Rejetées
              <span style={styles.filterCount}>{stats.rejected}</span>
            </button>
          </div>

          <div style={styles.searchContainer}>
            <BiSearchAlt style={styles.searchIcon} />
            <input
              type="text"
              placeholder="Rechercher par nom, PDG, IFU ou prestataire..."
              value={searchTerm}
              onChange={(e) => handleSearch(e.target.value)}
              style={styles.searchInput}
            />
            {searchTerm && (
              <button 
                onClick={() => handleSearch('')}
                style={styles.clearSearchButton}
              >
                ×
              </button>
            )}
          </div>
        </div>

        {/* Actions en masse */}
        {selectedEntreprises.length > 0 && (
          <div style={styles.bulkActionsBar}>
            <div style={styles.bulkActionsInfo}>
              <FiCheckCircle style={styles.bulkActionsIcon} />
              {selectedEntreprises.length} entreprise(s) sélectionnée(s)
            </div>
            <div style={styles.bulkActionsButtons}>
              <select
                value={bulkAction}
                onChange={(e) => setBulkAction(e.target.value)}
                style={styles.bulkSelect}
              >
                <option value="">Actions en masse...</option>
                <option value="validate">Valider</option>
                <option value="reject">Rejeter</option>
                <option value="export">Exporter</option>
                <option value="delete">Supprimer</option>
              </select>
              <button 
                onClick={handleBulkAction}
                disabled={!bulkAction}
                style={styles.bulkActionButton}
              >
                Appliquer
              </button>
              <button 
                onClick={() => setSelectedEntreprises([])}
                style={styles.bulkCancelButton}
              >
                Annuler
              </button>
            </div>
          </div>
        )}

        {/* Message d'erreur */}
        {error && (
          <div style={styles.error}>
            <HiOutlineExclamationCircle style={styles.errorIcon} />
            <div style={styles.errorContent}>
              <div style={styles.errorTitle}>Erreur de chargement</div>
              <p style={styles.errorText}>{error}</p>
            </div>
            <button 
              onClick={fetchEntreprises}
              style={styles.errorButton}
            >
              Réessayer
            </button>
          </div>
        )}

        {/* Liste des entreprises */}
        {filteredEntreprises.length === 0 ? (
          <div style={styles.emptyState}>
            <div style={styles.emptyIcon}>
              <BsBuilding style={styles.emptyIconSvg} />
            </div>
            <h3 style={styles.emptyTitle}>
              {searchTerm 
                ? `Aucun résultat pour "${searchTerm}"`
                : filter === 'all'
                  ? "Aucune entreprise enregistrée"
                  : `Aucune entreprise avec le statut "${filter}"`
              }
            </h3>
            <p style={styles.emptyText}>
              {searchTerm 
                ? "Essayez avec d'autres termes de recherche"
                : filter !== 'all'
                  ? "Essayez avec un autre filtre"
                  : "Les entreprises apparaîtront ici une fois créées"
              }
            </p>
            <div style={styles.emptyActions}>
              {searchTerm && (
                <button 
                  onClick={() => handleSearch('')}
                  style={styles.emptyActionButton}
                >
                  <FiFilter style={styles.emptyActionIcon} />
                  Effacer la recherche
                </button>
              )}
              {filter !== 'all' && (
                <button 
                  onClick={() => handleFilterChange('all')}
                  style={styles.emptyActionButton}
                >
                  <FiGrid style={styles.emptyActionIcon} />
                  Voir toutes les entreprises
                </button>
              )}
            </div>
          </div>
        ) : (
          <div style={styles.tableWrapper}>
            <div style={styles.tableHeaderInfo}>
              <div style={styles.tableStats}>
                <span style={styles.tableCount}>
                  {filteredEntreprises.length} entreprise(s)
                </span>
                {filter !== 'all' && (
                  <span style={styles.tableFilter}>
                    <FiFilter style={styles.tableFilterIcon} />
                    Filtre: {filter}
                  </span>
                )}
              </div>
              <div style={styles.tableControls}>
                <button 
                  onClick={handleSelectAll}
                  style={styles.selectAllButton}
                >
                  {selectedEntreprises.length === filteredEntreprises.length 
                    ? 'Tout désélectionner'
                    : 'Tout sélectionner'
                  }
                </button>
              </div>
            </div>

            <div style={styles.tableContainer}>
              <table style={styles.table}>
                <thead>
                  <tr style={styles.tableHeader}>
                    <th style={styles.th}>
                      <input
                        type="checkbox"
                        checked={selectedEntreprises.length === filteredEntreprises.length}
                        onChange={handleSelectAll}
                        style={styles.checkbox}
                      />
                    </th>
                    <th 
                      style={styles.th}
                      onClick={() => handleSort('name')}
                      className="sortable-header"
                    >
                      <div style={styles.thContent}>
                        <MdBusiness style={styles.thIcon} />
                        Entreprise
                        {getSortIcon('name')}
                      </div>
                    </th>
                    <th style={styles.th}>
                      <div style={styles.thContent}>
                        <BsPersonBadge style={styles.thIcon} />
                        Prestataire
                      </div>
                    </th>
                    <th style={styles.th}>
                      <div style={styles.thContent}>
                        <FaFileInvoice style={styles.thIcon} />
                        IFU
                      </div>
                    </th>
                    <th 
                      style={styles.th}
                      onClick={() => handleSort('created_at')}
                      className="sortable-header"
                    >
                      <div style={styles.thContent}>
                        <FaRegCalendarAlt style={styles.thIcon} />
                        Date
                        {getSortIcon('created_at')}
                      </div>
                    </th>
                    <th style={styles.th}>
                      <div style={styles.thContent}>
                        <FaRegClock style={styles.thIcon} />
                        Statut
                      </div>
                    </th>
                    <th style={styles.th}>
                      <div style={styles.thContent}>
                        <FiEye style={styles.thIcon} />
                        Actions
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {filteredEntreprises.map((entreprise) => (
                    <tr 
                      key={entreprise.id} 
                      style={styles.tableRow}
                      className="table-row"
                    >
                      <td style={styles.td}>
                        <input
                          type="checkbox"
                          checked={selectedEntreprises.includes(entreprise.id)}
                          onChange={() => handleSelectEntreprise(entreprise.id)}
                          style={styles.checkbox}
                        />
                      </td>
                      <td style={styles.td}>
                        <div style={styles.entrepriseCell}>
                          <div style={styles.entrepriseAvatar}>
                            {entreprise.logo ? (
                              <img 
                                src={`${import.meta.env.VITE_API_URL}/storage/${entreprise.logo}`}
                                alt={entreprise.name}
                                style={styles.entrepriseLogo}
                                onError={(e) => {
                                  e.target.onerror = null;
                                  e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHJ4PSI4IiBmaWxsPSIjRUZFQ0VGIi8+PHBhdGggZD0iTTIwIDI0QzIzLjMxMzcgMjQgMjYgMjEuMzEzNyAyNiAxOEMyNiAxNC42ODYzIDIzLjMxMzcgMTIgMjAgMTJDMTYuNjg2MyAxMiAxNCAxNC42ODYzIDE0IDE4QzE0IDIxLjMxMzcgMTYuNjg2MyAyNCAyMCAyNFoiIGZpbGw9IiM5QzVDQUIiLz48cGF0aCBkPSJNMjggMzBIMTJDMTAuODk1NCAzMCAxMCAyOS4xMDQ2IDEwIDI4VjE2QzEwIDE0Ljg5NTQgMTAuODk1NCAxNCAxMiAxNEgyOEMyOS4xMDQ2IDE0IDMwIDE0Ljg5NTQgMzAgMTZWMjhDMzAgMjkuMTA0NiAyOS4xMDQ2IDMwIDI4IDMwWiIgZmlsbD0iIzlDNUNBQiIvPjwvc3ZnPg==';
                                }}
                              />
                            ) : (
                              <BsBuilding style={styles.defaultLogoIcon} />
                            )}
                          </div>
                          <div style={styles.entrepriseInfo}>
                            <div style={styles.entrepriseName}>{entreprise.name}</div>
                            <div style={styles.entrepriseDetails}>
                              <span style={styles.entrepriseDetail}>
                                <FaUserTie style={styles.detailIcon} />
                                {entreprise.pdg_full_name || 'PDG non renseigné'}
                              </span>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td style={styles.td}>
                        {entreprise.prestataire ? (
                          <div style={styles.prestataireCell}>
                            <div style={styles.prestataireAvatar}>
                              {entreprise.prestataire.name?.charAt(0) || 'P'}
                            </div>
                            <span style={styles.prestataireName}>
                              {entreprise.prestataire.name}
                            </span>
                          </div>
                        ) : (
                          <span style={styles.notAssigned}>Non assigné</span>
                        )}
                      </td>
                      <td style={styles.td}>
                        <div style={styles.ifuCell}>
                          <FaIdCard style={styles.ifuIcon} />
                          <code style={styles.code}>{entreprise.ifu_number || 'N/A'}</code>
                        </div>
                      </td>
                      <td style={styles.td}>
                        <div style={styles.dateCell}>
                          <FaRegCalendarAlt style={styles.dateIcon} />
                          {new Date(entreprise.created_at).toLocaleDateString('fr-FR', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric'
                          })}
                        </div>
                      </td>
                      <td style={styles.td}>
                        {getStatusBadge(entreprise.status)}
                      </td>
                      <td style={styles.td}>
                        <div style={styles.actionButtons}>
                          <button 
                            onClick={() => navigate(`/admin/entreprises/${entreprise.id}`)}
                            style={styles.viewButton}
                            title="Voir détails"
                          >
                            <FiEye style={styles.viewButtonIcon} />
                            {entreprise.status === 'pending' ? 'Examiner' : 'Détails'}
                          </button>
                          <button 
                            onClick={() => navigate(`/admin/entreprises/${entreprise.id}`)}
                            style={styles.moreButton}
                            title="Plus d'actions"
                          >
                            <FiMoreVertical />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            <div style={styles.pagination}>
              <button 
                style={styles.paginationButton}
                disabled
              >
                <FiChevronLeft />
              </button>
              <span style={styles.paginationInfo}>
                Page 1 sur 1
              </span>
              <button 
                style={styles.paginationButton}
                disabled
              >
                <FiChevronRight />
              </button>
            </div>
          </div>
        )}

        {/* Guide de validation */}
        <div style={styles.infoGrid}>
          <div style={styles.infoCard}>
            <div style={styles.infoCardHeader}>
              <HiOutlineDocumentText style={styles.infoCardIcon} />
              <h3 style={styles.infoCardTitle}>Guide de validation</h3>
            </div>
            <ul style={styles.infoList}>
              <li style={styles.infoListItem}>
                <FaRegCheckCircle style={styles.listIcon} />
                Vérifier la cohérence des informations (nom, IFU, RCCM)
              </li>
              <li style={styles.infoListItem}>
                <FaRegCheckCircle style={styles.listIcon} />
                Assurer que les domaines d'activité correspondent
              </li>
              <li style={styles.infoListItem}>
                <FaRegCheckCircle style={styles.listIcon} />
                En cas de doute, contactez le prestataire avant de rejeter
              </li>
              <li style={styles.infoListItem}>
                <FaRegCheckCircle style={styles.listIcon} />
                Fournir des commentaires constructifs en cas de rejet
              </li>
            </ul>
          </div>
          
          <div style={styles.infoCard}>
            <div style={styles.infoCardHeader}>
              <FiAlertCircle style={styles.infoCardIcon} />
              <h3 style={styles.infoCardTitle}>Statistiques rapides</h3>
            </div>
            <div style={styles.statsMini}>
              <div style={styles.statMini}>
                <div style={styles.statMiniNumber}>{stats.pending}</div>
                <div style={styles.statMiniLabel}>En attente</div>
              </div>
              <div style={styles.statMini}>
                <div style={styles.statMiniNumber}>
                  {stats.total > 0 
                    ? Math.round((stats.validated / stats.total) * 100)
                    : 0
                  }%
                </div>
                <div style={styles.statMiniLabel}>Taux de validation</div>
              </div>
              <div style={styles.statMini}>
                <div style={styles.statMiniNumber}>
                  {stats.total > 0 
                    ? Math.round((filteredEntreprises.length / stats.total) * 100)
                    : 0
                  }%
                </div>
                <div style={styles.statMiniLabel}>Correspond au filtre</div>
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
          from { opacity: 0; transform: translateY(10px); }
          to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
          from { opacity: 0; transform: translateX(-20px); }
          to { opacity: 1; transform: translateX(0); }
        }
        
        .stat-card, .table-row, .action-card {
          transition: all 0.3s ease;
          animation: fadeIn 0.5s ease-out;
        }
        
        .stat-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .table-row:hover {
          background-color: rgba(59, 130, 246, 0.04);
        }
        
        .sortable-header {
          cursor: pointer;
          transition: background-color 0.2s;
        }
        
        .sortable-header:hover {
          background-color: rgba(59, 130, 246, 0.05);
        }
        
        .filterButtonActive {
          animation: slideIn 0.3s ease;
        }
        
        .viewButton:hover {
          transform: translateX(2px);
        }
      `}</style>
    </div>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f8fafc',
    padding: '2rem 0',
  },
  content: {
    maxWidth: '1400px',
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
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: '2.5rem',
    flexWrap: 'wrap',
    gap: '1.5rem',
  },
  headerLeft: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  backButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: 'transparent',
    border: 'none',
    color: theme.colors.primary,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    padding: '0.5rem 0',
    width: 'fit-content',
    textDecoration: 'none',
  },
  backButtonIcon: {
    fontSize: '1.25rem',
  },
  title: {
    fontSize: '2rem',
    fontWeight: '700',
    color: '#1e293b',
    margin: 0,
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  titleIcon: {
    fontSize: '2rem',
    color: theme.colors.primary,
  },
  subtitle: {
    color: '#64748b',
    fontSize: '1rem',
    lineHeight: '1.5',
    margin: 0,
  },
  headerActions: {
    display: 'flex',
    gap: '0.75rem',
  },
  headerActionButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#fff',
    border: `1px solid #e2e8f0`,
    borderRadius: theme.borderRadius.md,
    padding: '0.625rem 1rem',
    color: '#475569',
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  headerActionIcon: {
    fontSize: '1rem',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
    gap: '1.5rem',
    marginBottom: '2.5rem',
  },
  statCard: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    border: `1px solid #e2e8f0`,
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.05)',
    cursor: 'pointer',
  },
  pendingCard: {
    borderColor: '#fed7aa',
  },
  validatedCard: {
    borderColor: '#bbf7d0',
  },
  rejectedCard: {
    borderColor: '#fecaca',
  },
  statIconContainer: {
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
    borderRadius: theme.borderRadius.md,
    padding: '0.75rem',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  statIcon: {
    fontSize: '1.5rem',
    color: theme.colors.primary,
  },
  statContent: {
    flex: 1,
  },
  statNumber: {
    fontSize: '1.75rem',
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: '0.25rem',
  },
  statLabel: {
    fontSize: '0.875rem',
    color: '#64748b',
    fontWeight: '500',
  },
  statArrow: {
    fontSize: '1.25rem',
    color: '#cbd5e1',
  },
  controlBar: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: `1px solid #e2e8f0`,
    marginBottom: '1.5rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  filterButtons: {
    display: 'flex',
    gap: '0.75rem',
    flexWrap: 'wrap',
  },
  filterButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#f8fafc',
    border: `1px solid #e2e8f0`,
    padding: '0.625rem 1.25rem',
    borderRadius: theme.borderRadius.md,
    cursor: 'pointer',
    fontWeight: '500',
    fontSize: '0.875rem',
    color: '#475569',
    transition: 'all 0.2s',
  },
  filterButtonActive: {
    backgroundColor: theme.colors.primary,
    color: '#fff',
    borderColor: theme.colors.primary,
  },
  filterButtonIcon: {
    fontSize: '1rem',
  },
  filterCount: {
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    padding: '0.125rem 0.5rem',
    borderRadius: '9999px',
    fontSize: '0.75rem',
    fontWeight: '600',
    marginLeft: '0.25rem',
  },
  searchContainer: {
    position: 'relative',
    flex: 1,
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
    border: `1px solid #e2e8f0`,
    borderRadius: theme.borderRadius.md,
    fontSize: '0.95rem',
    outline: 'none',
    transition: 'all 0.2s',
  },
  clearSearchButton: {
    position: 'absolute',
    right: '1rem',
    top: '50%',
    transform: 'translateY(-50%)',
    backgroundColor: 'transparent',
    border: 'none',
    color: '#94a3b8',
    fontSize: '1.5rem',
    cursor: 'pointer',
    padding: 0,
    lineHeight: 1,
  },
  bulkActionsBar: {
    backgroundColor: '#dbeafe',
    padding: '1rem 1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: `1px solid #3b82f6`,
    marginBottom: '1.5rem',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  bulkActionsInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    color: '#1e40af',
    fontWeight: '500',
  },
  bulkActionsIcon: {
    fontSize: '1.25rem',
  },
  bulkActionsButtons: {
    display: 'flex',
    gap: '0.75rem',
    alignItems: 'center',
  },
  bulkSelect: {
    padding: '0.5rem 1rem',
    border: `1px solid #93c5fd`,
    borderRadius: theme.borderRadius.md,
    backgroundColor: '#fff',
    color: '#475569',
    fontSize: '0.875rem',
    cursor: 'pointer',
  },
  bulkActionButton: {
    backgroundColor: '#3b82f6',
    color: '#fff',
    border: 'none',
    padding: '0.5rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  bulkCancelButton: {
    backgroundColor: 'transparent',
    color: '#3b82f6',
    border: `1px solid #93c5fd`,
    padding: '0.5rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  error: {
    backgroundColor: '#fee2e2',
    border: `1px solid #ef4444`,
    borderRadius: theme.borderRadius.lg,
    padding: '1.5rem',
    marginBottom: '1.5rem',
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
    flexWrap: 'wrap',
  },
  errorIcon: {
    fontSize: '1.5rem',
    color: '#dc2626',
    flexShrink: 0,
  },
  errorContent: {
    flex: 1,
  },
  errorTitle: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#7f1d1d',
    marginBottom: '0.25rem',
  },
  errorText: {
    color: '#991b1b',
    fontSize: '0.875rem',
  },
  errorButton: {
    backgroundColor: '#ef4444',
    color: '#fff',
    border: 'none',
    padding: '0.5rem 1.5rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.875rem',
    fontWeight: '500',
    cursor: 'pointer',
    whiteSpace: 'nowrap',
  },
  emptyState: {
    backgroundColor: '#fff',
    padding: '4rem 2rem',
    borderRadius: theme.borderRadius.lg,
    textAlign: 'center',
    border: `2px dashed #e2e8f0`,
  },
  emptyIcon: {
    fontSize: '4rem',
    marginBottom: '1.5rem',
    color: '#cbd5e1',
  },
  emptyIconSvg: {
    fontSize: '4rem',
    color: '#cbd5e1',
  },
  emptyTitle: {
    fontSize: '1.5rem',
    fontWeight: '600',
    color: '#334155',
    marginBottom: '0.75rem',
  },
  emptyText: {
    color: '#64748b',
    fontSize: '1rem',
    marginBottom: '1.5rem',
  },
  emptyActions: {
    display: 'flex',
    gap: '0.75rem',
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  emptyActionButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#f8fafc',
    border: `1px solid #e2e8f0`,
    padding: '0.625rem 1.25rem',
    borderRadius: theme.borderRadius.md,
    cursor: 'pointer',
    fontWeight: '500',
    fontSize: '0.875rem',
    color: '#475569',
    transition: 'all 0.2s',
  },
  emptyActionIcon: {
    fontSize: '1rem',
  },
  tableWrapper: {
    backgroundColor: '#fff',
    borderRadius: theme.borderRadius.lg,
    border: `1px solid #e2e8f0`,
    overflow: 'hidden',
    marginBottom: '2rem',
  },
  tableHeaderInfo: {
    padding: '1rem 1.5rem',
    borderBottom: `1px solid #e2e8f0`,
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
  },
  tableStats: {
    display: 'flex',
    gap: '1rem',
    alignItems: 'center',
  },
  tableCount: {
    fontWeight: '600',
    color: '#475569',
    fontSize: '0.875rem',
  },
  tableFilter: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.25rem',
    backgroundColor: '#f1f5f9',
    padding: '0.25rem 0.75rem',
    borderRadius: '9999px',
    fontSize: '0.75rem',
    color: '#64748b',
  },
  tableFilterIcon: {
    fontSize: '0.75rem',
  },
  tableControls: {
    display: 'flex',
    gap: '0.75rem',
  },
  selectAllButton: {
    backgroundColor: 'transparent',
    border: `1px solid #cbd5e1`,
    padding: '0.25rem 0.75rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.75rem',
    color: '#64748b',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  tableContainer: {
    overflowX: 'auto',
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
    minWidth: '1000px',
  },
  tableHeader: {
    backgroundColor: '#f8fafc',
  },
  th: {
    padding: '1rem 1.5rem',
    textAlign: 'left',
    fontWeight: '600',
    color: '#475569',
    fontSize: '0.75rem',
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
    borderBottom: `1px solid #e2e8f0`,
  },
  thContent: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  thIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  sortIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
    marginLeft: 'auto',
  },
  checkbox: {
    width: '16px',
    height: '16px',
    cursor: 'pointer',
  },
  tableRow: {
    borderBottom: `1px solid #e2e8f0`,
    transition: 'background-color 0.2s',
  },
  td: {
    padding: '1rem 1.5rem',
    color: '#475569',
    fontSize: '0.875rem',
    verticalAlign: 'middle',
  },
  entrepriseCell: {
    display: 'flex',
    alignItems: 'center',
    gap: '1rem',
  },
  entrepriseAvatar: {
    width: '48px',
    height: '48px',
    borderRadius: theme.borderRadius.md,
    backgroundColor: '#f1f5f9',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    flexShrink: 0,
  },
  entrepriseLogo: {
    width: '100%',
    height: '100%',
    borderRadius: theme.borderRadius.md,
    objectFit: 'cover',
  },
  defaultLogoIcon: {
    fontSize: '1.5rem',
    color: '#94a3b8',
  },
  entrepriseInfo: {
    flex: 1,
  },
  entrepriseName: {
    fontWeight: '600',
    color: '#1e293b',
    fontSize: '0.95rem',
    marginBottom: '0.25rem',
  },
  entrepriseDetails: {
    display: 'flex',
    gap: '1rem',
    flexWrap: 'wrap',
  },
  entrepriseDetail: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.25rem',
    fontSize: '0.75rem',
    color: '#64748b',
  },
  detailIcon: {
    fontSize: '0.75rem',
  },
  prestataireCell: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  prestataireAvatar: {
    width: '32px',
    height: '32px',
    borderRadius: '50%',
    backgroundColor: theme.colors.primary,
    color: '#fff',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '0.75rem',
    fontWeight: '600',
    flexShrink: 0,
  },
  prestataireName: {
    fontWeight: '500',
    color: '#1e293b',
  },
  notAssigned: {
    color: '#94a3b8',
    fontStyle: 'italic',
  },
  ifuCell: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  ifuIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  code: {
    backgroundColor: '#f1f5f9',
    padding: '0.25rem 0.5rem',
    borderRadius: theme.borderRadius.sm,
    fontSize: '0.75rem',
    fontFamily: 'monospace',
    color: '#475569',
  },
  dateCell: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  dateIcon: {
    fontSize: '1rem',
    color: '#94a3b8',
  },
  badge: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.25rem',
    padding: '0.375rem 0.75rem',
    borderRadius: '9999px',
    fontSize: '0.75rem',
    fontWeight: '600',
    whiteSpace: 'nowrap',
    width: 'fit-content',
  },
  actionButtons: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  viewButton: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    backgroundColor: '#3b82f6',
    color: '#fff',
    border: 'none',
    padding: '0.375rem 0.75rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '0.75rem',
    fontWeight: '500',
    cursor: 'pointer',
    transition: 'all 0.2s',
  },
  viewButtonIcon: {
    fontSize: '0.875rem',
  },
  moreButton: {
    backgroundColor: 'transparent',
    border: `1px solid #e2e8f0`,
    padding: '0.375rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    color: '#94a3b8',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: 'all 0.2s',
  },
  pagination: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '0.75rem',
    padding: '1.5rem',
    borderTop: `1px solid #e2e8f0`,
  },
  paginationButton: {
    backgroundColor: '#f8fafc',
    border: `1px solid #e2e8f0`,
    padding: '0.5rem',
    borderRadius: theme.borderRadius.md,
    fontSize: '1rem',
    color: '#94a3b8',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: 'all 0.2s',
  },
  paginationInfo: {
    fontSize: '0.875rem',
    color: '#64748b',
  },
  infoGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
    gap: '1.5rem',
    marginTop: '2rem',
  },
  infoCard: {
    backgroundColor: '#fff',
    padding: '1.5rem',
    borderRadius: theme.borderRadius.lg,
    border: `1px solid #e2e8f0`,
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.05)',
  },
  infoCardHeader: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    marginBottom: '1rem',
  },
  infoCardIcon: {
    fontSize: '1.5rem',
    color: theme.colors.primary,
  },
  infoCardTitle: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#1e293b',
  },
  infoList: {
    listStyle: 'none',
    padding: 0,
    margin: 0,
  },
  infoListItem: {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '0.75rem',
    padding: '0.5rem 0',
    color: '#475569',
    fontSize: '0.875rem',
    lineHeight: '1.5',
  },
  listIcon: {
    fontSize: '0.875rem',
    color: theme.colors.success,
    marginTop: '0.125rem',
    flexShrink: 0,
  },
  statsMini: {
    display: 'flex',
    gap: '1.5rem',
    justifyContent: 'space-around',
  },
  statMini: {
    textAlign: 'center',
  },
  statMiniNumber: {
    fontSize: '1.5rem',
    fontWeight: '700',
    color: theme.colors.primary,
    marginBottom: '0.25rem',
  },
  statMiniLabel: {
    fontSize: '0.75rem',
    color: '#64748b',
    fontWeight: '500',
  },
};