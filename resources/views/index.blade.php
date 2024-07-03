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

        let recognition;
        let synth = window.speechSynthesis;
        let conversation = [];
        let isListening = false;

        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            alert('このブラウザは音声認識をサポートしていません。');
        } else {
            recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.lang = 'ja-JP';
            recognition.interimResults = false;
            recognition.continuous = true;

            startButton.addEventListener('click', () => {
                recognition.start();
                isListening = true;
                console.log('音声認識を開始しました');
            });

            recognition.onresult = async (event) => {
                if (!isListening) return;

                const transcript = event.results[event.resultIndex][0].transcript.trim();
                console.log('ユーザー発話: ' + transcript);
                output.innerHTML += `<p><strong>ユーザー:</strong> ${transcript}</p>`;
                conversation.push({ user: transcript });

                if (transcript.includes('終わり')) {
                    isListening = false;
                    recognition.stop();
                    try {
                        await saveConversationSummary();
                        output.innerHTML += `<p><strong>システム:</strong> 会話を終了しました。</p>`;
                    } catch (error) {
                        console.error('Failed to save conversation summary:', error);
                    }
                    return;
                }

                try {
                    const aiResponse = await getAIResponse(transcript);
                    console.log('AI応答: ' + aiResponse);
                    output.innerHTML += `<p><strong>AI:</strong> ${aiResponse}</p>`;
                    conversation.push({ ai: aiResponse });
                    speak(aiResponse, () => {
                        if (isListening) recognition.start(); // AIの発話が終わった後に音声認識を再開する
                    });
                } catch (error) {
                    console.error('Error getting AI response:', error);
                    output.innerHTML += `<p><strong>AI:</strong> エラーが発生しました。${error.message}</p>`;
                }
            };

            recognition.onerror = (event) => {
                console.error('Recognition error:', event.error);
                output.innerHTML += `<p><strong>エラー:</strong> 音声認識エラーが発生しました。${event.error}</p>`;
            };

            recognition.onstart = () => {
                console.log('音声認識が開始されました');
            };

            recognition.onend = () => {
                console.log('音声認識が終了しました');
            };
        }

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
                    const errorText = await response.text();
                    throw new Error(`API request failed with status ${response.status}: ${errorText}`);
                }

                const data = await response.json();
                return data.response;
            } catch (error) {
                console.error('Failed to get response from API:', error);
                return `Error: ${error.message}`;
            }
        }

        function speak(text, callback) {
            recognition.stop(); // AIの発話中は音声認識を停止する
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'ja-JP';
            utterance.onend = callback;
            synth.speak(utterance);
        }

        async function saveConversationSummary() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // 最も印象に残る部分（例：会話の最後の100文字を使用）
            const summaryText = conversation.map(entry => entry.user + " " + entry.ai).join(" ").slice(-100);

            try {
                const response = await fetch('/save-summary', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        summary: summaryText,
                        user_text: conversation.map(entry => entry.user).join(' '),
                        ai_response: conversation.map(entry => entry.ai).join(' ')
                    })
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Failed to save conversation summary with status ${response.status}: ${errorText}`);
                }

                const data = await response.json();
                console.log(data.message);
            } catch (error) {
                console.error('Failed to save conversation summary:', error);
                throw error;
            }
        }
    </script>
</body>
</html>
