'use strict';

/**
 * Opens the SQL, History and Find links of the top bar in windows of their own, like the script of the legacy
 * Misc::printTopbar(). A link names its window in data-window (see WebsiteComponents::buildTopBar()). Listens on the
 * document, so it doesn't matter where on the page the script is loaded.
 */
document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('ul.toplink a[data-window]') : null;

    if (link === null) {
        return;
    }

    const popup = window.open(
        link.href,
        link.dataset.window,
        'toolbar=no,width=700,height=500,resizable=yes,scrollbars=yes',
    );

    // A blocked popup leaves the link to the browser, which opens it like without the script.
    if (popup !== null) {
        popup.focus();
        event.preventDefault();
    }
});
