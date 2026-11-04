(function () {
    function isDevArtSupportParentLink(href) {
        if (href.indexOf('option=com_devartsupport') === -1) {
            return false;
        }

        return href.indexOf('view=') === -1;
    }

    function activateDevArtSupportMenu() {
        var wrap = document.querySelector('[data-devart-support-view]');
        var view = wrap ? wrap.getAttribute('data-devart-support-view') : '';
        var links = Array.prototype.slice.call(document.querySelectorAll(
            '#sidebar-wrapper a, #sidebarmenu a, .main-nav a, .sidebar-wrapper a, a[href*="option=com_devartsupport"]'
        ));
        var parent = links.find(function (a) {
            return isDevArtSupportParentLink(a.getAttribute('href') || '');
        });

        if (parent) {
            parent.setAttribute('aria-expanded', 'true');
            parent.classList.remove('devart-support-active-submenu');
            var li = parent.closest('li');
            if (li) {
                li.classList.remove('devart-support-active-submenu-item');
                li.classList.add('mm-active', 'open');
                var ul = li.querySelector('ul');
                if (ul) {
                    ul.classList.add('mm-show', 'show');
                }
            }
        }

        links.forEach(function (a) {
            var href = a.getAttribute('href') || '';
            if (href.indexOf('option=com_devartsupport') === -1) {
                return;
            }

            var item = a.closest('li');
            var isParent = a.classList.contains('has-arrow')
                || isDevArtSupportParentLink(href)
                || (item && (item.classList.contains('item-level-2') || item.querySelector(':scope > ul')));

            if (isParent) {
                a.classList.remove('devart-support-active-submenu');
                if (item) {
                    item.classList.remove('devart-support-active-submenu-item');
                }
                return;
            }

            a.classList.remove('mm-active', 'devart-support-active-submenu');
            a.removeAttribute('aria-current');
            if (item) {
                item.classList.remove('mm-active', 'devart-support-active-submenu-item');
            }

            var isDashboard = href.indexOf('view=dashboard') !== -1;
            var isTickets = href.indexOf('view=tickets') !== -1;
            var isCategories = href.indexOf('view=categories') !== -1;
            var isTools = href.indexOf('view=tools') !== -1;
            var active = (view === 'dashboard' && isDashboard)
                || (view === 'tickets' && isTickets)
                || (view === 'ticket' && isTickets)
                || (view === 'categories' && isCategories)
                || (view === 'category' && isCategories)
                || (view === 'tools' && isTools)
                || (view === 'settings' && isTools);

            if (active) {
                a.classList.add('mm-active', 'devart-support-active-submenu');
                a.setAttribute('aria-current', 'page');
                if (item) {
                    item.classList.add('mm-active', 'devart-support-active-submenu-item');
                }
            }
        });
    }

    function bindAttachmentCategoryToggle() {
        var wrap = document.querySelector('[data-devart-support-attachments]');
        var upload = document.querySelector('[data-devart-support-upload]');
        var disabled = document.querySelector('[data-devart-support-upload-disabled]');
        var select = document.getElementById('jform_category_id');

        if (!wrap || !upload || !disabled) {
            return;
        }

        var flags;

        try {
            flags = JSON.parse(wrap.getAttribute('data-devart-support-attachments') || '{}');
        } catch (error) {
            return;
        }

        function sync() {
            var categoryId = select ? String(select.value || '0') : '0';
            var categoryEnabled = categoryId === '0' || flags.categories[categoryId] !== 0;
            var allowed = flags.global === 1 && categoryEnabled;

            if (allowed) {
                upload.removeAttribute('hidden');
                disabled.setAttribute('hidden', 'hidden');
            } else {
                upload.setAttribute('hidden', 'hidden');
                disabled.removeAttribute('hidden');
            }
        }

        if (select) {
            select.addEventListener('change', sync);
        }

        sync();
    }

    function bindCheckAllToggles() {
        document.querySelectorAll('input[name="checkall-toggle"][data-devart-support-checkall]').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                if (window.Joomla && typeof window.Joomla.checkAll === 'function') {
                    window.Joomla.checkAll(toggle);
                }
            });
        });
    }

    function bindFilterAutosubmit() {
        document.querySelectorAll('select[data-devart-support-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () {
                var form = select.form;

                if (form) {
                    form.submit();
                }
            });
        });
    }

    function initDevArtSupportAdmin() {
        activateDevArtSupportMenu();
        bindAttachmentCategoryToggle();
        bindCheckAllToggles();
        bindFilterAutosubmit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDevArtSupportAdmin);
    } else {
        initDevArtSupportAdmin();
    }
})();
