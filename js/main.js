// Village Health Connect - Enhanced JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeComponents();
    handleFormValidation();
    setupImagePreview();
    setupNotifications();
    setupAutoRefresh();
    setupNavigationEnhancements();
});

// Initialize all components
function initializeComponents() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize Bootstrap popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            if (bootstrap.Alert.getOrCreateInstance) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        });
    }, 5000);
}

// Form validation
function handleFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');

    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

// Image preview functionality
function setupImagePreview() {
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');

    imageInputs.forEach(function(input) {
        input.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Remove existing preview
                    const existingPreview = document.querySelector('.image-preview-generated');
                    if (existingPreview) {
                        existingPreview.remove();
                    }

                    // Create new preview
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'image-preview image-preview-generated';
                    img.alt = 'Image preview';

                    // Insert after the input
                    input.parentNode.insertBefore(img, input.nextSibling);
                };
                reader.readAsDataURL(file);
            }
        });
    });
}

// Notification system
function setupNotifications() {
    // Mark notifications as read when clicked
    const notificationItems = document.querySelectorAll('.dropdown-item[data-notification-id]');
    notificationItems.forEach(function(item) {
        item.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-notification-id');
            markNotificationAsRead(notificationId);
        });
    });
}

// Auto-refresh for dashboards
function setupAutoRefresh() {
    if (window.location.pathname.includes('dashboard.php')) {
        // Refresh every 5 minutes
        setInterval(function() {
            // Only refresh if user hasn't interacted recently
            if (document.hidden || Date.now() - lastUserInteraction > 300000) { // 5 minutes
                location.reload();
            }
        }, 300000);
    }
}

// Track user interactions
let lastUserInteraction = Date.now();
document.addEventListener('click', function() { lastUserInteraction = Date.now(); });
document.addEventListener('keypress', function() { lastUserInteraction = Date.now(); });

// Utility Functions

// Show loading state on buttons
function showButtonLoading(button, text = 'Loading...') {
    const originalText = button.innerHTML;
    const originalDisabled = button.disabled;

    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + text;
    button.disabled = true;

    return function() {
        button.innerHTML = originalText;
        button.disabled = originalDisabled;
    };
}

// Show toast notifications
function showToast(message, type = 'info', duration = 5000) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(toast);

    // Auto remove
    setTimeout(function() {
        if (toast.parentNode) {
            toast.remove();
        }
    }, duration);
}

// Enhanced showToast with animation
function showToastEnhanced(message, type = 'info', duration = 5000, options = {}) {
    const { position = 'top-right', icon = null, actions = null } = options;
    
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    
    let positionStyle = 'top: 20px; right: 20px;';
    if (position === 'top-left') positionStyle = 'top: 20px; left: 20px;';
    if (position === 'bottom-right') positionStyle = 'bottom: 20px; right: 20px;';
    if (position === 'bottom-left') positionStyle = 'bottom: 20px; left: 20px;';
    if (position === 'top-center') positionStyle = 'top: 20px; left: 50%; transform: translateX(-50%);';
    
    toast.style.cssText = `${positionStyle} z-index: 9999; min-width: 300px; max-width: 500px;`;
    
    let content = '';
    if (icon) content += `<i class="${icon} me-2"></i>`;
    content += message;
    
    if (actions) {
        content += `<div class="mt-2">${actions}</div>`;
    }
    
    content += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    toast.innerHTML = content;
    document.body.appendChild(toast);

    // Auto remove
    setTimeout(function() {
        if (toast.parentNode) {
            toast.remove();
        }
    }, duration);
}

// Confirmation dialogs
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// AJAX helper function
function makeAjaxRequest(url, data, method = 'POST') {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve({ success: true, message: xhr.responseText });
                    }
                } else {
                    reject(new Error('Request failed'));
                }
            }
        };

        if (method === 'POST' && data) {
            const params = Object.keys(data)
                .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(data[key]))
                .join('&');
            xhr.send(params);
        } else {
            xhr.send();
        }
    });
}

// Problem management functions
function assignProblem(problemId, button) {
    const resetButton = showButtonLoading(button, 'Assigning...');

    makeAjaxRequest('/avms/assign_problem.php', { problem_id: problemId })
        .then(response => {
            if (response.success) {
                showToast('Problem assigned successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Error: ' + response.message, 'danger');
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'danger');
        })
        .finally(() => {
            resetButton();
        });
}

function updateProblemStatus(problemId, newStatus, notes = '') {
    const data = {
        problem_id: problemId,
        status: newStatus,
        notes: notes,
        ajax: 1
    };

    makeAjaxRequest('/avms/update_status.php', data)
        .then(response => {
            if (response.success) {
                showToast('Status updated successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Error: ' + response.message, 'danger');
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'danger');
        });
}

function escalateProblem(problemId, doctorId, notes) {
    const data = {
        problem_id: problemId,
        doctor_id: doctorId,
        notes: notes
    };

    makeAjaxRequest('/avms/escalate_problem.php', data)
        .then(response => {
            if (response.success) {
                showToast('Problem escalated to doctor successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Error: ' + response.message, 'danger');
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'danger');
        });
}

// Search and filter functionality
function filterProblems(searchTerm, statusFilter = '') {
    const problemCards = document.querySelectorAll('.problem-card');

    problemCards.forEach(function(card) {
        const text = card.textContent.toLowerCase();
        const status = card.getAttribute('data-status') || '';

        const matchesSearch = searchTerm === '' || text.includes(searchTerm.toLowerCase());
        const matchesStatus = statusFilter === '' || status === statusFilter;

        if (matchesSearch && matchesStatus) {
            card.style.display = 'block';
            card.classList.add('fade-in');
        } else {
            card.style.display = 'none';
        }
    });
}

// Real-time search
function setupSearch() {
    const searchInput = document.getElementById('problemSearch');
    const statusFilter = document.getElementById('statusFilter');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value;
            const statusValue = statusFilter ? statusFilter.value : '';
            filterProblems(searchTerm, statusValue);
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            const searchTerm = searchInput ? searchInput.value : '';
            filterProblems(searchTerm, this.value);
        });
    }
}

// Initialize search on page load
document.addEventListener('DOMContentLoaded', setupSearch);

// Form auto-save functionality
function setupAutoSave(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const inputs = form.querySelectorAll('input, textarea, select');

    inputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const key = `autosave_${formId}_${input.name}`;
            localStorage.setItem(key, input.value);
        });

        // Restore saved data
        const key = `autosave_${formId}_${input.name}`;
        const saved = localStorage.getItem(key);
        if (saved && !input.value) {
            input.value = saved;
        }
    });

    // Clear saved data on form submit
    form.addEventListener('submit', function() {
        inputs.forEach(function(input) {
            const key = `autosave_${formId}_${input.name}`;
            localStorage.removeItem(key);
        });
    });
}

// Mark notification as read
function markNotificationAsRead(notificationId) {
    makeAjaxRequest('/villager/mark_notification_read.php', { notification_id: notificationId })
        .then(response => {
            if (response.success) {
                // Update notification count
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    let count = parseInt(badge.textContent) - 1;
                    if (count <= 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.textContent = count > 9 ? '9+' : count;
                    }
                }
            }
        })
        .catch(error => {
            console.log('Error marking notification as read:', error);
        });
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Copied to clipboard!', 'success', 2000);
    }).catch(function() {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Copied to clipboard!', 'success', 2000);
    });
}

// Print functionality
function printPage() {
    window.print();
}

// Export data to CSV
function exportToCSV(data, filename) {
    const csv = data.map(row => {
        return row.map(field => {
            // Escape quotes and wrap in quotes if necessary
            if (typeof field === 'string' && (field.includes(',') || field.includes('"') || field.includes('\n'))) {
                return '"' + field.replace(/"/g, '""') + '"';
            }
            return field;
        }).join(',');
    }).join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');

    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Format dates
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
}

// Get relative time
function getRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' days ago';

    return date.toLocaleDateString();
}

// Animate counters
function animateCounters() {
    const counters = document.querySelectorAll('.stats-number');

    counters.forEach(counter => {
        const target = parseInt(counter.textContent);
        const increment = Math.ceil(target / 50);
        let current = 0;

        const updateCounter = () => {
            if (current < target) {
                current += increment;
                if (current > target) current = target;
                counter.textContent = current;
                setTimeout(updateCounter, 20);
            }
        };

        updateCounter();
    });
}

// Initialize counter animation on scroll
const observeCounters = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounters();
            observeCounters.disconnect();
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const statsCards = document.querySelector('.stats-card');
    if (statsCards) {
        observeCounters.observe(statsCards);
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + / for search
    if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        const searchInput = document.getElementById('problemSearch');
        if (searchInput) {
            searchInput.focus();
        }
    }

    // Escape to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        });
    }
});

// Service Worker for offline support (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/service-worker.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed');
            });
    });
}

// PWA install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Show install button if desired
    const installBtn = document.getElementById('installBtn');
    if (installBtn) {
        installBtn.style.display = 'block';
        installBtn.addEventListener('click', () => {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                deferredPrompt = null;
                installBtn.style.display = 'none';
            });
        });
    }
});

// Navigation enhancements
function setupNavigationEnhancements() {
    // Add active state to current page navigation
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        }
    });
    
    // Add click handlers for navigation links
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add loading state for navigation
            if (this.href && !this.href.includes('#')) {
                const icon = this.querySelector('i');
                if (icon) {
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    
                    // Reset icon after navigation (if user stays on page)
                    setTimeout(() => {
                        if (icon.className.includes('fa-spinner')) {
                            icon.className = originalClass;
                        }
                    }, 1000);
                }
            }
        });
    });
    
    // Add keyboard navigation support
    document.addEventListener('keydown', function(e) {
        // Alt + number keys for quick navigation
        if (e.altKey && e.key >= '1' && e.key <= '9') {
            e.preventDefault();
            const navLinks = Array.from(document.querySelectorAll('.navbar-nav .nav-link'));
            const index = parseInt(e.key) - 1;
            if (navLinks[index]) {
                navLinks[index].click();
            }
        }
        
        // Escape key to close dropdowns
        if (e.key === 'Escape') {
            const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
            openDropdowns.forEach(dropdown => {
                const dropdownToggle = dropdown.previousElementSibling;
                if (dropdownToggle && dropdownToggle.getAttribute('data-bs-toggle') === 'dropdown') {
                    dropdownToggle.click();
                }
            });
        }
    });
    
    // Add tooltips to navigation items
    const navItems = document.querySelectorAll('.navbar-nav .nav-link');
    navItems.forEach(item => {
        if (!item.hasAttribute('data-bs-toggle')) {
            item.setAttribute('data-bs-toggle', 'tooltip');
            item.setAttribute('title', item.textContent.trim());
        }
    });
    
    // Initialize tooltips for navigation
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('.navbar-nav [data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            placement: 'bottom',
            trigger: 'hover'
        });
    });
    
    // Add notification click handlers
    const notificationLinks = document.querySelectorAll('.notification-dropdown .dropdown-item[data-notification-id]');
    notificationLinks.forEach(link => {
        link.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-notification-id');
            if (notificationId) {
                markNotificationAsRead(notificationId);
            }
        });
    });
    
    // Add back button functionality
    const backButtons = document.querySelectorAll('a[href*="dashboard.php"]');
    backButtons.forEach(btn => {
        if (btn.textContent.toLowerCase().includes('back')) {
            btn.addEventListener('click', function(e) {
                // Add visual feedback
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        }
    });
}