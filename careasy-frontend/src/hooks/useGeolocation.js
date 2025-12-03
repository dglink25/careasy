// careasy-frontend/src/hooks/useGeolocation.js

import { useState, useEffect } from 'react';

export const useGeolocation = () => {
  const [location, setLocation] = useState({
    latitude: null,
    longitude: null,
    loading: true,
    error: null,
  });

  useEffect(() => {
    if (!navigator.geolocation) {
      setLocation({
        latitude: null,
        longitude: null,
        loading: false,
        error: 'La géolocalisation n\'est pas supportée par votre navigateur',
      });
      return;
    }

    const successHandler = (position) => {
      setLocation({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        loading: false,
        error: null,
      });
    };

    const errorHandler = (error) => {
      let errorMessage = 'Erreur de géolocalisation';
      
      switch(error.code) {
        case error.PERMISSION_DENIED:
          errorMessage = 'Permission de géolocalisation refusée';
          break;
        case error.POSITION_UNAVAILABLE:
          errorMessage = 'Position indisponible';
          break;
        case error.TIMEOUT:
          errorMessage = 'Timeout de géolocalisation';
          break;
      }

      setLocation({
        latitude: null,
        longitude: null,
        loading: false,
        error: errorMessage,
      });
    };

    // Options de géolocalisation
    const options = {
      enableHighAccuracy: true,  // Haute précision
      timeout: 10000,            // 10 secondes max
      maximumAge: 0              // Pas de cache
    };

    navigator.geolocation.getCurrentPosition(
      successHandler,
      errorHandler,
      options
    );
  }, []);

  return location;
};