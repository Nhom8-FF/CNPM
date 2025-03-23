const aiBubble = document.getElementById('ai-bubble');
const chatWidget = document.getElementById('chat-widget');
const minimizeBtn = document.getElementById('chat-minimize');
const sendBtn = document.getElementById('chat-send');
const chatInput = document.getElementById('chat-input');
const msgContainer = document.getElementById('chat-messages');
const chatFiles = document.getElementById('chat-files');

// Create language switcher
const langSwitcher = document.createElement('div');
langSwitcher.classList.add('lang-switcher');
langSwitcher.innerHTML = `
    <button data-lang="vi" class="lang-btn active">🇻🇳</button>
    <button data-lang="en" class="lang-btn">🇬🇧</button>
    <button data-lang="zh" class="lang-btn">🇨🇳</button>
    <button data-lang="ja" class="lang-btn">🇯🇵</button>
    <button data-lang="ko" class="lang-btn">🇰🇷</button>
    <button data-lang="fr" class="lang-btn">🇫🇷</button>
    <button data-lang="es" class="lang-btn">🇪🇸</button>
`;

// Add language switcher to the chat header
document.querySelector('.chat-header').appendChild(langSwitcher);

// Add CSS for language switcher
const styleElement = document.createElement('style');
styleElement.textContent = `
    .lang-switcher {
        position: absolute;
        right: 50px;
        top: 10px;
        display: flex;
        gap: 5px;
    }
    .lang-btn {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 1px solid #e0e0e0;
        background: white;
        cursor: pointer;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        opacity: 0.6;
        transition: all 0.2s;
    }
    .lang-btn:hover {
        transform: scale(1.1);
        opacity: 1;
    }
    .lang-btn.active {
        opacity: 1;
        border-color: #007bff;
        box-shadow: 0 0 0 2px rgba(0,123,255,0.3);
    }
`;
document.head.appendChild(styleElement);

// Handle language button clicks
document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        // Update active state
        document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Get language code
        const lang = btn.getAttribute('data-lang');
        
        // Remember selected language
        sessionStorage.setItem('preferred_language', lang);
        
        // Send language change command
        chatInput.value = `/lang ${lang}`;
        sendMessage();
        
        debugLog(`Changed language to: ${lang}`);
    });
});

// Thêm overlay với spinner
const loadingOverlay = document.createElement('div');
loadingOverlay.classList.add('loading-overlay');
loadingOverlay.innerHTML = '<div class="spinner"></div>';
document.body.appendChild(loadingOverlay); // Thêm vào body thay vì msgContainer

// Thêm hàm debug
function debugLog(message, data = null) {
    const logMsg = `[BotEdu] ${message}` + (data ? `: ${JSON.stringify(data, (key, value) => {
        // Don't stringify File objects or large data
        if (value instanceof File) {
            return `File(${value.name}, ${value.size} bytes, ${value.type})`;
        }
        if (typeof value === 'string' && value.length > 500) {
            return value.substring(0, 500) + '... [truncated]';
        }
        return value;
    })}` : '');
    
    // Log to console if we're in development
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log(logMsg);
    }
    
    // Store recent logs in session storage for debugging
    try {
        const logs = JSON.parse(sessionStorage.getItem('botEduLogs') || '[]');
        logs.push({
            time: new Date().toISOString(),
            message: logMsg
        });
        
        // Keep only the last 50 logs
        while (logs.length > 50) {
            logs.shift();
        }
        
        sessionStorage.setItem('botEduLogs', JSON.stringify(logs));
    } catch (e) {
        console.error('Error saving log:', e);
    }
}

// Hàm cập nhật vị trí spinner
function updateSpinnerPosition() {
  if (!loadingOverlay.classList.contains('active')) return;

  const rect = msgContainer.getBoundingClientRect();
  const scrollTop = msgContainer.scrollTop;
  const containerHeight = msgContainer.clientHeight;

  // Tính toán vị trí trung tâm của khung chat trong viewport
  const top = rect.top + (containerHeight / 2) - (loadingOverlay.offsetHeight / 2);
  const left = rect.left + (rect.width / 2) - (loadingOverlay.offsetWidth / 2);

  // Cập nhật vị trí spinner
  loadingOverlay.style.top = `${top}px`;
  loadingOverlay.style.left = `${left}px`;
  loadingOverlay.style.width = `${rect.width}px`;
  loadingOverlay.style.height = `${containerHeight}px`;
}

// Cập nhật vị trí khi cuộn hoặc thay đổi kích thước
msgContainer.addEventListener('scroll', updateSpinnerPosition);
window.addEventListener('resize', updateSpinnerPosition);

aiBubble.addEventListener('click', () => {
    chatWidget.classList.toggle('minimized');
    chatWidget.classList.toggle('expanded');
    if (chatWidget.classList.contains('expanded')) updateSpinnerPosition();
});

minimizeBtn.addEventListener('click', () => {
    chatWidget.classList.remove('expanded');
    chatWidget.classList.add('minimized');
});

let isDragging = false;
let offset = { x: 0, y: 0 };

chatWidget.addEventListener('mousedown', (e) => {
    if (!chatWidget.classList.contains('expanded')) return;
    isDragging = true;
    offset.x = e.clientX - chatWidget.offsetLeft;
    offset.y = e.clientY - chatWidget.offsetTop;
    chatWidget.style.transition = 'none';
});

document.addEventListener('mousemove', (e) => {
    if (isDragging) {
        chatWidget.style.left = `${e.clientX - offset.x}px`;
        chatWidget.style.top = `${e.clientY - offset.y}px`;
        chatWidget.style.right = 'auto';
        chatWidget.style.bottom = 'auto';
        updateSpinnerPosition(); // Cập nhật vị trí spinner khi kéo
    }
});

document.addEventListener('mouseup', () => {
    if (isDragging) {
        isDragging = false;
        chatWidget.style.transition = 'all 0.3s ease';
    }
});

document.querySelectorAll('.suggestion-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        chatInput.value = btn.getAttribute('data-question');
        sendMessage();
    });
});

function getFormattedTime() {
    const now = new Date();
    return `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
}

document.querySelectorAll('.timestamp').forEach(ts => {
    ts.textContent = getFormattedTime();
});

// Chặn form submission mặc định và sử dụng AJAX thay thế
document.getElementById('chat-form').addEventListener('submit', (e) => {
    e.preventDefault();
    sendMessage();
});

sendBtn.addEventListener('click', (e) => {
    e.preventDefault();
    sendMessage();
});

chatInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

let isThinking = false;

async function sendMessage() {
    if (isThinking) return;

    const userText = chatInput.value.trim();
    const files = chatFiles.files;
    if (!userText && files.length === 0) return;

    // Display user message
    const userDiv = document.createElement('div');
    userDiv.classList.add('message', 'user-message');
    let messageContent = userText ? `<span>${escapeHtml(userText)}</span>` : '';
    let fileHtml = '';

    // Log what we're sending
    debugLog('Sending message', { 
        text: userText,
        filesCount: files.length,
        fileInfo: Array.from(files).map(f => ({
            name: f.name, 
            type: f.type, 
            size: f.size
        }))
    });

    if (files.length > 0) {
        fileHtml = '<div class="file-list">';
        for (let file of files) {
            const fileURL = URL.createObjectURL(file);
            fileHtml += `<a href="${fileURL}" target="_blank" class="file-item">${file.name}</a>`;
            if (file.type.startsWith('image/')) {
                fileHtml += `<img src="${fileURL}" class="file-preview-img" alt="${file.name}">`;
            }
        }
        fileHtml += '</div>';
    }

    userDiv.innerHTML = `
        <div class="gpt-bubble">
            <svg class="message-icon" viewBox="0 0 24 24">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
            ${messageContent}${fileHtml}
            <span class="timestamp">${getFormattedTime()}</span>
        </div>
    `;
    msgContainer.appendChild(userDiv);
    msgContainer.scrollTop = msgContainer.scrollHeight;
    chatInput.value = '';

    // Show sending animation
    sendBtn.classList.add('sending');
    setTimeout(() => sendBtn.classList.remove('sending'), 300);

    // Show loading spinner
    isThinking = true;
    loadingOverlay.classList.add('active');
    updateSpinnerPosition();
    sendBtn.disabled = true;
    chatInput.disabled = true;

    try {
        // Create a FormData object for the request
        const formData = new FormData();
        
        // Add the prompt
        if (userText) {
            formData.append('prompt', userText);
        }
        
        // Add CSRF token
        const csrfToken = 
            document.querySelector('input[name="csrf_token"]')?.value || 
            window.CSRF_TOKEN;
            
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
            debugLog('Using CSRF token', { tokenPrefix: csrfToken.substring(0, 10) + '...' });
        } else {
            debugLog('No CSRF token found');
        }
        
        // Add files if any - with validation
        if (files.length > 0) {
            debugLog(`Adding ${files.length} files to request`);
            
            // Check file sizes and types
            let totalSize = 0;
            const maxFileSize = 10 * 1024 * 1024; // 10 MB limit
            const allowedTypes = [
                'image/jpeg', 'image/png', 'image/jpg', 
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // docx
            ];
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                totalSize += file.size;
                
                // Validate file type
                if (!allowedTypes.includes(file.type)) {
                    debugLog(`File type not allowed: ${file.type}`, { file: file.name });
                    throw new Error(`Loại tệp không được hỗ trợ: ${file.name}. Chỉ hỗ trợ JPG, PNG, PDF và DOCX.`);
                }
                
                // Validate file size
                if (file.size > maxFileSize) {
                    debugLog(`File too large: ${file.size} bytes`, { file: file.name });
                    throw new Error(`Tệp quá lớn: ${file.name}. Giới hạn là 10MB cho mỗi tệp.`);
                }
                
                // Append files with a better name pattern
                if (file.type.startsWith('image/')) {
                    formData.append('images[]', file, file.name);
                    debugLog(`Added image: ${file.name}`, { size: file.size });
                } else {
                    formData.append('documents[]', file, file.name);
                    debugLog(`Added document: ${file.name}`, { size: file.size });
                }
            }
            
            // Validate total size
            if (totalSize > 20 * 1024 * 1024) { // 20 MB total limit
                debugLog(`Total files size too large: ${totalSize} bytes`);
                throw new Error(`Tổng kích thước tệp quá lớn. Giới hạn là 20MB cho tất cả tệp.`);
            }
        }
        
        // Try the direct helper if available first
        if (typeof window.getChatbotResponse === 'function' && userText && files.length === 0) {
            debugLog('Using direct chatbot response helper');
            try {
                const directResponse = await window.getChatbotResponse(userText);
                if (directResponse && directResponse.status === 'success') {
                    debugLog('Direct response successful', directResponse);
                    displayBotResponse(directResponse.reply);
                    return;
                } else {
                    debugLog('Direct response failed, falling back to API', directResponse);
                }
            } catch (directError) {
                debugLog('Direct helper error, trying API endpoints', { error: directError.message });
            }
        }
        
        // Try the enhanced API endpoint first as it's most reliable
        let response = null;
        let responseData = null;
        let apiEndpoint = '';
        
        // Function to handle and log responses
        const processResponse = async (res, endpointName) => {
            debugLog(`${endpointName} status: ${res.status}`, { 
                statusText: res.statusText,
                headers: Object.fromEntries([...res.headers.entries()])
            });
            
            if (!res.ok) {
                throw new Error(`HTTP error! Status: ${res.status}`);
            }
            
            const responseText = await res.text();
            debugLog(`${endpointName} raw response`, { 
                text: responseText.substring(0, 100) + (responseText.length > 100 ? '...' : '')
            });
            
            try {
                return JSON.parse(responseText);
            } catch (e) {
                debugLog(`${endpointName} JSON parse error`, { error: e.message });
                throw new Error(`Không thể phân tích phản hồi: ${e.message}`);
            }
        };
        
        try {
            debugLog('Trying enhanced API endpoint');
            apiEndpoint = 'WebCourses/app/api/chatbot_response.php';
            
            // Fix URL construction to avoid double domain
            const fullUrl = new URL(apiEndpoint, window.location.origin).href + '?_=' + new Date().getTime();
            
            debugLog('Fetching from', { url: fullUrl });
            
            response = await fetch(fullUrl, {
                method: 'POST',
                body: formData
            });
            
            responseData = await processResponse(response, 'Enhanced API');
            debugLog('Enhanced API successful', responseData);
        } catch (e) {
            debugLog('Enhanced API error', { error: e.message });
            
            // If enhanced API endpoint failed, display a fallback message
            debugLog('Using fallback response due to API error');
            const fallbackResponse = getFallbackResponse(userText);
            displayBotResponse(fallbackResponse);
            return;
        }
        
        // Check if we got a valid response
        if (!responseData) {
            throw new Error('Không nhận được phản hồi từ máy chủ');
        }
        
        // Display bot response
        if (responseData.status === 'success' && responseData.reply) {
            displayBotResponse(responseData.reply);
        } else {
            throw new Error(responseData.message || 'Không nhận được phản hồi hợp lệ từ máy chủ');
        }
    } catch (error) {
        debugLog('Error in sendMessage', { error: error.message, stack: error.stack });
        
        // Get a fallback response if available
        const fallbackResponse = getFallbackResponse(userText);
        displayBotResponse(fallbackResponse);
    } finally {
        isThinking = false;
        loadingOverlay.classList.remove('active');
        sendBtn.disabled = false;
        chatInput.disabled = false;
        chatFiles.value = '';
    }
}

// Helper function to display bot response
function displayBotResponse(message) {
    const botDiv = document.createElement('div');
    botDiv.classList.add('message', 'bot-message');
    botDiv.innerHTML = `
        <div class="gpt-bubble">
            <svg class="message-icon" viewBox="0 0 1024 1024" fill="currentColor">
                <path d="M512.3 462.6m-253.7 0a253.7 253.7 0 1 0 507.4 0 253.7 253.7 0 1 0-507.4 0Z"/>
            </svg>
            <span>${message}</span>
            <span class="timestamp">${getFormattedTime()}</span>
        </div>
    `;
    msgContainer.appendChild(botDiv);
    msgContainer.scrollTop = msgContainer.scrollHeight;
}

// Simple helper to get hard-coded fallback responses
function getFallbackResponse(text) {
    const lowercaseText = text.toLowerCase();
    
    if (lowercaseText.includes('đăng ký') || lowercaseText.includes('register')) {
        return 'Để đăng ký khóa học, bạn cần đăng nhập vào hệ thống với vai trò học viên, sau đó truy cập vào trang khóa học và nhấn nút "Đăng ký".';
    }
    else if (lowercaseText.includes('khóa học') || lowercaseText.includes('course')) {
        return 'Chúng tôi cung cấp nhiều khóa học đa dạng trong các lĩnh vực như công nghệ thông tin, ngoại ngữ, kỹ năng mềm. Bạn có thể xem danh sách các khóa học ở trang chủ.';
    }
    else if (lowercaseText.includes('tài liệu') || lowercaseText.includes('material')) {
        return 'Tài liệu học tập được cung cấp trong từng bài học của khóa học. Sau khi đăng ký, bạn có thể truy cập vào các bài học để xem và tải tài liệu.';
    }
    else if (lowercaseText.includes('hỗ trợ') || lowercaseText.includes('support') || lowercaseText.includes('kỹ thuật') || lowercaseText.includes('technical')) {
        return 'Đội ngũ hỗ trợ kỹ thuật luôn sẵn sàng giúp đỡ bạn 24/7. Vui lòng mô tả chi tiết vấn đề bạn đang gặp phải hoặc liên hệ qua email hoctap435@gmail.com.';
    }
    
    return 'Xin lỗi, tôi không thể kết nối với dịch vụ AI. Tuy nhiên, tôi có thể hỗ trợ bạn với các thông tin cơ bản về hệ thống. Vui lòng thử lại câu hỏi của bạn sau hoặc liên hệ với chúng tôi qua email hoctap435@gmail.com.';
}

function escapeHtml(str) {
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Update language buttons based on stored preference
function updateLanguageButtons() {
    const preferredLang = sessionStorage.getItem('preferred_language') || 'vi';
    document.querySelectorAll('.lang-btn').forEach(btn => {
        if (btn.getAttribute('data-lang') === preferredLang) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

// Call on page load
updateLanguageButtons();