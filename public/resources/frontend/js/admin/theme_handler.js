const applyTheme = (themeName) => {
    // Apply theme class to body for immediate visual feedback
    document.body.className = document.body.className.replace(/theme-[\w-]+/g, '');
    document.body.classList.add(themeName);
    
    // Store theme for future visits in both cookie (for server) and localStorage (for fallback)
    localStorage.setItem('selectedTheme', themeName);
    document.cookie = `selectedTheme=${themeName};path=/;max-age=31536000;samesite=Lax`; // Expires in 1 year
};

// The initial theme is now applied by the server via a class on the <body> tag.
// This client-side application on load is removed to prevent FOUC.

// Attach event listeners to theme selector buttons
document.querySelectorAll('.theme-color-box').forEach(button => {
    button.addEventListener('click', (event) => {
        const theme = event.target.dataset.theme;
        applyTheme(theme);
    });
});