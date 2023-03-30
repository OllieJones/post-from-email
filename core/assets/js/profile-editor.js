document.addEventListener('DOMContentLoaded', () => {
    /* shrink the tinyMCE editor */
    const poll = setInterval(() => {
        const editor = document.querySelector('iframe#content_ifr')
        if (editor) {
            editor.style.height = '150px'
            clearInterval(poll)
        }
    }, 20);
});
