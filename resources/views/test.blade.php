<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>APIテストフォーム</title>
</head>
<body>
    <h1>APIテストフォーム</h1>
    <form id="apiTestForm">
        <label for="message">メッセージ:</label>
        <input type="text" id="message" name="message" value="Hello">
        <button type="submit">送信</button>
    </form>
    <div id="response"></div>

    <script>
        document.getElementById('apiTestForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const message = document.getElementById('message').value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch('https://potzapp.sakura.ne.jp/2024_rzpi_chat/api/get-ai-response', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    messages: [{ role: 'user', content: message }]
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('response').innerText = JSON.stringify(data);
            })
            .catch(error => {
                document.getElementById('response').innerText = 'Error: ' + error;
            });
        });
    </script>
</body>
</html>
