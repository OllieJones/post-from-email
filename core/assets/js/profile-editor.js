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

document.addEventListener('DOMContentLoaded', async () => {
    const url = '/wp-json/post-from-email/v1/test-credentials'
    const test_button = document.querySelector('td.test_button > div > input#test')

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

    if (test_button) {
        test_button.addEventListener('click', async _ => {
            const credentials = get_credentials();
            const nonce_element = document.getElementById('credentialnonce')
            const nonce = nonce_element ? nonce_element.value : ''
            const body = JSON.stringify({...credentials})
            const spinner = document.querySelector('td.test_button > div > span#credential-spinner.spinner');
            spinner && spinner.classList.add('is-active')
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
            const req = new Request(url, options)
            const res = await fetch(req)
            test_button.disabled = false
            spinner && spinner.classList.remove('is-active')
            if (res.status === 200) {
                const json = await res.json();
                const status_message = document.querySelector('#status_message')
                if (status_message) {
                    status_message.classList.remove('unknown')
                    /* An error message contains newlines */
                    if (json.includes("\n") || json.includes("\r")) {
                        const message = json.replace(/[\r\n]+/, '<br/>')
                        status_message.classList.add('failure')
                        status_message.classList.remove('success')
                        status_message.innerHTML = message
                    } else {
                        status_message.classList.add('success')
                        status_message.classList.remove('failure')
                        status_message.textContent = json
                    }
                }
            }
        })
    }
});
