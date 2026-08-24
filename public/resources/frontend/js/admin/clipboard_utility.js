function copyMessageToClipboard(buttonElement) {
    const card = buttonElement.closest('.card');
    const contentToCopy = card.querySelector('#actionTakenMessageDisplay');
    const iconElement = buttonElement.querySelector('i');
    const originalIconClasses = ['fas', 'fa-copy'];
    const successIconClasses = ['fas', 'fa-check'];
    const originalColor = 'var(--primary)';
    const successColor = '#28a745';

    if (contentToCopy) {
        const copyText = contentToCopy.innerText.replace(/\n?(>>?\s*)?Processed:[\s\S]*$/i, '').trim();
        const tempTextarea = document.createElement('textarea');
        tempTextarea.value = copyText;
        document.body.appendChild(tempTextarea);
        tempTextarea.select();

        try {
            document.execCommand('copy');
            if (window.NocTooltip) NocTooltip.showTempText(buttonElement, 'Copied!', 1500);
            iconElement.classList.remove(...originalIconClasses);
            iconElement.classList.add(...successIconClasses);
            buttonElement.style.color = successColor;
            setTimeout(() => {
                iconElement.classList.remove(...successIconClasses);
                iconElement.classList.add(...originalIconClasses);
                buttonElement.style.color = originalColor;
            }, 1500);
        } catch (err) {
            console.error('Failed to copy using execCommand: ', err);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(copyText).then(() => {
                    if (window.NocTooltip) NocTooltip.showTempText(buttonElement, 'Copied!', 1500);
                    iconElement.classList.remove(...originalIconClasses);
                    iconElement.classList.add(...successIconClasses);
                    buttonElement.style.color = successColor;
                    setTimeout(() => {
                        iconElement.classList.remove(...successIconClasses);
                        iconElement.classList.add(...originalIconClasses);
                        buttonElement.style.color = originalColor;
                    }, 1500);
                }).catch(err => {
                    console.error('Failed to copy using navigator.clipboard.writeText: ', err);
                    if (window.NocTooltip) NocTooltip.showTempText(buttonElement, 'Copy failed!', 2000);
                    buttonElement.style.color = '#dc3545';
                    setTimeout(() => {
                        buttonElement.style.color = originalColor;
                    }, 2000);
                });
            } else {
                console.warn('Clipboard API not available.');
                if (window.NocTooltip) NocTooltip.showTempText(buttonElement, 'Copy failed!', 2000);
                buttonElement.style.color = '#dc3545';
                setTimeout(() => {
                    buttonElement.style.color = originalColor;
                }, 2000);
            }
        } finally {
            document.body.removeChild(tempTextarea);
        }
    } else {
        console.warn('No #actionTakenMessageDisplay element found for copying');
    }
}
