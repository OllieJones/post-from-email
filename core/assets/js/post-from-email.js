/* Rig the iFrameResizer in the parent window. */
document.addEventListener('DOMContentLoaded', async () => {
    const postFromEmail = 'iframe#frame0.post-from-email';
    iFrameResize({log: true}, postFromEmail)

    /* Sometimes the iframe comes up a little short. Recompute it. */
    const do_the_resize = () => {
        const iframe = document.querySelector(postFromEmail)
        if ( iframe ) {
            const iFrameResizer = iframe.iFrameResizer
            if ( iFrameResizer && 'function' === typeof iFrameResizer.resize ) {
                iFrameResizer.resize()
            }
        }
    }

    for ( const delay of  [125, 250, 500, 1000, 2000, 4000 ] ) {
        setTimeout ( do_the_resize, delay);
    }


});
