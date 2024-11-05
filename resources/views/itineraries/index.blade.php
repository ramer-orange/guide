<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>しおり一覧ページ</title>
</head>
<body>
<main>
    <h1>しおり一覧ページ</h1>

    <div>
        @if ( $overviews->isEmpty())
            <p>まだしおりを作成していません</p>
        @else
            <table class="table">
                <thead>
                <tr>
                    <th>タイトル</th>
                    <th>概要</th>
                    <th>作成日</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                @foreach( $overviews as $overview)
                    <tr>
                        <td>{{ $overview->title }}</td>
                        <td>{{ Str::limit($overview->overview, 50) }}</td>
                        <td>{{ $overview->created_at->format('Y-m-d') }}</td>
                        <td>
                            <a href="{{ route('itineraries.edit', $overview->id) }}">編集する</a>
                            <form action="{{ route('itineraries.index.destroy', $overview->id) }}" method="post">
                                @csrf
                                @method('DELETE')
                                <button type="submit">削除する</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
            <a href="{{ route('itineraries.create') }}">新しくしおりを作成する</a>
    </div>
</main>
</body>
</html>
