// iportatation de stimilus pour webpack encore
import "./stimulus_bootstrap.js";

// any CSS you import will output into a single css file (app.scss in this case)
import "./styles/app.scss";

// gestion du menu deroulant de la barre de navigation
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.dropdown-menu');
    const button = document.querySelector('.dropdown-trigger');
    if (button.contains(e.target)) {
        dropdown.classList.toggle('hidden');
    } else {
        dropdown.classList.add('hidden');
    }
});

// Toggle Dark Mode
document.getElementById('darkModeToggle').addEventListener('click', () => {
document.documentElement.classList.toggle('dark');
localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
});

// Charger le mode sombre depuis localStorage
if (localStorage.getItem('darkMode') === 'true') {
document.documentElement.classList.add('dark');
}