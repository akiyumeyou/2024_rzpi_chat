<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gおしゃべり</title>
</head>
<body>
    <button id="startButton">Talk to AI</button>
    <div id="output"></div>
    <script>
        const startButton = document.getElementById('startButton');
        const output = document.getElementById('output');

        let recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
        recognition.lang = 'ja-JP';
        recognition.interimResults = false;
        recognition.continuous = true;

        let synth = window.speechSynthesis;

        startButton.addEventListener('click', () => {
            recognition.start();
        });

        recognition.onresult = async (event) => {
            const transcript = event.results[event.resultIndex][0].transcript.trim();
            output.innerHTML += `<p><strong>ユーザー:</strong> ${transcript}</p>`;
            try {
                const aiResponse = await getAIResponse(transcript);
                output.innerHTML += `<p><strong>AI:</strong> ${aiResponse}</p>`;
                speak(aiResponse);
            } catch (error) {
                console.error('Error getting AI response:', error);
                output.innerHTML += `<p><strong>AI:</strong> エラーが発生しました。${error.message}</p>`;
            }
        };

        recognition.onerror = (event) => {
            console.error('Recognition error:', event.error);
            output.innerHTML += `<p><strong>エラー:</strong> 音声認識エラーが発生しました。${event.error}</p>`;
        };

        async function getAIResponse(text) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            try {
                const response = await fetch('/api/gemini', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ text: text })
                });

                if (!response.ok) {
                    throw new Error(`API request failed with status ${response.status}`);
                }

                const data = await response.json();
                return data.response;
            } catch (error) {
                console.error('Failed to get response from API:', error);
                return `Error: ${error.message}`;
            }
        }

        function speak(text) {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'ja-JP';
            synth.speak(utterance);
        }
    </script>
</body>
</html>
