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
