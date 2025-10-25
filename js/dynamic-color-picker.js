// Glowtime Salon Dynamic Color Picker
class DynamicColorPicker {
    constructor() {
        this.defaultColors = {
            primary: '#e91e63',
            secondary: '#f06292',
            accent: '#f8bbd9',
            teal: '#4dd0e1',
            dark: '#00695c'
        };
        
        this.currentColors = this.loadColors();
        this.init();
    }

    init() {
        this.createColorPicker();
        this.applyColors();
        this.bindEvents();
    }

    createColorPicker() {
        const picker = document.createElement('div');
        picker.className = 'color-picker';
        picker.innerHTML = `
            <div class="color-picker-header">
                <span class="color-picker-title">🎨 Colors</span>
                <button class="color-picker-toggle" title="Toggle Color Picker">
                    <i class="bi bi-palette"></i>
                </button>
            </div>
            <div class="color-picker-content">
                <div class="color-section">
                    <label class="color-label">Primary Color</label>
                    <div class="color-inputs">
                        <input type="color" class="color-input" id="primary-color" value="${this.currentColors.primary}">
                        <input type="text" class="color-text" id="primary-text" value="${this.currentColors.primary}" placeholder="#e91e63">
                    </div>
                </div>
                
                <div class="color-section">
                    <label class="color-label">Secondary Color</label>
                    <div class="color-inputs">
                        <input type="color" class="color-input" id="secondary-color" value="${this.currentColors.secondary}">
                        <input type="text" class="color-text" id="secondary-text" value="${this.currentColors.secondary}" placeholder="#f06292">
                    </div>
                </div>
                
                <div class="color-section">
                    <label class="color-label">Accent Color</label>
                    <div class="color-inputs">
                        <input type="color" class="color-input" id="accent-color" value="${this.currentColors.accent}">
                        <input type="text" class="color-text" id="accent-text" value="${this.currentColors.accent}" placeholder="#f8bbd9">
                    </div>
                </div>
                
                <div class="color-section">
                    <label class="color-label">Teal Color</label>
                    <div class="color-inputs">
                        <input type="color" class="color-input" id="teal-color" value="${this.currentColors.teal}">
                        <input type="text" class="color-text" id="teal-text" value="${this.currentColors.teal}" placeholder="#4dd0e1">
                    </div>
                </div>
                
                <div class="color-section">
                    <label class="color-label">Dark Color</label>
                    <div class="color-inputs">
                        <input type="color" class="color-input" id="dark-color" value="${this.currentColors.dark}">
                        <input type="text" class="color-text" id="dark-text" value="${this.currentColors.dark}" placeholder="#00695c">
                    </div>
                </div>
                
                <div class="color-section">
                    <label class="color-label">Quick Presets</label>
                    <div class="color-presets">
                        <button class="color-preset" style="background: #e91e63;" data-colors='{"primary":"#e91e63","secondary":"#f06292","accent":"#f8bbd9","teal":"#4dd0e1","dark":"#00695c"}'></button>
                        <button class="color-preset" style="background: #2196f3;" data-colors='{"primary":"#2196f3","secondary":"#64b5f6","accent":"#bbdefb","teal":"#00bcd4","dark":"#006064"}'></button>
                        <button class="color-preset" style="background: #4caf50;" data-colors='{"primary":"#4caf50","secondary":"#81c784","accent":"#c8e6c9","teal":"#26a69a","dark":"#1b5e20"}'></button>
                        <button class="color-preset" style="background: #ff5722;" data-colors='{"primary":"#ff5722","secondary":"#ff8a65","accent":"#ffccbc","teal":"#9c27b0","dark":"#4a148c"}'></button>
                        <button class="color-preset" style="background: #9c27b0;" data-colors='{"primary":"#9c27b0","secondary":"#ba68c8","accent":"#e1bee7","teal":"#e91e63","dark":"#4a148c"}'></button>
                        <button class="color-preset" style="background: #bb86fc;" data-colors='{"primary":"#bb86fc","secondary":"#cf6679","accent":"#03dac6","teal":"#03dac6","dark":"#ffffff"}'></button>
                    </div>
                </div>
                
                <div class="color-section">
                    <button class="btn-reset-colors" style="width: 100%; padding: 8px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 500;">
                        🔄 Reset to Default
                    </button>
                </div>
            </div>
        `;

        // Add styles for the color picker
        const style = document.createElement('style');
        style.textContent = `
            .color-text {
                flex: 1;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 12px;
                font-family: monospace;
                background: var(--user-card-bg);
                color: var(--user-text);
                transition: all 0.3s ease;
            }
            
            .color-text:focus {
                outline: none;
                border-color: var(--user-primary);
                box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
            }
            
            .btn-reset-colors {
                transition: all 0.3s ease;
            }
            
            .btn-reset-colors:hover {
                background: #e0e0e0 !important;
                transform: translateY(-1px);
            }
            
            .color-preset.active {
                transform: scale(1.1);
                box-shadow: 0 0 0 2px var(--user-primary);
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(picker);
        this.picker = picker;
    }

    bindEvents() {
        // Toggle picker
        const toggle = this.picker.querySelector('.color-picker-toggle');
        toggle.addEventListener('click', () => {
            this.picker.classList.toggle('collapsed');
        });

        // Color inputs
        const colorInputs = this.picker.querySelectorAll('.color-input');
        colorInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const colorType = e.target.id.replace('-color', '');
                const textInput = this.picker.querySelector(`#${colorType}-text`);
                this.currentColors[colorType] = e.target.value;
                textInput.value = e.target.value;
                this.applyColors();
                this.saveColors();
            });
        });

        // Text inputs
        const textInputs = this.picker.querySelectorAll('.color-text');
        textInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const colorType = e.target.id.replace('-text', '');
                const colorInput = this.picker.querySelector(`#${colorType}-color`);
                if (this.isValidColor(e.target.value)) {
                    this.currentColors[colorType] = e.target.value;
                    colorInput.value = e.target.value;
                    this.applyColors();
                    this.saveColors();
                }
            });
        });

        // Preset buttons
        const presets = this.picker.querySelectorAll('.color-preset');
        presets.forEach(preset => {
            preset.addEventListener('click', (e) => {
                const colors = JSON.parse(e.target.dataset.colors);
                this.currentColors = { ...colors };
                this.updateInputs();
                this.applyColors();
                this.saveColors();
                this.updateActivePreset(e.target);
            });
        });

        // Reset button
        const resetBtn = this.picker.querySelector('.btn-reset-colors');
        resetBtn.addEventListener('click', () => {
            this.currentColors = { ...this.defaultColors };
            this.updateInputs();
            this.applyColors();
            this.saveColors();
            this.showNotification('Colors reset to default');
        });
    }

    updateInputs() {
        Object.keys(this.currentColors).forEach(colorType => {
            const colorInput = this.picker.querySelector(`#${colorType}-color`);
            const textInput = this.picker.querySelector(`#${colorType}-text`);
            if (colorInput) colorInput.value = this.currentColors[colorType];
            if (textInput) textInput.value = this.currentColors[colorType];
        });
    }

    updateActivePreset(activePreset) {
        const presets = this.picker.querySelectorAll('.color-preset');
        presets.forEach(preset => preset.classList.remove('active'));
        activePreset.classList.add('active');
    }

    applyColors() {
        const root = document.documentElement;
        root.style.setProperty('--user-primary', this.currentColors.primary);
        root.style.setProperty('--user-secondary', this.currentColors.secondary);
        root.style.setProperty('--user-accent', this.currentColors.accent);
        root.style.setProperty('--user-teal', this.currentColors.teal);
        root.style.setProperty('--user-dark', this.currentColors.dark);
        
        // Update meta theme-color for mobile browsers
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }
        metaThemeColor.content = this.currentColors.primary;
    }

    isValidColor(color) {
        return /^#[0-9A-F]{6}$/i.test(color);
    }

    saveColors() {
        localStorage.setItem('salon-colors', JSON.stringify(this.currentColors));
    }

    loadColors() {
        const saved = localStorage.getItem('salon-colors');
        if (saved) {
            try {
                return { ...this.defaultColors, ...JSON.parse(saved) };
            } catch (e) {
                return this.defaultColors;
            }
        }
        return this.defaultColors;
    }

    showNotification(message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'color-notification';
        notification.textContent = message;
        
        // Add notification styles
        const style = document.createElement('style');
        style.textContent = `
            .color-notification {
                position: fixed;
                top: 80px;
                right: 20px;
                background: var(--user-primary);
                color: white;
                padding: 12px 20px;
                border-radius: 25px;
                font-size: 14px;
                font-weight: 500;
                box-shadow: var(--user-shadow-lg);
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
                .color-notification {
                    top: 60px;
                    right: 10px;
                    font-size: 12px;
                    padding: 10px 16px;
                }
            }
        `;
        
        if (!document.querySelector('#color-notification-styles')) {
            style.id = 'color-notification-styles';
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

    // Public method to get current colors
    getCurrentColors() {
        return this.currentColors;
    }

    // Public method to set colors programmatically
    setColors(colors) {
        this.currentColors = { ...this.currentColors, ...colors };
        this.updateInputs();
        this.applyColors();
        this.saveColors();
    }
}

// Initialize color picker when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.colorPicker = new DynamicColorPicker();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DynamicColorPicker;
}
