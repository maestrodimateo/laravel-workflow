/**
 * Tailwind config for the Workflow Designer admin UI.
 *
 * The compiled, purged stylesheet is committed at resources/dist/app.css and
 * served same-origin by the package — consumers never need Node. Maintainers
 * regenerate it after editing any Blade view with:
 *
 *   npx tailwindcss@3 -c tailwind.config.js -i resources/css/input.css -o resources/dist/app.css --minify
 */
module.exports = {
    darkMode: 'class',
    content: ['./resources/views/**/*.blade.php'],
    theme: {
        extend: {
            colors: {
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                primary: { DEFAULT: 'hsl(var(--primary))', foreground: 'hsl(var(--primary-foreground))' },
                muted: { DEFAULT: 'hsl(var(--muted))', foreground: 'hsl(var(--muted-foreground))' },
                accent: { DEFAULT: 'hsl(var(--accent))', foreground: 'hsl(var(--accent-foreground))' },
                destructive: { DEFAULT: 'hsl(var(--destructive))', foreground: 'hsl(var(--destructive-foreground))' },
                card: { DEFAULT: 'hsl(var(--card))', foreground: 'hsl(var(--card-foreground))' },
            },
            borderRadius: { lg: '0.5rem', md: 'calc(0.5rem - 2px)', sm: 'calc(0.5rem - 4px)' },
        },
    },
};
