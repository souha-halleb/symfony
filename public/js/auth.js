/**
 * auth.js — Module WebAuthn / Passkeys
 * EventReservation App — ISSAT Sousse FIA3-GL
 */

'use strict';

/* ─── Utilitaires de conversion ArrayBuffer ↔ base64url ─── */

function bufferToBase64Url(buffer) {
    const bytes  = Array.from(new Uint8Array(buffer));
    const binary = bytes.map(b => String.fromCharCode(b)).join('');
    return btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

function base64UrlToBuffer(base64url) {
    let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    base64 += padding;
    const binary = atob(base64);
    return Uint8Array.from(binary, c => c.charCodeAt(0)).buffer;
}

/* ─── Enregistrement d'une Passkey ─── */

async function registerPasskey(email, displayName = '') {
    showStatus('Génération du challenge…', 'info');

    // 1. Récupérer les options du serveur
    const optRes = await fetch('/api/auth/register/options', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ email, displayName }),
    });

    if (!optRes.ok) {
        const err = await optRes.json();
        throw new Error(err.error || 'Échec de la récupération des options.');
    }

    const options = await optRes.json();
    showStatus('Veuillez utiliser votre authentificateur (Face ID, Touch ID, clé USB…)', 'info');

    // 2. Créer la credential via l'API du navigateur
    let credential;
    try {
        credential = await navigator.credentials.create({
            publicKey: {
                ...options,
                challenge: base64UrlToBuffer(options.challenge),
                user: {
                    ...options.user,
                    id: base64UrlToBuffer(options.user.id),
                },
                excludeCredentials: (options.excludeCredentials || []).map(c => ({
                    ...c,
                    id: base64UrlToBuffer(c.id),
                })),
            },
        });
    } catch (err) {
        throw new Error('Authentificateur annulé ou non supporté : ' + err.message);
    }

    showStatus('Vérification en cours…', 'info');

    // 3. Envoyer la réponse au serveur
    const verifyRes = await fetch('/api/auth/register/verify', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            email,
            credential: {
                id:    credential.id,
                rawId: bufferToBase64Url(credential.rawId),
                response: {
                    clientDataJSON:    bufferToBase64Url(credential.response.clientDataJSON),
                    attestationObject: bufferToBase64Url(credential.response.attestationObject),
                },
                type: credential.type,
                clientExtensionResults: credential.getClientExtensionResults(),
            },
        }),
    });

    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Échec de la vérification.');

    // 4. Stocker les tokens
    if (result.token) {
        localStorage.setItem('jwt_token', result.token);
        if (result.refresh_token) {
            localStorage.setItem('refresh_token', result.refresh_token);
        } else {
            localStorage.removeItem('refresh_token');
        }
    }

    showStatus('Passkey enregistrée avec succès !', 'success');
    return result;
}

/* ─── connexion avec  passkey ─── */

async function loginWithPasskey() {
    showStatus('Génération du challenge de connexion…', 'info');

    const optRes = await fetch('/api/auth/login/options', { method: 'POST' });

    if (!optRes.ok) {
        const err = await optRes.json();
        throw new Error(err.error || 'Échec de la récupération des options de connexion.');
    }

    const options = await optRes.json();
    showStatus('Veuillez vous authentifier avec votre Passkey…', 'info');

    let assertion;
    try {
        assertion = await navigator.credentials.get({
            publicKey: {
                ...options,
                challenge: base64UrlToBuffer(options.challenge),
                allowCredentials: (options.allowCredentials || []).map(c => ({
                    ...c,
                    id: base64UrlToBuffer(c.id),
                })),
            },
        });
    } catch (err) {
        throw new Error('Authentification annulée : ' + err.message);
    }

    showStatus('Vérification…', 'info');

    const verifyRes = await fetch('/api/auth/login/verify', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            credential: {
                id:    assertion.id,
                rawId: bufferToBase64Url(assertion.rawId),
                response: {
                    clientDataJSON:    bufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
                    signature:         bufferToBase64Url(assertion.response.signature),
                    userHandle: assertion.response.userHandle
                        ? bufferToBase64Url(assertion.response.userHandle)
                        : null,
                },
                type: assertion.type,
                clientExtensionResults: assertion.getClientExtensionResults(),
            },
        }),
    });

    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Échec de l\'authentification.');

    if (result.token) {
        localStorage.setItem('jwt_token', result.token);
        if (result.refresh_token) {
            localStorage.setItem('refresh_token', result.refresh_token);
        } else {
            localStorage.removeItem('refresh_token');
        }
    }

    showStatus('Connexion réussie !', 'success');
    return result;
}

/* ─── Connexion JWT ─── */

async function loginWithJwt() {
    const form     = document.getElementById('login-form');
    if (!form) return;
    const email    = form.querySelector('input[name="email"]').value.trim();
    const password = form.querySelector('input[name="password"]').value;

    const alertDiv = document.getElementById('login-alert');
    function showAlert(msg, type = 'danger') {
        if (!alertDiv) return;
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = msg;
        alertDiv.classList.remove('d-none');
    }

    if (!email || !password) {
        showAlert('Veuillez remplir tous les champs.', 'warning');
        return;
    }

    try {
        const res  = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();

        if (!res.ok) {
            showAlert(data.error || 'Email ou mot de passe incorrect.', 'danger');
            return;
        }

        if (data.token) {
            localStorage.setItem('jwt_token', data.token);
            if (data.refresh_token) {
                localStorage.setItem('refresh_token', data.refresh_token);
            } else {
                localStorage.removeItem('refresh_token');
            }
            window.location.href = '/';
        }
    } catch (e) {
        showAlert('Erreur serveur lors de la connexion.', 'danger');
    }
}

function showRegisterAlert(message, type = 'danger') {
    const alertDiv = document.getElementById('register-alert');
    if (!alertDiv) return;
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.classList.remove('d-none');
}

async function registerWithJwt() {
    const form     = document.getElementById('register-form');
    const email    = form.querySelector('input[name="email"]').value.trim();
    const password = form.querySelector('input[name="password"]').value;
    const confirm  = form.querySelector('input[name="confirm"]').value;

    if (!email || !password || !confirm) {
        showRegisterAlert('Veuillez remplir tous les champs.', 'warning');
        return;
    }

    if (password !== confirm) {
        showRegisterAlert('Les mots de passe ne correspondent pas.', 'warning');
        return;
    }

    if (password.length < 6) {
        showRegisterAlert('Le mot de passe doit contenir au moins 6 caractères.', 'warning');
        return;
    }

    try {
        const res  = await fetch('/api/auth/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();

        if (!res.ok) {
            showRegisterAlert(data.error || 'Erreur lors de l\'inscription.', 'danger');
            return;
        }

        if (data.token) {
            localStorage.setItem('jwt_token', data.token);
            if (data.refresh_token) {
                localStorage.setItem('refresh_token', data.refresh_token);
            } else {
                localStorage.removeItem('refresh_token');
            }
            window.location.href = '/';
            return;
        }

        showRegisterAlert('Inscription réussie.', 'success');
    } catch (e) {
        showRegisterAlert('Erreur serveur lors de l\'inscription.', 'danger');
    }
}


async function authFetch(url, options = {}) {
    const token   = localStorage.getItem('jwt_token');
    const headers = {
        ...(options.headers || {}),
        Authorization: token ? `Bearer ${token}` : '',
    };

    let res = await fetch(url, { ...options, headers });

    //  un refresh automatique
    if (res.status === 401) {
        const refreshed = await refreshToken();
        if (refreshed) {
            headers.Authorization = `Bearer ${localStorage.getItem('jwt_token')}`;
            res = await fetch(url, { ...options, headers });
        }
    }

    return res;
}

/* ─── Rafraîchissement du token JWT ─── */

async function refreshToken() {
    const refresh = localStorage.getItem('refresh_token');
    if (!refresh) return false;

    const res = await fetch('/api/token/refresh', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ refresh_token: refresh }),
    });

    if (!res.ok) {
        localStorage.removeItem('jwt_token');
        localStorage.removeItem('refresh_token');
        return false;
    }

    const data = await res.json();
    localStorage.setItem('jwt_token', data.token);
    if (data.refresh_token) {
        localStorage.setItem('refresh_token', data.refresh_token);
    }
    return true;
}


function logout() {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('refresh_token');
    showStatus('Déconnecté.', 'info');
}

window.registerWithJwt = registerWithJwt;
window.loginWithJwt = loginWithJwt;
window.logout = logout;


function isWebAuthnSupported() {
    return !!(window.PublicKeyCredential && navigator.credentials);
}


function showStatus(message, type = 'info') {
    const el = document.getElementById('passkey-status');
    if (!el) return;
    const colors = { info: '#0d6efd', success: '#198754', error: '#dc3545' };
    el.textContent = message;
    el.style.color = colors[type] || colors.info;
    el.style.display = 'block';
}


document.addEventListener('DOMContentLoaded', () => {
    if (!isWebAuthnSupported()) {
        const btns = document.querySelectorAll('.btn-passkey');
        btns.forEach(btn => {
            btn.disabled = true;
            btn.title = 'WebAuthn non supporté par ce navigateur.';
        });
        const warn = document.getElementById('webauthn-warning');
        if (warn) warn.style.display = 'block';
    }

    const btnRegister = document.getElementById('btn-register-passkey');
    if (btnRegister) {
        btnRegister.addEventListener('click', async () => {
            const emailInput = document.getElementById('passkey-email');
            if (!emailInput || !emailInput.value) {
                showStatus('Veuillez saisir votre email.', 'error');
                return;
            }
            btnRegister.disabled = true;
            try {
                const result = await registerPasskey(emailInput.value);
                showStatus(`Passkey créée ! Bienvenue ${result.user.email}`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } catch (err) {
                showStatus('Erreur : ' + err.message, 'error');
            } finally {
                btnRegister.disabled = false;
            }
        });
    }

    const btnLogin = document.getElementById('btn-login-passkey');
    if (btnLogin) {
        btnLogin.addEventListener('click', async () => {
            btnLogin.disabled = true;
            try {
                const result = await loginWithPasskey();
                showStatus(`Connecté en tant que ${result.user.email}`, 'success');
                setTimeout(() => window.location.href = '/', 1500);
            } catch (err) {
                showStatus('Erreur : ' + err.message, 'error');
            } finally {
                btnLogin.disabled = false;
            }
        });
    }
});