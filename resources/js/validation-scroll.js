Livewire.hook('commit', ({ succeed }) => {
    succeed(() => {
        setTimeout(() => {
            const firstErrorMessage = document.querySelector('.error-message')

            if (firstErrorMessage !== null) {
                firstErrorMessage.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'center'
                })
            }
        }, 0)
    })
})
