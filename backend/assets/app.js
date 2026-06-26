/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// iportatation de stimilus pour webpack encore
import './stimulus_bootstrap.js';

// any CSS you import will output into a single css file (app.scss in this case)
import './styles/app.scss';

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

// Supprime les messages flash après 5 seconde
setTimeout(() => {
    document.querySelectorAll('.animate-fade-in-down, .relative').forEach(el => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 600); // attend la fin de l'effet de disparition
    });
}, 5000);
