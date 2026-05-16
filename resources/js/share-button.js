const writeClipboard = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);
    const copied = document.execCommand('copy');
    document.body.removeChild(textarea);

    if (!copied) {
        throw new Error('execCommand copy failed');
    }
};

const showToast = (message) => {
    const existingToast = document.getElementById('share-copy-toast');

    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.id = 'share-copy-toast';
    toast.textContent = message;
    toast.setAttribute('role', 'status');
    toast.style.position = 'fixed';
    toast.style.right = '16px';
    toast.style.bottom = '16px';
    toast.style.zIndex = '9999';
    toast.style.background = '#111827';
    toast.style.color = '#ffffff';
    toast.style.padding = '10px 14px';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
    toast.style.fontSize = '14px';
    toast.style.lineHeight = '1.5';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(8px)';
    toast.style.transition = 'opacity 160ms ease, transform 160ms ease';

    document.body.appendChild(toast);

    window.requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    });

    window.setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        window.setTimeout(() => toast.remove(), 180);
    }, 1800);
};

const showManualCopyBox = (url) => {
    const existingBox = document.getElementById('share-manual-copy');

    if (existingBox) {
        existingBox.remove();
    }

    const box = document.createElement('div');
    box.id = 'share-manual-copy';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', '共有URLをコピー');
    box.style.position = 'fixed';
    box.style.right = '16px';
    box.style.bottom = '16px';
    box.style.zIndex = '9999';
    box.style.width = 'min(420px, calc(100vw - 32px))';
    box.style.background = '#ffffff';
    box.style.color = '#111827';
    box.style.padding = '14px';
    box.style.border = '1px solid #d1d5db';
    box.style.borderRadius = '8px';
    box.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.18)';

    const label = document.createElement('p');
    label.textContent = '自動コピーできませんでした。URLを選択してコピーしてください。';
    label.style.margin = '0 0 10px';
    label.style.fontSize = '13px';

    const input = document.createElement('input');
    input.value = url;
    input.readOnly = true;
    input.style.width = '100%';
    input.style.boxSizing = 'border-box';
    input.style.border = '1px solid #d1d5db';
    input.style.borderRadius = '6px';
    input.style.padding = '8px';
    input.style.fontSize = '14px';

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.textContent = '閉じる';
    closeButton.style.marginTop = '10px';
    closeButton.style.padding = '7px 10px';
    closeButton.style.border = '0';
    closeButton.style.borderRadius = '6px';
    closeButton.style.background = '#111827';
    closeButton.style.color = '#ffffff';
    closeButton.style.cursor = 'pointer';
    closeButton.addEventListener('click', () => box.remove());

    box.appendChild(label);
    box.appendChild(input);
    box.appendChild(closeButton);
    document.body.appendChild(box);

    input.focus();
    input.select();
    input.setSelectionRange(0, input.value.length);
};

const showCopiedMessage = (button) => {
    const message = button.getAttribute('data-copy-message') || 'URLをコピーしました';
    const originalTitle = button.getAttribute('title') || '';
    const originalLabel = button.getAttribute('aria-label') || '';

    button.setAttribute('title', message);
    button.setAttribute('aria-label', message);
    showToast(message);

    window.setTimeout(() => {
        button.setAttribute('title', originalTitle || '共有URLをコピー');
        button.setAttribute('aria-label', originalLabel || '共有URLをコピー');
    }, 1800);
};

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.webShareButton');

    if (!button) {
        return;
    }

    event.preventDefault();

    const shareUrl = button.getAttribute('data-share-url') || window.location.href;
    const shareTitle = button.getAttribute('data-share-title') || document.title;

    try {
        if (event.altKey && navigator.share) {
            await navigator.share({
                title: shareTitle,
                url: shareUrl,
            });
            return;
        }

        await writeClipboard(shareUrl);
        showCopiedMessage(button);
    } catch (error) {
        console.error('Share URL copy failed:', error);
        showManualCopyBox(shareUrl);
    }
});
