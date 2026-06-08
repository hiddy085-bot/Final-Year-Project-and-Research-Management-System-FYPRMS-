// Global variable to store current user role
let currentUserRole = null;

// Dynamic global background images (changes for every page load)
(function () {
    const images = [
        'images/education_1.jpg',
        'images/education_2.jpg',
        'images/education_3.jpg',
        'images/education_4.jpg',
        'images/education_5.jpg',
        'images/education_6.jpg',
        // Also support jfif variants if jpg not present
        'images/education_1.jfif',
        'images/education_2.jfif',
        'images/education_3.jfif',
        'images/education_4.jfif',
        'images/education_5.jfif',
        'images/education_6.jfif'
    ];

    function pickImage() {
        return images[Math.floor(Math.random() * images.length)];
    }

    function setBg(img) {
        // used in style.css: #container::before { background-image: var(--bg-img, none); }
        document.documentElement.style.setProperty('--bg-img', `url('${img}')`);
    }

    // On every full page load, choose a new image
    setBg(pickImage());
})();
// In-app navigation history to support proper Back button
window.appHistory = [];
window.currentPage = 'home';

// Initialize on page load
window.onload = function() {
    checkSession();
    loadStats();
};

function loadPage(page, params = {}, options = {}) {
    const url = new URL('system.php', window.location.href);
    // Push current page to in-app history unless instructed otherwise
    if (!options.doNotPush && window.currentPage && window.currentPage !== page) {
        window.appHistory.push(window.currentPage);
    }
    url.searchParams.append('page', page);
    for (let key in params) {
        url.searchParams.append(key, params[key]);
    }

    fetch(url, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('dynamic-content').innerHTML = html;
        // Add back button for inner pages using in-app history
        const dynamic = document.getElementById('dynamic-content');
        // remove existing back button if any
        const existing = dynamic.querySelector('.back-btn');
        if (existing) existing.remove();
        if (page !== 'home') {
            const back = document.createElement('button');
            back.className = 'back-btn';
            back.innerHTML = '<i class="fas fa-arrow-left"></i> Back';
            back.onclick = function() {
                if (window.appHistory && window.appHistory.length > 0) {
                    const prev = window.appHistory.pop();
                    loadPage(prev, {}, {doNotPush: true});
                } else {
                    loadPage('home', {}, {doNotPush: true});
                }
            };
            // If admin viewing admin pages, style the back button for dashboard
            if (currentUserRole === 'administrator' && (page === 'dashboard' || page.startsWith('manage_'))) {
                back.classList.add('admin-back');
            }
            dynamic.insertBefore(back, dynamic.firstChild);
        }

        const hero = document.getElementById('hero');
        const stats = document.getElementById('stats-container');
        if (page === 'home') {
            if (hero) hero.style.display = 'block';
            if (stats) stats.style.display = 'flex';
            loadStats();
        } else {
            if (hero) hero.style.display = 'none';
            if (stats) stats.style.display = 'none';
        }

        attachFormHandlers();
        // update current page
        window.currentPage = page;
    })
    .catch(error => {
        console.error('Error loading page:', error);
        document.getElementById('dynamic-content').innerHTML = '<div class="error">Error loading page. Please try again.</div>';
    });
}

function attachFormHandlers() {
    const regForm = document.getElementById('register-form');
    if (regForm) {
        regForm.onsubmit = function(e) {
            e.preventDefault();
            submitForm(regForm);
        };
    }

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.onsubmit = function(e) {
            e.preventDefault();
            submitForm(loginForm);
        };
    }

    const uploadForm = document.getElementById('upload-form');
    if (uploadForm) {
        uploadForm.onsubmit = function(e) {
            e.preventDefault();
            submitFormMultipart(uploadForm);
        };
    }

    const searchForm = document.getElementById('search-form');
    if (searchForm) {
        searchForm.onsubmit = function(e) {
            e.preventDefault();
            const keyword = document.getElementById('search-keyword') ? document.getElementById('search-keyword').value : '';
            const type = document.getElementById('search-type') ? document.getElementById('search-type').value : 'projects';
            performSearch(type, keyword);
        };
    }

    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.onclick = function() {
            const confirmMsg = this.getAttribute('data-confirm');
            if (confirmMsg && !confirm(confirmMsg)) return;
            const action = this.getAttribute('data-action');
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            if (action && id) {
                submitAction(action, id, type);
            }
        };
    });

    // Add user form (admin)
    const addUserForm = document.getElementById('add-user-form');
    if (addUserForm) {
        addUserForm.onsubmit = function(e) {
            e.preventDefault();
            submitForm(addUserForm);
        };
    }

    const addUniForm = document.getElementById('add-uni-form');
    if (addUniForm) {
        addUniForm.onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(addUniForm);
            formData.append('action', 'add_university');
            fetch('system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showMessage(data.message, data.success ? 'success' : 'error');
                if (data.success) loadPage('manage_universities');
            });
        };
    }
}

function submitForm(form) {
    const formData = new FormData(form);

    fetch('system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            if (data.redirect) {
                // Update nav state immediately after login
                checkSession();
                loadPage(data.redirect);
            } else if (data.reload) {
                location.reload();
            }
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
    });
}

function submitFormMultipart(form) {
    const formData = new FormData(form);
    fetch('system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            if (data.redirect) {
                loadPage(data.redirect);
            }
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
    });
}

function submitAction(action, id, type) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('id', id);
    if (type) formData.append('type', type);

    fetch('system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showMessage(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            if (data.redirect) {
                loadPage(data.redirect);
            } else if (data.reload) {
                location.reload();
            } else {
                // default behavior: reload current manager page
                location.reload();
            }
        }
    });
}

function showMessage(msg, type) {
    const msgDiv = document.getElementById('message-area');
    if (msgDiv) {
        msgDiv.innerHTML = `<div class="message ${type}">${msg}</div>`;
        setTimeout(() => {
            if (msgDiv) msgDiv.innerHTML = '';
        }, 5000);
    } else {
        alert(msg);
    }
}

function checkSession() {
    const navDashboard = document.getElementById('nav-dashboard');
    const navLogout = document.getElementById('nav-logout');
    const navRegister = document.getElementById('nav-register');
    const navLogin = document.getElementById('nav-login');

    // First hide all
    if (navDashboard) navDashboard.style.display = 'none';
    if (navLogout) navLogout.style.display = 'none';
    if (navRegister) navRegister.style.display = 'none';
    if (navLogin) navLogin.style.display = 'none';

    fetch('system.php?check_session=1&t=' + Date.now(), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        },
        cache: 'no-store'
    })
    .then(response => response.json())
    .then(data => {
        console.log('Session check response:', data);
        if (data.logged_in === true && data.logged_in !== false) {
            currentUserRole = data.role;
            if (navDashboard) navDashboard.style.display = 'block';
            if (navLogout) navLogout.style.display = 'block';
        } else {
            currentUserRole = null;
            if (navRegister) navRegister.style.display = 'block';
            if (navLogin) navLogin.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Session check error:', error);
        if (navRegister) navRegister.style.display = 'block';
        if (navLogin) navLogin.style.display = 'block';
    });
}

function loadStats() {
    fetch('system.php?get_stats=1&t=' + Date.now(), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('stat-projects').innerText = data.projects || 0;
        document.getElementById('stat-research').innerText = data.research || 0;
        document.getElementById('stat-universities').innerText = data.universities || 0;
        document.getElementById('stat-users').innerText = data.users || 0;
    })
    .catch(error => {
        console.error('Stats error:', error);
    });
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('system.php?logout=1&t=' + Date.now(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache, no-store'
            },
            cache: 'no-store'
        })
        .then(() => {
            localStorage.clear();
            sessionStorage.clear();
            document.cookie.split(";").forEach(function(c) {
                document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
            });
            currentUserRole = null;
            window.location.href = 'index.html?_=' + Date.now();
        })
        .catch(error => {
            console.error('Logout error:', error);
            window.location.href = 'index.html?_=' + Date.now();
        });
    }
}

function performSearch(type, keyword) {
    window.currentSearchType = type;
    if (!keyword) {
        keyword = document.getElementById('search-keyword') ? document.getElementById('search-keyword').value : '';
    }

    if (!keyword.trim()) {
        showMessage('Please enter a search keyword', 'error');
        return;
    }

    fetch(`system.php?search=1&type=${type}&keyword=${encodeURIComponent(keyword)}&t=${Date.now()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => response.text())
    .then(html => {
        const resultsDiv = document.getElementById('search-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = html;
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        showMessage('Search failed. Please try again.', 'error');
    });
}

function deleteUni(id) {
    if (confirm('Are you sure you want to delete this university?')) {
        const formData = new FormData();
        formData.append('action', 'delete_university');
        formData.append('id', id);

        fetch('system.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showMessage(data.message, data.success ? 'success' : 'error');
            if (data.success) loadPage('manage_universities');
        });
    }
}

// Force clear session function (for debugging)
window.forceLogout = function() {
    document.cookie.split(";").forEach(function(c) {
        document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
    });
    localStorage.clear();
    sessionStorage.clear();

    fetch('system.php?logout=1&t=' + Date.now())
    .then(() => {
        window.location.href = 'index.html?_=' + Date.now();
    });
};

// Manual session reset function
window.resetSession = function() {
    fetch('system.php?action=reset_session&t=' + Date.now(), {
        method: 'POST',
        headers: { 'Cache-Control': 'no-cache' }
    })
    .then(() => {
        window.location.href = 'index.html?_=' + Date.now();
    });
};

window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        checkSession();
    }
});

