export function digitsOnly(value) {
    return String(value ?? '').replace(/\D/g, '')
}

export function maskCpf(value) {
    const d = digitsOnly(value).slice(0, 11)
    return d
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
}

export function maskPhone(value) {
    const d = digitsOnly(value).slice(0, 11)
    if (d.length <= 10) {
        return d
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d{1,4})$/, '$1-$2')
    }
    return d
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d{1,4})$/, '$1-$2')
}

export function maskCro(value) {
    return digitsOnly(value).slice(0, 6)
}

export function isValidCpf(value) {
    const cpf = digitsOnly(value)
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false

    let sum = 0
    for (let i = 0; i < 9; i++) sum += parseInt(cpf[i]) * (10 - i)
    let digit = ((sum * 10) % 11) % 10
    if (digit !== parseInt(cpf[9])) return false

    sum = 0
    for (let i = 0; i < 10; i++) sum += parseInt(cpf[i]) * (11 - i)
    digit = ((sum * 10) % 11) % 10
    return digit === parseInt(cpf[10])
}

export function isValidCro(value) {
    const cro = digitsOnly(value)
    return cro === '' || /^\d{4,6}$/.test(cro)
}