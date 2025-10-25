// Glowtime Salon Dynamic Theme Switcher
class ThemeSwitcher {
    constructor() {
        this.themes = [
            { name: 'default', label: 'Pink & Teal', icon: '🌸' },
            { name: 'ocean', label: 'Ocean Blue', icon: '🌊' },
            { name: 'forest', label: 'Forest Green', icon: '🌿' },
            { name: 'sunset', label: 'Sunset Orange', icon: '🌅' },
            { name: 'lavender', label: 'Lavender Purple', icon: '💜' },
            { name: 'dark', label: 'Dark Mode', icon: '🌙' }
        ];
        
        this.currentTheme = localStorage.getItem('salon-theme') || 'default';
        this.init();
    }

    init() {
        this.createSwitcher();
        this.applyTheme(this.currentTheme);
        this.bindEvents();
    }

    createSwitcher() {
        const switcher = document.createElement('div');
        switcher.className = 'theme-switcher';
        switcher.innerHTML = `
            <div class="theme-switcher-header">
                <span class="theme-label">🎨 Themes</span>
            </div>
            <div class="theme-buttons">
                ${this.themes.map(theme => `
                    <button 
                        class="theme-btn theme-${theme.name}" 
                        data-theme="${theme.name}"
                        title="${theme.label}"
                        aria-label="Switch to ${theme.label} theme"
                    >
                        <span class="theme-icon">${theme.icon}</span>
                    </button>
                `).join('')}
            </div>
        `;

        // Add styles for the switcher
        const style = document.createElement('style');
        style.textContent = `
            .theme-switcher {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                background: var(--salon-card-bg);
                border-radius: 50px;
                padding: 12px;
                box-shadow: var(--salon-shadow-lg);
                border: 1px solid rgba(0, 0, 0, 0.1);
                backdrop-filter: blur(10px);
                transition: all 0.3s ease;
                max-width: 300px;
            }

            .theme-switcher-header {
                text-align: center;
                margin-bottom: 8px;
            }

            .theme-label {
                font-size: 12px;
                font-weight: 600;
                color: var(--salon-text);
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .theme-buttons {
                display: flex;
                gap: 4px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .theme-btn {
                background: none;
                border: none;
                padding: 10px;
                border-radius: 50%;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .theme-btn:hover {
                transform: scale(1.1);
                box-shadow: var(--salon-shadow);
            }

            .theme-btn.active {
                box-shadow: var(--salon-shadow);
                transform: scale(1.1);
            }

            .theme-btn.active::after {
                content: '✓';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 10px;
                font-weight: bold;
                color: white;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            }

            .theme-icon {
                font-size: 16px;
                transition: transform 0.3s ease;
            }

            .theme-btn:hover .theme-icon {
                transform: scale(1.2);
            }

            /* Theme Colors for Switcher */
            .theme-default { background: #e91e63; }
            .theme-ocean { background: #2196f3; }
            .theme-forest { background: #4caf50; }
            .theme-sunset { background: #ff5722; }
            .theme-lavender { background: #9c27b0; }
            .theme-dark { background: #bb86fc; }

            /* Mobile Responsive */
            @media (max-width: 768px) {
                .theme-switcher {
                    top: 10px;
                    right: 10px;
                    padding: 8px;
                    max-width: 250px;
                }

                .theme-btn {
                    width: 35px;
                    height: 35px;
                    padding: 8px;
                }

                .theme-icon {
                    font-size: 14px;
                }
            }

            /* Collapsible switcher */
            .theme-switcher.collapsed {
                width: 50px;
                overflow: hidden;
            }

            .theme-switcher.collapsed .theme-switcher-header,
            .theme-switcher.collapsed .theme-buttons {
                display: none;
            }

            .theme-switcher.collapsed .theme-btn {
                display: none;
            }

            .theme-switcher.collapsed .theme-btn.active {
                display: flex;
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(switcher);
        this.switcher = switcher;
    }

    bindEvents() {
        const buttons = this.switcher.querySelectorAll('.theme-btn');
        
        buttons.forEach(button => {
            button.addEventListener('click', (e) => {
                const theme = e.currentTarget.dataset.theme;
                this.switchTheme(theme);
            });
        });

        // Add toggle functionality for mobile
        this.switcher.addEventListener('click', (e) => {
            if (e.target === this.switcher || e.target.classList.contains('theme-label')) {
                this.switcher.classList.toggle('collapsed');
            }
        });
    }

    switchTheme(themeName) {
        this.currentTheme = themeName;
        this.applyTheme(themeName);
        this.updateActiveButton();
        localStorage.setItem('salon-theme', themeName);
        
        // Show notification
        this.showNotification(`Theme changed to ${this.getThemeLabel(themeName)}`);
    }

    applyTheme(themeName) {
        document.documentElement.setAttribute('data-theme', themeName);
        
        // Update meta theme-color for mobile browsers
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }
        
        // Set theme color based on current theme
        const themeColors = {
            'default': '#e91e63',
            'ocean': '#2196f3',
            'forest': '#4caf50',
            'sunset': '#ff5722',
            'lavender': '#9c27b0',
            'dark': '#bb86fc'
        };
        
        metaThemeColor.content = themeColors[themeName] || themeColors['default'];
    }

    updateActiveButton() {
        const buttons = this.switcher.querySelectorAll('.theme-btn');
        buttons.forEach(button => {
            button.classList.remove('active');
            if (button.dataset.theme === this.currentTheme) {
                button.classList.add('active');
            }
        });
    }

    getThemeLabel(themeName) {
        const theme = this.themes.find(t => t.name === themeName);
        return theme ? theme.label : 'Unknown';
    }

    showNotification(message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'theme-notification';
        notification.textContent = message;
        
        // Add notification styles
        const style = document.createElement('style');
        style.textContent = `
            .theme-notification {
                position: fixed;
                top: 80px;
                right: 20px;
                background: var(--salon-gradient-primary);
                color: white;
                padding: 12px 20px;
                border-radius: 25px;
                font-size: 14px;
                font-weight: 500;
                box-shadow: var(--salon-shadow-lg);
                z-index: 10000;
                animation: slideInRight 0.3s ease-out;
                max-width: 250px;
            }

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

            @media (max-width: 768px) {
                .theme-notification {
                    top: 60px;
                    right: 10px;
                    font-size: 12px;
                    padding: 10px 16px;
                }
            }
        `;
        
        if (!document.querySelector('#theme-notification-styles')) {
            style.id = 'theme-notification-styles';
            document.head.appendChild(style);
        }
        
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideInRight 0.3s ease-out reverse';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Public method to get current theme
    getCurrentTheme() {
        return this.currentTheme;
    }

    // Public method to get all available themes
    getAvailableThemes() {
        return this.themes;
    }
}

// Initialize theme switcher when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.themeSwitcher = new ThemeSwitcher();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeSwitcher;
}
