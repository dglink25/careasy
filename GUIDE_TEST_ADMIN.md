# Guide de Test - Pages Admin

## 🎯 Objectif
Vérifier que les 3 pages admin (Signalements, Prestataires, Abonnements) affichent correctement les données réelles depuis la base de données.

## ✅ Prérequis
- Un compte utilisateur avec `role = 'admin'` dans la table `users`
- Données de test dans la base de données :
  - Des entreprises validées avec prestataires
  - Des abonnements créés (trial ou payants)
  - Des avis signalés (optionnel pour la page signalements)

## 🔧 Créer un compte admin (si nécessaire)

```bash
cd careasy
php artisan tinker
```

```php
// Dans tinker
$user = \App\Models\User::find(1); // ou l'ID de ton utilisateur
$user->role = 'admin';
$user->save();
exit
```

## 📊 1. Tester la page Abonnements

### Backend
```bash
# Test de l'endpoint
curl -X GET "http://localhost:8000/api/admin/abonnements" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Réponse attendue :**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "reference": "ABO-2024-XXXXX",
      "type": "trial",
      "prestataire_name": "Jean Dupont",
      "prestataire_email": "jean@example.com",
      "entreprise_name": "Auto Service Pro",
      "plan": {
        "id": 1,
        "name": "Plan Essai",
        "code": "TRIAL"
      },
      "date_debut": "10/06/2024",
      "date_fin": "10/07/2024",
      "statut": "actif",
      "jours_restants": 30,
      "montant": "Gratuit"
    }
  ],
  "stats": {
    "total": 5,
    "actifs": 3,
    "trial": 2,
    "paid": 1,
    "expire": 1
  }
}
```

### Frontend
1. Ouvrir `http://localhost:3000/admin/abonnements` (ou le port de ton frontend)
2. Vérifier que :
   - ✅ Les statistiques s'affichent en haut (Total, Actifs, Essais, Payants, Expirés)
   - ✅ Le tableau affiche les abonnements avec :
     - Référence
     - Nom du prestataire et email
     - Nom de l'entreprise
     - Badge du plan (coloré)
     - Statut (avec badge coloré)
     - Dates de début et fin
     - Jours restants
     - Montant
   - ✅ La recherche fonctionne (taper un nom de prestataire)
   - ✅ Les filtres fonctionnent (statut, type)
   - ✅ Le tri par colonne fonctionne
   - ✅ La pagination s'affiche si > 20 résultats

## 👥 2. Tester la page Prestataires

### Backend
```bash
curl -X GET "http://localhost:8000/api/admin/entreprises" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Frontend
1. Ouvrir `http://localhost:3000/admin/prestataires`
2. Vérifier que :
   - ✅ Les statistiques s'affichent
   - ✅ Le tableau affiche les prestataires avec :
     - Avatar et nom
     - Email et téléphone
     - Badge du plan actuel
     - Nombre d'entreprises
     - Date d'inscription
     - Statut
   - ✅ Cliquer sur "Détails" ouvre une modal avec :
     - Informations personnelles
     - Abonnement actif
     - Historique des abonnements
     - Liste des entreprises

## 🚨 3. Tester la page Signalements

### Backend
```bash
curl -X GET "http://localhost:8000/api/admin/signalements" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Note :** Si aucun avis n'a été signalé, créer un signalement de test :

```php
// Dans tinker
$review = \App\Models\Review::first();
$review->reported = true;
$review->report_reason = "Contenu inapproprié";
$review->reported_at = now();
$review->save();
```

### Frontend
1. Ouvrir `http://localhost:3000/admin/signalements`
2. Vérifier que :
   - ✅ Les statistiques s'affichent
   - ✅ Le tableau affiche les signalements avec :
     - Client
     - Prestataire
     - Service
     - Note (avec étoiles)
     - Motif
     - Date du signalement
     - Statut (en attente / traité)
   - ✅ Cliquer sur "Voir" ouvre une modal avec tous les détails
   - ✅ Le bouton "Marquer comme traité" fonctionne

## 🔍 Vérifications communes

Pour chaque page, vérifier que :
1. ✅ Le bouton "Rafraîchir" recharge les données
2. ✅ Les filtres s'appliquent correctement
3. ✅ La recherche filtre les résultats
4. ✅ Le message "Aucun résultat" s'affiche si les filtres ne retournent rien
5. ✅ Les badges colorés correspondent aux statuts
6. ✅ Le design est cohérent et responsive

## 🐛 Debugging

### Si les données ne s'affichent pas :

1. **Vérifier la console du navigateur**
   - Ouvrir DevTools (F12)
   - Onglet Console : chercher les erreurs
   - Onglet Network : vérifier que l'API répond

2. **Vérifier les logs Laravel**
```bash
tail -f storage/logs/laravel.log
```

3. **Tester l'endpoint directement**
```bash
# Remplacer YOUR_TOKEN par ton token d'authentification
curl -v http://localhost:8000/api/admin/abonnements \
  -H "Authorization: Bearer YOUR_TOKEN"
```

4. **Vérifier que l'utilisateur est admin**
```php
// Dans tinker
$user = auth()->user();
dd($user->role); // Doit être "admin"
```

## 📝 Données de test recommandées

Pour une démo complète, avoir au minimum :
- 5-10 entreprises avec différents statuts (validated, pending, rejected)
- 3-5 prestataires avec des abonnements variés (trial, VP1, VP2, VP3)
- 2-3 signalements pour tester la page signalements

## ✨ Fonctionnalités testées

### Abonnements
- [x] Affichage des données réelles depuis `/admin/abonnements`
- [x] Filtrage par statut (actif, expiré, annulé)
- [x] Filtrage par type (trial, payant)
- [x] Recherche par prestataire, entreprise, référence
- [x] Tri par colonnes
- [x] Pagination
- [x] Statistiques calculées côté backend

### Prestataires
- [x] Affichage des prestataires avec leurs entreprises
- [x] Agrégation des abonnements par prestataire
- [x] Modal de détails complet
- [x] Filtrage par plan
- [x] Filtrage par statut d'entreprise

### Signalements
- [x] Affichage des avis signalés
- [x] Modal de détails
- [x] Action "Marquer comme traité"
- [x] Action "Ignorer"
- [x] Filtres par statut et note
