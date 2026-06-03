/* ════════════════════════════════════════════
   URGENCES SN — Configuration & utilitaires JS
   Utilisé par toutes les pages frontend
════════════════════════════════════════════ */

// URL de base de l'API Laravel (backend)
const API_URL = 'http://localhost:8000/api';

// Token CSRF stocké après connexion
function getToken() {
    return localStorage.getItem('token') || '';
}

// En-têtes communs pour les requêtes API
function apiHeaders() {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + getToken()
    };
}

// Appel API générique
async function apiCall(endpoint, method = 'GET', body = null) {
    const options = { method, headers: apiHeaders() };
    if (body) options.body = JSON.stringify(body);
    const res = await fetch(API_URL + endpoint, options);
    return res.json();
}

// Rediriger si non connecté
function requireAuth(roleAttendu = null) {
    const user = JSON.parse(localStorage.getItem('user') || 'null');
    if (!user || !getToken()) {
        window.location.href = '/frontend/pages/login.html';
        return null;
    }
    if (roleAttendu && user.role !== roleAttendu && user.role !== 'admin') {
        window.location.href = '/frontend/pages/login.html';
        return null;
    }
    return user;
}

// Déconnexion
function deconnecter() {
    apiCall('/logout', 'POST').finally(() => {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '/frontend/pages/login.html';
    });
}

// Labels lisibles des statuts
const STATUTS_LABELS = {
    en_attente:     'En attente',
    pris_en_charge: 'Pris en charge',
    en_route:       'En route',
    sur_place:      'Sur place',
    termine:        'Terminé'
};

// Statut suivant dans le flux
const STATUT_SUIVANT = {
    en_attente:     'pris_en_charge',
    pris_en_charge: 'en_route',
    en_route:       'sur_place',
    sur_place:      'termine'
};

// Emojis par type d'urgence
const EMOJIS = { incendie: '🔥', accident: '🚗', medical: '🏥', autre: '⚠️' };

// Son d'alerte simulé (Web Audio API)
function jouerSonAlerte() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [0, 0.3, 0.6].forEach(delay => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.frequency.value = 880; osc.type = 'sine';
            gain.gain.setValueAtTime(0.3, ctx.currentTime + delay);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.25);
            osc.start(ctx.currentTime + delay);
            osc.stop(ctx.currentTime + delay + 0.25);
        });
    } catch(e) {}
}

// Horloge temps réel
function demarrerHorloge(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const maj = () => el.textContent = new Date().toLocaleTimeString('fr-FR');
    maj();
    setInterval(maj, 1000);
}

// Afficher une notification popup
function afficherNotif(titre, texte, popupId = 'notif-popup') {
    const popup = document.getElementById(popupId);
    if (!popup) return;
    const t = popup.querySelector('.notif-titre');
    const s = popup.querySelector('.notif-texte');
    if (t) t.textContent = titre;
    if (s) s.textContent = texte;
    popup.style.display = 'block';
    setTimeout(() => popup.style.display = 'none', 6000);
}

// Construire la sidebar avec les infos utilisateur
function construireSidebar(liens) {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    const initiale = (user.prenom || 'U').charAt(0).toUpperCase();

    document.getElementById('user-nom').textContent  = (user.prenom || '') + ' ' + (user.nom || '');
    document.getElementById('user-role').textContent = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : '';
    document.getElementById('user-avatar').textContent = initiale;

    const nav = document.getElementById('sidebar-nav');
    if (nav) {
        nav.innerHTML = liens.map(l => `
            <a href="${l.href}" class="nav-link-dash ${l.actif ? 'active' : ''}">
                <i class="bi ${l.icon}"></i> ${l.label}
            </a>
        `).join('');
    }
}
