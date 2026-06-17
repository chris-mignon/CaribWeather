<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0f3b57">
  <meta name="description" content="Caribbean weather dashboard, alerts, maps, AI assistant, and analytics.">
  <title>CaribWeather</title>
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" href="/assets/img/icon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://cdn.tailwindcss.com">
  <link rel="preconnect" href="https://unpkg.com">
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQOYn+QO7Qm6tf5l+6qnxuZg2i6vFsc=" crossorigin="">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            lagoon: '#0f766e',
            storm: '#0f3b57',
            reef: '#2dd4bf',
            sunburst: '#f59e0b'
          },
          fontFamily: {
            display: ['Inter', 'system-ui', 'sans-serif']
          }
        }
      }
    };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-white" x-data="caribWeatherApp()" x-init="init()">
  <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[9999] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-slate-950">Skip to content</a>

  <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,.28),_transparent_32rem),linear-gradient(135deg,_#061826_0%,_#0f3b57_45%,_#11253b_100%)]">
    <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/75 backdrop-blur-xl">
      <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8" aria-label="Primary navigation">
        <div class="flex items-center gap-3">
          <img src="/assets/img/icon.svg" alt="" class="h-10 w-10">
          <div>
            <p class="text-lg font-black tracking-tight">CaribWeather</p>
            <p class="text-xs text-cyan-100/75">Caribbean PWA Forecasting</p>
          </div>
        </div>
        <button class="rounded-full border border-white/15 px-3 py-2 text-sm font-semibold text-cyan-50 sm:hidden" type="button" @click="navOpen = !navOpen" :aria-expanded="navOpen.toString()">Menu</button>
        <div class="hidden items-center gap-1 sm:flex">
          <template x-for="item in navItems" :key="item.id">
            <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold transition" :class="activeView === item.id ? 'bg-white text-slate-950' : 'text-cyan-50 hover:bg-white/10'" @click="setView(item.id)" x-text="item.label"></button>
          </template>
        </div>
      </nav>
      <div class="border-t border-white/10 px-4 py-3 sm:hidden" x-show="navOpen" x-transition>
        <div class="grid grid-cols-2 gap-2">
          <template x-for="item in navItems" :key="item.id">
            <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold" :class="activeView === item.id ? 'bg-white text-slate-950' : 'bg-white/10 text-white'" @click="setView(item.id); navOpen = false" x-text="item.label"></button>
          </template>
        </div>
      </div>
    </header>

    <main id="main" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      <section class="mb-6 overflow-hidden rounded-[2rem] border border-white/10 bg-white/10 p-5 shadow-2xl backdrop-blur md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1.35fr_.65fr] lg:items-end">
          <div>
            <p class="mb-3 inline-flex rounded-full bg-cyan-300/15 px-3 py-1 text-sm font-bold text-cyan-100 ring-1 ring-cyan-200/20">Live Caribbean conditions, marine safety, and tropical awareness</p>
            <h1 class="text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">Weather built for island decisions.</h1>
            <p class="mt-4 max-w-3xl text-base leading-7 text-cyan-50/80">Monitor current conditions, forecasts, marine data, storm layers, AI-assisted guidance, alerts, and historical climate trends from one installable PWA.</p>
          </div>
          <form class="rounded-3xl border border-white/10 bg-slate-950/45 p-4" @submit.prevent="searchLocation()">
            <label for="location" class="text-sm font-bold text-cyan-50">Location</label>
            <div class="mt-2 flex gap-2">
              <input id="location" x-model="locationQuery" class="min-w-0 flex-1 rounded-2xl border border-white/10 bg-white px-4 py-3 text-slate-950 outline-none ring-cyan-300 focus:ring-4" placeholder="Grenada, Barbados, Castries" autocomplete="off">
              <button class="rounded-2xl bg-sunburst px-4 py-3 font-black text-slate-950 transition hover:bg-amber-300" type="submit">Search</button>
            </div>
            <label for="city-select" class="mt-3 block text-xs font-bold uppercase tracking-[.2em] text-cyan-100/80">Quick Caribbean City</label>
            <select id="city-select" x-model="selectedCityKey" class="mt-2 w-full rounded-2xl border border-white/10 bg-white px-4 py-3 text-slate-950 outline-none ring-cyan-300 focus:ring-4" @change="chooseCity()">
              <option value="">Choose a saved city...</option>
              <template x-for="city in cityOptions" :key="city.key">
                <option :value="city.key" x-text="city.name"></option>
              </template>
            </select>
            <div class="mt-3 flex flex-wrap gap-2">
              <button type="button" class="rounded-full bg-white/10 px-3 py-2 text-sm font-semibold text-white hover:bg-white/20" @click="useGps()">Use GPS</button>
              <button type="button" class="rounded-full bg-white/10 px-3 py-2 text-sm font-semibold text-white hover:bg-white/20" @click="toggleUnits()">Units: <span x-text="unitLabel"></span></button>
              <span class="rounded-full bg-emerald-400/15 px-3 py-2 text-sm font-semibold text-emerald-100" x-text="online ? 'Online' : 'Offline cache mode'"></span>
            </div>
          </form>
        </div>
      </section>

      <p x-show="notice" x-text="notice" class="mb-5 rounded-2xl border border-amber-300/30 bg-amber-300/15 px-4 py-3 text-sm font-semibold text-amber-50"></p>

      <section x-show="activeView === 'dashboard'" x-transition>
        <div class="mb-5 grid gap-4 lg:grid-cols-[1.05fr_.95fr]">
          <article class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-white p-6 text-slate-950 shadow-2xl">
            <div class="absolute right-0 top-0 h-44 w-44 rounded-bl-full bg-cyan-100"></div>
            <div class="relative">
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <p class="text-sm font-black uppercase tracking-[.25em] text-lagoon" x-text="weather.location"></p>
                  <h2 class="mt-2 text-5xl font-black tracking-tight" x-text="displayTemp(weather.current.tempC)"></h2>
                  <p class="mt-2 text-lg font-bold text-slate-600" x-text="weather.current.summary"></p>
                </div>
                <div class="rounded-3xl bg-slate-950 px-5 py-4 text-right text-white">
                  <p class="text-xs uppercase tracking-[.22em] text-cyan-100">Feels Like</p>
                  <p class="text-3xl font-black" x-text="displayTemp(weather.current.feelsLikeC)"></p>
                </div>
              </div>
              <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <template x-for="metric in primaryMetrics" :key="metric.label">
                  <div class="rounded-3xl bg-slate-100 p-4">
                    <p class="text-sm font-bold text-slate-500" x-text="metric.label"></p>
                    <p class="mt-1 text-2xl font-black" x-text="metric.value"></p>
                    <p class="mt-1 text-xs font-semibold text-slate-500" x-text="metric.note"></p>
                  </div>
                </template>
              </div>
              <p class="mt-5 text-sm font-semibold text-slate-500">Last updated <span x-text="weather.lastUpdated"></span> <span x-show="weather.cache">(cached)</span></p>
            </div>
          </article>

          <article class="rounded-[2rem] border border-white/10 bg-slate-950/55 p-6 shadow-2xl backdrop-blur">
            <div class="flex items-center justify-between gap-4">
              <div>
                <h2 class="text-2xl font-black">Marine & Environmental</h2>
                <p class="mt-1 text-sm text-cyan-50/70">Safety-critical signals for coastal users.</p>
              </div>
              <span class="rounded-full bg-cyan-300/15 px-3 py-2 text-sm font-black text-cyan-100">Caribbean tuned</span>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
              <template x-for="metric in marineMetrics" :key="metric.label">
                <div class="rounded-3xl border border-white/10 bg-white/10 p-4">
                  <p class="text-sm font-bold text-cyan-50/70" x-text="metric.label"></p>
                  <p class="mt-1 text-2xl font-black" x-text="metric.value"></p>
                  <p class="mt-1 text-xs font-semibold text-cyan-50/60" x-text="metric.note"></p>
                </div>
              </template>
            </div>
          </article>
        </div>

        <div class="space-y-4">
          <article class="rounded-[2rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
            <h2 class="text-xl font-black">Next 24 Hours</h2>
            <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
              <template x-for="hour in weather.hourly" :key="hour.time">
                <div class="min-w-28 rounded-3xl bg-white p-4 text-center text-slate-950">
                  <p class="text-sm font-black" x-text="hour.time"></p>
                  <p class="mt-2 text-2xl font-black" x-text="displayTemp(hour.tempC)"></p>
                  <p class="mt-1 text-xs font-bold text-slate-500" x-text="hour.rain + '% rain'"></p>
                </div>
              </template>
            </div>
          </article>
          <article class="rounded-[2rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
            <h2 class="text-xl font-black">7-Day Forecast</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
              <template x-for="day in weather.daily" :key="day.day">
                <div class="rounded-3xl bg-white p-4 text-slate-950">
                  <p class="font-black" x-text="day.day"></p>
                  <p class="mt-2 text-sm font-semibold text-slate-500" x-text="day.condition"></p>
                  <p class="mt-3 text-lg font-black"><span x-text="displayTemp(day.highC)"></span> / <span class="text-slate-500" x-text="displayTemp(day.lowC)"></span></p>
                  <p class="mt-1 text-xs font-bold text-lagoon" x-text="day.rain + '% rain'"></p>
                </div>
              </template>
            </div>
          </article>
        </div>
      </section>

      <section x-show="activeView === 'map'" x-transition>
        <div class="grid gap-5 lg:grid-cols-[18rem_1fr]">
          <aside class="rounded-[2rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
            <h2 class="text-xl font-black">Weather Map</h2>
            <p class="mt-2 text-sm text-cyan-50/70">OpenStreetMap base with selectable MVP overlays.</p>
            <div class="mt-5 space-y-2">
              <template x-for="layer in mapLayers" :key="layer.id">
                <button type="button" class="w-full rounded-2xl px-4 py-3 text-left text-sm font-black transition" :class="activeLayer === layer.id ? 'bg-white text-slate-950' : 'bg-white/10 text-white hover:bg-white/20'" @click="setLayer(layer.id)">
                  <span x-text="layer.label"></span>
                </button>
              </template>
            </div>
            <div class="mt-5 rounded-2xl bg-slate-950/55 p-4">
              <p class="text-sm font-black">Legend</p>
              <p class="mt-1 text-sm text-cyan-50/70" x-text="activeLayerDescription"></p>
            </div>
          </aside>
          <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/10 p-3 shadow-2xl">
            <div id="weather-map" class="h-[32rem] rounded-[1.5rem]"></div>
          </div>
        </div>
      </section>

      <section x-show="activeView === 'assistant'" x-transition>
        <div class="grid gap-5 lg:grid-cols-[1fr_.7fr]">
          <article class="rounded-[2rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
            <h2 class="text-2xl font-black">AI Weather Assistant</h2>
            <p class="mt-2 text-sm text-cyan-50/70">Routes to `/api/assistant/query` when available, with safe fallback guidance for the MVP.</p>
            <div class="mt-5 h-[28rem] overflow-y-auto rounded-3xl bg-slate-950/60 p-4">
              <template x-for="message in chat" :key="message.id">
                <div class="mb-3 flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                  <p class="max-w-[85%] rounded-3xl px-4 py-3 text-sm leading-6" :class="message.role === 'user' ? 'bg-sunburst text-slate-950' : 'bg-white text-slate-950'" x-text="message.text"></p>
                </div>
              </template>
            </div>
            <form class="mt-4 flex gap-2" @submit.prevent="askAssistant()">
              <input x-model="assistantQuery" class="min-w-0 flex-1 rounded-2xl border border-white/10 bg-white px-4 py-3 text-slate-950 outline-none ring-cyan-300 focus:ring-4" placeholder="Will the sea be rough tomorrow morning?">
              <button type="submit" class="rounded-2xl bg-reef px-5 py-3 font-black text-slate-950">Ask</button>
            </form>
          </article>
          <aside class="rounded-[2rem] border border-white/10 bg-white p-5 text-slate-950">
            <h3 class="text-xl font-black">Try These</h3>
            <div class="mt-4 grid gap-2">
              <template x-for="prompt in samplePrompts" :key="prompt">
                <button type="button" class="rounded-2xl bg-slate-100 px-4 py-3 text-left text-sm font-bold hover:bg-cyan-100" @click="assistantQuery = prompt" x-text="prompt"></button>
              </template>
            </div>
          </aside>
        </div>
      </section>

      <section x-show="activeView === 'alerts'" x-transition>
        <div class="grid gap-5 lg:grid-cols-[.8fr_1.2fr]">
          <form class="rounded-[2rem] border border-white/10 bg-white p-5 text-slate-950" @submit.prevent="saveAlert()">
            <h2 class="text-2xl font-black">Create Alert</h2>
            <label class="mt-4 block text-sm font-black" for="alert-location">Location</label>
            <input id="alert-location" x-model="alertForm.location" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:ring-4 focus:ring-cyan-200" placeholder="St. George's, Grenada">
            <label class="mt-4 block text-sm font-black" for="alert-type">Alert Type</label>
            <select id="alert-type" x-model="alertForm.type" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:ring-4 focus:ring-cyan-200">
              <template x-for="type in alertTypes" :key="type">
                <option :value="type" x-text="type"></option>
              </template>
            </select>
            <label class="mt-4 block text-sm font-black" for="alert-threshold">Threshold</label>
            <input id="alert-threshold" x-model="alertForm.threshold" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:ring-4 focus:ring-cyan-200" placeholder="e.g. UV > 8, wind > 45 km/h">
            <label class="mt-4 block text-sm font-black" for="quiet-hours">Quiet Hours</label>
            <input id="quiet-hours" x-model="alertForm.quietHours" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:ring-4 focus:ring-cyan-200" placeholder="10:00 PM - 6:00 AM">
            <button class="mt-5 w-full rounded-2xl bg-storm px-5 py-3 font-black text-white" type="submit">Save Alert</button>
          </form>

          <article class="rounded-[2rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-2xl font-black">Active Subscriptions</h2>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-full bg-white/10 px-4 py-2 text-sm font-black hover:bg-white/20" @click="checkAlertsNow()">Refresh Alerts</button>
                <button type="button" class="rounded-full bg-white/10 px-4 py-2 text-sm font-black hover:bg-white/20" @click="requestPushPermission()">Enable Push</button>
              </div>
            </div>
            <div class="mt-5 rounded-3xl border border-cyan-200/20 bg-slate-950/55 p-4">
              <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-black">Recent In-App Alerts</h3>
                <button type="button" class="rounded-full bg-white/10 px-3 py-2 text-xs font-black hover:bg-white/20" @click="loadNotifications()">Reload</button>
              </div>
              <div class="mt-3 grid gap-2">
                <template x-if="notifications.length === 0">
                  <p class="rounded-2xl bg-white/10 p-4 text-sm text-cyan-50/70">No triggered alerts yet. The scheduler checks saved thresholds every 15 minutes.</p>
                </template>
                <template x-for="notification in notifications" :key="notification.id">
                  <div class="rounded-2xl bg-white p-4 text-slate-950" :class="notification.readAt ? 'opacity-70' : ''">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p class="text-xs font-black uppercase tracking-[.2em] text-lagoon" x-text="notification.type"></p>
                        <p class="mt-1 text-sm font-bold" x-text="notification.message"></p>
                        <p class="mt-1 text-xs font-semibold text-slate-500" x-text="notification.createdAt ? new Date(notification.createdAt).toLocaleString() : ''"></p>
                      </div>
                      <button x-show="!notification.readAt" type="button" class="rounded-full bg-slate-100 px-3 py-2 text-xs font-black text-slate-700" @click="markNotificationRead(notification.id)">Mark Read</button>
                    </div>
                  </div>
                </template>
              </div>
            </div>
            <div class="mt-5 grid gap-3">
              <template x-if="alerts.length === 0">
                <p class="rounded-3xl bg-white/10 p-5 text-cyan-50/70">No alerts configured yet.</p>
              </template>
              <template x-for="alert in alerts" :key="alert.id">
                <div class="rounded-3xl bg-white p-5 text-slate-950">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p class="text-sm font-black uppercase tracking-[.2em] text-lagoon" x-text="alert.type"></p>
                      <h3 class="mt-1 text-xl font-black" x-text="alert.location"></h3>
                      <p class="mt-1 text-sm font-semibold text-slate-500" x-text="alert.threshold"></p>
                      <p class="mt-1 text-xs font-semibold text-slate-400">Quiet hours: <span x-text="alert.quietHours || 'None'"></span></p>
                    </div>
                    <button type="button" class="rounded-full bg-rose-100 px-3 py-2 text-sm font-black text-rose-700" @click="deleteAlert(alert.id)">Delete</button>
                  </div>
                </div>
              </template>
            </div>
          </article>
        </div>
      </section>

      <section x-show="activeView === 'analytics'" x-transition>
        <div class="rounded-[2rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
              <h2 class="text-2xl font-black">Historical Weather Analytics</h2>
              <p class="mt-2 text-sm text-cyan-50/70">Prepared for Meteostat-backed data with CSV export.</p>
            </div>
            <form class="grid gap-2 sm:grid-cols-4" @submit.prevent="loadHistorical()">
              <input x-model="historyForm.location" class="rounded-2xl border border-white/10 bg-white px-4 py-3 text-slate-950" placeholder="Location">
              <input x-model="historyForm.start" type="date" class="rounded-2xl border border-white/10 bg-white px-4 py-3 text-slate-950">
              <input x-model="historyForm.end" type="date" class="rounded-2xl border border-white/10 bg-white px-4 py-3 text-slate-950">
              <button class="rounded-2xl bg-sunburst px-4 py-3 font-black text-slate-950" type="submit">Update</button>
            </form>
          </div>
          <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="rounded-3xl bg-white p-4"><canvas id="temperature-chart" height="220"></canvas></div>
            <div class="rounded-3xl bg-white p-4"><canvas id="rainfall-chart" height="220"></canvas></div>
            <div class="rounded-3xl bg-white p-4"><canvas id="wind-chart" height="220"></canvas></div>
            <div class="rounded-3xl bg-white p-4"><canvas id="humidity-chart" height="220"></canvas></div>
            <div class="rounded-3xl bg-white p-4 lg:col-span-2"><canvas id="pressure-chart" height="180"></canvas></div>
          </div>
          <button type="button" class="mt-5 rounded-2xl bg-white px-5 py-3 font-black text-slate-950" @click="exportCsv()">Export CSV</button>
        </div>
      </section>
    </main>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
  <script src="/assets/js/app.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</body>
</html>
