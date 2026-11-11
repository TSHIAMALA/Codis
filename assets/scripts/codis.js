// Interactions communes (thème, menu, modales). Fonctions globales : appelées depuis les attributs onclick des gabarits.
function setTheme(theme) {
	document.documentElement.setAttribute('data-theme', theme);
	localStorage.setItem('codis_theme', theme);
	updateThemeSwitcherUI(theme);
}

function updateThemeSwitcherUI(theme) {
	const lightBtn = document.getElementById('themeLightBtn');
	const darkBtn = document.getElementById('themeDarkBtn');
	if (lightBtn && darkBtn) {
		if (theme === 'light') {
			lightBtn.classList.add('active');
			darkBtn.classList.remove('active');
		} else {
			darkBtn.classList.add('active');
			lightBtn.classList.remove('active');
		}
	}
}

// Initialisation du statut du sélecteur au chargement
document.addEventListener('DOMContentLoaded', function() {
	const currentTheme = document.documentElement.getAttribute('data-theme') || localStorage.getItem('codis_theme') || 'light';
	updateThemeSwitcherUI(currentTheme);
});

// Menu latéral masquable : l'état est mémorisé dans ce navigateur.
function setSidebarCollapsed(collapsed) {
	const root = document.documentElement;
	if (collapsed)
		root.setAttribute('data-sidebar', 'collapsed');
	else
		root.removeAttribute('data-sidebar');
	try {
		localStorage.setItem('codis_sidebar', collapsed ? 'collapsed' : 'expanded');
	} catch (e) {}
	updateSidebarToggleUI();
}

function toggleSidebar() {
	setSidebarCollapsed(document.documentElement.getAttribute('data-sidebar') !== 'collapsed');
}

function updateSidebarToggleUI() {
	const btn = document.getElementById('sidebarToggle');
	if (!btn)
		return;
	const collapsed = document.documentElement.getAttribute('data-sidebar') === 'collapsed';
	const label = collapsed ? 'Afficher le menu' : 'Masquer le menu';
	btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
	btn.setAttribute('aria-label', label);
	btn.title = label + ' (Ctrl+B)';
}

document.addEventListener('DOMContentLoaded', updateSidebarToggleUI);

// Raccourci Ctrl+B (hors champs de saisie)
document.addEventListener('keydown', function (e) {
	if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'b') {
		const t = e.target;
		if (t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)))
			return;
		e.preventDefault();
		toggleSidebar();
	}
});

function toggleSubmenu(el) {
	const navItem = el.closest('.nav-item');
	if (navItem) 
		navItem.classList.toggle('open');
}

function openModal(id) {
	const modal = document.getElementById(id);
	if (modal) 
		modal.classList.add('active');
}

function closeModal(id) {
	const modal = document.getElementById(id);
	if (modal) 
		modal.classList.remove('active');
}

document.addEventListener('click', function (e) {
	if (e.target.classList.contains('modal-backdrop')) {
		e.target.classList.remove('active');
	}
});
