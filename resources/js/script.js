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
    const aiResponse = await getAIResponse(transcript);
    output.innerHTML += `<p><strong>AI:</strong> ${aiResponse}</p>`;
    speak(aiResponse);
};

recognition.onerror = (event) => {
    console.error('Recognition error:', event.error);
};

async function getAIResponse(text) {
    const response = await fetch('https://api.openai.com/v1/engines/davinci-codex/completions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer YOUR_OPENAI_API_KEY'
        },
        body: JSON.stringify({
            prompt: text,
            max_tokens: 150,
            n: 1,
            stop: null,
            temperature: 0.9
        })
    });

    const data = await response.json();
    return data.choices[0].text.trim();
}

function speak(text) {
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'ja-JP';
    synth.speak(utterance);
}
