{{-- The club's colours for the sign-in page only. The rest of the admin keeps the panel's amber. --}}
<style>
    :root {
        --primary-300: #FFC83D;
        --primary-400: #FEB203;
        --primary-800: #1C1917;
        --primary-900: #1C1917;
        --primary-950: #1C1917;
    }

    html.dark {
        --primary-500: #FFC83D;
        --primary-600: #FEB203;
    }

    .fi-body { background-color: #EEECE7; }
    html.dark .fi-body { background-color: #0B0A10; }

    .fi-simple-main { border-top: 4px solid #FEB203; }

    .lri-login-logo { display: flex; justify-content: center; margin-bottom: 1.5rem; }
    .lri-login-logo img { display: block; height: 5.5rem; width: auto; }
    .lri-login-logo-dark { display: none !important; }
    html.dark .lri-login-logo-dark { display: block !important; }
    html.dark .lri-login-logo-light { display: none !important; }

    .lri-login-back { margin: 0; text-align: center; font-size: .875rem; }
</style>
