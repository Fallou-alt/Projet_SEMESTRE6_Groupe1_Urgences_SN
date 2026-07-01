const API_URL = 'http://localhost:8000/api';

function getToken() {
    return localStorage.getItem('token') || '';
}

function getUtilisateur() {
    return JSON.parse(localStorage.getItem('utilisateur') || 'null');
}

function entetes() {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer ' + getToken()
    };
}

async function apiCall(endpoint, methode = 'GET', corps = null) {
    const options = { method: methode, headers: entetes() };
    if (corps) options.body = JSON.stringify(corps);
    const reponse = await fetch(API_URL + endpoint, options);
    return reponse.json();
}

function verifierAuth(roleAttendu = null) {
    const utilisateur = getUtilisateur();
    if (!utilisateur || !getToken()) {
        window.location.href = 'login.html';
        return null;
    }
    if (roleAttendu && utilisateur.role !== roleAttendu && utilisateur.role !== 'ADMIN') {
        window.location.href = 'login.html';
        return null;
    }
    return utilisateur;
}

function deconnecter() {
    apiCall('/deconnexion', 'POST').finally(() => {
        localStorage.removeItem('token');
        localStorage.removeItem('utilisateur');
        window.location.href = 'login.html';
    });
}

const STATUTS_LABELS = {
    EN_ATTENTE : 'En attente',
    AFFECTE    : 'Affecté',
    EN_ROUTE   : 'En route',
    SUR_PLACE  : 'Sur place',
    TERMINE    : 'Terminé',
    ANNULE     : 'Annulé'
};

const STATUT_SUIVANT = {
    EN_ATTENTE : 'AFFECTE',
    AFFECTE    : 'EN_ROUTE',
    EN_ROUTE   : 'SUR_PLACE',
    SUR_PLACE  : 'TERMINE',
    TERMINE    : null,
    ANNULE     : null
};

// Alias pour compatibilité dashboards
function requireAuth(role) {
    const utilisateur = getUtilisateur();
    if (!utilisateur || !getToken()) {
        window.location.href = 'login.html';
        return null;
    }
    return utilisateur;
}

const EMOJIS = {
    incendie : '🔥',
    accident : '🚗',
    medical  : '🏥',
    autre    : '⚠️'
};

function jouerSonAlerte() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [0, 0.3, 0.6].forEach(delai => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.3, ctx.currentTime + delai);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delai + 0.25);
            osc.start(ctx.currentTime + delai);
            osc.stop(ctx.currentTime + delai + 0.25);
        });
    } catch (e) {}
}

function demarrerHorloge(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const maj = () => el.textContent = new Date().toLocaleTimeString('fr-FR');
    maj();
    setInterval(maj, 1000);
}

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
