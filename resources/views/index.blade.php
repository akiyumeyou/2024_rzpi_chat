<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AIとリアルタイム音声会話</title>
</head>
<body>
    <button id="startButton">AIと会話を開始</button>
    <div id="output"></div>

<script>
const startButton = document.getElementById('startButton');
const output = document.getElementById('output');

let recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
recognition.lang = 'ja-JP';
recognition.interimResults = false;
recognition.continuous = false;

let synth = window.speechSynthesis;
let conversationHistory = [];
let conversationTimeout;
let isSpeaking = false;

startButton.addEventListener('click', () => {
    recognition.start();
    output.innerHTML += '<p><em>会話を開始しました...</em></p>';
    startConversationTimeout();
});

recognition.onresult = async (event) => {
    const transcript = event.results[event.resultIndex][0].transcript.trim();
    if (transcript && !isSpeaking) {
        output.innerHTML += `<p><strong>ユーザー:</strong> ${transcript}</p>`;
        conversationHistory.push({ role: 'user', content: transcript });

        if (transcript.includes('終わり')) {
            recognition.stop();
            const summary = conversationHistory.map(entry => entry.content).join(' ').slice(-100);
            try {
                await saveConversationSummary(summary);
                output.innerHTML += `<p><strong>システム:</strong> 会話を終了しました。要約を保存しました。</p>`;
            } catch (error) {
                console.error('Failed to save conversation summary:', error);
                output.innerHTML += `<p><strong>システム:</strong> 要約の保存に失敗しました。</p>`;
            }
            return;
        }

        recognition.stop();
        const aiResponse = await getAIResponse(transcript);
        output.innerHTML += `<p><strong>AI:</strong> ${aiResponse}</p>`;
        conversationHistory.push({ role: 'assistant', content: aiResponse });
        await speakWithGammaWaveEffect(aiResponse);
    }
};

recognition.onerror = (event) => {
    console.error('Recognition error:', event.error);
    output.innerHTML += `<p><strong>エラー:</strong> ${event.error}</p>`;
};

async function getAIResponse(userText) {
    try {
        const apiKey = await getApiKey();

        // システムプロンプトを追加
        const promptText = `あなたは高齢者に寄り添う会話の専門家です。ユーザーが話すことに対して、短い相槌やおうむ返しを使い、相手の話を促進するようにしてください。話が途切れた場合には、簡単な質問をして会話を続けてください。必ず短く的確に応えてください。ユーザーの発話内容: ${userText}`;

        conversationHistory.push({ role: 'system', content: promptText });

        const response = await fetch('https://api.openai.com/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${apiKey}`
            },
            body: JSON.stringify({
                model: 'gpt-3.5-turbo',
                messages: conversationHistory,
                max_tokens: 60,  // 応答のトークン数を制限
                n: 1,
                stop: ["。", "！", "？"],  // 応答が文の区切りで終わるようにする
                temperature: 0.7
            })
        });

        const data = await response.json();
        if (response.ok) {
            const aiMessage = data.choices[0].message.content.trim();
            conversationHistory.pop();  // システムプロンプトを削除
            return aiMessage;
        } else {
            console.error('API error:', data);
            conversationHistory.pop();  // システムプロンプトを削除
            return 'エラーが発生しました。もう一度試してください。';
        }
    } catch (error) {
        console.error('Fetch error:', error);
        return 'エラーが発生しました。ネットワークを確認してください。';
    }
}

async function getApiKey() {
    try {
        const response = await fetch('/api/openai-key');
        const data = await response.json();
        return data.api_key;
    } catch (error) {
        console.error('API key fetch error:', error);
        throw new Error('APIキーの取得に失敗しました');
    }
}

async function speakWithGammaWaveEffect(text) {
    return new Promise((resolve, reject) => {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'ja-JP';

        utterance.onstart = () => {
            isSpeaking = true;
        };

        utterance.onend = async () => {
            isSpeaking = false;
            await applyGammaWaveEffect();
            recognition.start();
            resolve();
        };

        synth.speak(utterance);
    });
}

async function applyGammaWaveEffect() {
    const audioContext = new AudioContext();
    const oscillator = audioContext.createOscillator();
    oscillator.type = 'sine';
    oscillator.frequency.value = 40; // ガンマ波の周波数帯域を設定
    const gainNode = audioContext.createGain();
    gainNode.gain.value = 0.5;

    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    oscillator.start();
    setTimeout(() => oscillator.stop(), 500); // 0.5秒間ガンマ波を再生
}

async function saveConversationSummary(summary) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const userText = conversationHistory.filter(entry => entry.role === 'user').map(entry => entry.content).join(' ');
    const aiResponse = conversationHistory.filter(entry => entry.role === 'assistant').map(entry => entry.content).join(' ');

    try {
        const response = await fetch('/save-summary', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                summary: summary,
                user_text: userText,
                ai_response: aiResponse
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

function startConversationTimeout() {
    clearTimeout(conversationTimeout);
    conversationTimeout = setTimeout(() => {
        output.innerHTML += '<p><strong>AI:</strong> 5分が経過しました。一旦会話を終了します。</p>';
        speak('5分が経過しました。一旦会話を終了します。');
        recognition.stop();
    }, 5 * 60 * 1000);
}

</script>
</body>
</html>
