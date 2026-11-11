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

// Bouton « agrandir » ajouté automatiquement dans l'en-tête de chaque fenêtre modale.
function toggleModalSize(content) {
	const maximized = content.classList.toggle('modal-maximized');
	const btn = content.querySelector('.modal-maximize');
	if (btn) {
		const label = maximized ? 'Réduire la fenêtre' : 'Agrandir la fenêtre';
		btn.title = label;
		btn.setAttribute('aria-label', label);
		btn.setAttribute('aria-pressed', maximized ? 'true' : 'false');
		btn.innerHTML = '<i class="fa-solid ' + (maximized ? 'fa-down-left-and-up-right-to-center' : 'fa-up-right-and-down-left-from-center') + '"></i>';
	}
}

document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.modal-content > .modal-header').forEach(function (header) {
		const content = header.parentElement;
		const close = header.querySelector('.modal-close');
		const actions = document.createElement('div');
		actions.className = 'modal-actions';
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'modal-maximize';
		btn.title = 'Agrandir la fenêtre';
		btn.setAttribute('aria-label', 'Agrandir la fenêtre');
		btn.setAttribute('aria-pressed', 'false');
		btn.innerHTML = '<i class="fa-solid fa-up-right-and-down-left-from-center"></i>';
		btn.addEventListener('click', function () {
			toggleModalSize(content);
		});
		actions.appendChild(btn);
		if (close) {
			header.insertBefore(actions, close);
			actions.appendChild(close);
		} else {
			header.appendChild(actions);
		}
		// Double-clic sur la barre de titre = agrandir / réduire
		header.addEventListener('dblclick', function (e) {
			if (!e.target.closest('button'))
				toggleModalSize(content);
		});
	});
});

document.addEventListener('click', function (e) {
	if (e.target.classList.contains('modal-backdrop')) {
		e.target.classList.remove('active');
	}
});
