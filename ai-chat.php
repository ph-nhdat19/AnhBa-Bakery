<?php
// ai-chat.php
?>
<!-- Nút chat nổi -->
<div style="position:fixed; bottom:20px; right:20px; z-index:9999;">
    <button onclick="toggleAIChat()" 
            style="width:65px; height:65px; border-radius:50%; background:#c97b84; color:white; border:none; font-size:28px; box-shadow:0 5px 20px rgba(201,123,132,0.4); cursor:pointer; display:flex; align-items:center; justify-content:center;">
        🍰
    </button>
</div>

<!-- Chat Box -->
<div id="aiChatBox" style="display:none; position:fixed; bottom:100px; right:20px; width:380px; height:520px; background:#fff; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.25); overflow:hidden; z-index:10000;">
    
    <!-- Header -->
    <div style="background:#6b3f2a; color:white; padding:15px; font-weight:600; display:flex; align-items:center; gap:10px;">
        <span style="font-size:24px;">🍰</span>
        <div>
            <div>Anh Ba Bakery AI</div>
            <div style="font-size:12px; opacity:0.8;">Tư vấn bánh kem & bánh ngọt</div>
        </div>
        <button onclick="toggleAIChat()" style="margin-left:auto; background:none; border:none; color:white; font-size:22px; cursor:pointer;">✕</button>
    </div>

    <!-- Messages -->
    <div id="aiMessages" style="height:380px; overflow-y:auto; padding:15px; background:#fdf6ef;">
        <div style="background:#f0d4d8; padding:12px; border-radius:12px; max-width:85%; margin-bottom:12px;">
            Xin chào! 🍰<br>
            Em là trợ lý AI của Anh Ba Bakery.<br>
            Bạn đang tìm bánh gì hôm nay ạ?
        </div>
    </div>

    <!-- Input -->
    <div style="padding:12px; background:white; border-top:1px solid #eee;">
        <div style="display:flex; gap:8px;">
            <input type="text" id="aiInput" 
                   placeholder="Ví dụ: Bánh sinh nhật cho bé gái..." 
                   style="flex:1; padding:12px; border:1px solid #ddd; border-radius:8px; outline:none;"
                   onkeypress="if(event.key === 'Enter') sendAIMessage()">
            <button onclick="sendAIMessage()" 
                    style="background:#c97b84; color:white; border:none; width:50px; border-radius:8px; cursor:pointer;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Toggle chat
function toggleAIChat() {
    const box = document.getElementById('aiChatBox');
    box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
}

// Gửi tin nhắn
async function sendAIMessage() {
    const input = document.getElementById('aiInput');
    const message = input.value.trim();
    if (!message) return;

    addMessage('user', message);
    input.value = '';

    // Hiển thị "Đang trả lời..."
    const loadingId = 'loading-' + Date.now();
    addMessage('bot', '<i>Đang suy nghĩ...</i>', loadingId);

    try {
        const res = await fetch('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=AIzaSyC8LacZmlchXf3JZ4SrPZ1R7PZ5lgWj-A8', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                contents: [{
                    parts: [{
                        text: `Bạn là nhân viên tư vấn bánh rất thân thiện tại Anh Ba Bakery.
                        Tính cách: vui vẻ, nhiệt tình, am hiểu bánh kem, bánh mì, bánh lẻ.
                        Khách hàng hỏi: "${message}"
                        Trả lời ngắn gọn, thuyết phục, gợi ý sản phẩm phù hợp.`
                    }]
                }]
            })
        });

        const data = await res.json();
        const reply = data.candidates[0].content.parts[0].text;
        
        // Xóa tin nhắn loading và thêm câu trả lời thật
        document.getElementById(loadingId).remove();
        addMessage('bot', reply);
        
    } catch (e) {
        document.getElementById(loadingId).innerHTML = "Xin lỗi, em đang bận. Bạn thử hỏi lại nhé ❤️";
    }
}

function addMessage(sender, text, id = null) {
    const container = document.getElementById('aiMessages');
    const div = document.createElement('div');
    if (id) div.id = id;
    
    div.style.marginBottom = '12px';
    div.style.padding = sender === 'user' ? '12px 14px' : '12px 14px';
    div.style.borderRadius = '12px';
    div.style.maxWidth = '85%';
    
    if (sender === 'user') {
        div.style.background = '#c97b84';
        div.style.color = 'white';
        div.style.marginLeft = 'auto';
    } else {
        div.style.background = '#f0d4d8';
        div.style.color = '#6b3f2a';
    }
    
    div.innerHTML = text;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}
</script>
