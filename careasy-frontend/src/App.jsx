// careasy-frontend/src/App.jsx - VERSION COMPLÈTE FINALE
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import Layout from './components/Layout';
import ProtectedRoute from './components/ProtectedRoute';
import AdminRoute from './components/AdminRoute';
import Home from './pages/Home';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';

// Entreprises (Prestataire)
import MesEntreprises from './pages/entreprises/MesEntreprises';
import CreerEntreprise from './pages/entreprises/CreerEntreprise';
import DetailsEntreprise from './pages/entreprises/DetailsEntreprise';

// Services (Prestataire)
import MesServices from './pages/services/MesServices';
import CreerService from './pages/services/CreerService';
import DetailsService from './pages/services/DetailsService';

// Admin
import AdminDashboard from './pages/admin/AdminDashboard';
import AdminEntreprises from './pages/admin/AdminEntreprises';
import AdminEntrepriseDetails from './pages/admin/AdminEntrepriseDetails';

// Public
import PublicEntreprises from './pages/public/PublicEntreprises';
import PublicEntrepriseDetails from './pages/public/PublicEntrepriseDetails';
import PublicServices from './pages/public/PublicServices';

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <div style={{ minHeight: '100vh' }}>
          <Routes>
            {/* Routes avec Navbar (utilisant Layout) */}
            <Route element={<Layout />}>
              <Route path="/" element={<Home />} />
              
              {/* Dashboard */}
              <Route
                path="/dashboard"
                element={
                  <ProtectedRoute>
                    <Dashboard />
                  </ProtectedRoute>
                }
              />

              {/* Entreprises (Prestataire) */}
              <Route
                path="/mes-entreprises"
                element={
                  <ProtectedRoute>
                    <MesEntreprises />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/entreprises/creer"
                element={
                  <ProtectedRoute>
                    <CreerEntreprise />
                  </ProtectedRoute>
                }
              />

              {/* Services (Prestataire) */}
              <Route
                path="/mes-services"
                element={
                  <ProtectedRoute>
                    <MesServices />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/services/creer"
                element={
                  <ProtectedRoute>
                    <CreerService />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/services/:id"
                element={
                  <ProtectedRoute>
                    <DetailsService />
                  </ProtectedRoute>
                }
              />

              {/* Admin */}
              <Route
                path="/admin/dashboard"
                element={
                  <AdminRoute>
                    <AdminDashboard />
                  </AdminRoute>
                }
              />
              <Route
                path="/admin/entreprises"
                element={
                  <AdminRoute>
                    <AdminEntreprises />
                  </AdminRoute>
                }
              />
              <Route
                path="/admin/entreprises/:id"
                element={
                  <AdminRoute>
                    <AdminEntrepriseDetails />
                  </AdminRoute>
                }
              />

              {/* Pages Publiques */}
              <Route path="/entreprises" element={<PublicEntreprises />} />
              <Route path="/entreprises/:id" element={<PublicEntrepriseDetails />} />
              <Route path="/services" element={<PublicServices />} />
            </Route>
            
            {/* Routes sans Navbar */}
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route path="/forgot-password" element={<ForgotPassword />} />
            <Route path="/reset-password" element={<ResetPassword />} />
          </Routes>
        </div>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;