import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tabsContainer', 'titlesContainer', 'descriptionsContainer', 'addButtonContainer'];

    static values = {
        supportedLocales: { type: Object, default: {} }
    };

    connect() {
        try {
            this.activeLocale = 'default';
            this.syncLocales();
            this.initializeUI();
        } catch (e) {
            console.error('[TranslationTabs] Error al conectar:', e);
        }
    }

    syncLocales() {
        try {
            const activeLocales = this.getActiveLocales();
            activeLocales.forEach(locale => {
                this.ensureLocaleInputs(locale);
            });
        } catch (e) {
            console.error('[TranslationTabs] Error en syncLocales:', e);
        }
    }

    ensureLocaleInputs(locale) {
        if (locale === 'default') return;

        try {
            // 1. Asegurar que exista el título para este idioma
            const titlesController = this.hasTitlesContainerTarget ? this.application.getControllerForElementAndIdentifier(this.titlesContainerTarget, 'collection') : null;
            if (titlesController) {
                const hasTitle = Array.from(this.titlesContainerTarget.querySelectorAll('[data-item="element"] select'))
                    .some(select => select.value === locale);
                
                if (!hasTitle) {
                    titlesController.addElement();
                    const elements = this.titlesContainerTarget.querySelectorAll('[data-item="element"]');
                    const newElement = elements[elements.length - 1];
                    if (newElement) {
                        const select = newElement.querySelector('select');
                        if (select) select.value = locale;
                    }
                }
            }

            // 2. Asegurar que exista la descripción para este idioma
            const descController = this.hasDescriptionsContainerTarget ? this.application.getControllerForElementAndIdentifier(this.descriptionsContainerTarget, 'collection') : null;
            if (descController) {
                const hasDesc = Array.from(this.descriptionsContainerTarget.querySelectorAll('[data-item="element"] select'))
                    .some(select => select.value === locale);
                
                if (!hasDesc) {
                    descController.addElement();
                    const elements = this.descriptionsContainerTarget.querySelectorAll('[data-item="element"]');
                    const newElement = elements[elements.length - 1];
                    if (newElement) {
                        const select = newElement.querySelector('select');
                        if (select) select.value = locale;
                    }
                }
            }
        } catch (e) {
            console.error('[TranslationTabs] Error en ensureLocaleInputs:', e);
        }
    }

    initializeUI() {
        try {
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
        } catch (e) {
            console.error('[TranslationTabs] Error en initializeUI:', e);
        }
    }

    hideNativeLocaleSelectors() {
        try {
            const containers = [];
            if (this.hasTitlesContainerTarget) containers.push(this.titlesContainerTarget);
            if (this.hasDescriptionsContainerTarget) containers.push(this.descriptionsContainerTarget);

            containers.forEach(container => {
                if (!container) return;
                const elements = container.querySelectorAll('[data-item="element"]');
                elements.forEach(el => {
                    const select = el.querySelector('select');
                    if (select) {
                        const parentDiv = select.closest('div');
                        if (parentDiv) parentDiv.style.display = 'none';
                    }
                });
            });
        } catch (e) {
            console.error('[TranslationTabs] Error en hideNativeLocaleSelectors:', e);
        }
    }

    renderTabs() {
        try {
            this.tabsElement.innerHTML = '';

            // Pestaña por defecto (Principal)
            this.addTabButton('default', 'Principal (Defecto)');

            // Encontrar todos los idiomas ya añadidos en las colecciones
            const activeLocales = this.getActiveLocales();
            activeLocales.forEach(locale => {
                const label = this.supportedLocalesValue[locale] || locale.toUpperCase();
                this.addTabButton(locale, label, true);
            });
        } catch (e) {
            console.error('[TranslationTabs] Error en renderTabs:', e);
        }
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
        try {
            const locales = new Set();
            const containers = [];
            if (this.hasTitlesContainerTarget) containers.push(this.titlesContainerTarget);
            if (this.hasDescriptionsContainerTarget) containers.push(this.descriptionsContainerTarget);

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
        } catch (e) {
            console.error('[TranslationTabs] Error en getActiveLocales:', e);
            return [];
        }
    }

    switchLanguage(locale) {
        try {
            if (locale !== 'default') {
                this.ensureLocaleInputs(locale);
            }

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
            const defaultTitle = this.element.querySelector('#poll_title')?.closest('div');
            const defaultDesc = this.element.querySelector('#poll_description')?.closest('div');
            
            if (locale === 'default') {
                if (defaultTitle) defaultTitle.style.display = '';
                if (defaultDesc) defaultDesc.style.display = '';
            } else {
                if (defaultTitle) defaultTitle.style.display = 'none';
                if (defaultDesc) defaultDesc.style.display = 'none';
            }

            // 3. Mostrar/ocultar inputs localizados y formatear sus etiquetas de forma dinámica
            const titles = this.hasTitlesContainerTarget ? Array.from(this.titlesContainerTarget.querySelectorAll('[data-item="element"]')) : [];
            const descs = this.hasDescriptionsContainerTarget ? Array.from(this.descriptionsContainerTarget.querySelectorAll('[data-item="element"]')) : [];
            const allItems = [...titles, ...descs];

            allItems.forEach(item => {
                const select = item.querySelector('select');
                if (select && select.value === locale) {
                    item.style.display = '';

                    // Formatear etiquetas de forma hermosa e integrada
                    const label = item.querySelector('label');
                    if (label) {
                        const languageName = this.supportedLocalesValue[locale] || locale.toUpperCase();
                        const labelFor = label.getAttribute('for') || '';
                        if (labelFor.includes('localizedTitles')) {
                            label.innerHTML = `Nombre de tu consulta (${languageName}) <span class="label__details">(opcional, máx. 200 caracteres)</span>`;
                        } else if (labelFor.includes('localizedDescriptions')) {
                            label.innerHTML = `Descripción (${languageName}) <span class="label__details">(opcional)</span>`;
                        }
                    }
                } else {
                    item.style.display = 'none';
                }
            });

            this.hideNativeLocaleSelectors();
        } catch (e) {
            console.error('[TranslationTabs] Error en switchLanguage:', e);
        }
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

        // 1. Agregar a localizedTitles usando la API oficial de Stimulus
        const titlesController = this.hasTitlesContainerTarget ? this.application.getControllerForElementAndIdentifier(this.titlesContainerTarget, 'collection') : null;
        if (titlesController) {
            titlesController.addElement();
            // Buscar el último elemento añadido y asignarle el locale
            const elements = this.titlesContainerTarget.querySelectorAll('[data-item="element"]');
            const newElement = elements[elements.length - 1];
            if (newElement) {
                const select = newElement.querySelector('select');
                if (select) {
                    select.value = locale;
                }
            }
        }

        // 2. Agregar a localizedDescriptions usando la API oficial de Stimulus
        const descController = this.hasDescriptionsContainerTarget ? this.application.getControllerForElementAndIdentifier(this.descriptionsContainerTarget, 'collection') : null;
        if (descController) {
            descController.addElement();
            // Buscar el último elemento añadido y asignarle el locale
            const elements = this.descriptionsContainerTarget.querySelectorAll('[data-item="element"]');
            const newElement = elements[elements.length - 1];
            if (newElement) {
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
        if (this.hasTitlesContainerTarget) {
            const titleItems = this.titlesContainerTarget.querySelectorAll('[data-item="element"]');
            titleItems.forEach(item => {
                const select = item.querySelector('select');
                if (select && select.value === locale) {
                    const btn = item.querySelector('button[data-action="collection#removeElement"]');
                    if (btn) btn.click();
                }
            });
        }

        // Eliminar elementos de descripciones
        if (this.hasDescriptionsContainerTarget) {
            const descItems = this.descriptionsContainerTarget.querySelectorAll('[data-item="element"]');
            descItems.forEach(item => {
                const select = item.querySelector('select');
                if (select && select.value === locale) {
                    const btn = item.querySelector('button[data-action="collection#removeElement"]');
                    if (btn) btn.click();
                }
            });
        }

        this.renderTabs();
        this.switchLanguage('default');
    }
}
