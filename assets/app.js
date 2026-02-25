/**
 * ProKB - Frontend Application
 */

// API Configuration
const API_URL = '/api/index.php';

// Application State
const App = {
    user: null,
    token: localStorage.getItem('token'),
    projects: [],
    employees: [],
    tasks: [],
    currentProject: null,
    designSections: [],
    standardInvestigations: [],
};

// ============================================
// API FUNCTIONS
// ============================================

async function api(action, data = {}, method = 'POST') {
    const url = `${API_URL}?action=${action}`;
    
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    if (App.token) {
        options.headers['Authorization'] = `Bearer ${App.token}`;
    }
    
    if (method !== 'GET' && Object.keys(data).length > 0) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'Ошибка сервера');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// ============================================
// AUTH
// ============================================

async function login(email, password) {
    const result = await api('auth/login', { email, password });
    
    if (result.success) {
        App.user = result.data.user;
        App.token = result.data.token;
        localStorage.setItem('token', App.token);
        showApp();
        showToast('Добро пожаловать!', 'success');
    }
    
    return result;
}

async function logout() {
    try {
        await api('auth/logout');
    } catch (e) {}
    
    App.user = null;
    App.token = null;
    localStorage.removeItem('token');
    showLogin();
}

async function checkAuth() {
    if (!App.token) {
        showLogin();
        return;
    }
    
    try {
        const result = await api('auth/me');
        if (result.success) {
            App.user = result.data;
            showApp();
        } else {
            showLogin();
        }
    } catch (e) {
        showLogin();
    }
}

// ============================================
// UI FUNCTIONS
// ============================================

function showLogin() {
    document.getElementById('login-page').classList.remove('hidden');
    document.getElementById('app').classList.add('hidden');
}

function showApp() {
    document.getElementById('login-page').classList.add('hidden');
    document.getElementById('app').classList.remove('hidden');
    
    // Update user info
    document.getElementById('user-name').textContent = App.user.name;
    document.getElementById('user-role').textContent = getRoleLabel(App.user.role);
    document.getElementById('user-avatar').textContent = getInitials(App.user.name);
    document.getElementById('welcome-text').textContent = `Добро пожаловать, ${App.user.name.split(' ')[0]}!`;
    
    // Show/hide navigation items
    const isManagement = App.user.role === 'director' || App.user.role === 'gip';
    const isDirector = App.user.role === 'director';
    
    document.getElementById('employees-nav').classList.toggle('hidden', !isDirector);
    document.getElementById('admin-nav').classList.toggle('hidden', !isDirector);
    document.getElementById('new-project-btn').classList.toggle('hidden', !isManagement);
    
    // Load data
    loadProjects();
    loadEmployees();
    loadDesignSections();
    loadStandardInvestigations();
}

function getRoleLabel(role) {
    const labels = {
        director: 'Директор',
        gip: 'ГИП',
        employee: 'Сотрудник'
    };
    return labels[role] || role;
}

function getInitials(name) {
    const parts = name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

function showPage(pageId) {
    document.querySelectorAll('.page').forEach(p => p.classList.add('hidden'));
    document.getElementById(`${pageId}-page`).classList.remove('hidden');
    
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.classList.toggle('active', item.dataset.page === pageId);
    });
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}

function showModal(modalId) {
    document.getElementById('modal-overlay').classList.remove('hidden');
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModals() {
    document.getElementById('modal-overlay').classList.add('hidden');
    document.querySelectorAll('.modal').forEach(m => m.classList.add('hidden'));
}

// ============================================
// PROJECTS
// ============================================

async function loadProjects() {
    try {
        const result = await api('projects');
        App.projects = result.data || [];
        renderProjects();
        updateStats();
    } catch (e) {
        showToast('Ошибка загрузки проектов', 'error');
    }
}

function renderProjects() {
    const container = document.getElementById('projects-list');
    
    if (App.projects.length === 0) {
        container.innerHTML = '<p class="empty-message">Нет проектов</p>';
        return;
    }
    
    container.innerHTML = App.projects.map(project => `
        <div class="project-card" onclick="openProject(${project.id})">
            <div class="project-card-header">
                <h3>${escapeHtml(project.name)}</h3>
                ${project.code ? `<span class="project-code">${escapeHtml(project.code)}</span>` : ''}
            </div>
            ${project.address ? `<p class="project-address">${escapeHtml(project.address)}</p>` : ''}
            <div class="project-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${project.progress || 0}%"></div>
                </div>
            </div>
            <p class="project-status">${project.progress || 0}% завершено</p>
        </div>
    `).join('');
}

async function openProject(projectId) {
    try {
        const result = await api(`projects/${projectId}`);
        App.currentProject = result.data;
        renderProjectPage();
        showPage('project');
    } catch (e) {
        showToast('Ошибка загрузки проекта', 'error');
    }
}

function renderProjectPage() {
    const project = App.currentProject;
    
    document.getElementById('project-title').textContent = project.name;
    
    // Render sections
    const sectionsContainer = document.getElementById('project-sections');
    sectionsContainer.innerHTML = (project.sections || []).map(section => `
        <div class="section-card" onclick="openSection(${section.id})">
            <div class="section-code">${escapeHtml(section.code)}</div>
            <span class="section-status status-${section.status}">${getStatusLabel(section.status)}</span>
        </div>
    `).join('') || '<p>Нет разделов</p>';
    
    // Render investigations
    renderInvestigations();
}

function renderInvestigations() {
    const container = document.getElementById('investigations-list');
    const investigations = App.currentProject.investigations || [];
    
    if (investigations.length === 0) {
        container.innerHTML = '<p class="empty-message">Нет изысканий</p>';
        return;
    }
    
    container.innerHTML = `<div class="investigations-grid">${investigations.map(inv => `
        <div class="investigation-card status-${inv.status}">
            <h4>${escapeHtml(inv.name)}</h4>
            <span class="section-status status-${inv.status}" style="margin-top: 10px;">${getStatusLabel(inv.status)}</span>
        </div>
    `).join('')}</div>`;
}

function getStatusLabel(status) {
    const labels = {
        not_started: 'Не начато',
        in_progress: 'В работе',
        completed: 'Завершено',
        revision: 'На доработке'
    };
    return labels[status] || status;
}

// ============================================
// INVESTIGATIONS
// ============================================

async function loadStandardInvestigations() {
    try {
        const result = await api('investigations/standard');
        App.standardInvestigations = result.data || [];
    } catch (e) {
        console.error('Error loading standard investigations', e);
    }
}

function openAddInvestigationModal() {
    const select = document.getElementById('inv-standard-id');
    select.innerHTML = '<option value="">-- Выберите из списка --</option>' +
        App.standardInvestigations.map(inv => `<option value="${inv.id}">${escapeHtml(inv.name)}</option>`).join('');
    
    document.getElementById('inv-custom-name').value = '';
    showModal('add-investigation-modal');
}

async function addInvestigation(formData) {
    try {
        const data = {
            standard_id: formData.get('standard_id') || null,
            name: formData.get('custom_name') || null
        };
        
        const result = await api(`projects/${App.currentProject.id}/investigations`, data);
        
        if (result.success) {
            closeModals();
            showToast('Изыскание добавлено', 'success');
            openProject(App.currentProject.id);
        }
    } catch (e) {
        showToast('Ошибка добавления: ' + e.message, 'error');
    }
}

// ============================================
// EMPLOYEES
// ============================================

async function loadEmployees() {
    try {
        const result = await api('employees');
        App.employees = result.data || [];
    } catch (e) {
        console.error('Error loading employees', e);
    }
}

// ============================================
// DESIGN SECTIONS
// ============================================

async function loadDesignSections() {
    try {
        const result = await api('admin/sections');
        App.designSections = result.data || [];
        updateSectionsCheckboxes();
    } catch (e) {
        console.error('Error loading design sections', e);
    }
}

function updateSectionsCheckboxes() {
    const container = document.getElementById('project-sections-checkboxes');
    if (!container) return;
    
    container.innerHTML = App.designSections
        .filter(s => s.is_active)
        .map(s => `
            <label class="checkbox-label">
                <input type="checkbox" name="sections[]" value="${escapeHtml(s.code)}">
                <span>${escapeHtml(s.code)}</span>
            </label>
        `).join('');
}

// ============================================
// STATS
// ============================================

function updateStats() {
    document.getElementById('stat-projects').textContent = App.projects.length;
    document.getElementById('stat-in-progress').textContent = App.projects.filter(p => p.status === 'in_work').length;
    document.getElementById('stat-completed').textContent = App.projects.filter(p => p.status === 'completed').length;
    document.getElementById('stat-employees').textContent = App.employees.length;
}

// ============================================
// CREATE PROJECT
// ============================================

async function createProject(formData) {
    const sections = [];
    document.querySelectorAll('input[name="sections[]"]:checked').forEach(cb => {
        sections.push(cb.value);
    });
    
    try {
        const result = await api('projects', {
            name: formData.get('name'),
            code: formData.get('code'),
            address: formData.get('address'),
            type: formData.get('type'),
            deadline: formData.get('deadline'),
            description: formData.get('description'),
            sections: sections
        });
        
        if (result.success) {
            closeModals();
            showToast('Проект создан', 'success');
            loadProjects();
        }
    } catch (e) {
        showToast('Ошибка создания: ' + e.message, 'error');
    }
}

// ============================================
// UTILITIES
// ============================================

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// EVENT LISTENERS
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    // Login form
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        
        try {
            await login(email, password);
        } catch (error) {
            document.getElementById('login-error').textContent = error.message;
            document.getElementById('login-error').classList.remove('hidden');
        }
    });
    
    // Logout
    document.getElementById('logout-btn').addEventListener('click', logout);
    
    // Navigation
    document.querySelectorAll('.sidebar-item[data-page]').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            showPage(item.dataset.page);
        });
    });
    
    // New project button
    document.getElementById('new-project-btn').addEventListener('click', () => {
        showModal('new-project-modal');
    });
    
    // New project form
    document.getElementById('new-project-form').addEventListener('submit', (e) => {
        e.preventDefault();
        createProject(new FormData(e.target));
    });
    
    // Add investigation button
    document.getElementById('add-investigation-btn').addEventListener('click', openAddInvestigationModal);
    
    // Add investigation form
    document.getElementById('add-investigation-form').addEventListener('submit', (e) => {
        e.preventDefault();
        addInvestigation(new FormData(e.target));
    });
    
    // Back buttons
    document.getElementById('back-to-projects').addEventListener('click', () => showPage('projects'));
    document.getElementById('back-to-project').addEventListener('click', () => showPage('project'));
    
    // Project status tabs
    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            // Filter projects by status
        });
    });
    
    // Project tabs
    document.querySelectorAll('.project-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.project-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById(`${tab.dataset.tab}-tab`).classList.remove('hidden');
        });
    });
    
    // Modal close
    document.getElementById('modal-overlay').addEventListener('click', closeModals);
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', closeModals);
    });
    
    // Check authentication
    checkAuth();
});
