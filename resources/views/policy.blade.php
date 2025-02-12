@extends('layouts.app')

@section('title')
    プライバシーポリシー - {{ config('app.name') }}
@endsection

@section('meta')
    <!-- Primary Meta Tags -->
    <meta name="title" content="config('app.name') - プライバシーポリシー">
    <meta name="description" content="config('app.name')のプライバシーポリシーです。ユーザーの個人情報の取り扱いや管理方法について、当社の方針を明示しています。">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="config('app.name') - プライバシーポリシー">
    <meta property="og:description" content="config('app.name')のプライバシーポリシーです。個人情報の収集方法、利用目的、第三者提供、開示方法などについてご確認いただけます。">

    <!-- Twitter -->
    <meta property="twitter:title" content="config('app.name') - プライバシーポリシー">
    <meta property="twitter:description" content="config('app.name')のプライバシーポリシーです。ユーザーの個人情報の取り扱いに関する詳細な方針を掲載しています。">
@endsection


@section('content')
<div class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8 pt-24">
    <h1 class="text-3xl sm:text-4xl font-bold mb-8">プライバシーポリシー</h1>

    <div class="prose max-w-none">
        <p class="mb-8">config('app.name')（以下、「当社」といいます。）は、本ウェブサイトで提供するサービス（以下、「本サービス」といいます。）における、ユーザーの個人情報の取扱いについて、以下のとおりプライバシーポリシー（以下、「本ポリシー」といいます。）を定めます。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第1条（個人情報の収集方法）</h2>
        <p>当社は、ユーザーが利用登録をする際に、メールアドレス、パスワードなどの個人情報をお尋ねすることがあります。また、ユーザーと提携先などとの間でなされた取引記録や、決済に関する情報を当社の提携先（情報提供元、広告主、広告配信先などを含みます。以下、｢提携先｣といいます。）などから収集することがあります。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第2条（個人情報を収集・利用する目的）</h2>
        <p>当社が個人情報を収集・利用する目的は、以下のとおりです。</p>
        <ol class="list-decimal pl-6 mb-6">
            <li class="mb-2">本サービスの提供・運営のため</li>
            <li class="mb-2">ユーザーからのお問い合わせに回答するため</li>
            <li class="mb-2">ユーザーが利用中のサービスの新機能、更新情報、キャンペーン等及び当社が提供する他のサービスの案内のメールを送付するため</li>
            <li class="mb-2">メンテナンス、重要なお知らせなど必要に応じたご連絡のため</li>
            <li class="mb-2">利用規約に違反したユーザーや、不正・不当な目的でサービスを利用しようとするユーザーの特定をし、ご利用をお断りするため</li>
        </ol>

        <h2 class="text-xl font-bold mt-8 mb-4">第3条（個人情報の第三者提供）</h2>
        <p>当社は、次に掲げる場合を除いて、あらかじめユーザーの同意を得ることなく、第三者に個人情報を提供することはありません。ただし、個人情報保護法その他の法令で認められる場合を除きます。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第4条（個人情報の開示）</h2>
        <p>当社は、本人から個人情報の開示を求められたときは、本人に対し、遅滞なくこれを開示します。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第5条（個人情報の訂正および削除）</h2>
        <p>ユーザーは、当社の保有する自己の個人情報が誤った情報である場合には、当社が定める手続きにより、当社に対して個人情報の訂正、追加または削除を請求することができます。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第6条（プライバシーポリシーの変更）</h2>
        <p>本ポリシーの内容は、法令その他本ポリシーに別段の定めのある事項を除いて、ユーザーに通知することなく、変更することができるものとします。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第7条（お問い合わせ窓口）</h2>
        <p>本ポリシーに関するお問い合わせは、下記の窓口までお願いいたします。</p>
        <p class="mt-4">
            メールアドレス：[お問い合わせメールアドレス]<br>
        </p>
    </div>
</div>
@endsection
