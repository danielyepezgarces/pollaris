import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tabsContainer', 'titlesContainer', 'descriptionsContainer', 'addButtonContainer'];

    static values = {
        supportedLocales: { type: Object, default: {} }
    };

    connect() {
        this.activeLocale = 'default';
        this.initializeUI();
    }

    initializeUI() {
        // 1. Crear el contenedor de pestañas
        this.tabsElement = document.createElement('div');
        this.tabsElement.className = 'cols cols--gap-small cols--always margin-bottom';
        this.tabsElement.style.borderBottom = '1px solid var(--border-color, #ccc)';
        this.tabsElement.style.paddingBottom = '0.5rem';
        this.tabsElement.style.marginBottom = '1rem';
        this.tabsContainerTarget.appendChild(this.tabsElement);

        // 2. Ocultar los selectores nativos gigantes de idioma en los elementos hijos
        this.hideNativeLocaleSelectors();

        // 3. Crear el menú de agregar idioma
        this.buildAddLanguageSelector();

        // 4. Renderizar las pestañas iniciales
        this.renderTabs();

        // 5. Aplicar visibilidad inicial
        this.switchLanguage('default');
    }

    hideNativeLocaleSelectors() {
        const containers = [this.titlesContainerTarget, this.descriptionsContainerTarget];
        containers.forEach(container => {
            if (!container) return;
            const elements = container.querySelectorAll('[data-item="element"]');
            elements.forEach(el => {
                const select = el.querySelector('select');
                if (select) {
                    const parentDiv = select.closest('.flow--small > div, .flow > div') || select.parentElement;
                    if (parentDiv) parentDiv.style.display = 'none';
                }
            });
        });
    }

    renderTabs() {
        this.tabsElement.innerHTML = '';

        // Pestaña por defecto (Principal)
        this.addTabButton('default', 'Principal (Defecto)');

        // Encontrar todos los idiomas ya añadidos en las colecciones
        const activeLocales = this.getActiveLocales();
        activeLocales.forEach(locale => {
            const label = this.supportedLocalesValue[locale] || locale.toUpperCase();
            this.addTabButton(locale, label, true);
        });
    }

    addTabButton(locale, label, canDelete = false) {
        const btnContainer = document.createElement('div');
        btnContainer.className = 'col--content';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `button button--smaller ${this.activeLocale === locale ? 'button--primary' : 'button--secondary'}`;
        btn.style.marginRight = '0.25rem';
        btn.style.marginBottom = '0.25rem';
        btn.textContent = label;
        btn.addEventListener('click', () => this.switchLanguage(locale));

        btnContainer.appendChild(btn);

        if (canDelete) {
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'button button--smaller button--danger';
            delBtn.style.padding = '0.1rem 0.4rem';
            delBtn.style.marginLeft = '-0.15rem';
            delBtn.style.borderTopLeftRadius = '0';
            delBtn.style.borderBottomLeftRadius = '0';
            delBtn.innerHTML = '&times;';
            delBtn.title = 'Eliminar idioma';
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.removeLanguage(locale);
            });
            btn.style.borderTopRightRadius = '0';
            btn.style.borderBottomRightRadius = '0';
            btnContainer.appendChild(delBtn);
        }

        this.tabsElement.appendChild(btnContainer);
    }

    getActiveLocales() {
        const locales = new Set();
        const containers = [this.titlesContainerTarget, this.descriptionsContainerTarget];
        containers.forEach(container => {
            if (!container) return;
            const selects = container.querySelectorAll('[data-item="element"] select');
            selects.forEach(select => {
                if (select.value) {
                    locales.add(select.value);
                }
            });
        });
        return Array.from(locales);
    }

    switchLanguage(locale) {
        this.activeLocale = locale;

        // 1. Alternar botones de pestañas
        const buttons = this.tabsElement.querySelectorAll('button');
        buttons.forEach(btn => {
            if (btn.textContent.includes('Principal') && locale === 'default') {
                btn.className = 'button button--smaller button--primary';
            } else if (!btn.textContent.includes('Principal') && btn.textContent === (this.supportedLocalesValue[locale] || locale.toUpperCase())) {
                btn.className = 'button button--smaller button--primary';
            } else {
                if (!btn.classList.contains('button--danger')) {
                    btn.className = 'button button--smaller button--secondary';
                }
            }
        });

        // 2. Mostrar/ocultar inputs principales (default)
        const defaultTitle = this.element.querySelector('#poll_title').closest('.flow--small > div, .flow > div') || this.element.querySelector('#poll_title').parentElement;
        const defaultDesc = this.element.querySelector('#poll_description').closest('.flow--small > div, .flow > div') || this.element.querySelector('#poll_description').parentElement;
        
        if (locale === 'default') {
            if (defaultTitle) defaultTitle.style.display = '';
            if (defaultDesc) defaultDesc.style.display = '';
        } else {
            if (defaultTitle) defaultTitle.style.display = 'none';
            if (defaultDesc) defaultDesc.style.display = 'none';
        }

        // 3. Mostrar/ocultar inputs localizados
        const allItems = [
            ...this.titlesContainerTarget.querySelectorAll('[data-item="element"]'),
            ...this.descriptionsContainerTarget.querySelectorAll('[data-item="element"]')
        ];
        allItems.forEach(item => {
            const select = item.querySelector('select');
            if (select && select.value === locale) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });

        this.hideNativeLocaleSelectors();
    }

    buildAddLanguageSelector() {
        this.addButtonContainerTarget.innerHTML = '';

        const container = document.createElement('div');
        container.className = 'cols cols--gap-small cols--always cols--center';
        container.style.marginTop = '0.5rem';

        const select = document.createElement('select');
        select.className = 'select--small col--extend';
        
        // Opción inicial
        const placeholderOpt = document.createElement('option');
        placeholderOpt.value = '';
        placeholderOpt.textContent = '--- Añadir traducción ---';
        select.appendChild(placeholderOpt);

        // Rellenar idiomas
        Object.entries(this.supportedLocalesValue).forEach(([code, name]) => {
            const opt = document.createElement('option');
            opt.value = code;
            opt.textContent = `${name} (${code.toUpperCase()})`;
            select.appendChild(opt);
        });

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'button button--smaller button--secondary col--content';
        addBtn.textContent = 'Añadir';
        addBtn.addEventListener('click', () => {
            const code = select.value;
            if (code) {
                this.addLanguage(code);
                select.value = '';
            }
        });

        container.appendChild(select);
        container.appendChild(addBtn);
        this.addButtonContainerTarget.appendChild(container);
    }

    addLanguage(locale) {
        const activeLocales = this.getActiveLocales();
        if (activeLocales.includes(locale)) {
            this.switchLanguage(locale);
            return;
        }

        // 1. Agregar a localizedTitles
        const titlesController = this.element.querySelector('[data-controller="collection"]').__stimulusController;
        if (titlesController) {
            titlesController.addElement();
            // Buscar el último elemento añadido y asignarle el locale
            const elements = this.titlesContainerTarget.querySelectorAll('[data-item="element"]');
            const newElement = elements[elements.length - 1];
            const select = newElement.querySelector('select');
            if (select) {
                select.value = locale;
            }
        }

        // 2. Agregar a localizedDescriptions
        const descBlock = this.element.querySelectorAll('[data-controller="collection"]')[1];
        if (descBlock) {
            const descController = descBlock.__stimulusController;
            if (descController) {
                descController.addElement();
                // Buscar el último elemento añadido y asignarle el locale
                const elements = this.descriptionsContainerTarget.querySelectorAll('[data-item="element"]');
                const newElement = elements[elements.length - 1];
                const select = newElement.querySelector('select');
                if (select) {
                    select.value = locale;
                }
            }
        }

        this.renderTabs();
        this.switchLanguage(locale);
    }

    removeLanguage(locale) {
        // Eliminar elementos de títulos
        const titleItems = this.titlesContainerTarget.querySelectorAll('[data-item="element"]');
        titleItems.forEach(item => {
            const select = item.querySelector('select');
            if (select && select.value === locale) {
                const btn = item.querySelector('button[data-action="collection#removeElement"]');
                if (btn) btn.click();
            }
        });

        // Eliminar elementos de descripciones
        const descItems = this.descriptionsContainerTarget.querySelectorAll('[data-item="element"]');
        descItems.forEach(item => {
            const select = item.querySelector('select');
            if (select && select.value === locale) {
                const btn = item.querySelector('button[data-action="collection#removeElement"]');
                if (btn) btn.click();
            }
        });

        this.renderTabs();
        this.switchLanguage('default');
    }
}
