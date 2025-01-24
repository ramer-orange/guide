// 共有ボタンの要素を全て取得
const shareButtons = document.querySelectorAll('.webShareButton');

// 各ボタンにクリックイベントを設定
shareButtons.forEach((button) => {
    button.addEventListener('click', async () => {
        const shareUrl = button.getAttribute('data-share-url') || window.location.href;
        const shareTitle = button.getAttribute('data-share-title') || document.title;

        // シェア処理
        await navigator.share({
            title: shareTitle,
            url: shareUrl,
        });
    });
});
