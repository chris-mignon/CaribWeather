function caribWeatherApp() {
  return {
    activeView: 'dashboard',
    navOpen: false,
    online: navigator.onLine,
    notice: '',
    unit: localStorage.getItem('cw-unit') || 'metric',
    locationQuery: localStorage.getItem('cw-location') || 'St. George\'s, Grenada',
    weather: mockWeather('St. George\'s, Grenada'),
    map: null,
    mapMarker: null,
    activeLayer: 'temperature',
    chartInstances: {},
    assistantQuery: '',
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
    mapLayers: [
      { id: 'temperature', label: 'Temperature', description: 'Warm oranges indicate higher surface temperatures.' },
      { id: 'rainfall', label: 'Rainfall / Radar', description: 'Blue zones identify likely rainfall and precipitation movement.' },
      { id: 'wind', label: 'Wind Speed', description: 'Teal vectors and shading represent wind speed and direction.' },
      { id: 'clouds', label: 'Cloud Coverage', description: 'Grey shading indicates cloud density.' },
      { id: 'storms', label: 'Tropical Storms', description: 'Storm track overlay placeholder for NOAA/NHC feeds.' }
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

    init() {
      const hashView = window.location.hash.replace('#', '');
      if (this.navItems.some((item) => item.id === hashView)) this.activeView = hashView;
      window.addEventListener('online', () => { this.online = true; });
      window.addEventListener('offline', () => { this.online = false; });
      window.addEventListener('hashchange', () => {
        const view = window.location.hash.replace('#', '');
        if (this.navItems.some((item) => item.id === view)) this.activeView = view;
      });
      this.registerServiceWorker();
      this.refreshWeather();
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

    async registerServiceWorker() {
      if (!('serviceWorker' in navigator)) return;
      try {
        await navigator.serviceWorker.register('/service-worker.js');
      } catch (error) {
        this.notice = 'PWA service worker could not be registered in this environment.';
      }
    },

    async refreshWeather() {
      await this.fetchWeather(this.locationQuery);
      setInterval(() => this.fetchWeather(this.locationQuery, true), 10 * 60 * 1000);
    },

    async fetchWeather(location, quiet = false) {
      try {
        const response = await fetch(`/api/weather/current?location=${encodeURIComponent(location)}`, {
          headers: { Accept: 'application/json' }
        });
        if (!response.ok) throw new Error('Weather backend unavailable');
        const data = await response.json();
        this.weather = data;
        this.notice = '';
      } catch (error) {
        const cached = localStorage.getItem(`cw-weather-${location}`);
        this.weather = cached ? JSON.parse(cached) : mockWeather(location);
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
      this.fetchWeather(location);
    },

    useGps() {
      if (!navigator.geolocation) {
        this.notice = 'Geolocation is not supported by this browser.';
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const { latitude, longitude } = position.coords;
          this.locationQuery = `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;
          this.weather.coordinates = [latitude, longitude];
          this.fetchWeather(this.locationQuery);
          if (this.map) this.map.setView([latitude, longitude], 9);
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

    initMap() {
      if (typeof L === 'undefined') return;
      if (!this.map) {
        this.map = L.map('weather-map', { zoomControl: true }).setView(this.weather.coordinates, 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors',
          maxZoom: 18
        }).addTo(this.map);
        this.map.on('click', (event) => this.showMapPopup(event.latlng));
      }
      this.map.invalidateSize();
      this.updateMapMarker();
    },

    updateMapMarker() {
      if (!this.map || typeof L === 'undefined') return;
      if (this.mapMarker) this.mapMarker.remove();
      this.mapMarker = L.marker(this.weather.coordinates)
        .addTo(this.map)
        .bindPopup(`<strong>${escapeHtml(this.weather.location)}</strong><br>${escapeHtml(this.weather.current.summary)}<br>${this.displayTemp(this.weather.current.tempC)} | ${this.weather.current.rainChance}% rain`);
      this.map.setView(this.weather.coordinates, 7);
    },

    setLayer(layer) {
      this.activeLayer = layer;
      if (this.map) this.showMapPopup(this.map.getCenter());
    },

    showMapPopup(latlng) {
      if (!this.map || typeof L === 'undefined') return;
      const layerText = this.activeLayerDescription;
      L.popup()
        .setLatLng(latlng)
        .setContent(`<strong>${escapeHtml(this.activeLayer)}</strong><br>${escapeHtml(layerText)}<br>Lat ${latlng.lat.toFixed(3)}, Lng ${latlng.lng.toFixed(3)}`)
        .openOn(this.map);
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

    saveAlert() {
      if (!this.alertForm.location.trim()) return;
      this.alerts.unshift({ ...this.alertForm, id: crypto.randomUUID(), createdAt: new Date().toISOString() });
      localStorage.setItem('cw-alerts', JSON.stringify(this.alerts));
      this.alertForm.location = this.locationQuery;
    },

    deleteAlert(id) {
      this.alerts = this.alerts.filter((alert) => alert.id !== id);
      localStorage.setItem('cw-alerts', JSON.stringify(this.alerts));
    },

    async requestPushPermission() {
      if (!('Notification' in window)) {
        this.notice = 'Push notifications are not supported by this browser.';
        return;
      }
      const permission = await Notification.requestPermission();
      this.notice = permission === 'granted'
        ? 'Push permission granted. Laravel Web Push can now subscribe this device.'
        : 'Push notification permission was not granted.';
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
      const rows = [['date', 'high_c', 'mean_c', 'low_c', 'rainfall_mm', 'wind_kph', 'humidity_percent']];
      this.historical.labels.forEach((label, index) => {
        rows.push([
          label,
          this.historical.highs[index],
          this.historical.means[index],
          this.historical.lows[index],
          this.historical.rainfall[index],
          this.historical.wind[index],
          this.historical.humidity[index]
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
    humidity: [78, 82, 76, 85, 80, 74, 79]
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
