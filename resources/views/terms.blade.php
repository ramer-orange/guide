@extends('layouts.app')

@section('title')
    利用規約 - {{ config('app.name') }}
@endsection

@section('meta')
    <!-- Primary Meta Tags -->
    <meta name="title" content="config('app.name') - 利用規約">
    <meta name="description" content="config('app.name')の利用規約です。本サイトの利用にあたってのルールや注意事項についてご確認ください。">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="config('app.name') - 利用規約">
    <meta property="og:description" content="config('app.name')の利用規約です。ユーザーが安心してサービスをご利用いただくために、利用条件や注意事項を定めています。">
    <!-- OGP画像は共通の場合、レイアウト側で設定している場合はここに記載しなくてもOK -->

    <!-- Twitter -->
    <meta property="twitter:title" content="config('app.name') - 利用規約">
    <meta property="twitter:description" content="config('app.name')の利用規約です。本サイト利用に際するルールや注意事項について詳しくご説明しています。">
    <!-- Twitter用の画像も共通の場合は、レイアウト側での設定を検討してください -->
@endsection


@section('content')
<div class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8 pt-24">
    <h1 class="text-3xl sm:text-4xl font-bold mb-8">利用規約</h1>

    <div class="prose max-w-none">
        <p class="mb-8">この利用規約（以下、「本規約」といいます。）は、{{ config('app.name') }}（以下、「当社」といいます。）がこのウェブサイトで提供するサービス（以下、「本サービス」といいます。）の利用条件を定めるものです。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第1条（適用）</h2>
        <p>本規約は、ユーザーと当社との間の本サービスの利用に関わる一切の関係に適用されるものとします。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第2条（利用登録）</h2>
        <ol class="list-decimal pl-6 mb-6">
            <li class="mb-2">登録希望者が当社の定める方法によって利用登録を申請し、当社がこれを承認することによって、利用登録が完了するものとします。</li>
            <li class="mb-2">当社は、利用登録の申請者に以下の事由があると判断した場合、利用登録の申請を承認しないことがあります。</li>
        </ol>

        <h2 class="text-xl font-bold mt-8 mb-4">第3条（ユーザーIDおよびパスワードの管理）</h2>
        <ol class="list-decimal pl-6 mb-6">
            <li class="mb-2">ユーザーは、自己の責任において、本サービスのユーザーIDおよびパスワードを適切に管理するものとします。</li>
            <li class="mb-2">ユーザーは、いかなる場合にも、ユーザーIDおよびパスワードを第三者に譲渡または貸与し、もしくは第三者と共用することはできません。</li>
        </ol>

        <h2 class="text-xl font-bold mt-8 mb-4">第4条（禁止事項）</h2>
        <p>ユーザーは、本サービスの利用にあたり、以下の行為をしてはなりません。</p>
        <ol class="list-decimal pl-6 mb-6">
            <li class="mb-2">法令または公序良俗に違反する行為</li>
            <li class="mb-2">犯罪行為に関連する行為</li>
            <li class="mb-2">当社のサーバーまたはネットワークの機能を破壊したり、妨害したりする行為</li>
            <li class="mb-2">他のユーザーに関する個人情報等を収集または蓄積する行為</li>
            <li class="mb-2">他のユーザーに成りすます行為</li>
        </ol>

        <h2 class="text-xl font-bold mt-8 mb-4">第5条（本サービスの提供の停止等）</h2>
        <p>当社は、以下のいずれかの事由があると判断した場合、ユーザーに事前に通知することなく本サービスの全部または一部の提供を停止または中断することができるものとします。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第6条（免責事項）</h2>
        <p>当社は、本サービスに関して、ユーザーと他のユーザーまたは第三者との間において生じた取引、連絡または紛争等について一切責任を負いません。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第7条（サービス内容の変更等）</h2>
        <p>当社は、ユーザーに通知することなく、本サービスの内容を変更しまたは本サービスの提供を中止することができるものとし、これによってユーザーに生じた損害について一切の責任を負いません。</p>

        <h2 class="text-xl font-bold mt-8 mb-4">第8条（利用規約の変更）</h2>
        <p>当社は、必要と判断した場合には、ユーザーに通知することなくいつでも本規約を変更することができるものとします。</p>
    </div>
</div>
@endsection
