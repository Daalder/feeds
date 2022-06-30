const mix = require('laravel-mix');
const tailwindcss = require('tailwindcss');


mix.js([
    './resources/js/app.js'
], 'public/js/feeds.js').vue();

mix.sass('./resources/scss/styles.scss', 'public/css/feeds-package-styles.css')
    .options({
        processCssUrls: false,
        postCss: [tailwindcss('../../../vendor/pionect/backoffice/resources/js/tailwind.config.js')],
    });


// copy backoffice assets to public
mix.copyDirectory('./public', '../../../public');
