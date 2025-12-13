// main.js - Fixed sidebar functionality

// Theme Toggle
const themeSwitch = document.getElementById('theme-switch');
const htmlElement = document.documentElement;

// Check for saved theme preference
const savedTheme = localStorage.getItem('theme') || 'light';
if (savedTheme === 'dark') {
    themeSwitch.checked = true;
    htmlElement.setAttribute('data-theme', 'dark');
}

themeSwitch.addEventListener('change', function() {
    if (this.checked) {
        htmlElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
    } else {
        htmlElement.setAttribute('data-theme', 'light');
        localStorage.setItem('theme', 'light');
    }
});

// Mobile Navigation Toggle
const navToggle = document.querySelector('.nav-toggle');
const navMenu = document.querySelector('.nav-menu');

if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
        navMenu.classList.toggle('active');
        navToggle.classList.toggle('active');
    });
}

// Close mobile menu when clicking on a link
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if (navMenu) navMenu.classList.remove('active');
        if (navToggle) navToggle.classList.remove('active');
    });
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 80,
                behavior: 'smooth'
            });
        }
    });
});

// Sidebar Functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        // Prevent body scrolling when sidebar is open
        if (sidebar.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

// Update sidebar user info
function updateSidebarUserInfo() {
    const isLoggedIn = localStorage.getItem('userLoggedIn') === 'true';
    const userName = localStorage.getItem('userName') || 'Guest User';
    
    // Update sidebar content if it exists
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    const userAvatar = document.getElementById('user-avatar');
    const userNameElement = document.getElementById('user-name');
    const userStatusElement = document.getElementById('user-status');
    const sidebarActions = document.getElementById('sidebar-actions');
    
    if (userAvatar && userNameElement && userStatusElement && sidebarActions) {
        if (isLoggedIn) {
            // User is logged in
            userAvatar.textContent = userName.charAt(0).toUpperCase();
            userNameElement.textContent = userName;
            userStatusElement.textContent = 'Premium Member';
            userAvatar.style.background = 'var(--gradient-primary)';
            
            // Show logged-in actions
            sidebarActions.innerHTML = `
                <button class="sidebar-btn sidebar-btn-primary" onclick="window.location.href='dashboard.php'; toggleSidebar();">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </button>
                <button class="sidebar-btn sidebar-btn-secondary" onclick="window.location.href='profile.php'; toggleSidebar();">
                    <i class="fas fa-user-edit"></i>
                    Edit Profile
                </button>
                <button class="sidebar-btn sidebar-btn-logout" onclick="logout(); toggleSidebar();">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            `;
        } else {
            // User is not logged in
            userAvatar.textContent = 'G';
            userNameElement.textContent = 'Guest User';
            userStatusElement.textContent = 'Not logged in';
            userAvatar.style.background = 'var(--gradient-dark)';
            
            // Show login/register actions
            sidebarActions.innerHTML = `
                <button class="sidebar-btn sidebar-btn-primary" onclick="window.location.href='login.php'; toggleSidebar();">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
                <button class="sidebar-btn sidebar-btn-secondary" onclick="window.location.href='register.php'; toggleSidebar();">
                    <i class="fas fa-user-plus"></i>
                    Sign Up
                </button>
                <button class="sidebar-btn sidebar-btn-primary" onclick="openMembershipModal(); toggleSidebar();">
                    <i class="fas fa-fire"></i>
                    Join Now
                </button>
            `;
        }
    }
}

// Logout function
function logout() {
    localStorage.removeItem('userLoggedIn');
    localStorage.removeItem('userName');
    localStorage.removeItem('userEmail');
    updateSidebarUserInfo();
    alert('Logged out successfully!');
}

// Initialize sidebar on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSidebarUserInfo();
    
    // Close sidebar with escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }
    });
    
    // Debug: Check if sidebar button is visible
    const sidebarBtn = document.querySelector('.sidebar-toggle');
    console.log('Sidebar button:', sidebarBtn);
    if (sidebarBtn) {
        sidebarBtn.style.display = 'flex';
        sidebarBtn.style.visibility = 'visible';
    }
});

// Animate counter numbers
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 100;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 20);
}

// Initialize counters when in viewport
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const counters = entry.target.querySelectorAll('[data-count]');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                animateCounter(counter, target);
            });
        }
    });
}, { threshold: 0.5 });

// Observe hero stats
const heroStats = document.querySelector('.hero-stats');
if (heroStats) observer.observe(heroStats);

// Back to Top Button
const backToTop = document.querySelector('.back-to-top');

if (backToTop) {
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            backToTop.classList.add('visible');
        } else {
            backToTop.classList.remove('visible');
        }
    });

    backToTop.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// Modal Functions
let currentStep = 1;
let selectedPlan = '';

function openMembershipModal() {
    const modal = document.getElementById('membershipModal');
    if (modal) {
        modal.style.display = 'flex';
        resetForm();
    }
}

function closeModal() {
    const modal = document.getElementById('membershipModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function selectPlan(plan) {
    selectedPlan = plan;
    openMembershipModal();
}

function selectPlanOption(plan) {
    selectedPlan = plan;
    document.querySelectorAll('.plan-option').forEach(option => {
        option.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
}

function nextStep() {
    if (currentStep < 3) {
        document.getElementById(`step${currentStep}`).classList.remove('active');
        currentStep++;
        document.getElementById(`step${currentStep}`).classList.add('active');
        updateProgressBar();
    }
}

function prevStep() {
    if (currentStep > 1) {
        document.getElementById(`step${currentStep}`).classList.remove('active');
        currentStep--;
        document.getElementById(`step${currentStep}`).classList.add('active');
        updateProgressBar();
    }
}

function updateProgressBar() {
    document.querySelectorAll('.progress-step').forEach(step => {
        const stepNum = parseInt(step.getAttribute('data-step'));
        if (stepNum <= currentStep) {
            step.classList.add('active');
        } else {
            step.classList.remove('active');
        }
    });
}

function resetForm() {
    currentStep = 1;
    selectedPlan = '';
    document.querySelectorAll('.form-step').forEach(step => {
        step.classList.remove('active');
    });
    const step1 = document.getElementById('step1');
    if (step1) step1.classList.add('active');
    document.querySelectorAll('.plan-option').forEach(option => {
        option.classList.remove('selected');
    });
    document.querySelectorAll('.progress-step').forEach(step => {
        step.classList.remove('active');
    });
    const firstProgressStep = document.querySelector('.progress-step[data-step="1"]');
    if (firstProgressStep) firstProgressStep.classList.add('active');
    const registrationForm = document.getElementById('registrationForm');
    if (registrationForm) registrationForm.reset();
}

// Form Submission
const registrationForm = document.getElementById('registrationForm');
if (registrationForm) {
    registrationForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {
            plan: selectedPlan,
            name: formData.get('name'),
            email: formData.get('email'),
            phone: formData.get('phone')
        };
        
        setTimeout(() => {
            alert(`Registration successful!\nWelcome to Conquer Gym!\nPlan: ${selectedPlan}\nName: ${data.name}`);
            closeModal();
            resetForm();
        }, 1000);
    });
}

// Close modal when clicking outside
const membershipModal = document.getElementById('membershipModal');
if (membershipModal) {
    membershipModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
}

// Navigation scroll effect
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    const isDarkMode = htmlElement.getAttribute('data-theme') === 'dark';
    
    if (window.scrollY > 100) {
        if (isDarkMode) {
            navbar.style.background = 'rgba(30, 39, 46, 0.98)';
        } else {
            navbar.style.background = 'rgba(255, 255, 255, 0.98)';
        }
        navbar.style.boxShadow = 'var(--shadow-md)';
    } else {
        if (isDarkMode) {
            navbar.style.background = 'rgba(30, 39, 46, 0.95)';
        } else {
            navbar.style.background = 'rgba(255, 255, 255, 0.95)';
        }
        navbar.style.boxShadow = 'var(--shadow-sm)';
    }
});

// Hide loading screen
window.addEventListener('load', function() {
    const loadingScreen = document.querySelector('.loading-screen');
    if (loadingScreen) {
        setTimeout(() => {
            loadingScreen.style.opacity = '0';
            setTimeout(() => {
                loadingScreen.style.display = 'none';
            }, 500);
        }, 1000);
    }
});

// Demo functions
function simulateLogin() {
    localStorage.setItem('userLoggedIn', 'true');
    localStorage.setItem('userName', 'John Doe');
    localStorage.setItem('userEmail', 'john@example.com');
    updateSidebarUserInfo();
    alert('Demo: Logged in as John Doe');
}

function simulateLogout() {
    localStorage.removeItem('userLoggedIn');
    localStorage.removeItem('userName');
    localStorage.removeItem('userEmail');
    updateSidebarUserInfo();
    alert('Demo: Logged out');
}