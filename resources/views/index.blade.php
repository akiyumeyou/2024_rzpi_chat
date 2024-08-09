<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AIとリアルタイム音声会話</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        button {
            margin: 5px;
            padding: 10px 20px;
            font-size: 16px;
        }

        #output {
            margin-top: 20px;
            border: 1px solid #ccc;
            padding: 10px;
            max-width: 600px;
        }

        p {
            margin: 5px 0;
        }
    </style>
</head>

<body>
    <button id="startButton">AIと会話を開始</button>
    <button id="endButton">会話を終了</button>
    <a href="{{ route('sum.index') }}">会話要約参照</a>
    <div id="output"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const startButton = document.getElementById('startButton');
            const endButton = document.getElementById('endButton');
            const output = document.getElementById('output');
            let voiceType = 'male';
            let isSpeaking = false;
            let recognitionActive = false;
            let conversationStarted = false;
            let currentAudio = null;
            let conversationHistory = [];
            let lastProcessedTranscript = ""; // 最後に処理した発話を保存
            let selectedVoice = "";  // 選択した声の名前を保持

            // Web Speech APIを使用して音声認識を初期化
            const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.lang = 'ja-JP';
            recognition.interimResults = false;
            recognition.continuous = true;

            // 音声認識の結果を処理するイベントリスナー
            recognition.onresult = async (event) => {
                if (isSpeaking) return;  // 発話中は処理しない

                const lastResultIndex = event.results.length - 1;
                const transcript = event.results[lastResultIndex][0].transcript.trim();

                // 同じ発話を2回処理しないようにする
                if (lastProcessedTranscript === transcript || !transcript) return;
                lastProcessedTranscript = transcript;

                if (event.results[lastResultIndex].isFinal) {
                    // ユーザーの発話を1回のみ表示
                    output.innerHTML += `<p><strong>のぶこ:</strong> ${transcript}</p>`;
                    conversationHistory.push({ role: 'user', content: transcript });

                    if (!conversationStarted) {
                        await handleInitialConversation(transcript);
                    } else {
                        await handleConversation(transcript);
                    }
                }
            };

            recognition.onend = () => {
                if (!isSpeaking && recognitionActive) {
                    startRecognition();
                }
            };

            recognition.onerror = (event) => {
                console.error('Recognition error:', event.error);
                output.innerHTML += `<p><strong>エラー:</strong> ${event.error}</p>`;
                if (event.error !== 'no-speech') {
                    startRecognition();
                }
            };

            startButton.addEventListener('click', async () => {
                if (!conversationStarted) {
                    await startConversation();
                } else {
                    console.log('会話は既に開始されています。');
                }
            });

            endButton.addEventListener('click', async () => {
                await endConversation();
            });

            async function startConversation() {
                if (recognitionActive) {
                    stopRecognition();
                }

                output.innerHTML = '<p><em>会話を開始しました...</em></p>';
                await speak("今日は誰と話しますか？", 'male');
                conversationHistory.push({ role: 'system', content: "今日は誰と話しますか？" });
                output.innerHTML += '<p><strong>LA:</strong> 今日は誰と話しますか？</p>';
                startRecognition();
            }

            function convertSpecificKanjiToHiragana(text) {
                // 「明子」という漢字を「あきこ」に変換
                return text.replace(/明子/g, 'あきこ');
            }

            async function handleInitialConversation(transcript) {
                stopRecognition();

                // 発話内容を正規化して判定を緩やかにする
                const normalizedTranscript = convertSpecificKanjiToHiragana(transcript.replace(/\s+/g, "").toLowerCase());

                if (/(あなた|デフォルト|デフォルトの男性)/.test(normalizedTranscript)) {
                    voiceType = 'male';
                    selectedVoice = 'あなた';
                    conversationStarted = true;
                    await speak("あなたの声で会話を開始します。今日も楽しくおしゃべりしましょう。", 'male');
                    output.innerHTML += '<p><strong>LA:</strong> あなたの声で会話を開始します。今日も楽しくおしゃべりしましょう。</p>';
                    startRecognition();
                } else if (/あきこ[さん]?|akiko/.test(normalizedTranscript)) {
                    voiceType = 'clone';
                    selectedVoice = 'あきこ';
                    conversationStarted = true;
                    await speak("あきこの声で会話を開始します。今日も楽しくおしゃべりしましょう。", 'clone');
                    output.innerHTML += '<p><strong>LA:</strong> あきこの声で会話を開始します。今日も楽しくおしゃべりしましょう。</p>';
                    startRecognition();
                } else {
                    await speak("申し訳ありませんが、再度お名前を教えてください。", 'male');
                    output.innerHTML += '<p><strong>LA:</strong> 申し訳ありませんが、再度お名前を教えてください。</p>';
                    startRecognition();
                }
            }

            async function handleConversation(transcript) {
                stopRecognition();

                if (transcript.includes("終了")) {
                    await endConversation();
                    return;
                }

                // // ユーザーの発言を表示
                // output.innerHTML += `<p><strong>のぶこ:</strong> ${transcript}</p>`;
                // conversationHistory.push({ role: 'user', content: transcript });

                // 70%の確率で相槌を先に入れる
                if (Math.random() < 0.7) {
                    const aizuchiResponse = randomAizuchi();
                    output.innerHTML += `<p><strong>LA:</strong> ${aizuchiResponse}</p>`;
                    const aizuchiAudio = await textToSpeech(aizuchiResponse, voiceType);
                    await playAudio(aizuchiAudio);
                }

                // AI応答を取得
                try {
                    const aiResponse = await getAIResponse(conversationHistory, selectedVoice);
                    output.innerHTML += `<p><strong>LA:</strong> ${aiResponse}</p>`;
                    conversationHistory.push({ role: 'assistant', content: aiResponse });

                    const responseAudio = await textToSpeech(aiResponse, voiceType);
                    await playAudio(responseAudio);
                } catch (error) {
                    console.error("AI応答エラー:", error);
                    output.innerHTML += `<p><strong>エラー:</strong> AI応答に失敗しました。</p>`;
                    startRecognition(); // エラー発生時も音声認識を再開
                }
            }

            async function playAudio(audioUrl) {
                if (!audioUrl) {
                    startRecognition();
                    return;
                }

                currentAudio = new Audio(audioUrl);

                currentAudio.onended = () => {
                    URL.revokeObjectURL(audioUrl);
                    isSpeaking = false;
                    startRecognition();
                };

                currentAudio.onplay = () => {
                    isSpeaking = true;
                    stopRecognition();
                };

                currentAudio.onerror = () => {
                    console.error("Audio playback error");
                    output.innerHTML += "<p><strong>エラー:</strong> 音声の再生中にエラーが発生しました。</p>";
                    isSpeaking = false;
                };

                try {
                    await currentAudio.play();
                } catch (error) {
                    console.error("Playback failed", error);
                    output.innerHTML += `<p><strong>Playback error:</strong> ${error.message}</p>`;
                }
            }

            async function speak(text, voiceType) {
                if (isSpeaking && currentAudio) {
                    currentAudio.pause();
                    currentAudio = null;
                }

                isSpeaking = true;

                const response = await fetch('/text-to-speech', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ text, voiceType }),
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Failed to fetch speech synthesis with status ${response.status}: ${errorText}`);
                }

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const audio = new Audio(url);

                audio.onended = () => {
                    isSpeaking = false;
                    URL.revokeObjectURL(url);
                    startRecognition();
                };

                audio.onplay = () => {
                    isSpeaking = true;
                    stopRecognition();
                };

                audio.onerror = () => {
                    console.error("Audio playback error");
                    output.innerHTML += "<p><strong>エラー:</strong> 音声の再生中にエラーが発生しました。</p>";
                };

                try {
                    await audio.play();
                } catch (error) {
                    console.error("Playback failed", error);
                    output.innerHTML += `<p><strong>Playback error:</strong> ${error.message}</p>`;
                }

                currentAudio = audio;
            }

            async function getAIResponse(messages, selectedVoice) {
                try {
                    const response = await fetch('/api/get-ai-response', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ messages: messages.map(m => ({ role: m.role, content: m.content })) }),
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error(`Failed to fetch AI response: ${errorText}`);
                        throw new Error(`Failed to fetch AI response with status ${response.status}`);
                    }

                    const data = await response.json();
                    // AIが「明子さん」と呼ぶのを「のぶこさん」に変更
                    return data.message.replace(/明子さん/g, 'のぶこさん').replace(/ユーザー/g, 'のぶこ');
                } catch (error) {
                    console.error('Fetch error:', error);
                    throw new Error(`AI応答に失敗しました。ネットワークを確認してください。詳細: ${error.message}`);
                }
            }

            async function textToSpeech(text, voiceType) {
                const response = await fetch('/text-to-speech', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ text, voiceType }),
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Failed to fetch speech synthesis with status ${response.status}: ${errorText}`);
                }

                const blob = await response.blob();
                return URL.createObjectURL(blob);
            }

            function startRecognition() {
                if (!recognitionActive && !isSpeaking) {
                    recognition.start();
                    recognitionActive = true;
                    console.log("音声認識を開始しました");
                }
            }

            function stopRecognition() {
                if (recognitionActive) {
                    recognition.stop();
                    recognitionActive = false;
                    console.log("音声認識を停止しました");
                }
            }

            function randomAizuchi() {
                const aizuchi = ['へえ', 'なるほど', 'そうなんですね', 'ほーう'];
                return aizuchi[Math.floor(Math.random() * aizuchi.length)];
            }

            async function endConversation() {
                stopRecognition();
                if (currentAudio) {
                    currentAudio.pause();
                    currentAudio = null;
                }
                conversationHistory.push({ role: 'user', content: '終了' });

                try {
                    const response = await fetch('/api/summarize-conversation', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ conversation: conversationHistory.map(m => m.content) }),
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Failed to summarize conversation with status ${response.status}: ${errorText}`);
                    }

                    const data = await response.json();
                    const summary = data.summary;

                    const saveResponse = await fetch('/conversation-summaries', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ summary }),
                    });

                    if (!saveResponse.ok) {
                        const saveErrorText = await saveResponse.text();
                        throw new Error(`Failed to save conversation summary with status ${saveResponse.status}: ${saveErrorText}`);
                    }

                    output.innerHTML += '<p><em>会話が終了しました。要約が保存されました。</em></p>';
                } catch (error) {
                    console.error('Error:', error);
                    output.innerHTML += `<p><strong>エラー:</strong> ${error.message}</p>`;
                }
            }
        });
    </script>
</body>

</html>
