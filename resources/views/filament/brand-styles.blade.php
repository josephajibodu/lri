{{-- The club's look for the whole admin, sign-in page included. The shades themselves are in AdminPanelProvider. --}}
<style>
    /* In dark mode Filament colours its buttons with shade 600, and the real yellow is shade 400. */
    html.dark {
        --primary-500: #FFC83D;
        --primary-600: #FEB203;
    }

    .fi-body { background-color: #EEECE7; }
    html.dark .fi-body { background-color: #0B0A10; }

    /* A stripe of yellow: along the top of the admin, and on the sign-in card. */
    .fi-body-has-topbar::before {
        content: "";
        position: fixed;
        inset: 0 0 auto;
        z-index: 31;
        height: 3px;
        background-color: #FEB203;
    }
    .fi-simple-main { border-top: 4px solid #FEB203; }

    /* Page headings the same size as the sign-in page's. */
    .fi-header-heading { font-size: 1.5rem; line-height: 2rem; }

    /* No line under a card's heading, so the card reads as one block. */
    .fi-section:not(.fi-section-not-contained):not(.fi-aside).fi-section-has-header:not(.fi-collapsed) > .fi-section-content-ctn { border-top-width: 0; }
    .fi-section:not(.fi-section-not-contained):not(.fi-aside).fi-section-has-header > .fi-section-content-ctn > .fi-section-content { padding-top: 0; }

    .lri-login-logo { display: flex; justify-content: center; margin-bottom: 1.5rem; }
    .lri-login-logo img { display: block; height: 5.5rem; width: auto; }
    .lri-login-logo-dark { display: none !important; }
    html.dark .lri-login-logo-dark { display: block !important; }
    html.dark .lri-login-logo-light { display: none !important; }

    .lri-login-back { margin: 0; text-align: center; font-size: .875rem; }
</style>
