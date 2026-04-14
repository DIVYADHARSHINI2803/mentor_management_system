/**
 * Digital Mentor Book Management System
 * Main JavaScript File
 * Author: System Developer
 * Version: 1.0
 */

// ========================
// GLOBAL VARIABLES
// ========================
let currentUser = null;
let notificationTimeout = null;
let autoSaveTimer = null;
let formDirty = false;

// ========================
// DOM READY EVENT
// ========================
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    loadUserData();
    checkNotifications();
    setupAutoSave();
    initializeTooltips();
    setupFormValidation();
    initializeCharts();
});

// ========================
// INITIALIZATION FUNCTIONS
// ========================
function initializeApp() {
    console.log('Digital Mentor Book System Initialized');
    
    // Add loading class to body
    document.body.classList.add('loaded');
    
    // Setup CSRF protection
    setupCSRFProtection();
    
    // Initialize sidebar toggle for mobile
    initMobileSidebar();
    
    // Setup logout timer for idle users
    setupIdleTimer();
    
    // Load theme preference
    loadThemePreference();
}

function setupEventListeners() {
    // Form submissions
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
    
    // Input validation on blur
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('blur', validateField);
        field.addEventListener('input', markFormDirty);
    });
    
    // Delete confirmation buttons
    document.querySelectorAll('.delete-btn, .btn-delete').forEach(btn => {
        btn.addEventListener('click', confirmDelete);
    });
    
    // Print buttons
    document.querySelectorAll('.print-btn').forEach(btn => {
        btn.addEventListener('click', printPage);
    });
    
    // Refresh buttons
    document.querySelectorAll('.refresh-btn').forEach(btn => {
        btn.addEventListener('click', refreshData);
    });
    
    // Search inputs with debounce
    document.querySelectorAll('.search-input').forEach(input => {
        input.addEventListener('input', debounce(handleSearch, 500));
    });
}

// ========================
// SECURITY FUNCTIONS
// ========================
function setupCSRFProtection() {
    // Generate CSRF token if not exists
    if (!getCookie('csrf_token')) {
        const token = generateCSRFToken();
        setCookie('csrf_token', token, 1);
    }
    
    // Add CSRF token to all forms
    document.querySelectorAll('form').forEach(form => {
        if (!form.querySelector('input[name="csrf_token"]')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = getCookie('csrf_token');
            form.appendChild(input);
        }
    });
}

function generateCSRFToken() {
    return Math.random().toString(36).substring(2) + Date.now().toString(36);
}

// ========================
// USER INTERFACE FUNCTIONS
// ========================
function showNotification(message, type = 'success') {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Clear existing timeout
    if (notificationTimeout) {
        clearTimeout(notificationTimeout);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">&times;</button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        animation: slideInRight 0.3s ease;
        min-width: 300px;
        max-width: 450px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
    `;
    
    document.body.appendChild(notification);
    
    // Add close button functionality
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.remove();
    });
    
    // Auto remove after 5 seconds
    notificationTimeout = setTimeout(() => {
        if (notification) notification.remove();
    }, 5000);
}

function getNotificationIcon(type) {
    switch(type) {
        case 'success': return 'fa-check-circle';
        case 'error': return 'fa-exclamation-circle';
        case 'warning': return 'fa-exclamation-triangle';
        case 'info': return 'fa-info-circle';
        default: return 'fa-bell';
    }
}

function getNotificationColor(type) {
    switch(type) {
        case 'success': return '#28a745';
        case 'error': return '#dc3545';
        case 'warning': return '#ffc107';
        case 'info': return '#17a2b8';
        default: return '#667eea';
    }
}

function showLoading(selector) {
    const element = document.querySelector(selector);
    if (element) {
        const originalHTML = element.innerHTML;
        element.setAttribute('data-original', originalHTML);
        element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        element.disabled = true;
    }
}

function hideLoading(selector) {
    const element = document.querySelector(selector);
    if (element && element.hasAttribute('data-original')) {
        element.innerHTML = element.getAttribute('data-original');
        element.removeAttribute('data-original');
        element.disabled = false;
    }
}

// ========================
// FORM HANDLING FUNCTIONS
// ========================
function handleFormSubmit(e) {
    const form = e.target;
    
    // Validate form before submission
    if (!validateForm(form)) {
        e.preventDefault();
        return false;
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        showLoading(submitBtn);
    }
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            showFieldError(input, 'This field is required');
            isValid = false;
        } else {
            clearFieldError(input);
        }
        
        // Additional validation based on input type
        if (input.type === 'email' && input.value) {
            if (!isValidEmail(input.value)) {
                showFieldError(input, 'Please enter a valid email address');
                isValid = false;
            }
        }
        
        if (input.type === 'password' && input.value && input.value.length < 6) {
            showFieldError(input, 'Password must be at least 6 characters');
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(e) {
    const field = e.target;
    
    if (field.hasAttribute('required') && !field.value.trim()) {
        showFieldError(field, 'This field is required');
        return false;
    }
    
    if (field.type === 'email' && field.value) {
        if (!isValidEmail(field.value)) {
            showFieldError(field, 'Please enter a valid email address');
            return false;
        }
    }
    
    if (field.type === 'password' && field.value && field.value.length < 6) {
        showFieldError(field, 'Password must be at least 6 characters');
        return false;
    }
    
    clearFieldError(field);
    return true;
}

function showFieldError(field, message) {
    field.classList.add('error');
    
    let errorDiv = field.parentElement.querySelector('.error-message');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.cssText = 'color: #dc3545; font-size: 0.85rem; margin-top: 5px;';
        field.parentElement.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
}

function clearFieldError(field) {
    field.classList.remove('error');
    const errorDiv = field.parentElement.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.remove();
    }
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function markFormDirty() {
    formDirty = true;
}

// ========================
// DATA HANDLING FUNCTIONS
// ========================
function loadUserData() {
    // Get user data from session storage or fetch from server
    const storedUser = sessionStorage.getItem('user_data');
    if (storedUser) {
        currentUser = JSON.parse(storedUser);
    }
}

function fetchData(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-Token', getCookie('csrf_token'));
        
        if (method === 'POST' && data) {
            xhr.setRequestHeader('Content-Type', 'application/json');
        }
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch(e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error(`Request failed with status ${xhr.status}`));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error occurred'));
        };
        
        if (method === 'POST' && data) {
            xhr.send(JSON.stringify(data));
        } else {
            xhr.send();
        }
    });
}

function refreshData() {
    showNotification('Refreshing data...', 'info');
    setTimeout(() => {
        location.reload();
    }, 500);
}

// ========================
// SEARCH AND FILTER FUNCTIONS
// ========================
function handleSearch(e) {
    const searchTerm = e.target.value.toLowerCase();
    const table = document.querySelector('.data-table, .users-table, .report-table');
    
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show no results message
    let noResults = table.parentElement.querySelector('.no-results');
    if (visibleCount === 0) {
        if (!noResults) {
            noResults = document.createElement('div');
            noResults.className = 'no-results';
            noResults.style.cssText = 'text-align: center; padding: 50px; color: #999;';
            noResults.innerHTML = '<i class="fas fa-search" style="font-size: 3rem;"></i><p>No results found</p>';
            table.parentElement.appendChild(noResults);
        }
        noResults.style.display = 'block';
    } else if (noResults) {
        noResults.style.display = 'none';
    }
}

// ========================
// TABLE FUNCTIONS
// ========================
function sortTable(table, column, type = 'text') {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const sortedRows = rows.sort((a, b) => {
        let aVal = a.cells[column].textContent;
        let bVal = b.cells[column].textContent;
        
        if (type === 'number') {
            aVal = parseFloat(aVal) || 0;
            bVal = parseFloat(bVal) || 0;
        } else if (type === 'date') {
            aVal = new Date(aVal);
            bVal = new Date(bVal);
        }
        
        if (aVal < bVal) return -1;
        if (aVal > bVal) return 1;
        return 0;
    });
    
    // Reorder rows
    sortedRows.forEach(row => tbody.appendChild(row));
    
    // Toggle sort indicator
    const headers = table.querySelectorAll('th');
    headers.forEach(header => {
        header.classList.remove('sort-asc', 'sort-desc');
    });
    headers[column].classList.add('sort-asc');
}

// ========================
// MODAL FUNCTIONS
// ========================
function openModal(modalId, data = null) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        
        if (data) {
            populateModalForm(modal, data);
        }
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function populateModalForm(modal, data) {
    const form = modal.querySelector('form');
    if (form && data) {
        Object.keys(data).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = data[key];
            }
        });
    }
}

// ========================
// CONFIRMATION DIALOGS
// ========================
function confirmDelete(e) {
    e.preventDefault();
    
    if (confirm('Are you sure you want to delete this item? This action cannot be undone!')) {
        const href = e.currentTarget.getAttribute('href');
        if (href) {
            window.location.href = href;
        }
    }
}

function showConfirmDialog(message, onConfirm, onCancel) {
    const dialog = document.createElement('div');
    dialog.className = 'confirm-dialog';
    dialog.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        z-index: 10000;
        min-width: 300px;
        text-align: center;
    `;
    
    dialog.innerHTML = `
        <i class="fas fa-question-circle" style="font-size: 3rem; color: #ffc107; margin-bottom: 15px;"></i>
        <p style="margin-bottom: 20px;">${message}</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button class="confirm-yes" style="padding: 8px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">Yes</button>
            <button class="confirm-no" style="padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">No</button>
        </div>
    `;
    
    document.body.appendChild(dialog);
    
    dialog.querySelector('.confirm-yes').addEventListener('click', () => {
        if (onConfirm) onConfirm();
        dialog.remove();
    });
    
    dialog.querySelector('.confirm-no').addEventListener('click', () => {
        if (onCancel) onCancel();
        dialog.remove();
    });
}

// ========================
// PRINT FUNCTIONS
// ========================
function printPage() {
    const originalTitle = document.title;
    document.title = 'Digital Mentor Book - Report';
    window.print();
    document.title = originalTitle;
}

// Add print styles dynamically
const printStyles = `
    @media print {
        .sidebar, .navbar, .action-buttons, .no-print {
            display: none !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .card, .report-table-container {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        body {
            background: white !important;
        }
    }
`;

const styleSheet = document.createElement("style");
styleSheet.textContent = printStyles;
document.head.appendChild(styleSheet);

// ========================
// AUTO-SAVE FUNCTIONALITY
// ========================
function setupAutoSave() {
    const forms = document.querySelectorAll('form[data-auto-save]');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                if (autoSaveTimer) clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => autoSaveForm(form), 3000);
            });
        });
    });
}

function autoSaveForm(form) {
    const formData = new FormData(form);
    const action = form.getAttribute('action') || window.location.href;
    
    fetch(action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Auto-saved successfully', 'success');
            formDirty = false;
        }
    })
    .catch(error => {
        console.error('Auto-save failed:', error);
    });
}

// ========================
// IDLE TIMER FUNCTIONALITY
// ========================
let idleTimer;
let idleTimeout = 30 * 60 * 1000; // 30 minutes

function setupIdleTimer() {
    resetIdleTimer();
    
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    events.forEach(event => {
        document.addEventListener(event, resetIdleTimer);
    });
}

function resetIdleTimer() {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(logoutUser, idleTimeout);
}

function logoutUser() {
    showConfirmDialog('Your session has expired due to inactivity. Would you like to stay logged in?', () => {
        // Refresh session
        fetch('keep_alive.php', { method: 'POST' })
            .then(() => {
                resetIdleTimer();
                showNotification('Session extended', 'success');
            });
    }, () => {
        window.location.href = '../logout.php';
    });
}

// ========================
// CHART INITIALIZATION
// ========================
function initializeCharts() {
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') return;
    
    // Bar Chart
    const barCtx = document.getElementById('barChart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Achievements',
                    data: [12, 19, 15, 17, 14, 23],
                    backgroundColor: 'rgba(102, 126, 234, 0.7)'
                }]
            }
        });
    }
    
    // Line Chart
    const lineCtx = document.getElementById('lineChart');
    if (lineCtx) {
        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Performance',
                    data: [65, 70, 75, 82],
                    borderColor: '#667eea',
                    tension: 0.4
                }]
            }
        });
    }
}

// ========================
// TOOLTIP FUNCTIONS
// ========================
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const element = e.target;
    const text = element.getAttribute('data-tooltip');
    
    const tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: #333;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 0.85rem;
        z-index: 10000;
        white-space: nowrap;
    `;
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = `${rect.top - 30 + window.scrollY}px`;
    tooltip.style.left = `${rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)}px`;
    
    document.body.appendChild(tooltip);
    element._tooltip = tooltip;
}

function hideTooltip(e) {
    const element = e.target;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

// ========================
// MOBILE SIDEBAR
// ========================
function initMobileSidebar() {
    const hamburger = document.querySelector('.mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    
    if (hamburger && sidebar) {
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }
}

// ========================
// THEME FUNCTIONS
// ========================
function loadThemePreference() {
    const theme = localStorage.getItem('theme') || 'light';
    document.body.classList.add(`theme-${theme}`);
}

function toggleTheme() {
    const currentTheme = document.body.classList.contains('theme-dark') ? 'dark' : 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    document.body.classList.remove(`theme-${currentTheme}`);
    document.body.classList.add(`theme-${newTheme}`);
    localStorage.setItem('theme', newTheme);
    
    showNotification(`${newTheme} theme activated`, 'info');
}

// ========================
// UTILITY FUNCTIONS
// ========================
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function setCookie(name, value, days) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value}; expires=${date.toUTCString()}; path=/`;
}

function formatDate(date, format = 'YYYY-MM-DD') {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    
    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day)
        .replace('HH', hours)
        .replace('mm', minutes);
}

function numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// ========================
// EXPORT FUNCTIONS (Global)
// ========================
window.DigitalMentorBook = {
    showNotification,
    showConfirmDialog,
    openModal,
    closeModal,
    fetchData,
    toggleTheme,
    formatDate,
    numberWithCommas
};

// Add animation keyframes dynamically
const animationStyles = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
    
    .error {
        border-color: #dc3545 !important;
    }
    
    .fa-spin {
        animation: spin 1s linear infinite;
    }
`;

const animStyleSheet = document.createElement("style");
animStyleSheet.textContent = animationStyles;
document.head.appendChild(animStyleSheet);