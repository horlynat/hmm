
// iportatation de stimilus pour webpack encore
import "./stimulus_bootstrap.js";

// any CSS you import will output into a single css file (app.scss in this case)
import "./styles/profile.scss";

// Toggle Dark Mode
document.getElementById('darkModeToggle').addEventListener('click', () => {
document.documentElement.classList.toggle('dark');
localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
});

// Charger le mode sombre depuis localStorage
if (localStorage.getItem('darkMode') === 'true') {
document.documentElement.classList.add('dark');
}