# 🚀 Guide d'Intégration API - Système de Réservation Parapente

**Version** : 1.0.0  
**Date** : 2025-11-05  
**Public cible** : Développeurs Frontend

---

## 📋 Table des Matières

1. [Introduction](#introduction)
2. [Configuration Initiale](#configuration-initiale)
3. [Authentification](#authentification)
4. [Gestion des Réservations](#gestion-des-réservations)
5. [Intégration Stripe](#intégration-stripe)
6. [Gestion des Erreurs](#gestion-des-erreurs)
7. [Exemples de Code](#exemples-de-code)
8. [Bonnes Pratiques](#bonnes-pratiques)
9. [Dépannage](#dépannage)

---

## Introduction

Ce guide vous aidera à intégrer l'API de réservation parapente dans votre application frontend. L'API est RESTful et utilise Laravel Sanctum pour l'authentification.

### Prérequis

- Connaissance de base de JavaScript/TypeScript
- Compréhension des requêtes HTTP (fetch, axios, etc.)
- Compte Stripe (pour les paiements)
- Accès à l'environnement de développement

### Ressources

- **Documentation API complète** : `docs/API.md`
- **Documentation OpenAPI/Swagger** : `docs/openapi.yaml`
- **Base URL Development** : `http://localhost:8000/api/v1`
- **Base URL Production** : `https://api.parapente.example.com/api/v1`

---

## Configuration Initiale

### 1. Configuration de l'URL de Base

Créez un fichier de configuration pour votre environnement :

```javascript
// config/api.js
const API_CONFIG = {
  development: {
    baseURL: 'http://localhost:8000/api/v1',
    stripePublishableKey: 'pk_test_...'
  },
  production: {
    baseURL: 'https://api.parapente.example.com/api/v1',
    stripePublishableKey: 'pk_live_...'
  }
};

const env = process.env.NODE_ENV || 'development';
export const API_BASE_URL = API_CONFIG[env].baseURL;
export const STRIPE_PUBLISHABLE_KEY = API_CONFIG[env].stripePublishableKey;
```

### 2. Service API de Base

Créez un service pour gérer toutes les requêtes API :

```javascript
// services/api.js
import { API_BASE_URL } from '../config/api';

class ApiService {
  constructor() {
    this.baseURL = API_BASE_URL;
    this.token = localStorage.getItem('auth_token');
  }

  setToken(token) {
    this.token = token;
    if (token) {
      localStorage.setItem('auth_token', token);
    } else {
      localStorage.removeItem('auth_token');
    }
  }

  getToken() {
    return this.token || localStorage.getItem('auth_token');
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    };

    // Ajouter le token d'authentification si disponible
    const token = this.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    try {
      const response = await fetch(url, {
        ...options,
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined
      });

      const data = await response.json();

      // Gérer les erreurs HTTP
      if (!response.ok) {
        throw new ApiError(
          data.message || 'Une erreur est survenue',
          response.status,
          data.errors
        );
      }

      return data;
    } catch (error) {
      if (error instanceof ApiError) {
        throw error;
      }
      throw new ApiError('Erreur de connexion', 0, null);
    }
  }

  get(endpoint, params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const url = queryString ? `${endpoint}?${queryString}` : endpoint;
    return this.request(url, { method: 'GET' });
  }

  post(endpoint, body) {
    return this.request(endpoint, {
      method: 'POST',
      body
    });
  }

  put(endpoint, body) {
    return this.request(endpoint, {
      method: 'PUT',
      body
    });
  }

  patch(endpoint, body) {
    return this.request(endpoint, {
      method: 'PATCH',
      body
    });
  }

  delete(endpoint) {
    return this.request(endpoint, {
      method: 'DELETE'
    });
  }
}

// Classe d'erreur personnalisée
class ApiError extends Error {
  constructor(message, status, errors = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

export const apiService = new ApiService();
export { ApiError };
```

---

## Authentification

### 1. Connexion

```javascript
// services/auth.js
import { apiService } from './api';

export const authService = {
  async login(email, password) {
    try {
      const response = await apiService.post('/auth/login', {
        email,
        password
      });

      if (response.success && response.data.token) {
        // Stocker le token
        apiService.setToken(response.data.token);
        
        // Stocker les informations utilisateur
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        return response.data.user;
      }
      
      throw new Error('Erreur de connexion');
    } catch (error) {
      console.error('Erreur de connexion:', error);
      throw error;
    }
  },

  async register(name, email, password, passwordConfirmation) {
    try {
      const response = await apiService.post('/auth/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation
      });

      if (response.success && response.data.token) {
        apiService.setToken(response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        return response.data.user;
      }
      
      throw new Error('Erreur d\'inscription');
    } catch (error) {
      console.error('Erreur d\'inscription:', error);
      throw error;
    }
  },

  async logout() {
    try {
      await apiService.post('/auth/logout');
    } catch (error) {
      console.error('Erreur de déconnexion:', error);
    } finally {
      // Nettoyer même en cas d'erreur
      apiService.setToken(null);
      localStorage.removeItem('user');
    }
  },

  async getProfile() {
    try {
      const response = await apiService.get('/auth/me');
      return response.data;
    } catch (error) {
      console.error('Erreur de récupération du profil:', error);
      throw error;
    }
  },

  isAuthenticated() {
    return !!apiService.getToken();
  },

  getUser() {
    const userStr = localStorage.getItem('user');
    return userStr ? JSON.parse(userStr) : null;
  }
};
```

### 2. Utilisation dans un Composant React

```jsx
// components/LoginForm.jsx
import { useState } from 'react';
import { authService } from '../services/auth';

function LoginForm() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      await authService.login(email, password);
      // Rediriger vers le dashboard
      window.location.href = '/dashboard';
    } catch (err) {
      setError(err.message || 'Erreur de connexion');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {error && <div className="error">{error}</div>}
      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Email"
        required
      />
      <input
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        placeholder="Mot de passe"
        required
      />
      <button type="submit" disabled={loading}>
        {loading ? 'Connexion...' : 'Se connecter'}
      </button>
    </form>
  );
}
```

---

## Gestion des Réservations

### 1. Créer une Réservation

```javascript
// services/reservations.js
import { apiService } from './api';

export const reservationService = {
  async create(data) {
    try {
      // Valider les contraintes côté client
      if (data.customer_weight && (data.customer_weight < 40 || data.customer_weight > 120)) {
        throw new Error('Le poids doit être entre 40 et 120 kg');
      }

      if (data.customer_height && data.customer_height < 140) {
        throw new Error('La taille minimum est de 1.40m (140 cm)');
      }

      const response = await apiService.post('/reservations', data);
      
      if (response.success) {
        return response.data;
      }
      
      throw new Error('Erreur de création de réservation');
    } catch (error) {
      console.error('Erreur de création de réservation:', error);
      throw error;
    }
  },

  async getByUuid(uuid) {
    try {
      const response = await apiService.get(`/reservations/${uuid}`);
      return response.data;
    } catch (error) {
      console.error('Erreur de récupération de réservation:', error);
      throw error;
    }
  },

  async addOptions(uuid, options, paymentMethodId) {
    try {
      const response = await apiService.post(`/reservations/${uuid}/add-options`, {
        options,
        payment_method_id: paymentMethodId
      });
      return response.data;
    } catch (error) {
      console.error('Erreur d\'ajout d\'options:', error);
      throw error;
    }
  },

  async applyCoupon(uuid, couponCode) {
    try {
      const response = await apiService.post(`/reservations/${uuid}/apply-coupon`, {
        coupon_code: couponCode
      });
      return response.data;
    } catch (error) {
      console.error('Erreur d\'application du coupon:', error);
      throw error;
    }
  },

  async getMyReservations(filters = {}) {
    try {
      const response = await apiService.get('/my/reservations', filters);
      return response.data;
    } catch (error) {
      console.error('Erreur de récupération des réservations:', error);
      throw error;
    }
  },

  async getHistory(reservationId) {
    try {
      const response = await apiService.get(`/my/reservations/${reservationId}/history`);
      return response.data;
    } catch (error) {
      console.error('Erreur de récupération de l\'historique:', error);
      throw error;
    }
  }
};
```

### 2. Formulaire de Réservation

```jsx
// components/ReservationForm.jsx
import { useState } from 'react';
import { reservationService } from '../services/reservations';
import { stripeService } from '../services/stripe';

function ReservationForm() {
  const [formData, setFormData] = useState({
    customer_email: '',
    customer_first_name: '',
    customer_last_name: '',
    customer_weight: '',
    customer_height: '',
    flight_type: 'tandem',
    participants_count: 1,
    payment_type: 'deposit',
    payment_method_id: null
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      // 1. Créer le PaymentMethod avec Stripe
      const paymentMethod = await stripeService.createPaymentMethod(cardElement);
      
      // 2. Créer la réservation
      const reservation = await reservationService.create({
        ...formData,
        payment_method_id: paymentMethod.id,
        customer_weight: parseInt(formData.customer_weight),
        customer_height: parseInt(formData.customer_height)
      });

      // 3. Confirmer le paiement avec Stripe
      if (reservation.payment?.client_secret) {
        await stripeService.confirmPayment(reservation.payment.client_secret);
      }

      // Rediriger vers la page de confirmation
      window.location.href = `/reservations/${reservation.reservation.uuid}`;
    } catch (err) {
      setError(err.message || 'Erreur de création de réservation');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {error && <div className="error">{error}</div>}
      
      <input
        type="email"
        value={formData.customer_email}
        onChange={(e) => setFormData({ ...formData, customer_email: e.target.value })}
        placeholder="Email"
        required
      />
      
      <input
        type="number"
        value={formData.customer_weight}
        onChange={(e) => setFormData({ ...formData, customer_weight: e.target.value })}
        placeholder="Poids (kg)"
        min="40"
        max="120"
        required
      />
      
      <input
        type="number"
        value={formData.customer_height}
        onChange={(e) => setFormData({ ...formData, customer_height: e.target.value })}
        placeholder="Taille (cm)"
        min="140"
        max="250"
        required
      />
      
      <select
        value={formData.flight_type}
        onChange={(e) => setFormData({ ...formData, flight_type: e.target.value })}
      >
        <option value="tandem">Tandem</option>
        <option value="initiation">Initiation</option>
        <option value="perfectionnement">Perfectionnement</option>
      </select>
      
      <button type="submit" disabled={loading}>
        {loading ? 'Création...' : 'Réserver'}
      </button>
    </form>
  );
}
```

---

## Intégration Stripe

### 1. Configuration Stripe

```javascript
// services/stripe.js
import { loadStripe } from '@stripe/stripe-js';
import { STRIPE_PUBLISHABLE_KEY } from '../config/api';

let stripePromise = null;

export const getStripe = () => {
  if (!stripePromise) {
    stripePromise = loadStripe(STRIPE_PUBLISHABLE_KEY);
  }
  return stripePromise;
};

export const stripeService = {
  async createPaymentMethod(cardElement) {
    const stripe = await getStripe();
    const { paymentMethod, error } = await stripe.createPaymentMethod({
      type: 'card',
      card: cardElement
    });

    if (error) {
      throw new Error(error.message);
    }

    return paymentMethod;
  },

  async confirmPayment(clientSecret) {
    const stripe = await getStripe();
    const { error } = await stripe.confirmCardPayment(clientSecret);

    if (error) {
      throw new Error(error.message);
    }
  },

  async handlePaymentMethod(event, paymentMethodId) {
    // Utiliser ce PaymentMethod pour les paiements futurs
    return paymentMethodId;
  }
};
```

### 2. Composant de Paiement Stripe

```jsx
// components/StripePayment.jsx
import { useState, useEffect } from 'react';
import { CardElement, useStripe, useElements } from '@stripe/react-stripe-js';

function StripePayment({ amount, onSuccess, onError }) {
  const stripe = useStripe();
  const elements = useElements();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!stripe || !elements) {
      return;
    }

    setError(null);
    setLoading(true);

    try {
      const cardElement = elements.getElement(CardElement);
      
      // Créer le PaymentMethod
      const { paymentMethod, error: pmError } = await stripe.createPaymentMethod({
        type: 'card',
        card: cardElement
      });

      if (pmError) {
        throw new Error(pmError.message);
      }

      // Créer le PaymentIntent via l'API
      const response = await fetch('/api/v1/payments/intent', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          amount: amount * 100, // Convertir en centimes
          payment_method_id: paymentMethod.id,
          currency: 'EUR'
        })
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Erreur de paiement');
      }

      // Confirmer le paiement
      const { error: confirmError } = await stripe.confirmCardPayment(
        data.data.client_secret
      );

      if (confirmError) {
        throw new Error(confirmError.message);
      }

      onSuccess(paymentMethod.id);
    } catch (err) {
      setError(err.message);
      onError(err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <CardElement
        options={{
          style: {
            base: {
              fontSize: '16px',
              color: '#424770',
              '::placeholder': {
                color: '#aab7c4',
              },
            },
          },
        }}
      />
      {error && <div className="error">{error}</div>}
      <button type="submit" disabled={loading || !stripe}>
        {loading ? 'Paiement...' : `Payer ${amount}€`}
      </button>
    </form>
  );
}
```

---

## Gestion des Erreurs

### 1. Intercepteur d'Erreurs Global

```javascript
// utils/errorHandler.js
import { ApiError } from '../services/api';

export const handleApiError = (error) => {
  if (error instanceof ApiError) {
    switch (error.status) {
      case 401:
        // Token expiré ou invalide
        localStorage.removeItem('auth_token');
        window.location.href = '/login';
        return 'Session expirée. Veuillez vous reconnecter.';
      
      case 403:
        return 'Accès non autorisé.';
      
      case 404:
        return 'Ressource non trouvée.';
      
      case 422:
        // Erreurs de validation
        if (error.errors) {
          const messages = Object.values(error.errors).flat();
          return messages.join(', ');
        }
        return error.message;
      
      case 500:
        return 'Erreur serveur. Veuillez réessayer plus tard.';
      
      default:
        return error.message || 'Une erreur est survenue.';
    }
  }
  
  return 'Erreur de connexion. Vérifiez votre connexion internet.';
};

// Utilisation
try {
  await apiService.post('/reservations', data);
} catch (error) {
  const message = handleApiError(error);
  alert(message);
}
```

---

## Exemples de Code

### 1. Liste des Réservations avec Pagination

```jsx
// components/ReservationList.jsx
import { useState, useEffect } from 'react';
import { reservationService } from '../services/reservations';

function ReservationList() {
  const [reservations, setReservations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15
  });

  useEffect(() => {
    loadReservations();
  }, [pagination.current_page]);

  const loadReservations = async () => {
    setLoading(true);
    try {
      const response = await reservationService.getMyReservations({
        page: pagination.current_page,
        per_page: pagination.per_page
      });
      
      setReservations(response.data);
      setPagination({
        current_page: response.current_page,
        last_page: response.last_page,
        per_page: response.per_page
      });
    } catch (error) {
      console.error('Erreur:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div>Chargement...</div>;
  }

  return (
    <div>
      <h2>Mes Réservations</h2>
      {reservations.map(reservation => (
        <div key={reservation.id}>
          <h3>{reservation.customer_first_name} {reservation.customer_last_name}</h3>
          <p>Statut: {reservation.status}</p>
          <p>Montant: {reservation.total_amount}€</p>
        </div>
      ))}
      
      <div className="pagination">
        <button
          onClick={() => setPagination({ ...pagination, current_page: pagination.current_page - 1 })}
          disabled={pagination.current_page === 1}
        >
          Précédent
        </button>
        <span>Page {pagination.current_page} sur {pagination.last_page}</span>
        <button
          onClick={() => setPagination({ ...pagination, current_page: pagination.current_page + 1 })}
          disabled={pagination.current_page === pagination.last_page}
        >
          Suivant
        </button>
      </div>
    </div>
  );
}
```

### 2. Gestion des Notifications

```javascript
// services/notifications.js
import { apiService } from './api';

export const notificationService = {
  async getAll(filters = {}) {
    try {
      const response = await apiService.get('/notifications', filters);
      return response.data;
    } catch (error) {
      console.error('Erreur de récupération des notifications:', error);
      throw error;
    }
  },

  async markAsRead(notificationId) {
    try {
      await apiService.post(`/notifications/${notificationId}/read`);
    } catch (error) {
      console.error('Erreur de marquage de notification:', error);
      throw error;
    }
  },

  async markAllAsRead() {
    try {
      await apiService.post('/notifications/mark-all-read');
    } catch (error) {
      console.error('Erreur de marquage de toutes les notifications:', error);
      throw error;
    }
  },

  async getUnreadCount() {
    try {
      const response = await apiService.get('/notifications/unread-count');
      return response.data.unread_count;
    } catch (error) {
      console.error('Erreur de comptage des notifications:', error);
      throw error;
    }
  }
};
```

---

## Bonnes Pratiques

### 1. Gestion du Token

- **Stockage** : Utilisez `localStorage` pour le développement, `httpOnly cookies` pour la production
- **Expiration** : Vérifiez régulièrement la validité du token
- **Renouvellement** : Implémentez un mécanisme de refresh token si disponible

### 2. Validation Côté Client

Validez les données avant l'envoi à l'API :

```javascript
const validateReservation = (data) => {
  const errors = [];

  if (data.customer_weight < 40 || data.customer_weight > 120) {
    errors.push('Le poids doit être entre 40 et 120 kg');
  }

  if (data.customer_height < 140) {
    errors.push('La taille minimum est de 1.40m');
  }

  return errors;
};
```

### 3. Gestion de la Pagination

Toujours gérer la pagination pour les listes :

```javascript
const loadMore = async () => {
  if (hasMore && !loading) {
    setLoading(true);
    try {
      const response = await apiService.get('/endpoint', {
        page: currentPage + 1
      });
      setData([...data, ...response.data.data]);
      setCurrentPage(response.data.current_page);
      setHasMore(response.data.current_page < response.data.last_page);
    } finally {
      setLoading(false);
    }
  }
};
```

### 4. Debounce pour les Recherches

```javascript
import { debounce } from 'lodash';

const searchReservations = debounce(async (query) => {
  const response = await apiService.get('/admin/reservations', { search: query });
  setResults(response.data.data);
}, 300);
```

---

## Dépannage

### Problèmes Courants

#### 1. Erreur 401 (Non authentifié)

**Cause** : Token manquant ou expiré

**Solution** :
```javascript
// Vérifier le token avant chaque requête
if (!apiService.getToken()) {
  window.location.href = '/login';
}
```

#### 2. Erreur CORS

**Cause** : Configuration CORS côté serveur

**Solution** : Vérifier que le serveur autorise votre domaine

#### 3. Erreur de Validation

**Cause** : Données invalides

**Solution** :
```javascript
if (error.status === 422 && error.errors) {
  // Afficher les erreurs de validation
  Object.entries(error.errors).forEach(([field, messages]) => {
    console.error(`${field}: ${messages.join(', ')}`);
  });
}
```

### Support

Pour toute question ou problème :
- **Documentation API** : `docs/API.md`
- **OpenAPI/Swagger** : `docs/openapi.yaml`
- **Email support** : support@parapente.example.com

---

**Dernière mise à jour** : 2025-11-05

