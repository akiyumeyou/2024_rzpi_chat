<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>のぶこのお話し履歴</title>
</head>
<body>
    <h1>要約</h1>
    <table border="1" cellspacing="0" cellpadding="10">
        <thead>
            <tr>
                <th>内容</th>
                <th>日時</th>
            </tr>
        </thead>
        <tbody>
            @forelse($summaries as $summary)
                <tr>
                    <td>{{ $summary->summary }}</td>
                    <td>{{ $summary->created_at->format('Y-m-d H:i:s') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">要約はありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <a href="{{ url('/') }}">戻る</a>
</body>
</html>
