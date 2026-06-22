function caribWeatherApp() {
  return {
    activeView: 'dashboard',
    navOpen: false,
    online: navigator.onLine,
    notice: '',
    clientId: localStorage.getItem('cw-client-id') || crypto.randomUUID(),
    authToken: localStorage.getItem('cw-auth-token') || '',
    currentUser: JSON.parse(localStorage.getItem('cw-user') || 'null'),
    authMode: 'login',
    authError: '',
    authForm: {
      name: '',
      email: '',
      password: ''
    },
    unit: localStorage.getItem('cw-unit') || 'metric',
    locationQuery: localStorage.getItem('cw-location') || 'St. George\'s, Grenada',
    selectedCityKey: localStorage.getItem('cw-selected-city') || '',
    weather: mockWeather('St. George\'s, Grenada'),
    map: null,
    mapProvider: window.CARIBWEATHER_GOOGLE_MAPS_KEY ? 'google' : 'leaflet',
    googleMap: null,
    googleInfoWindow: null,
    googleMapsApiPromise: null,
    mapMarker: null,
    radarLayer: null,
    stormLayer: null,
    stormGeometryLayer: null,
    stormGeometryEnabled: true,
    weatherPointLayer: null,
    weatherTileLayer: null,
    mapStatus: '',
    activeLayer: 'temperature',
    chartInstances: {},
    assistantQuery: '',
    notifications: [],
    savedLocations: [],
    chat: [
      {
        id: crypto.randomUUID(),
        role: 'assistant',
        text: 'Ask about rain, fishing safety, UV, marine conditions, hurricanes, or what to wear. I will use live backend data when connected.'
      }
    ],
    alerts: JSON.parse(localStorage.getItem('cw-alerts') || '[]'),
    alertForm: {
      location: 'St. George\'s, Grenada',
      type: 'High UV Warning',
      threshold: 'UV Index > 8',
      quietHours: '10:00 PM - 6:00 AM'
    },
    historyForm: {
      location: 'Grenada',
      start: '2026-06-01',
      end: '2026-06-07'
    },
    historical: mockHistorical(),
    navItems: [
      { id: 'dashboard', label: 'Dashboard' },
      { id: 'map', label: 'Map' },
      { id: 'assistant', label: 'AI Assistant' },
      { id: 'alerts', label: 'Alerts' },
      { id: 'analytics', label: 'Analytics' }
    ],
    cityOptions: [
      { key: 'all-saints-ag', name: 'All Saints, Antigua and Barbuda', lat: 17.0739, lon: -61.7978 },
      { key: 'basseterre-kn', name: 'Basseterre, Saint Kitts and Nevis', lat: 17.2955, lon: -62.7266 },
      { key: 'belmopan-bz', name: 'Belmopan, Belize', lat: 17.2514, lon: -88.7590 },
      { key: 'belize-city-bz', name: 'Belize City, Belize', lat: 17.5046, lon: -88.1962 },
      { key: 'bridgetown-bb', name: 'Bridgetown, Barbados', lat: 13.0975, lon: -59.6177 },
      { key: 'brades-ms', name: 'Brades, Montserrat', lat: 16.7919, lon: -62.2106 },
      { key: 'camaguey-cu', name: 'Camaguey, Cuba', lat: 21.3808, lon: -77.9169 },
      { key: 'cap-haitien-ht', name: 'Cap-Haitien, Haiti', lat: 19.7594, lon: -72.2090 },
      { key: 'castries-lc', name: 'Castries, Saint Lucia', lat: 14.0101, lon: -60.9875 },
      { key: 'charlotte-amalie-vi', name: 'Charlotte Amalie, U.S. Virgin Islands', lat: 18.3419, lon: -64.9307 },
      { key: 'cockburn-town-tc', name: 'Cockburn Town, Turks and Caicos Islands', lat: 21.4735, lon: -71.1396 },
      { key: 'fort-de-france-mq', name: 'Fort-de-France, Martinique', lat: 14.6161, lon: -61.0588 },
      { key: 'freeport-bs', name: 'Freeport, Bahamas', lat: 26.5333, lon: -78.7000 },
      { key: 'georgetown-gy', name: 'Georgetown, Guyana', lat: 6.8013, lon: -58.1552 },
      { key: 'george-town-ky', name: 'George Town, Cayman Islands', lat: 19.2869, lon: -81.3674 },
      { key: 'gouyave-gd', name: 'Gouyave, Grenada', lat: 12.1696, lon: -61.7297 },
      { key: 'grenville-gd', name: 'Grenville, Grenada', lat: 12.1310, lon: -61.6888 },
      { key: 'havana-cu', name: 'Havana, Cuba', lat: 23.1136, lon: -82.3666 },
      { key: 'kingstown-vc', name: 'Kingstown, Saint Vincent and the Grenadines', lat: 13.1600, lon: -61.2248 },
      { key: 'kingston-jm', name: 'Kingston, Jamaica', lat: 17.9642, lon: -76.7923 },
      { key: 'kralendijk-bq', name: 'Kralendijk, Bonaire', lat: 12.1443, lon: -68.2655 },
      { key: 'marigot-dm', name: 'Marigot, Dominica', lat: 15.5389, lon: -61.3923 },
      { key: 'marigot-mf', name: 'Marigot, Saint Martin', lat: 18.0663, lon: -63.0822 },
      { key: 'montego-bay-jm', name: 'Montego Bay, Jamaica', lat: 18.4762, lon: -77.8939 },
      { key: 'nassau-bs', name: 'Nassau, Bahamas', lat: 25.0343, lon: -77.3963 },
      { key: 'oistins-bb', name: 'Oistins, Barbados', lat: 13.0650, lon: -59.5338 },
      { key: 'oranjestad-aw', name: 'Oranjestad, Aruba', lat: 12.5092, lon: -70.0086 },
      { key: 'paramaribo-sr', name: 'Paramaribo, Suriname', lat: 5.8664, lon: -55.1668 },
      { key: 'philipsburg-sx', name: 'Philipsburg, Sint Maarten', lat: 18.0075, lon: -63.0413 },
      { key: 'pointe-a-pitre-gp', name: 'Pointe-a-Pitre, Guadeloupe', lat: 16.2416, lon: -61.5330 },
      { key: 'port-au-prince-ht', name: 'Port-au-Prince, Haiti', lat: 18.5944, lon: -72.3074 },
      { key: 'port-of-spain-tt', name: 'Port of Spain, Trinidad and Tobago', lat: 10.6549, lon: -61.5019 },
      { key: 'providenciales-tc', name: 'Providenciales, Turks and Caicos Islands', lat: 21.7767, lon: -72.2659 },
      { key: 'road-town-vg', name: 'Road Town, British Virgin Islands', lat: 18.4260, lon: -64.6208 },
      { key: 'roseau-dm', name: 'Roseau, Dominica', lat: 15.3014, lon: -61.3899 },
      { key: 'san-ignacio-bz', name: 'San Ignacio, Belize', lat: 17.1612, lon: -89.0636 },
      { key: 'san-juan-pr', name: 'San Juan, Puerto Rico', lat: 18.4655, lon: -66.1057 },
      { key: 'santo-domingo-do', name: 'Santo Domingo, Dominican Republic', lat: 18.4861, lon: -69.9312 },
      { key: 'sauteurs-gd', name: 'Sauteurs, Grenada', lat: 12.2212, lon: -61.6390 },
      { key: 'scarborough-tt', name: 'Scarborough, Trinidad and Tobago', lat: 11.1853, lon: -60.7390 },
      { key: 'speightstown-bb', name: 'Speightstown, Barbados', lat: 13.2507, lon: -59.6430 },
      { key: 'spanish-town-jm', name: 'Spanish Town, Jamaica', lat: 17.9833, lon: -76.9547 },
      { key: 'st-georges-gd', name: 'St. George\'s, Grenada', lat: 12.0561, lon: -61.7488 },
      { key: 'st-johns-ag', name: 'St. John\'s, Antigua and Barbuda', lat: 17.1219, lon: -61.8457 },
      { key: 'the-valley-ai', name: 'The Valley, Anguilla', lat: 18.2170, lon: -63.0578 },
      { key: 'vieux-fort-lc', name: 'Vieux Fort, Saint Lucia', lat: 13.7166, lon: -60.9471 },
      { key: 'willemstad-cw', name: 'Willemstad, Curacao', lat: 12.1097, lon: -68.9301 }
    ],
    mapLayers: [
      { id: 'temperature', label: 'Temperature', description: 'OpenWeather temperature tiles plus selected-location reading.' },
      { id: 'rainfall', label: 'Rainfall / Radar', description: 'Live RainViewer radar shows recent precipitation movement.' },
      { id: 'wind', label: 'Wind Speed', description: 'OpenWeather wind tiles plus selected-location wind reading.' },
      { id: 'clouds', label: 'Cloud Coverage', description: 'OpenWeather cloud tiles plus selected-location cloud coverage.' },
      { id: 'storms', label: 'Tropical Storms', description: 'NOAA/NHC active storm positions with advisory links.' }
    ],
    samplePrompts: [
      'Will it rain in Grenville tomorrow?',
      'Is it safe to go fishing this afternoon?',
      'What should I wear today?',
      'Will the sea be rough tomorrow morning?',
      'Is there a hurricane coming?',
      'What is the UV level this week in Barbados?'
    ],
    alertTypes: [
      'Heavy Rain',
      'Flooding Risk',
      'Hurricane / Tropical Storm',
      'High Winds',
      'Lightning Storm',
      'Extreme Heat',
      'High UV Warning',
      'Poor Air Quality'
    ],

    get unitLabel() {
      return this.unit === 'metric' ? 'C / km/h' : 'F / mph';
    },

    get primaryMetrics() {
      return [
        { label: 'Humidity', value: `${this.weather.current.humidity}%`, note: 'Current relative humidity' },
        { label: 'Wind', value: this.displayWind(this.weather.current.windKph), note: `${this.weather.current.windDirection} (${this.weather.current.windDegrees} deg)` },
        { label: 'Rain Chance', value: `${this.weather.current.rainChance}%`, note: 'Current period probability' },
        { label: 'Cloud Cover', value: `${this.weather.current.cloudCover}%`, note: 'Sky coverage estimate' }
      ];
    },

    get marineMetrics() {
      return [
        { label: 'UV Index', value: `${this.weather.current.uvIndex} - ${this.uvLabel(this.weather.current.uvIndex)}`, note: 'Use sun protection when high' },
        { label: 'AQI', value: `${this.weather.current.aqi} - ${this.aqiLabel(this.weather.current.aqi)}`, note: this.weather.current.aqiAdvisory },
        { label: 'Wave Height', value: `${this.weather.marine.waveHeightM} m`, note: 'Open-Meteo Marine target' },
        { label: 'Sea Temperature', value: `${this.weather.marine.seaTempC} C`, note: `Swell ${this.weather.marine.swellDirection}` },
        { label: 'Sunrise', value: this.weather.sun.sunrise, note: 'Local time' },
        { label: 'Sunset', value: this.weather.sun.sunset, note: 'Local time' }
      ];
    },

    get activeLayerDescription() {
      return this.mapLayers.find((layer) => layer.id === this.activeLayer)?.description || '';
    },

    get mapBaseDescription() {
      return this.mapProvider === 'google'
        ? 'Google Maps basemap with selectable MVP overlays.'
        : 'OpenStreetMap base with selectable MVP overlays.';
    },

    get mapProviderLabel() {
      return this.mapProvider === 'google' ? 'Google Maps' : 'Leaflet / OSM';
    },

    get mapProviderTone() {
      return this.mapProvider === 'google' ? 'bg-blue-500/20 text-blue-50 ring-blue-300/30' : 'bg-white/10 text-cyan-50 ring-white/10';
    },

    init() {
      localStorage.setItem('cw-client-id', this.clientId);
      const hashView = window.location.hash.replace('#', '');
      if (this.navItems.some((item) => item.id === hashView)) this.activeView = hashView;
      window.addEventListener('online', () => { this.online = true; });
      window.addEventListener('offline', () => { this.online = false; });
      window.addEventListener('hashchange', () => {
        const view = window.location.hash.replace('#', '');
        if (this.navItems.some((item) => item.id === view)) this.activeView = view;
      });
      this.registerServiceWorker();
      this.loadCurrentUser();
      this.refreshWeather();
      this.loadAlerts();
      this.loadNotifications();
      this.loadSavedLocations();
      this.$watch('activeView', (value) => {
        if (value === 'map') setTimeout(() => this.initMap(), 100);
        if (value === 'analytics') setTimeout(() => this.renderCharts(), 100);
      });
      if (this.activeView === 'map') setTimeout(() => this.initMap(), 100);
      if (this.activeView === 'analytics') setTimeout(() => this.renderCharts(), 100);
    },

    setView(view) {
      this.activeView = view;
      history.replaceState(null, '', `#${view}`);
    },

    apiHeaders(extra = {}) {
      const headers = {
        Accept: 'application/json',
        'X-CaribWeather-Client': this.clientId,
        ...extra
      };
      if (this.authToken) headers.Authorization = `Bearer ${this.authToken}`;
      return headers;
    },

    async loadCurrentUser() {
      if (!this.authToken) return;
      try {
        const response = await fetch('/api/auth/user', { headers: this.apiHeaders() });
        if (!response.ok) throw new Error('Session expired');
        const payload = await response.json();
        this.currentUser = payload.user;
        localStorage.setItem('cw-user', JSON.stringify(this.currentUser));
      } catch (error) {
        this.authToken = '';
        this.currentUser = null;
        localStorage.removeItem('cw-auth-token');
        localStorage.removeItem('cw-user');
      }
    },

    async submitAuth() {
      this.authError = '';
      const endpoint = this.authMode === 'login' ? '/api/auth/login' : '/api/auth/register';
      const payload = this.authMode === 'login'
        ? { email: this.authForm.email, password: this.authForm.password }
        : this.authForm;

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: this.apiHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Authentication failed');
        this.authToken = result.token;
        this.currentUser = result.user;
        localStorage.setItem('cw-auth-token', this.authToken);
        localStorage.setItem('cw-user', JSON.stringify(this.currentUser));
        this.authForm.password = '';
        this.notice = `Signed in as ${this.currentUser.name}.`;
        await this.loadAlerts();
        await this.loadNotifications();
        await this.loadSavedLocations();
      } catch (error) {
        this.authError = error.message;
      }
    },

    async logout() {
      try {
        await fetch('/api/auth/logout', { method: 'POST', headers: this.apiHeaders() });
      } catch (error) {
        // Local logout still clears the token if the network is unavailable.
      }
      this.authToken = '';
      this.currentUser = null;
      localStorage.removeItem('cw-auth-token');
      localStorage.removeItem('cw-user');
      this.notice = 'Signed out. Guest alerts are stored on this browser.';
      await this.loadAlerts();
      await this.loadNotifications();
      await this.loadSavedLocations();
    },

    async registerServiceWorker() {
      if (!('serviceWorker' in navigator)) return;
      try {
        await navigator.serviceWorker.register('/service-worker.js');
      } catch (error) {
        this.notice = 'PWA service worker could not be registered in this environment.';
      }
    },

    async refreshWeather() {
      const city = this.cityOptions.find((option) => option.key === this.selectedCityKey);
      if (city) {
        this.locationQuery = city.name;
        await this.fetchWeather(`${city.lat.toFixed(4)}, ${city.lon.toFixed(4)}`, false, city.name);
        setInterval(() => this.fetchWeather(`${city.lat.toFixed(4)}, ${city.lon.toFixed(4)}`, true, city.name), 10 * 60 * 1000);
        return;
      }

      await this.fetchWeather(this.locationQuery);
      setInterval(() => this.fetchWeather(this.locationQuery, true), 10 * 60 * 1000);
    },

    async fetchWeather(location, quiet = false, label = null) {
      try {
        const response = await fetch(`/api/weather/current?location=${encodeURIComponent(location)}`, {
          headers: { Accept: 'application/json' }
        });
        if (!response.ok) throw new Error('Weather backend unavailable');
        const data = await response.json();
        if (label) data.location = label;
        this.weather = data;
        this.notice = '';
      } catch (error) {
        const cached = localStorage.getItem(`cw-weather-${location}`);
        this.weather = cached ? JSON.parse(cached) : mockWeather(location);
        if (label) this.weather.location = label;
        this.weather.cache = true;
        if (!quiet) this.notice = 'Using MVP fallback data until the Laravel API proxy is connected.';
      }
      localStorage.setItem('cw-location', location);
      localStorage.setItem(`cw-weather-${location}`, JSON.stringify(this.weather));
      this.updateMapMarker();
    },

    searchLocation() {
      const location = this.locationQuery.trim();
      if (!location) return;
      this.selectedCityKey = '';
      localStorage.removeItem('cw-selected-city');
      this.fetchWeather(location);
    },

    chooseCity() {
      const city = this.cityOptions.find((option) => option.key === this.selectedCityKey);
      if (!city) return;
      const coordinateQuery = `${city.lat.toFixed(4)}, ${city.lon.toFixed(4)}`;
      this.locationQuery = city.name;
      localStorage.setItem('cw-selected-city', city.key);
      this.fetchWeather(coordinateQuery, false, city.name);
    },

    useGps() {
      if (!navigator.geolocation) {
        this.notice = 'Geolocation is not supported by this browser.';
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const { latitude, longitude } = position.coords;
          this.selectedCityKey = '';
          localStorage.removeItem('cw-selected-city');
          this.locationQuery = `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;
          this.weather.coordinates = [latitude, longitude];
          this.fetchWeather(this.locationQuery);
          this.setMapCenter([latitude, longitude], 9);
        },
        () => { this.notice = 'GPS permission was denied or unavailable.'; },
        { enableHighAccuracy: true, timeout: 8000 }
      );
    },

    toggleUnits() {
      this.unit = this.unit === 'metric' ? 'imperial' : 'metric';
      localStorage.setItem('cw-unit', this.unit);
    },

    displayTemp(tempC) {
      if (this.unit === 'imperial') return `${Math.round((tempC * 9 / 5) + 32)} F`;
      return `${Math.round(tempC)} C`;
    },

    displayWind(kph) {
      if (this.unit === 'imperial') return `${Math.round(kph * 0.621371)} mph`;
      return `${Math.round(kph)} km/h`;
    },

    uvLabel(value) {
      if (value >= 11) return 'Extreme';
      if (value >= 8) return 'Very High';
      if (value >= 6) return 'High';
      if (value >= 3) return 'Moderate';
      return 'Low';
    },

    aqiLabel(value) {
      if (value > 200) return 'Very Unhealthy';
      if (value > 150) return 'Unhealthy';
      if (value > 100) return 'Sensitive Groups';
      if (value > 50) return 'Moderate';
      return 'Good';
    },

    hasGoogleMapsKey() {
      return Boolean(window.CARIBWEATHER_GOOGLE_MAPS_KEY);
    },

    loadGoogleMapsApi() {
      if (window.google?.maps) return Promise.resolve(window.google.maps);
      if (this.googleMapsApiPromise) return this.googleMapsApiPromise;

      const key = window.CARIBWEATHER_GOOGLE_MAPS_KEY;
      if (!key) return Promise.reject(new Error('Google Maps API key is not configured.'));

      this.googleMapsApiPromise = new Promise((resolve, reject) => {
        const existing = document.getElementById('google-maps-js');
        if (existing) {
          existing.addEventListener('load', () => resolve(window.google?.maps));
          existing.addEventListener('error', () => reject(new Error('Google Maps failed to load.')));
          return;
        }

        const script = document.createElement('script');
        script.id = 'google-maps-js';
        script.async = true;
        script.defer = true;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly`;
        script.onload = () => resolve(window.google?.maps);
        script.onerror = () => reject(new Error('Google Maps failed to load.'));
        document.head.appendChild(script);
      });

      return this.googleMapsApiPromise;
    },

    async initMap() {
      try {
        if (this.hasGoogleMapsKey()) {
          await this.initGoogleMap();
        } else {
          this.initLeafletMap();
        }
      } catch (error) {
        this.notice = 'Google Maps could not load; using Leaflet fallback.';
        this.initLeafletMap();
      }

      if (this.mapProvider === 'leaflet' && this.map) {
        this.map.invalidateSize();
      }

      this.updateMapMarker();
      await this.setLayer(this.activeLayer);
    },

    initLeafletMap() {
      if (typeof L === 'undefined') return;
      const needsNewMap = !this.map || this.mapProvider !== 'leaflet';
      this.mapProvider = 'leaflet';
      if (needsNewMap) {
        this.map = L.map('weather-map', { zoomControl: true }).setView(this.weather.coordinates, 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors',
          maxZoom: 18
        }).addTo(this.map);
        this.map.on('click', (event) => this.showMapPopup(event.latlng));
      }
    },

    async initGoogleMap() {
      const maps = await this.loadGoogleMapsApi();
      if (!maps) throw new Error('Google Maps API unavailable');

      this.mapProvider = 'google';
      const [lat, lng] = this.weather.coordinates;
      const center = { lat, lng };

      if (!this.googleMap) {
        this.googleInfoWindow = new maps.InfoWindow();
        this.googleMap = new maps.Map(document.getElementById('weather-map'), {
          center,
          zoom: 7,
          mapTypeControl: true,
          streetViewControl: false,
          fullscreenControl: true,
          clickableIcons: false
        });
        this.googleMap.addListener('click', (event) => this.showMapPopup(event.latLng));
      } else {
        this.googleMap.setCenter(center);
        this.googleMap.setZoom(7);
      }

      this.map = this.googleMap;
    },

    setMapCenter(coordinates, zoom = 7) {
      if (this.mapProvider === 'google' && this.googleMap) {
        this.googleMap.setCenter({ lat: coordinates[0], lng: coordinates[1] });
        this.googleMap.setZoom(zoom);
        return;
      }

      if (this.map && typeof this.map.setView === 'function') {
        this.map.setView(coordinates, zoom);
      }
    },

    removeOverlay(layer) {
      if (!layer) return;
      if (Array.isArray(layer)) {
        layer.forEach((item) => this.removeOverlay(item));
        return;
      }

      if (typeof layer.removeFrom === 'function' && this.map) {
        layer.removeFrom(this.map);
        return;
      }

      if (typeof layer.setMap === 'function') {
        layer.setMap(null);
      }
    },

    updateMapMarker() {
      if (!this.map) return;

      if (this.mapProvider === 'google' && this.googleMap && window.google?.maps) {
        if (this.mapMarker) this.mapMarker.setMap(null);
        const maps = window.google.maps;
        const [lat, lng] = this.weather.coordinates;
        this.mapMarker = new maps.Marker({
          position: { lat, lng },
          map: this.googleMap,
          title: this.weather.location
        });
        this.mapMarker.addListener('click', () => {
          this.googleInfoWindow.setContent(`<strong>${escapeHtml(this.weather.location)}</strong><br>${escapeHtml(this.weather.current.summary)}<br>${this.displayTemp(this.weather.current.tempC)} | ${this.weather.current.rainChance}% rain`);
          this.googleInfoWindow.setPosition({ lat, lng });
          this.googleInfoWindow.open({ map: this.googleMap, anchor: this.mapMarker });
        });
        this.setMapCenter(this.weather.coordinates, 7);
        return;
      }

      if (typeof L === 'undefined') return;
      if (this.mapMarker) this.mapMarker.remove();
      this.mapMarker = L.marker(this.weather.coordinates)
        .addTo(this.map)
        .bindPopup(`<strong>${escapeHtml(this.weather.location)}</strong><br>${escapeHtml(this.weather.current.summary)}<br>${this.displayTemp(this.weather.current.tempC)} | ${this.weather.current.rainChance}% rain`);
      this.map.setView(this.weather.coordinates, 7);
    },

    async setLayer(layer) {
      this.activeLayer = layer;
      if (!this.map) return;
      this.clearMapOverlays();
      this.mapStatus = '';

      if (layer === 'rainfall') {
        await this.addRainViewerLayer();
      } else if (layer === 'storms') {
        await this.addStormLayer();
      } else {
        this.addCurrentWeatherOverlay(layer);
      }
    },

    clearMapOverlays() {
      if (this.mapProvider === 'google' && this.googleMap?.overlayMapTypes) {
        this.googleMap.overlayMapTypes.clear();
      }

      [this.radarLayer, this.stormLayer, this.stormGeometryLayer, this.weatherPointLayer, this.weatherTileLayer].forEach((layer) => {
        this.removeOverlay(layer);
      });
      this.radarLayer = null;
      this.stormLayer = null;
      this.stormGeometryLayer = null;
      this.weatherPointLayer = null;
      this.weatherTileLayer = null;
    },

    reloadStormGeometry() {
      if (!this.map || this.activeLayer !== 'storms') return;
      this.removeOverlay(this.stormGeometryLayer);
      this.stormGeometryLayer = null;
      if (!this.stormGeometryEnabled) {
        this.mapStatus = 'Tracks/cones hidden.';
        return;
      }

      // Geometry is drawn inside addStormLayer(); here we just force a refresh.
      this.addStormLayer();
    },

    async addRainViewerLayer() {
      try {
        this.mapStatus = 'Loading RainViewer radar...';
        const response = await fetch('https://api.rainviewer.com/public/weather-maps.json');
        if (!response.ok) throw new Error('RainViewer unavailable');
        const data = await response.json();
        const latest = data.radar?.past?.at(-1) || data.radar?.nowcast?.[0];
        if (!latest?.path) throw new Error('No radar frame available');

        if (this.mapProvider === 'google' && this.googleMap && window.google?.maps) {
          this.radarLayer = new window.google.maps.ImageMapType({
            getTileUrl: (coord, zoom) => `https://tilecache.rainviewer.com${latest.path}/256/${zoom}/${coord.x}/${coord.y}/2/1_1.png`,
            tileSize: new window.google.maps.Size(256, 256),
            opacity: 0.68,
            name: 'RainViewer radar'
          });
          this.googleMap.overlayMapTypes.push(this.radarLayer);
        } else {
          this.radarLayer = L.tileLayer(`https://tilecache.rainviewer.com${latest.path}/256/{z}/{x}/{y}/2/1_1.png`, {
            opacity: 0.68,
            attribution: 'Radar: RainViewer'
          }).addTo(this.map);
        }

        this.mapStatus = `Radar frame: ${new Date((latest.time || 0) * 1000).toLocaleTimeString()}`;
      } catch (error) {
        this.mapStatus = 'RainViewer radar is unavailable right now.';
      }
    },

    async addStormLayer() {
      try {
        this.mapStatus = 'Loading NOAA/NHC active storms...';
        const response = await fetch('/api/storms/active', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Storm feed unavailable');
        const data = await response.json();
        const useGoogle = this.mapProvider === 'google' && this.googleMap && window.google?.maps;
        const maps = window.google?.maps;
        this.stormLayer = useGoogle ? [] : L.layerGroup().addTo(this.map);

        if (!data.storms?.length) {
          this.mapStatus = 'No active NOAA/NHC storms reported.';
          return;
        }

        data.storms.forEach((storm) => {
          const popupHtml = `
            <strong>${escapeHtml(storm.name)}</strong><br>
            ${escapeHtml(storm.classification || 'Active system')} | ${storm.intensity || 'N/A'} kt<br>
            Pressure: ${storm.pressure || 'N/A'} mb<br>
            ${storm.publicAdvisoryUrl ? `<a href="${escapeHtml(storm.publicAdvisoryUrl)}" target="_blank" rel="noopener">Public advisory</a>` : ''}
          `;

          if (useGoogle) {
            const marker = new maps.Marker({
              position: { lat: storm.latitude, lng: storm.longitude },
              map: this.googleMap,
              title: storm.name,
              icon: {
                path: maps.SymbolPath.CIRCLE,
                scale: 7,
                fillColor: '#fb7185',
                fillOpacity: 1,
                strokeColor: '#fff',
                strokeWeight: 2
              }
            });
            marker.addListener('click', () => {
              this.googleInfoWindow.setContent(popupHtml);
              this.googleInfoWindow.open({ map: this.googleMap, anchor: marker });
            });
            this.stormLayer.push(marker);
          } else {
            const marker = L.marker([storm.latitude, storm.longitude], {
              icon: L.divIcon({
                className: 'storm-marker',
                html: '<span></span>',
                iconSize: [28, 28],
                iconAnchor: [14, 14]
              })
            });
            marker.bindPopup(popupHtml);
            marker.addTo(this.stormLayer);
          }
        });

        if (!this.stormGeometryEnabled) {
          this.mapStatus = `${data.storms.length} active NOAA/NHC storm system(s). Tracks/cones hidden.`;
          return;
        }

        // Draw storm tracks / cones (GeoJSON) if available.
        try {
          const geoResponse = await fetch('/api/storms/active-geojson', { headers: { Accept: 'application/json' } });
          if (!geoResponse.ok) throw new Error('GeoJSON unavailable');

          const geoData = await geoResponse.json();
          const features = geoData?.geojson?.features || [];
          if (features.length) {
            const styles = {
              track: { strokeColor: '#fb7185', strokeWeight: 3, strokeOpacity: 0.85, fillOpacity: 0 },
              cone: { strokeColor: '#60a5fa', strokeWeight: 2, strokeOpacity: 0.9, fillColor: '#60a5fa', fillOpacity: 0.18 },
              unknown: { strokeColor: '#94a3b8', strokeWeight: 2, strokeOpacity: 0.7, fillOpacity: 0 }
            };

            const getStyle = (feature) => styles[feature?.properties?.category] || styles.unknown;

            if (useGoogle) {
              this.stormGeometryLayer = new maps.Data();
              this.stormGeometryLayer.addGeoJson(geoData.geojson);
              this.stormGeometryLayer.setStyle((feature) => {
                const style = getStyle(feature);
                return {
                  strokeColor: style.strokeColor,
                  strokeWeight: style.strokeWeight,
                  strokeOpacity: style.strokeOpacity,
                  fillColor: style.fillColor || style.strokeColor,
                  fillOpacity: style.fillOpacity,
                  clickable: false
                };
              });
              this.stormGeometryLayer.setMap(this.googleMap);
            } else {
              this.stormGeometryLayer = L.geoJSON(geoData.geojson, {
                style: (feature) => {
                  const s = getStyle(feature);
                  return {
                    color: s.strokeColor,
                    weight: s.strokeWeight,
                    opacity: s.strokeOpacity,
                    fillColor: s.fillColor || s.strokeColor,
                    fillOpacity: s.fillOpacity
                  };
                },
                pointToLayer: (feature, latlng) => L.circleMarker(latlng, { radius: 6, color: getStyle(feature).strokeColor, weight: 2, fillOpacity: 0.6 }),
              }).addTo(this.map);
            }

            this.mapStatus = `${data.storms.length} active NOAA/NHC storm system(s). Geometry loaded (${features.length} feature(s)).`;
          } else {
            this.mapStatus = `${data.storms.length} active NOAA/NHC storm system(s). Track/cone geometry unavailable right now.`;
          }
        } catch (e) {
          this.mapStatus = `${data.storms.length} active NOAA/NHC storm system(s). Track/cone geometry unavailable right now.`;
        }
      } catch (error) {
        this.mapStatus = 'NOAA/NHC storm feed is unavailable right now.';
      }
    },

    addCurrentWeatherOverlay(layer) {
      const tileLayer = { temperature: 'temp_new', wind: 'wind_new', clouds: 'clouds_new' }[layer];
      if (tileLayer && this.mapProvider === 'google' && this.googleMap && window.google?.maps) {
        this.weatherTileLayer = new window.google.maps.ImageMapType({
          getTileUrl: (coord, zoom) => `/api/map/tiles/${tileLayer}/${zoom}/${coord.x}/${coord.y}.png`,
          tileSize: new window.google.maps.Size(256, 256),
          opacity: 0.55,
          name: `OpenWeather ${layer}`
        });
        this.googleMap.overlayMapTypes.push(this.weatherTileLayer);
      } else if (tileLayer) {
        this.weatherTileLayer = L.tileLayer(`/api/map/tiles/${tileLayer}/{z}/{x}/{y}.png`, {
          opacity: 0.55,
          attribution: 'Weather tiles: OpenWeather'
        }).addTo(this.map);
      }

      const [lat, lon] = this.weather.coordinates;
      const label = this.mapLayerValue(layer);
      if (this.mapProvider === 'google' && this.googleMap && window.google?.maps) {
        this.weatherPointLayer = new window.google.maps.Circle({
          map: this.googleMap,
          center: { lat, lng: lon },
          radius: 18000,
          strokeColor: layer === 'temperature' ? '#f97316' : layer === 'wind' ? '#14b8a6' : '#94a3b8',
          strokeOpacity: 0.85,
          strokeWeight: 3,
          fillColor: layer === 'temperature' ? '#fb923c' : layer === 'wind' ? '#2dd4bf' : '#cbd5e1',
          fillOpacity: 0.35
        });
        this.weatherPointLayer.addListener('click', () => {
          this.googleInfoWindow.setContent(`<strong>${escapeHtml(this.weather.location)}</strong><br>${escapeHtml(label)}`);
          this.googleInfoWindow.setPosition({ lat, lng: lon });
          this.googleInfoWindow.open({ map: this.googleMap });
        });
      } else {
        this.weatherPointLayer = L.circleMarker([lat, lon], {
          radius: 18,
          color: layer === 'temperature' ? '#f97316' : layer === 'wind' ? '#14b8a6' : '#94a3b8',
          fillColor: layer === 'temperature' ? '#fb923c' : layer === 'wind' ? '#2dd4bf' : '#cbd5e1',
          fillOpacity: 0.45,
          weight: 3
        }).addTo(this.map).bindPopup(`<strong>${escapeHtml(this.weather.location)}</strong><br>${escapeHtml(label)}`);
      }
      this.mapStatus = tileLayer
        ? 'Live tile layer enabled. Click anywhere for point weather lookup.'
        : 'Click anywhere on the map for point weather lookup.';
    },

    mapLayerValue(layer) {
      if (layer === 'temperature') return `Temperature: ${this.displayTemp(this.weather.current.tempC)}`;
      if (layer === 'wind') return `Wind: ${this.displayWind(this.weather.current.windKph)} ${this.weather.current.windDirection}`;
      if (layer === 'clouds') return `Cloud cover: ${this.weather.current.cloudCover}%`;
      return this.activeLayerDescription;
    },

    async showMapPopup(latlng) {
      if (!this.map) return;

      const getLat = () => (typeof latlng.lat === 'function' ? latlng.lat() : latlng.lat);
      const getLng = () => (typeof latlng.lng === 'function' ? latlng.lng() : latlng.lng);
      const lat = getLat();
      const lng = getLng();

      if (this.mapProvider === 'google' && this.googleInfoWindow && window.google?.maps) {
        this.googleInfoWindow.setPosition({ lat, lng });
        this.googleInfoWindow.setContent(`Loading point weather...<br>Lat ${lat.toFixed(3)}, Lng ${lng.toFixed(3)}`);
        this.googleInfoWindow.open({ map: this.googleMap });
      } else if (typeof L !== 'undefined') {
        const popup = L.popup()
          .setLatLng(latlng)
          .setContent(`Loading point weather...<br>Lat ${lat.toFixed(3)}, Lng ${lng.toFixed(3)}`)
          .openOn(this.map);
        this._activeLeafletPopup = popup;
      }

      try {
        const location = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        const response = await fetch(`/api/weather/current?location=${encodeURIComponent(location)}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Point weather unavailable');
        const data = await response.json();
        const content = `
          <strong>Point Weather</strong><br>
          ${escapeHtml(data.current.summary)}<br>
          ${this.displayTemp(data.current.tempC)} | Wind ${this.displayWind(data.current.windKph)}<br>
          Rain ${data.current.rainChance}% | Clouds ${data.current.cloudCover}%<br>
          Lat ${lat.toFixed(3)}, Lng ${lng.toFixed(3)}
        `;

        if (this.mapProvider === 'google' && this.googleInfoWindow) {
          this.googleInfoWindow.setContent(content);
        } else if (this._activeLeafletPopup) {
          this._activeLeafletPopup.setContent(content);
        }
      } catch (error) {
        const content = `<strong>${escapeHtml(this.activeLayer)}</strong><br>${escapeHtml(this.activeLayerDescription)}<br>Lat ${lat.toFixed(3)}, Lng ${lng.toFixed(3)}`;
        if (this.mapProvider === 'google' && this.googleInfoWindow) {
          this.googleInfoWindow.setContent(content);
        } else if (this._activeLeafletPopup) {
          this._activeLeafletPopup.setContent(content);
        }
      }
    },

    async askAssistant() {
      const query = this.assistantQuery.trim();
      if (!query) return;
      this.chat.push({ id: crypto.randomUUID(), role: 'user', text: query });
      this.assistantQuery = '';
      try {
        const response = await fetch('/api/assistant/query', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ query, context: this.weather, history: this.chat.slice(-10) })
        });
        if (!response.ok) throw new Error('Assistant backend unavailable');
        const data = await response.json();
        this.chat.push({ id: crypto.randomUUID(), role: 'assistant', text: data.answer });
      } catch (error) {
        this.chat.push({ id: crypto.randomUUID(), role: 'assistant', text: fallbackAssistantAnswer(query, this.weather) });
      }
    },

    async loadAlerts() {
      try {
        const response = await fetch('/api/alerts', {
          headers: this.apiHeaders()
        });
        if (!response.ok) throw new Error('Alerts backend unavailable');
        const payload = await response.json();
        this.alerts = payload.data || [];
        localStorage.setItem('cw-alerts', JSON.stringify(this.alerts));
      } catch (error) {
        this.alerts = JSON.parse(localStorage.getItem('cw-alerts') || '[]');
      }
    },

    async loadSavedLocations() {
      try {
        const response = await fetch('/api/saved-locations', { headers: this.apiHeaders() });
        if (!response.ok) throw new Error('Saved locations unavailable');
        const payload = await response.json();
        this.savedLocations = payload.data || [];
      } catch (error) {
        this.savedLocations = [];
      }
    },

    async saveCurrentLocation() {
      const coordinates = this.weather.coordinates || [];
      if (coordinates.length < 2) return;
      try {
        const response = await fetch('/api/saved-locations', {
          method: 'POST',
          headers: this.apiHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({
            name: this.weather.location || this.locationQuery,
            latitude: coordinates[0],
            longitude: coordinates[1],
            isDefault: this.savedLocations.length === 0
          })
        });
        if (!response.ok) throw new Error('Could not save location');
        this.notice = 'Location saved.';
        await this.loadSavedLocations();
      } catch (error) {
        this.notice = error.message;
      }
    },

    async useSavedLocation(location) {
      this.selectedCityKey = '';
      this.locationQuery = location.name;
      await this.fetchWeather(`${Number(location.latitude).toFixed(4)}, ${Number(location.longitude).toFixed(4)}`, false, location.name);
    },

    async deleteSavedLocation(id) {
      const previous = [...this.savedLocations];
      this.savedLocations = this.savedLocations.filter((location) => location.id !== id);
      try {
        const response = await fetch(`/api/saved-locations/${id}`, {
          method: 'DELETE',
          headers: this.apiHeaders()
        });
        if (!response.ok) throw new Error('Could not delete saved location');
      } catch (error) {
        this.savedLocations = previous;
        this.notice = error.message;
      }
    },

    async loadNotifications() {
      try {
        const response = await fetch('/api/notifications', {
          headers: this.apiHeaders()
        });
        if (!response.ok) throw new Error('Notifications backend unavailable');
        const payload = await response.json();
        this.notifications = payload.data || [];
      } catch (error) {
        this.notifications = [];
      }
    },

    async markNotificationRead(id) {
      try {
        const response = await fetch(`/api/notifications/${id}/read`, {
          method: 'POST',
          headers: this.apiHeaders()
        });
        if (!response.ok) throw new Error('Could not mark notification read');
        const payload = await response.json();
        this.notifications = this.notifications.map((notification) => notification.id === id ? payload.data : notification);
      } catch (error) {
        this.notice = 'Could not update notification status.';
      }
    },

    async checkAlertsNow() {
      this.notice = 'Reloaded recent in-app alerts. The scheduler checks thresholds every 15 minutes.';
      await this.loadNotifications();
    },

    async saveAlert() {
      if (!this.alertForm.location.trim()) return;
      const payload = {
        ...this.alertForm,
        latitude: this.weather.coordinates?.[0] || null,
        longitude: this.weather.coordinates?.[1] || null,
        channels: ['in_app', 'email', 'push']
      };

      try {
        const response = await fetch('/api/alerts', {
          method: 'POST',
          headers: this.apiHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify(payload)
        });
        if (!response.ok) throw new Error('Alert save failed');
        const result = await response.json();
        this.alerts.unshift(result.data);
        this.notice = 'Alert subscription saved.';
        this.loadNotifications();
      } catch (error) {
        this.alerts.unshift({ ...payload, id: crypto.randomUUID(), createdAt: new Date().toISOString() });
        this.notice = 'Alert saved locally because the backend was unavailable.';
      }

      localStorage.setItem('cw-alerts', JSON.stringify(this.alerts));
      this.alertForm.location = this.locationQuery;
    },

    async deleteAlert(id) {
      const previous = [...this.alerts];
      this.alerts = this.alerts.filter((alert) => alert.id !== id);
      localStorage.setItem('cw-alerts', JSON.stringify(this.alerts));
      if (typeof id !== 'number') return;

      try {
        const response = await fetch(`/api/alerts/${id}`, {
          method: 'DELETE',
          headers: this.apiHeaders()
        });
        if (!response.ok) throw new Error('Delete failed');
      } catch (error) {
        this.alerts = previous;
        localStorage.setItem('cw-alerts', JSON.stringify(this.alerts));
        this.notice = 'Could not delete the alert from the backend. Try again.';
      }
    },

    async requestPushPermission() {
      if (!('Notification' in window)) {
        this.notice = 'Push notifications are not supported by this browser.';
        return;
      }
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        this.notice = 'Push notification permission was not granted.';
        return;
      }

      try {
        const keyResponse = await fetch('/api/push/vapid-public-key', { headers: this.apiHeaders() });
        const keyPayload = await keyResponse.json();
        if (!keyPayload.publicKey) throw new Error('VAPID public key is not configured yet.');
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(keyPayload.publicKey)
        });
        const response = await fetch('/api/push-subscriptions', {
          method: 'POST',
          headers: this.apiHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify(subscription.toJSON())
        });
        if (!response.ok) throw new Error('Could not save push subscription.');
        this.notice = 'Push notifications are enabled for this browser.';
      } catch (error) {
        this.notice = error.message;
      }
    },

    async loadHistorical() {
      try {
        const params = new URLSearchParams(this.historyForm).toString();
        const response = await fetch(`/api/weather/historical?${params}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Historical backend unavailable');
        this.historical = await response.json();
      } catch (error) {
        this.historical = mockHistorical();
        this.notice = 'Using sample historical data until the Meteostat proxy is connected.';
      }
      this.renderCharts();
    },

    renderCharts() {
      if (typeof Chart === 'undefined') return;
      const labels = this.historical.labels;
      this.drawChart('temperature-chart', 'Temperature C', labels, [
        { label: 'High', data: this.historical.highs, borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,.18)' },
        { label: 'Mean', data: this.historical.means, borderColor: '#0f766e', backgroundColor: 'rgba(15,118,110,.16)' },
        { label: 'Low', data: this.historical.lows, borderColor: '#38bdf8', backgroundColor: 'rgba(56,189,248,.16)' }
      ]);
      this.drawChart('rainfall-chart', 'Rainfall mm', labels, [
        { label: 'Rainfall', data: this.historical.rainfall, backgroundColor: '#0ea5e9', borderColor: '#0284c7' }
      ], 'bar');
      this.drawChart('wind-chart', 'Wind km/h', labels, [
        { label: 'Wind Speed', data: this.historical.wind, borderColor: '#14b8a6', backgroundColor: 'rgba(20,184,166,.18)', fill: true }
      ]);
      this.drawChart('humidity-chart', 'Humidity %', labels, [
        { label: 'Humidity', data: this.historical.humidity, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.18)', fill: true }
      ]);
      this.drawChart('pressure-chart', 'Pressure hPa', labels, [
        { label: 'Pressure', data: this.historical.pressure || [], borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,.16)', fill: true }
      ]);
    },

    drawChart(canvasId, title, labels, datasets, type = 'line') {
      const canvas = document.getElementById(canvasId);
      if (!canvas) return;
      if (this.chartInstances[canvasId]) this.chartInstances[canvasId].destroy();
      this.chartInstances[canvasId] = new Chart(canvas, {
        type,
        data: { labels, datasets },
        options: {
          responsive: true,
          plugins: {
            legend: { labels: { color: '#0f172a', font: { weight: 'bold' } } },
            title: { display: true, text: title, color: '#0f172a', font: { size: 16, weight: '900' } },
            tooltip: { enabled: true }
          },
          scales: {
            x: { ticks: { color: '#334155' }, grid: { color: 'rgba(15,23,42,.08)' } },
            y: { ticks: { color: '#334155' }, grid: { color: 'rgba(15,23,42,.08)' } }
          }
        }
      });
    },

    exportCsv() {
      const rows = [['date', 'high_c', 'mean_c', 'low_c', 'rainfall_mm', 'wind_kph', 'humidity_percent', 'pressure_hpa']];
      this.historical.labels.forEach((label, index) => {
        rows.push([
          label,
          this.historical.highs[index],
          this.historical.means[index],
          this.historical.lows[index],
          this.historical.rainfall[index],
          this.historical.wind[index],
          this.historical.humidity[index],
          this.historical.pressure?.[index] ?? ''
        ]);
      });
      const blob = new Blob([rows.map((row) => row.join(',')).join('\n')], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'caribweather-historical.csv';
      link.click();
      URL.revokeObjectURL(url);
    }
  };
}

function mockWeather(location) {
  const coordinates = guessCoordinates(location);
  return {
    location,
    coordinates,
    cache: false,
    lastUpdated: new Date().toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }),
    current: {
      tempC: 30,
      feelsLikeC: 34,
      humidity: 78,
      windKph: 24,
      windDirection: 'ESE',
      windDegrees: 112,
      uvIndex: 9,
      aqi: 42,
      aqiAdvisory: 'Air quality is generally acceptable.',
      rainChance: 48,
      cloudCover: 62,
      summary: 'Warm, breezy, and humid with scattered showers nearby'
    },
    marine: {
      waveHeightM: 1.6,
      seaTempC: 28.7,
      swellDirection: 'ENE'
    },
    sun: {
      sunrise: '5:43 AM',
      sunset: '6:31 PM'
    },
    hourly: Array.from({ length: 8 }, (_, index) => ({
      time: `${(index * 3 + 6).toString().padStart(2, '0')}:00`,
      tempC: 27 + Math.round(Math.sin(index / 2) * 3 + index / 3),
      rain: [28, 34, 42, 54, 48, 36, 30, 24][index]
    })),
    daily: ['Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Mon'].map((day, index) => ({
      day,
      condition: ['Showers', 'Partly Cloudy', 'Humid', 'Thunderstorms', 'Breezy', 'Sunny', 'Scattered Rain'][index],
      highC: 30 + (index % 3),
      lowC: 24 + (index % 2),
      rain: [55, 35, 30, 62, 42, 24, 39][index]
    }))
  };
}

function mockHistorical() {
  return {
    labels: ['Jun 1', 'Jun 2', 'Jun 3', 'Jun 4', 'Jun 5', 'Jun 6', 'Jun 7'],
    highs: [31, 32, 31, 30, 33, 32, 31],
    means: [28, 29, 28, 27, 29, 29, 28],
    lows: [25, 25, 24, 24, 25, 26, 25],
    rainfall: [4, 12, 0, 18, 6, 2, 9],
    wind: [22, 26, 18, 31, 24, 20, 23],
    humidity: [78, 82, 76, 85, 80, 74, 79],
    pressure: [1012, 1011, 1013, 1010, 1012, 1014, 1011]
  };
}

function guessCoordinates(location) {
  const key = location.toLowerCase();
  const known = [
    ['barbados', [13.1939, -59.5432]],
    ['grenada', [12.1165, -61.6790]],
    ['trinidad', [10.6918, -61.2225]],
    ['tobago', [11.2500, -60.6670]],
    ['jamaica', [18.1096, -77.2975]],
    ['castries', [14.0101, -60.9875]],
    ['dominica', [15.4150, -61.3710]],
    ['antigua', [17.0608, -61.7964]]
  ];
  const match = known.find(([name]) => key.includes(name));
  if (match) return match[1];
  const coordinateMatch = location.match(/(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/);
  if (coordinateMatch) return [Number(coordinateMatch[1]), Number(coordinateMatch[2])];
  return [12.1165, -61.6790];
}

function fallbackAssistantAnswer(query, weather) {
  const lower = query.toLowerCase();
  if (lower.includes('fish') || lower.includes('sea') || lower.includes('rough')) {
    return `For ${weather.location}, seas are around ${weather.marine.waveHeightM} m with ${weather.current.windDirection} winds at ${weather.current.windKph} km/h. Small craft should monitor local marine advisories, especially if winds strengthen.`;
  }
  if (lower.includes('rain')) {
    return `Rain chance for ${weather.location} is currently about ${weather.current.rainChance}%. Keep an umbrella nearby and check live radar once the RainViewer proxy is connected.`;
  }
  if (lower.includes('wear') || lower.includes('uv')) {
    return `It is ${weather.current.summary.toLowerCase()} with UV ${weather.current.uvIndex}. Wear light clothing, sunscreen, sunglasses, and carry water.`;
  }
  if (lower.includes('hurricane') || lower.includes('storm')) {
    return 'No live NOAA/NHC feed is connected in this MVP. When the backend is enabled, I will summarize active advisories and uncertainty clearly.';
  }
  return `For ${weather.location}, conditions are ${weather.current.summary.toLowerCase()}, ${weather.current.tempC} C, with ${weather.current.rainChance}% rain chance. Live AI responses will use the Laravel OpenAI proxy when connected.`;
}

function escapeHtml(value) {
  return String(value).replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;'
  }[char]));
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; i++) {
    outputArray[i] = rawData.charCodeAt(i);
  }

  return outputArray;
}
