export async function compressImage(file, { maxWidth = 480, quality = 0.82 } = {}) {
    if (!file?.type?.startsWith('image/')) {
        return file
    }

    const dataUrl = await readFileAsDataUrl(file)

    return new Promise((resolve) => {
        const img = new Image()
        img.onload = () => {
            let { width, height } = img

            if (width > maxWidth) {
                height = Math.round((height * maxWidth) / width)
                width = maxWidth
            }

            const canvas = document.createElement('canvas')
            canvas.width = width
            canvas.height = height

            const ctx = canvas.getContext('2d')
            ctx.drawImage(img, 0, 0, width, height)

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        resolve(file)
                        return
                    }
                    resolve(new File([blob], file.name.replace(/\.\w+$/, '.jpg'), {
                        type: 'image/jpeg',
                        lastModified: Date.now(),
                    }))
                },
                'image/jpeg',
                quality,
            )
        }
        img.onerror = () => resolve(file)
        img.src = dataUrl
    })
}

function readFileAsDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = (e) => resolve(e.target.result)
        reader.onerror = reject
        reader.readAsDataURL(file)
    })
}