document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('activeDA')) {
        console.log('Metrepay Plugin - No element activeDA');
        return
    }

    const activeDA =
        document.getElementById('activeDA').value == 'yes' ? true : false

    const currentCartInstallments = getCookie('mp_installments_quantity')
        ? parseInt(getCookie('mp_installments_quantity'))
        : null
    console.log('Metrepay Plugin - currentCartInstallments', currentCartInstallments)

    const labelAllowedPaymentMethods = '<br>' +
        currentCartInstallments && currentCartInstallments > 0
            ? `<p>
                * Este carrito aplica para d&eacute;bito autom&aacute;tico
            </p>`
            : `<p>
                * Este carrito solo puede pagarse mediante pago &uacute;nico
            </p>`

    const pagoUnicoRadioHTML = `
        <div>
            <input 
                type="radio" 
                id="mp-pagounico" 
                name="metrepayoption" 
                value="pagounico"
                ${currentCartInstallments ? 'disabled' : 'checked'} 
                />
            <label>Pago &uacute;nico</label>
        </div>`

    const debitoAutomaticoRadioHTML = `
        <div>
            <input 
                type="radio" 
                id ="mp-debitoautomatico" 
                name="metrepayoption" 
                value="debitoautomatico"
                ${currentCartInstallments ? 'checked' : 'disabled'}
                />
            <label>D&eacute;bito autom&aacute;tico</label>
        </div>  `

    setInterval(() => {
        const metrepayselector = document.getElementById('metrepayselector')
        if (
            metrepayselector &&
            (metrepayselector.innerHTML === '\n    \n    \n    ' ||
            metrepayselector.innerHTML === '')
        ) {
            metrepayselector.innerHTML = activeDA
                ? pagoUnicoRadioHTML + debitoAutomaticoRadioHTML
                : pagoUnicoRadioHTML
            metrepayselector.innerHTML += labelAllowedPaymentMethods

            const inputPU = document.getElementById('mp-pagounico')
            const inputDA = document.getElementById('mp-debitoautomatico')

            if (inputPU.checked) {
                console.log('Metrepay Plugin - Seleccionado PAGO_UNICO')
            }

            if (activeDA) {
                if (inputDA.checked) {
                    console.log('Metrepay Plugin - Seleccionado DEBITO_AUTOMATICO')
                }
            }
        }
    }, 1000)

    function setCookie(cname, cvalue, exdays) {
        var d = new Date()
        d.setTime(d.getTime() + exdays * 24 * 60 * 60 * 1000)
        var expires = 'expires=' + d.toUTCString()
        document.cookie = cname + '=' + cvalue + ';' + expires + ';path=/'
    }

    function getCookie(cname) {
        let name = cname + '='
        let decodedCookie = decodeURIComponent(document.cookie)
        let ca = decodedCookie.split(';')
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i]
            while (c.charAt(0) == ' ') {
                c = c.substring(1)
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length)
            }
        }
        return null
    }
})
