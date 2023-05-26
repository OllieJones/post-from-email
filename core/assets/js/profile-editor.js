document.addEventListener('DOMContentLoaded', () => {
    /* shrink the tinyMCE editor */
    const poll = setInterval(() => {
        const editor = document.querySelector('iframe#content_ifr')
        if (editor) {
            editor.style.height = '150px'
            clearInterval(poll)
        }
    }, 20);

    /* Configure the help dialog boxes for metaboxes */
    jQuery('div.dialog.popup.help-popup')
        .each(function (_) {
            if (this.dataset.target) {
                const helpbox = this
                jQuery(`div#wpbody div.postbox-container div#${this.dataset.target} > div.postbox-header > h2`)
                    .each(function (_) {
                        const width = Math.min(this.parentElement.offsetWidth, 800);
                        this.classList.add('has-help-icon')
                        const link = document.createElement('div')
                        link.style.float = 'left'
                        link.classList.add('dashicons', 'dashicons-editor-help', 'help-icon')
                        const dialog_box = jQuery(helpbox).dialog({
                            position: {
                                my: 'left bottom',
                                collision: 'flipfit flipfit',
                                at: 'left-5 top',
                                of: this.parentElement
                            },
                            classes: {'ui-dialog': 'helpbox'},
                            autoOpen: false,
                            width: width + 1,
                        })
                        helpbox.classList.remove('hidden')
                        link.addEventListener('click', function (_) {
                            if (dialog_box.dialog('isOpen')) dialog_box.dialog('close')
                            else dialog_box.dialog('open')
                        })
                        this.before(link)
                    })
            }
        })
});

document.addEventListener('DOMContentLoaded', async () => {
    const nonce_element = document.getElementById('credentialnonce')
    const nonce = nonce_element ? nonce_element.value : ''

    /**
     * Fetch credentials[whatever] fields from inputs.
     *
     * @returns [] Associative array.
     */
    function get_credentials() {
        const inputs = []
        inputs.push(...document.getElementsByTagName('input'))
        inputs.push(...document.getElementsByTagName('textarea'))
        inputs.push(...document.getElementsByTagName('select'))
        const regex = /^credentials\[([_a-z]+)]$/
        const credentials = []
        for (const element of inputs) {
            const elname = element.name
            if (typeof elname === 'string' && elname.length > 0) {
                if (elname.startsWith('credentials[')) {
                    const name = elname.replace(regex, '$1')
                    credentials[name] = element.value
                }
            }
        }
        return credentials
    }

    const test_button = document.querySelector('td.test_button > div > input#test')
    if (test_button) {
        test_button.addEventListener('click', async _ => {
            const endpoint = test_button.dataset.endpoint
            const credentials = get_credentials();
            const body = JSON.stringify({...credentials})
            const spinner = document.querySelector('td.test_button > div > span#credential_spinner.spinner');
            spinner && spinner.classList.add('is-active')
            const status_message = document.querySelector('#status_message')
            if (status_message) {
                status_message.classList.add('unknown')
                status_message.classList.remove('success', 'failure')
            }
            test_button.disabled = true
            const options = {
                method: 'POST',
                body: body,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                mode: 'same-origin',
                credentials: 'include',
                cache: 'no-cache',
                referrerPolicy: 'same-origin'
            };
            const req = new Request(endpoint, options)
            const res = await fetch(req)
            test_button.disabled = false
            spinner && spinner.classList.remove('is-active')
            if (res.status === 200) {
                const reply = await res.json();
                const status_message = document.querySelector('#status_message')
                if (status_message) {
                    status_message.classList.remove('unknown')
                    /* An error message contains newlines */
                    if (!reply.startsWith('OK')) {
                        status_message.classList.add('failure')
                        status_message.classList.remove('success', 'unknown')
                        status_message.innerHTML = reply
                    } else {
                        const message = reply.replace(/^OK\s*/, '')
                        status_message.classList.add('success')
                        status_message.classList.remove('failure', 'unknown')
                        status_message.textContent = message
                    }
                }
            }
        })
    }
    const urlpost_button = document.getElementById('urlpost_button')
    if (urlpost_button) {
        urlpost_button.addEventListener('click', async _ => {
            const endpoint = urlpost_button.dataset.endpoint
            const profile_id = urlpost_button.dataset.profile_id
            const urlpost_url = document.getElementById('urlpost_url');
            const urlpost = (urlpost_url && 'string' === typeof urlpost_url.value) ? urlpost_url.value : '';
            if (urlpost.length > 0) {
                const body = JSON.stringify({url: urlpost, profile_id: profile_id})
                const spinner = document.getElementById('urlpost_spinner');
                spinner && spinner.classList.add('is-active')
                const status_message = document.getElementById('urlpost_message')
                if (status_message) {
                    status_message.classList.add('unknown')
                    status_message.classList.remove('success', 'failure')
                }
                urlpost_button.disabled = true
                const options = {
                    method: 'POST',
                    body: body,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    mode: 'same-origin',
                    credentials: 'include',
                    cache: 'no-cache',
                    referrerPolicy: 'same-origin'
                };
                const req = new Request(endpoint, options)
                const res = await fetch(req)
                urlpost_button.disabled = false
                spinner && spinner.classList.remove('is-active')
                if (res.status === 200) {
                    const reply = await res.json();
                    const status_message = document.querySelector('#status_message')
                    if (status_message) {
                        status_message.classList.remove('unknown')
                        /* An error message contains newlines */
                        if (!reply.startsWith('OK')) {
                            status_message.classList.add('failure')
                            status_message.classList.remove('success', 'unknown')
                            status_message.innerHTML = reply
                        } else {
                            const message = reply.replace(/^OK\s*/, '')
                            status_message.classList.add('success')
                            status_message.classList.remove('failure', 'unknown')
                            status_message.textContent = message
                        }
                    }
                }
            }
        })
    }
});
