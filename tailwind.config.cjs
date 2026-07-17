module.exports = {
  content: [
    './resources/**/*.blade.php',
    './app/**/*.php',
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
};
