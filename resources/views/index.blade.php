<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AIとリアルタイム音声会話</title>
</head>
<body>
    <button id="startButton">AIと会話を開始</button>
    <button id="endButton">会話を終了</button>
    <div id="output"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const startButton = document.getElementById('startButton');
            const endButton = document.getElementById('endButton');
            const output = document.getElementById('output');

            let recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.lang = 'ja-JP';
            recognition.interimResults = false;
            recognition.continuous = false;

            let synth = window.speechSynthesis;
            let conversationHistory = [];
            let isSpeaking = false;

            const randomAizuchi = () => {
                const aizuchi = ["うんうん", "そうですね", "へえ、そうなんですね", "なるほど"];
                return aizuchi[Math.floor(Math.random() * aizuchi.length)];
            };

            startButton.addEventListener('click', () => {
                recognition.start();
                output.innerHTML += '<p><em>会話を開始しました...</em></p>';
            });

            endButton.addEventListener('click', () => {
                conversationHistory.push({ role: 'user', content: '終了' });
                getAIResponse(conversationHistory).then(response => {
                    output.innerHTML += `<p><strong>システム:</strong> ${response}</p>`;
                    recognition.stop();
                }).catch(error => {
                    output.innerHTML += `<p><strong>システム:</strong> ${error.message}</p>`;
                });
            });

            recognition.onresult = async (event) => {
                const transcript = event.results[event.resultIndex][0].transcript.trim();
                if (transcript && !isSpeaking) {
                    output.innerHTML += `<p><strong>ユーザー:</strong> ${transcript}</p>`;
                    conversationHistory.push({ role: 'user', content: transcript });

                    recognition.stop();

                    // 30%の確率で相槌を挿入する
                    if (Math.random() < 0.3) {
                        const aizuchi = randomAizuchi();
                        output.innerHTML += `<p><strong>AI:</strong> ${aizuchi}</p>`;
                        await speak(aizuchi);
                    } else {
                        const aiResponse = await getAIResponse(conversationHistory);
                        output.innerHTML += `<p><strong>AI:</strong> ${aiResponse}</p>`;
                        conversationHistory.push({ role: 'assistant', content: aiResponse });
                        await speak(aiResponse);
                    }

                    // 次のユーザーの発言を待つ
                    if (transcript !== '終了') {
                        recognition.start();
                    }
                }
            };

            recognition.onerror = (event) => {
                console.error('Recognition error:', event.error);
                output.innerHTML += `<p><strong>エラー:</strong> ${event.error}</p>`;
            };

            async function getAIResponse(messages) {
                try {
                    const response = await fetch('https://potzapp.sakura.ne.jp/2024_rzpi_chat/api/get-ai-response', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ messages }),
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Failed to fetch AI response with status ${response.status}: ${errorText}`);
                    }

                    const data = await response.json();
                    return data.message;
                } catch (error) {
                    console.error('Fetch error:', error);
                    return `エラーが発生しました。ネットワークを確認してください。詳細: ${error.message}`;
                }
            }

            async function speak(text) {
                return new Promise((resolve) => {
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = 'ja-JP';

                    utterance.onstart = () => {
                        isSpeaking = true;
                    };

                    utterance.onend = () => {
                        isSpeaking = false;
                        resolve();
                    };

                    synth.speak(utterance);
                });
            }
        });
    </script>
</body>
</html>
