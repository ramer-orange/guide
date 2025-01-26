document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.openDeleteModalButton');

    // 各削除ボタンにクリックイベントを設定
    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const actionUrl = button.dataset.url; // data-url 属性から取得
            const targetModal = button.dataset.target; // data-target 属性から取得
            const modal = document.querySelector(targetModal);

            if (modal) {
                const deleteForm = modal.querySelector('.deleteForm');
                deleteForm.action = actionUrl; // フォームのアクションURLを設定
                modal.classList.remove('hidden'); // モーダルを表示
            } else {
                console.error('指定されたモーダルが見つかりません:', targetModal);
            }
        });
    });

    const modals = document.querySelectorAll('.deleteModal');

    modals.forEach((modal) => {
        const cancelButton = modal.querySelector('.cancelButton');

        // キャンセルボタンでモーダルを閉じる
        if (cancelButton) {
            cancelButton.addEventListener('click', () => {
                modal.classList.add('hidden'); // モーダルを非表示
            });
        }

        // 背景クリックでモーダルを閉じる
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden'); // モーダルを非表示
            }
        });
    });
});
